<?php

namespace App\Services;

use App\Models\NotificationContact;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\Payment;
use App\Models\SalesLink;
use App\Models\TenantNotificationSetting;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\Http;
use Throwable;

class EvolutionNotificationService
{
    public function notifySaleCreated(SalesLink $salesLink): void
    {
        $salesLink->loadMissing('product');
        $provider = NotificationSetting::evolution();
        $settings = TenantNotificationSetting::forTenant((int) $salesLink->product->tenant_setting_id);

        if (! $settings->enabled || ! $settings->notify_sale_created) {
            return;
        }

        $this->sendToRecipients(
            $provider,
            $settings,
            'sale_created',
            $salesLink,
            null,
            $settings->sale_created_message ?: NotificationSetting::DEFAULT_SALE_CREATED_MESSAGE
        );
    }

    public function notifyPaymentUpdated(Payment $payment): void
    {
        if ($payment->status !== 'approved') {
            return;
        }

        $payment->loadMissing('salesLink.product');
        $provider = NotificationSetting::evolution();
        $settings = TenantNotificationSetting::forTenant((int) $payment->salesLink->product->tenant_setting_id);

        if (! $settings->enabled || ! $settings->notify_payment_approved) {
            return;
        }

        $this->sendToRecipients(
            $provider,
            $settings,
            'payment_approved',
            $payment->salesLink,
            $payment,
            $settings->payment_approved_message ?: NotificationSetting::DEFAULT_PAYMENT_APPROVED_MESSAGE
        );
    }

    public function sendTest(string $phone, ?string $name = null): NotificationLog
    {
        $provider = NotificationSetting::evolution();
        $tenant = TenantSetting::current();
        $message = 'Teste de notificacao Store TI via Evolution.';
        $recipient = [
            'name' => $name ?: 'Teste',
            'phone' => $phone,
            'type' => 'test',
        ];

        return $this->send($provider, 'test', null, null, $recipient, $message, false, $tenant->id);
    }

    private function sendToRecipients(
        NotificationSetting $settings,
        TenantNotificationSetting $tenantSettings,
        string $event,
        ?SalesLink $salesLink,
        ?Payment $payment,
        string $template
    ): void {
        if (! $salesLink) {
            return;
        }

        $message = $this->renderMessage($template, $salesLink, $payment);
        $tenantId = (int) $tenantSettings->tenant_setting_id;

        foreach ($this->recipients($tenantSettings, $salesLink) as $recipient) {
            $this->send($settings, $event, $salesLink, $payment, $recipient, $message, true, $tenantId);
        }
    }

    private function recipients(TenantNotificationSetting $settings, SalesLink $salesLink): array
    {
        $recipients = NotificationContact::where('active', true)
            ->where('tenant_setting_id', $settings->tenant_setting_id)
            ->get()
            ->map(fn (NotificationContact $contact) => [
                'name' => $contact->name,
                'phone' => $contact->phone,
                'type' => 'fixed',
            ])
            ->all();

        if ($settings->dynamic_customer_enabled) {
            $phone = $salesLink->metadata['customer_phone'] ?? null;

            if (filled($phone)) {
                $recipients[] = [
                    'name' => $salesLink->metadata['customer_name'] ?? 'Cliente',
                    'phone' => $phone,
                    'type' => 'customer',
                ];
            }
        }

        $unique = [];

        foreach ($recipients as $recipient) {
            $phone = $this->normalizePhone($recipient['phone']);

            if (! $phone || isset($unique[$recipient['type'].'-'.$phone])) {
                continue;
            }

            $unique[$recipient['type'].'-'.$phone] = [
                ...$recipient,
                'phone' => $phone,
            ];
        }

        return array_values($unique);
    }

