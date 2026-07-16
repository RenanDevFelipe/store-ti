<?php

namespace App\Services;

use App\Models\SalesLink;
use App\Models\PaymentSetting;
use App\Models\Payment;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use MercadoPago\Client\Common\RequestOptions;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;
use RuntimeException;
use Throwable;

class MercadoPagoCheckoutService
{
    public function createPreference(SalesLink $salesLink): SalesLink
    {
        $settings = PaymentSetting::mercadoPago();
        $token = $settings->access_token;

        if (! $token) {
            $salesLink->update([
                'status' => 'draft',
                'metadata' => array_merge($salesLink->metadata ?? [], [
                    'payment_setup' => 'missing_mercado_pago_access_token',
                ]),
            ]);

            return $salesLink->refresh();
        }

        MercadoPagoConfig::setAccessToken($token);

        if ($settings->sandbox) {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        }

        $client = new PreferenceClient();
        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            'X-Idempotency-Key: sales-link-'.$salesLink->public_id,
        ]);

        try {
            $preference = $client->create($this->preferencePayload($salesLink), $requestOptions);
        } catch (MPApiException $exception) {
            $content = $exception->getApiResponse()?->getContent();

            throw new RuntimeException('Mercado Pago recusou a preferencia: '.json_encode($content));
        } catch (Throwable $exception) {
            throw new RuntimeException('Falha ao criar preferencia no Mercado Pago: '.$exception->getMessage());
        }

        $responseContent = $this->preferenceResponseContent($preference);
        $initPoint = $this->preferenceValue($preference, $responseContent, 'init_point');
        $sandboxInitPoint = $this->preferenceValue($preference, $responseContent, 'sandbox_init_point');
        $checkoutUrl = $settings->sandbox ? ($sandboxInitPoint ?: $initPoint) : $initPoint;
        $liveMode = $this->preferenceValue($preference, $responseContent, 'live_mode');

        if (! $checkoutUrl) {
            $salesLink->update([
                'status' => 'draft',
                'metadata' => array_merge($salesLink->metadata ?? [], [
                    'payment_setup' => 'missing_checkout_url',
                    'mp_response' => $responseContent,
                ]),
            ]);

            throw new RuntimeException('Mercado Pago criou a preferencia, mas nao retornou URL de pagamento.');
        }

        $salesLink->update([
            'status' => 'ready',
            'mp_preference_id' => $this->preferenceValue($preference, $responseContent, 'id'),
            'checkout_url' => $checkoutUrl,
            'metadata' => array_merge($salesLink->metadata ?? [], [
                'payment_setup' => 'configured',
                'mp_live_mode' => $liveMode,
            ]),
        ]);

        return $salesLink->refresh();
    }

    public function fetchPayment(string $paymentId): object
    {
        $token = PaymentSetting::mercadoPago()->access_token;

        if (! $token) {
            throw new RuntimeException('MERCADO_PAGO_ACCESS_TOKEN nao configurado.');
        }

        MercadoPagoConfig::setAccessToken($token);

        return (new PaymentClient())->get($paymentId);
    }

    public function createPixPayment(SalesLink $salesLink, array $payer): Payment
    {
        $settings = PaymentSetting::mercadoPago();
        $token = $settings->access_token;

        if (! $token) {
            throw new RuntimeException('Mercado Pago nao configurado.');
        }

        MercadoPagoConfig::setAccessToken($token);

        if ($settings->sandbox) {
            MercadoPagoConfig::setRuntimeEnviroment(MercadoPagoConfig::LOCAL);
        }

        $client = new PaymentClient();
        $requestOptions = new RequestOptions();
        $requestOptions->setCustomHeaders([
            'X-Idempotency-Key: pix-'.$salesLink->public_id.'-'.Str::uuid(),
        ]);

        try {
            $mpPayment = $client->create($this->pixPaymentPayload($salesLink, $payer), $requestOptions);
        } catch (MPApiException $exception) {
            $content = $exception->getApiResponse()?->getContent();

            throw new RuntimeException('Mercado Pago recusou o pagamento: '.json_encode($content));
        } catch (Throwable $exception) {
            throw new RuntimeException('Falha ao gerar pagamento no Mercado Pago: '.$exception->getMessage());
        }

        $content = $this->resourceResponseContent($mpPayment);
        $paymentId = (string) $this->resourceValue($mpPayment, $content, 'id');
        $rawStatus = $this->resourceValue($mpPayment, $content, 'status') ?? 'pending';
        $approvedAt = $this->resourceValue($mpPayment, $content, 'date_approved');
        $status = $rawStatus === 'approved' && blank($approvedAt) ? 'pending' : $rawStatus;

        $payment = Payment::updateOrCreate(
            ['mp_payment_id' => $paymentId],
            [
                'sales_link_id' => $salesLink->id,
                'provider' => 'mercado_pago',
                'provider_payment_id' => $paymentId,
                'status' => $status,
                'status_detail' => $this->resourceValue($mpPayment, $content, 'status_detail'),
                'payment_method_id' => $this->resourceValue($mpPayment, $content, 'payment_method_id') ?? 'pix',
                'payment_type_id' => $this->resourceValue($mpPayment, $content, 'payment_type_id') ?? 'bank_transfer',
                'amount_cents' => (int) round(($this->resourceValue($mpPayment, $content, 'transaction_amount') ?? 0) * 100),
                'paid_at' => $status === 'approved' ? $approvedAt : null,
                'raw_payload' => array_merge($content, [
                    'checkout_customer' => $payer,
                    'raw_status_from_provider' => $rawStatus,
                ]),
            ]
        );

        $salesLink->update([
            'status' => match ($status) {
                'approved' => filled($approvedAt) ? 'paid' : 'pending',
                'pending', 'in_process' => 'pending',
                'cancelled', 'refunded', 'charged_back', 'rejected' => 'cancelled',
                default => $salesLink->status,
            },
        ]);

        app(EvolutionNotificationService::class)->notifyPaymentUpdated($payment->load('salesLink.product'));

        return $payment;
    }

    public function syncPayment(Payment $payment): Payment
    {
        if (! $payment->mp_payment_id) {
            return $payment;
        }

        $mpPayment = $this->fetchPayment($payment->mp_payment_id);
        $content = $this->resourceResponseContent($mpPayment);
        $rawStatus = $this->resourceValue($mpPayment, $content, 'status') ?? $payment->status;
        $approvedAt = $this->resourceValue($mpPayment, $content, 'date_approved');
        $status = $rawStatus === 'approved' && blank($approvedAt) ? 'pending' : $rawStatus;

        $payment->update([
            'status' => $status,
            'status_detail' => $this->resourceValue($mpPayment, $content, 'status_detail'),
            'payment_method_id' => $this->resourceValue($mpPayment, $content, 'payment_method_id') ?? $payment->payment_method_id,
            'payment_type_id' => $this->resourceValue($mpPayment, $content, 'payment_type_id') ?? $payment->payment_type_id,
            'amount_cents' => (int) round(($this->resourceValue($mpPayment, $content, 'transaction_amount') ?? ($payment->amount_cents / 100)) * 100),
            'paid_at' => $status === 'approved' ? $approvedAt : null,
            'raw_payload' => array_merge($payment->raw_payload ?? [], $content, [
                'raw_status_from_provider' => $rawStatus,
            ]),
        ]);

        $payment->salesLink?->update([
            'status' => match ($status) {
                'approved' => 'paid',
                'pending', 'in_process' => 'pending',
                'cancelled', 'refunded', 'charged_back', 'rejected' => 'cancelled',
                default => $payment->salesLink->status,
            },
        ]);

        app(EvolutionNotificationService::class)->notifyPaymentUpdated($payment->load('salesLink.product'));

        return $payment->refresh();
    }

    private function preferencePayload(SalesLink $salesLink): array
    {
        $product = $salesLink->product;
        $settings = PaymentSetting::mercadoPago();
        $backUrls = [
            'success' => URL::route('checkout.result', ['status' => 'success', 'link' => $salesLink->public_id], true),
            'failure' => URL::route('checkout.result', ['status' => 'failure', 'link' => $salesLink->public_id], true),
            'pending' => URL::route('checkout.result', ['status' => 'pending', 'link' => $salesLink->public_id], true),
        ];
        $notificationUrl = URL::route('mercadopago.webhook', [], true);

        $payload = [
            'items' => [[
                'id' => (string) $product->id,
                'title' => $salesLink->title,
                'description' => $product->description,
                'quantity' => 1,
                'currency_id' => $product->currency,
                'unit_price' => $salesLink->final_amount_cents / 100,
            ]],
            'payer' => array_filter([
                'email' => $salesLink->customer_email,
            ]),
            'back_urls' => $backUrls,
            'external_reference' => $salesLink->public_id,
            'statement_descriptor' => $settings->statement_descriptor,
            'expires' => (bool) $salesLink->expires_at,
            'expiration_date_to' => $salesLink->expires_at?->toIso8601String(),
            'metadata' => [
                'sales_link_id' => $salesLink->id,
                'public_id' => $salesLink->public_id,
            ],
        ];

        if ($this->isPublicHttpUrl($notificationUrl)) {
            $payload['notification_url'] = $notificationUrl;
        }

        if ($this->isPublicHttpUrl($backUrls['success'])) {
            $payload['auto_return'] = 'approved';
        }

        return $payload;
    }

    private function pixPaymentPayload(SalesLink $salesLink, array $payer): array
    {
        $notificationUrl = URL::route('mercadopago.webhook', [], true);

        $payload = [
            'transaction_amount' => $salesLink->final_amount_cents / 100,
            'description' => $salesLink->title,
            'payment_method_id' => 'pix',
            'external_reference' => $salesLink->public_id,
            'payer' => array_filter([
                'email' => $payer['email'],
                'first_name' => $payer['name'],
                'identification' => filled($payer['cpf'] ?? null) ? [
                    'type' => 'CPF',
                    'number' => preg_replace('/\D+/', '', $payer['cpf']),
                ] : null,
            ]),
            'metadata' => [
                'sales_link_id' => $salesLink->id,
                'public_id' => $salesLink->public_id,
                'customer_name' => $payer['name'],
                'customer_phone' => $payer['phone'] ?? null,
                'customer_cpf' => isset($payer['cpf']) ? preg_replace('/\D+/', '', $payer['cpf']) : null,
            ],
        ];

        if ($this->isPublicHttpUrl($notificationUrl)) {
            $payload['notification_url'] = $notificationUrl;
        }

        return $payload;
    }

    private function isPublicHttpUrl(string $url): bool
    {
        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        return in_array($scheme, ['http', 'https'], true)
            && filled($host)
            && ! in_array($configuredHost, ['localhost', '127.0.0.1', '::1'], true)
            && ! in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    private function preferenceResponseContent(object $preference): array
    {
        return $this->resourceResponseContent($preference);
    }

    private function resourceResponseContent(object $resource): array
    {
        if (! method_exists($resource, 'getResponse')) {
            return [];
        }

        try {
            return $resource->getResponse()->getContent();
        } catch (Throwable) {
            return [];
        }
    }

    private function preferenceValue(object $preference, array $responseContent, string $key): mixed
    {
        return $this->resourceValue($preference, $responseContent, $key);
    }

    private function resourceValue(object $resource, array $responseContent, string $key): mixed
    {
        if (isset($resource->{$key})) {
            return $resource->{$key};
        }

        return $responseContent[$key] ?? null;
    }
}
