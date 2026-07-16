<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\SalesLink;
use App\Models\TenantSetting;
use RuntimeException;

class PaymentCheckoutService
{
    public function createPixPayment(SalesLink $salesLink, array $payer): Payment
    {
        $provider = $this->providerFor($salesLink);

        return match ($provider) {
            'mercado_pago' => app(MercadoPagoCheckoutService::class)->createPixPayment($salesLink, $payer),
            'asaas' => app(AsaasCheckoutService::class)->createPixPayment($salesLink, $payer),
            default => throw new RuntimeException("Provedor de pagamento [$provider] ainda nao integrado."),
        };
    }

    public function syncPayment(Payment $payment): Payment
    {
        return match ($payment->provider ?: 'mercado_pago') {
            'mercado_pago' => app(MercadoPagoCheckoutService::class)->syncPayment($payment),
            'asaas' => app(AsaasCheckoutService::class)->syncPayment($payment),
            default => throw new RuntimeException("Provedor de pagamento [{$payment->provider}] ainda nao integrado."),
        };
    }

    public function configured(TenantSetting $tenant): bool
    {
        return match ($tenant->active_payment_provider) {
            'mercado_pago' => \App\Models\PaymentSetting::mercadoPago()->configured(),
            'asaas' => filled(data_get($tenant->payment_credentials, 'asaas.api_key')),
            default => false,
        };
    }

    public function response(Payment $payment): array
    {
        return [
            'sale_id' => $payment->salesLink?->public_id,
            'payment_id' => $payment->provider_payment_id ?: $payment->mp_payment_id,
            'provider' => $payment->provider ?: 'mercado_pago',
            'status' => $payment->status,
            'status_detail' => $payment->status_detail,
            'payment_method' => $payment->payment_method_id ?: 'pix',
            'qr_code' => data_get($payment->raw_payload, 'normalized.qr_code')
                ?? data_get($payment->raw_payload, 'point_of_interaction.transaction_data.qr_code'),
            'qr_code_base64' => data_get($payment->raw_payload, 'normalized.qr_code_base64')
                ?? data_get($payment->raw_payload, 'point_of_interaction.transaction_data.qr_code_base64'),
            'ticket_url' => data_get($payment->raw_payload, 'normalized.ticket_url')
                ?? data_get($payment->raw_payload, 'point_of_interaction.transaction_data.ticket_url'),
            'checkout_url' => data_get($payment->raw_payload, 'normalized.checkout_url'),
            'expires_at' => data_get($payment->raw_payload, 'normalized.expires_at'),
        ];
    }

    public function checkoutUrl(SalesLink $salesLink): string
    {
        $provider = $this->providerFor($salesLink);

        if ($provider === 'mercado_pago') {
            return (string) app(MercadoPagoCheckoutService::class)->createPreference($salesLink)->checkout_url;
        }

        $url = data_get($salesLink->payments()->latest()->first()?->raw_payload, 'normalized.checkout_url');

        if (! $url) {
            throw new RuntimeException('Gere o pagamento Pix antes de abrir o checkout do Asaas.');
        }

        return $url;
    }

    private function providerFor(SalesLink $salesLink): string
    {
        $tenant = $salesLink->product?->tenant ?: TenantSetting::current();
        $provider = $tenant->active_payment_provider ?: 'mercado_pago';

        abort_unless((bool) data_get($tenant->payment_providers, "$provider.enabled", $provider === 'mercado_pago'), 422, 'Provedor de pagamento inativo.');

        return $provider;
    }
}
