<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SalesLink;
use App\Models\TenantSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AsaasCheckoutService
{
    public function createPixPayment(SalesLink $salesLink, array $payer): Payment
    {
        $tenant = $salesLink->product?->tenant ?: TenantSetting::current();
        $customerId = $this->customerId($tenant, $salesLink, $payer);
        $dueDate = $salesLink->expires_at?->toDateString() ?: now()->addDay()->toDateString();

        $charge = $this->json($this->client($tenant)->post('payments', [
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => $salesLink->final_amount_cents / 100,
            'dueDate' => $dueDate,
            'description' => $salesLink->title,
            'externalReference' => $salesLink->public_id,
        ]), 'criar cobranca Pix');

        $paymentId = (string) data_get($charge, 'id');
        if ($paymentId === '') {
            throw new RuntimeException('Asaas criou a cobranca sem retornar o identificador.');
        }

        $qrCode = $this->json($this->client($tenant)->get("payments/$paymentId/pixQrCode"), 'obter QR Code Pix');

        return $this->persist($salesLink, $tenant, $charge, $qrCode, $payer);
    }

    public function syncPayment(Payment $payment): Payment
    {
        $salesLink = $payment->salesLink?->loadMissing('product.tenant');
        if (! $salesLink) {
            return $payment;
        }

        $tenant = $salesLink->product?->tenant ?: TenantSetting::current();
        $paymentId = $payment->provider_payment_id ?: $payment->mp_payment_id;
        $charge = $this->json($this->client($tenant)->get("payments/$paymentId"), 'consultar cobranca');

        return $this->persist($salesLink, $tenant, $charge, [], data_get($payment->raw_payload, 'checkout_customer', []));
    }

    public function syncWebhookPayment(TenantSetting $tenant, array $charge): ?Payment
    {
        $salesLink = SalesLink::where('public_id', data_get($charge, 'externalReference'))->first();

        return $salesLink ? $this->persist($salesLink->load('product'), $tenant, $charge) : null;
    }

    private function customerId(TenantSetting $tenant, SalesLink $salesLink, array $payer): string
    {
        $externalReference = 'customer-'.($salesLink->customer_id ?: $salesLink->public_id);
        $existing = $this->json($this->client($tenant)->get('customers', [
            'externalReference' => $externalReference,
            'limit' => 1,
        ]), 'consultar cliente');

        if (filled(data_get($existing, 'data.0.id'))) {
            return (string) data_get($existing, 'data.0.id');
        }

        $customer = $this->json($this->client($tenant)->post('customers', array_filter([
            'name' => $payer['name'] ?? $salesLink->customer?->name,
            'cpfCnpj' => isset($payer['cpf']) ? preg_replace('/\D+/', '', $payer['cpf']) : null,
            'email' => $payer['email'] ?? $salesLink->customer_email,
            'mobilePhone' => isset($payer['phone']) ? preg_replace('/\D+/', '', $payer['phone']) : null,
            'externalReference' => $externalReference,
            'notificationDisabled' => true,
        ], fn ($value) => $value !== null && $value !== '')), 'criar cliente');

        return (string) data_get($customer, 'id');
    }

    private function persist(SalesLink $salesLink, TenantSetting $tenant, array $charge, array $qrCode = [], array $payer = []): Payment
    {
        $rawStatus = (string) data_get($charge, 'status', 'PENDING');
        $status = $this->status($rawStatus);
        $paidAt = in_array($status, ['approved'], true)
            ? data_get($charge, 'paymentDate') ?? data_get($charge, 'clientPaymentDate') ?? now()
            : null;
        $providerId = (string) data_get($charge, 'id');
        $existing = Payment::where('provider', 'asaas')->where('provider_payment_id', $providerId)->first();

        $payment = Payment::updateOrCreate(
            ['provider' => 'asaas', 'provider_payment_id' => $providerId],
            [
                'sales_link_id' => $salesLink->id,
                'status' => $status,
                'status_detail' => $rawStatus,
                'payment_method_id' => 'pix',
                'payment_type_id' => 'bank_transfer',
                'amount_cents' => (int) round(((float) data_get($charge, 'value', $salesLink->final_amount_cents / 100)) * 100),
                'paid_at' => $paidAt,
                'raw_payload' => array_merge($existing?->raw_payload ?? [], [
                    'asaas' => $charge,
                    'checkout_customer' => $payer ?: data_get($existing?->raw_payload, 'checkout_customer', []),
                    'raw_status_from_provider' => $rawStatus,
                    'normalized' => array_filter([
                        'qr_code' => data_get($qrCode, 'payload') ?? data_get($existing?->raw_payload, 'normalized.qr_code'),
                        'qr_code_base64' => data_get($qrCode, 'encodedImage') ?? data_get($existing?->raw_payload, 'normalized.qr_code_base64'),
                        'ticket_url' => data_get($charge, 'invoiceUrl') ?? data_get($existing?->raw_payload, 'normalized.ticket_url'),
                        'checkout_url' => data_get($charge, 'invoiceUrl') ?? data_get($existing?->raw_payload, 'normalized.checkout_url'),
                        'expires_at' => data_get($qrCode, 'expirationDate') ?? data_get($existing?->raw_payload, 'normalized.expires_at'),
                    ], fn ($value) => $value !== null),
                ]),
            ]
        );

        $salesLink->update(['status' => match ($status) {
            'approved' => 'paid',
            'cancelled', 'rejected', 'refunded', 'charged_back' => 'cancelled',
            default => 'pending',
        }]);

        app(EvolutionNotificationService::class)->notifyPaymentUpdated($payment->load('salesLink.product'));

        return $payment->refresh();
    }

    private function status(string $status): string
    {
        return match ($status) {
            'RECEIVED', 'RECEIVED_IN_CASH' => 'approved',
            'CONFIRMED' => 'in_process',
            'REFUNDED', 'REFUND_REQUESTED' => 'refunded',
            'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE', 'AWAITING_CHARGEBACK_REVERSAL' => 'charged_back',
            'DELETED' => 'cancelled',
            'OVERDUE' => 'pending',
            default => 'pending',
        };
    }

    private function client(TenantSetting $tenant): PendingRequest
    {
        $key = (string) data_get($tenant->payment_credentials, 'asaas.api_key');
        if ($key === '') {
            throw new RuntimeException('Asaas nao configurado para esta empresa.');
        }

        $baseUrl = str_starts_with($key, '$aact_prod_')
            ? 'https://api.asaas.com/v3'
            : 'https://api-sandbox.asaas.com/v3';

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'access_token' => $key,
                'User-Agent' => 'StoreTI/1.0 (Laravel)',
            ])
            ->timeout(30)
            ->retry(2, 300, throw: false);
    }

    private function json(Response $response, string $operation): array
    {
        if (! $response->successful()) {
            $message = collect($response->json('errors', []))->pluck('description')->filter()->implode(' ');
            throw new RuntimeException('Asaas recusou a operacao de '.$operation.': '.($message ?: "HTTP {$response->status()}"));
        }

        return $response->json() ?: [];
    }
}
