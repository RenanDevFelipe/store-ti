<?php

namespace App\Http\Controllers;

use App\Models\SalesLink;
use App\Models\PaymentSetting;
use App\Models\TenantSetting;
use App\Services\EvolutionNotificationService;
use App\Services\MercadoPagoCheckoutService;
use App\Rules\ValidCpf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PublicSalesLinkController extends Controller
{
    public function show(SalesLink $salesLink): JsonResponse
    {
        abort_if($salesLink->expires_at?->isPast(), 410);
        abort_unless(($salesLink->product?->tenant ?: TenantSetting::current())->active, 404);

        $salesLink->load(['product', 'payments' => fn ($query) => $query->latest()]);

        return response()->json([
            ...$salesLink->toArray(),
            'tenant' => $this->tenantPayload($salesLink->product?->tenant ?: TenantSetting::current()),
            'payment_configured' => ($salesLink->product?->tenant ?: TenantSetting::current())->active_payment_provider === 'mercado_pago'
                && PaymentSetting::mercadoPago()->configured(),
        ]);
    }

    public function pix(
        Request $request,
        SalesLink $salesLink,
        MercadoPagoCheckoutService $checkout,
        EvolutionNotificationService $notifications
    ): JsonResponse
    {
        $customer = auth('customer')->user();
        abort_unless($customer, 401, 'Entre na sua conta de cliente para comprar.');

        abort_if($salesLink->expires_at?->isPast(), 410);
        $tenant = $salesLink->product?->tenant ?: TenantSetting::current();
        abort_unless($tenant->active, 404);
        abort_unless($customer->tenant_setting_id === $tenant->id, 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:30'],
            'cpf' => ['nullable', 'string', 'max:20', new ValidCpf()],
            'cep' => ['nullable', 'string', 'max:12'],
            'shipping_region' => ['nullable', 'string', 'max:80'],
            'shipping_eta' => ['nullable', 'string', 'max:80'],
            'shipping_amount_cents' => ['nullable', 'integer', 'min:0'],
            'selected_size' => ['nullable', 'string', 'max:40'],
            'selected_color' => ['nullable', 'string', 'max:40'],
            'customer_address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
        ]);

        if (filled($data['customer_address_id'] ?? null)) {
            abort_unless($customer->addresses()->whereKey($data['customer_address_id'])->exists(), 403);
        }
        if ($salesLink->product?->requires_shipping) {
            abort_if(blank($data['customer_address_id'] ?? null), 422, 'Selecione um endereco de entrega.');
        }

        $salesLink->update([
            'customer_id' => $customer->id,
            'customer_address_id' => $data['customer_address_id'] ?? null,
            'customer_email' => $customer->email,
            'metadata' => array_merge($salesLink->metadata ?? [], [
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'customer_cpf' => $customer->cpf,
                'customer_cep' => preg_replace('/\D+/', '', $data['cep'] ?? ''),
                'shipping_region' => $data['shipping_region'] ?? null,
                'shipping_eta' => $data['shipping_eta'] ?? null,
                'shipping_amount_cents' => $data['shipping_amount_cents'] ?? 0,
                'selected_size' => $data['selected_size'] ?? null,
                'selected_color' => $data['selected_color'] ?? null,
            ]),
        ]);

        $notifications->notifySaleCreated($salesLink->load('product'));

        try {
            $payment = $checkout->createPixPayment($salesLink->load('product'), [
                ...$data,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'cpf' => $customer->cpf,
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        $transactionData = data_get($payment->raw_payload, 'point_of_interaction.transaction_data', []);

        return response()->json([
            'payment_id' => $payment->mp_payment_id,
            'status' => $payment->status,
            'status_detail' => $payment->status_detail,
            'qr_code' => $transactionData['qr_code'] ?? null,
            'qr_code_base64' => $transactionData['qr_code_base64'] ?? null,
            'ticket_url' => $transactionData['ticket_url'] ?? null,
        ], 201);
    }

    public function status(SalesLink $salesLink, MercadoPagoCheckoutService $checkout): JsonResponse
    {
        abort_unless(($salesLink->product?->tenant ?: TenantSetting::current())->active, 404);

        $payment = $salesLink->payments()->latest()->first();

        if ($payment && ! in_array($payment->status, ['approved', 'cancelled', 'rejected', 'refunded', 'charged_back'], true)) {
            try {
                $payment = $checkout->syncPayment($payment->load('salesLink'));
                $salesLink = $payment->salesLink;
            } catch (RuntimeException) {
                //
            }
        }

        return response()->json([
            'sales_link_status' => $salesLink->status,
            'payment' => $payment ? [
                'id' => $payment->mp_payment_id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'amount_cents' => $payment->amount_cents,
                'paid_at' => $payment->paid_at,
            ] : null,
        ]);
    }

    public function checkout(SalesLink $salesLink, MercadoPagoCheckoutService $checkout): RedirectResponse
    {
        abort_unless(auth('customer')->user(), 401, 'Entre na sua conta de cliente para comprar.');

        abort_if($salesLink->expires_at?->isPast(), 410);
        abort_unless(($salesLink->product?->tenant ?: TenantSetting::current())->active, 404);

        try {
            $salesLink = $checkout->createPreference($salesLink->load('product'));
        } catch (RuntimeException) {
            return redirect()->route('public.sales-link.page', $salesLink)
                ->with('payment_error', 'Nao foi possivel gerar o pagamento agora.');
        }

        if (! $salesLink->checkout_url) {
            return redirect()->route('public.sales-link.page', $salesLink)
                ->with('payment_error', 'Pagamento ainda nao configurado.');
        }

        return redirect()->away($salesLink->checkout_url);
    }

    private function tenantPayload(TenantSetting $tenant): array
    {
        return [
            'name' => $tenant->name,
            'id' => $tenant->id,
            'store_slug' => $tenant->store_slug,
            'store_title' => $tenant->store_title ?: $tenant->name,
            'checkout_primary_color' => $tenant->checkout_primary_color,
            'checkout_button_color' => $tenant->checkout_button_color,
            'store_shipping_regions' => $tenant->store_shipping_regions ?: TenantSetting::defaultShippingRegions(),
            'payment_provider_label' => TenantSetting::PAYMENT_PROVIDERS[$tenant->active_payment_provider] ?? $tenant->active_payment_provider,
        ];
    }
}
