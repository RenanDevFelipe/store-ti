<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\SalesLink;
use App\Models\TenantSetting;
use App\Rules\ValidCpf;
use App\Services\EvolutionNotificationService;
use App\Services\PaymentCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PublicProductCheckoutController extends Controller
{
    public function show(string $publicId): JsonResponse
    {
        $product = Product::where('public_id', $publicId)->firstOrFail();

        abort_unless($product->active && ($product->tenant ?: TenantSetting::current())->active, 404);

        return response()->json([
            ...$product->toArray(),
            'discount_amount_cents' => $product->discountAmountCents(),
            'final_amount_cents' => $product->finalAmountCents(),
            'tenant' => $this->tenantPayload($product->tenant ?: TenantSetting::current()),
            'payment_configured' => app(PaymentCheckoutService::class)->configured($product->tenant ?: TenantSetting::current()),
        ]);
    }

    public function pix(
        Request $request,
        string $publicId,
        PaymentCheckoutService $checkout,
        EvolutionNotificationService $notifications
    ): JsonResponse
    {
        $customer = auth('customer')->user();
        abort_unless($customer, 401, 'Entre na sua conta de cliente para comprar.');

        $product = Product::where('public_id', $publicId)->firstOrFail();
        $tenant = $product->tenant ?: TenantSetting::current();

        abort_unless($product->active && $tenant->active, 404);
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
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'customer_address_id' => ['nullable', 'integer', 'exists:customer_addresses,id'],
        ]);

        $quantity = (int) ($data['quantity'] ?? 1);
        if (filled($data['customer_address_id'] ?? null)) {
            abort_unless($customer->addresses()->whereKey($data['customer_address_id'])->exists(), 403);
        }
        if ($product->requires_shipping) {
            abort_if(blank($data['customer_address_id'] ?? null), 422, 'Selecione um endereco de entrega.');
        }

        $shippingAmountCents = (int) ($data['shipping_amount_cents'] ?? 0);
        $variant = $this->selectedVariant($product, $data['selected_size'] ?? null, $data['selected_color'] ?? null);
        $unitAmountCents = $variant['price_cents'] ?? $product->finalAmountCents();
        $originalAmountCents = $unitAmountCents * $quantity;
        $discountAmountCents = 0;
        $finalAmountCents = ($unitAmountCents * $quantity) + $shippingAmountCents;

        $salesLink = SalesLink::create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'customer_address_id' => $data['customer_address_id'] ?? null,
            'title' => $product->name,
            'customer_email' => $customer->email,
            'quantity' => $quantity,
            'discount_type' => $product->discount_type,
            'discount_value_cents' => $product->discount_value_cents,
            'discount_percent' => $product->discount_percent,
            'original_amount_cents' => $originalAmountCents,
            'discount_amount_cents' => $discountAmountCents,
            'final_amount_cents' => $finalAmountCents,
            'status' => 'pending',
            'metadata' => [
                'origin' => 'product_checkout',
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'customer_cpf' => $customer->cpf,
                'customer_cep' => preg_replace('/\D+/', '', $data['cep'] ?? ''),
                'shipping_region' => $data['shipping_region'] ?? null,
                'shipping_eta' => $data['shipping_eta'] ?? null,
                'shipping_amount_cents' => $shippingAmountCents,
                'selected_size' => $data['selected_size'] ?? null,
                'selected_color' => $data['selected_color'] ?? null,
                'variant_price_cents' => $variant['price_cents'] ?? null,
                'variant_image_url' => $variant['image_url'] ?? null,
                'quantity' => $quantity,
            ],
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
            $salesLink->update([
                'status' => 'pending',
                'metadata' => array_merge($salesLink->metadata ?? [], [
                    'payment_error' => $exception->getMessage(),
                ]),
            ]);

            return response()->json([
                'sale_id' => $salesLink->public_id,
                'status' => $salesLink->status,
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json($checkout->response($payment->load('salesLink')), 201);
    }

    public function status(string $publicId, Request $request, PaymentCheckoutService $checkout): JsonResponse
    {
        $salesLink = SalesLink::where('public_id', $request->query('sale'))->first();
        $payment = $salesLink?->payments()->latest()->first();

        if ($payment && ! in_array($payment->status, ['approved', 'cancelled', 'rejected', 'refunded', 'charged_back'], true)) {
            try {
                $payment = $checkout->syncPayment($payment->load('salesLink'));
                $salesLink = $payment->salesLink;
            } catch (RuntimeException) {
                //
            }
        }

        return response()->json([
            'sales_link_status' => $salesLink?->status,
            'payment' => $payment ? [
                'id' => $payment->mp_payment_id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'amount_cents' => $payment->amount_cents,
                'paid_at' => $payment->paid_at,
            ] : null,
        ]);
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

    private function selectedVariant(Product $product, ?string $size, ?string $color): ?array
    {
        $variants = $product->options['variants'] ?? [];

        return collect($variants)
            ->first(fn (array $variant) => ($variant['size'] ?? null) === $size && ($variant['color'] ?? null) === $color)
            ?: collect($variants)->first(fn (array $variant) => ($variant['size'] ?? null) === $size && blank($variant['color'] ?? null))
            ?: collect($variants)->first(fn (array $variant) => blank($variant['size'] ?? null) && ($variant['color'] ?? null) === $color);
    }
}