    private function send(
        NotificationSetting $settings,
        string $event,
        ?SalesLink $salesLink,
        ?Payment $payment,
        array $recipient,
        string $message,
        bool $preventDuplicate = true,
        ?int $tenantId = null
    ): NotificationLog {
        $phone = $this->normalizePhone($recipient['phone'] ?? null);
        $tenantId ??= $salesLink?->product?->tenant_setting_id;

        if (! $phone) {
            return NotificationLog::create([
                'tenant_setting_id' => $tenantId,
                'event' => $event,
                'sales_link_id' => $salesLink?->id,
                'payment_id' => $payment?->id,
                'recipient_name' => $recipient['name'] ?? null,
                'recipient_phone' => (string) ($recipient['phone'] ?? ''),
                'recipient_type' => $recipient['type'] ?? 'fixed',
                'status' => 'failed',
                'message' => $message,
                'error_message' => 'Telefone invalido. Informe com DDD, exemplo: 81982523877.',
            ]);
        }

        if ($preventDuplicate && $this->alreadySent($event, $salesLink, $payment, $phone)) {
            return NotificationLog::create([
                'tenant_setting_id' => $tenantId,
                'event' => $event,
                'sales_link_id' => $salesLink?->id,
                'payment_id' => $payment?->id,
                'recipient_name' => $recipient['name'] ?? null,
                'recipient_phone' => $phone,
                'recipient_type' => $recipient['type'] ?? 'fixed',
                'status' => 'skipped',
                'message' => $message,
                'error_message' => 'Notificacao ja enviada para este evento.',
            ]);
        }

        $log = NotificationLog::create([
            'tenant_setting_id' => $tenantId,
            'event' => $event,
            'sales_link_id' => $salesLink?->id,
            'payment_id' => $payment?->id,
            'recipient_name' => $recipient['name'] ?? null,
            'recipient_phone' => $phone,
            'recipient_type' => $recipient['type'] ?? 'fixed',
            'status' => 'pending',
            'message' => $message,
        ]);

        if (! $settings->configured()) {
            $log->update([
                'status' => 'skipped',
                'error_message' => 'Evolution nao configurado ou desativado.',
            ]);

            return $log->refresh();
        }

        try {
            $response = $this->postTextMessage($settings, [
                'number' => $phone,
                'text' => $message,
            ]);

            if ($response->status() === 400) {
                $response = $this->postTextMessage($settings, [
                    'number' => $phone,
                    'textMessage' => [
                        'text' => $message,
                    ],
                ]);
            }

            $log->update([
                'status' => $response->successful() ? 'sent' : 'failed',
                'response_payload' => $response->json() ?? ['body' => $response->body()],
                'error_message' => $response->successful() ? null : $this->responseErrorMessage($response),
                'sent_at' => $response->successful() ? now() : null,
            ]);
        } catch (Throwable $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }

        return $log->refresh();
    }

    private function postTextMessage(NotificationSetting $settings, array $payload): \Illuminate\Http\Client\Response
    {
        return Http::withHeaders([
            'apikey' => $settings->api_key,
            'Accept' => 'application/json',
        ])->timeout(12)->post($this->sendTextUrl($settings), $payload);
    }

    private function responseErrorMessage(\Illuminate\Http\Client\Response $response): string
    {
        $content = $response->json();
        $message = data_get($content, 'message')
            ?? data_get($content, 'error')
            ?? data_get($content, 'response.message')
            ?? $response->body();

        $message = trim((string) $message);

        return 'Evolution retornou HTTP '.$response->status().($message ? ': '.$message : '.');
    }

    private function alreadySent(string $event, ?SalesLink $salesLink, ?Payment $payment, string $phone): bool
    {
        return NotificationLog::where('event', $event)
            ->where('sales_link_id', $salesLink?->id)
            ->where('payment_id', $payment?->id)
            ->where('recipient_phone', $phone)
            ->where('status', 'sent')
            ->exists();
    }

    private function renderMessage(string $template, SalesLink $salesLink, ?Payment $payment): string
    {
        $salesLink->loadMissing('product');

        $replacements = [
            '{cliente}' => $salesLink->metadata['customer_name'] ?? 'Cliente',
            '{email}' => $salesLink->customer_email ?? 'sem email',
            '{telefone}' => $salesLink->metadata['customer_phone'] ?? 'sem telefone',
            '{cpf}' => $salesLink->metadata['customer_cpf'] ?? 'sem cpf',
            '{produto}' => $salesLink->product?->name ?? $salesLink->title,
            '{valor}' => 'R$ '.number_format($salesLink->final_amount_cents / 100, 2, ',', '.'),
            '{status}' => $salesLink->status,
            '{pedido}' => $salesLink->public_id,
            '{pagamento}' => $payment?->mp_payment_id ?? 'sem pagamento',
        ];

        return strtr($template, $replacements);
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if (strlen($digits) === 10 || strlen($digits) === 11) {
            return '55'.$digits;
        }

        return strlen($digits) >= 12 ? $digits : null;
    }

    private function sendTextUrl(NotificationSetting $settings): string
    {
        return rtrim((string) $settings->base_url, '/').'/message/sendText/'.rawurlencode((string) $settings->instance);
    }
}
