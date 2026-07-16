<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SalesLink;
use App\Models\TenantSetting;
use App\Services\PaymentCheckoutService;
use App\Services\SalesLinkPricing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class SalesLinkController extends Controller
{
    public function __construct(
        private readonly SalesLinkPricing $pricing,
        private readonly PaymentCheckoutService $checkout,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        return response()->json(
            SalesLink::with(['product', 'payments' => fn ($query) => $query->latest()])
                ->whereHas('product', fn ($query) => $query->where('tenant_setting_id', $tenantId))
                ->when($request->user()->role === 'seller', fn ($query) => $query->where('user_id', $request->user()->id))
                ->latest()
                ->get()
                ->map(fn (SalesLink $link) => $this->present($link))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'title' => ['required', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:160'],
            'quantity' => ['required', 'integer', 'min:1'],
            'discount_type' => ['required', 'in:none,fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $product = Product::findOrFail($data['product_id']);
        abort_unless($product->tenant_setting_id === $this->tenantId($request), 403);

        $discountValue = (float) ($data['discount_value'] ?? 0);
        $discountValueCents = $data['discount_type'] === 'fixed' ? (int) round($discountValue * 100) : 0;
        $discountPercent = $data['discount_type'] === 'percent' ? $discountValue : 0;
        $amounts = $this->pricing->calculate(
            $product,
            (int) $data['quantity'],
            $data['discount_type'],
            $discountValueCents,
            $discountPercent
        );
        $paymentConfigured = $this->checkout->configured($product->tenant ?: TenantSetting::current());

        $salesLink = SalesLink::create([
            'product_id' => $product->id,
            'user_id' => $request->user()->id,
            'title' => $data['title'],
            'customer_email' => $data['customer_email'] ?? null,
            'quantity' => $data['quantity'],
            'discount_type' => $data['discount_type'],
            'discount_value_cents' => $discountValueCents,
            'discount_percent' => $discountPercent,
            'expires_at' => $data['expires_at'] ?? null,
            'status' => $paymentConfigured ? 'ready' : 'draft',
            'metadata' => $paymentConfigured
                ? ['payment_setup' => 'configured']
                : ['payment_setup' => 'missing_provider_credentials'],
            ...$amounts,
        ]);

        return response()->json($this->present($salesLink->load(['product', 'payments'])), 201);
    }

    public function refresh(SalesLink $salesLink): JsonResponse
    {
        $this->authorizeTenant(request(), $salesLink);

        $payment = $salesLink->payments()->latest()->first();

        if (! $payment) {
            return response()->json([
                'message' => 'Esta venda ainda nao tem pagamento Pix para sincronizar.',
                'sale' => $this->present($salesLink->load(['product', 'payments' => fn ($query) => $query->latest()])),
            ], 422);
        }

        try {
            $this->checkout->syncPayment($payment->load('salesLink'));
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'sale' => $this->present($salesLink->refresh()->load(['product', 'payments' => fn ($query) => $query->latest()])),
            ], 422);
        }

        return response()->json([
            'message' => 'Venda sincronizada com o Mercado Pago.',
            'sale' => $this->present($salesLink->refresh()->load(['product', 'payments' => fn ($query) => $query->latest()])),
        ]);
    }

    public function update(Request $request, SalesLink $salesLink): JsonResponse
    {
        $this->authorizeTenant($request, $salesLink);

        $data = $request->validate([
            'status' => ['nullable', 'in:draft,ready,pending,paid,cancelled'],
            'delivery_status' => ['nullable', 'in:waiting_payment,preparing,shipped,delivered,cancelled'],
            'tracking_code' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'delivery_note' => ['nullable', 'string', 'max:500'],
        ]);

        $metadata = $salesLink->metadata ?? [];
        foreach (['delivery_status', 'tracking_code', 'tracking_url', 'delivery_note'] as $field) {
            if ($request->has($field)) {
                $metadata[$field] = $data[$field] ?? null;
            }
        }

        $salesLink->update([
            ...(isset($data['status']) ? ['status' => $data['status']] : []),
            'metadata' => $metadata,
        ]);

        return response()->json($this->present($salesLink->load(['product', 'payments'])));
    }

    public function destroy(SalesLink $salesLink): JsonResponse
    {
        $this->authorizeTenant(request(), $salesLink);

        $salesLink->delete();

        return response()->json(status: 204);
    }

    private function present(SalesLink $salesLink): array
    {
        $payment = $salesLink->payments->first();
        $checkoutCustomer = $payment?->raw_payload['checkout_customer'] ?? [];

        return [
            ...$salesLink->toArray(),
            'public_url' => $salesLink->publicUrl(),
            'customer' => [
                'name' => $salesLink->metadata['customer_name'] ?? $checkoutCustomer['name'] ?? null,
                'email' => $salesLink->customer_email ?? $checkoutCustomer['email'] ?? null,
                'phone' => $salesLink->metadata['customer_phone'] ?? $checkoutCustomer['phone'] ?? null,
                'cpf' => $salesLink->metadata['customer_cpf'] ?? $checkoutCustomer['cpf'] ?? null,
            ],
            'delivery' => [
                'status' => $salesLink->metadata['delivery_status'] ?? null,
                'tracking_code' => $salesLink->metadata['tracking_code'] ?? null,
                'tracking_url' => $salesLink->metadata['tracking_url'] ?? null,
                'note' => $salesLink->metadata['delivery_note'] ?? null,
                'region' => $salesLink->metadata['shipping_region'] ?? null,
                'eta' => $salesLink->metadata['shipping_eta'] ?? null,
            ],
        ];
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()->isSuperAdmin()
            ? TenantSetting::current()->id
            : $request->user()->tenant_setting_id;

        abort_unless($tenantId, 403, 'Usuario sem empresa vinculada.');

        return $tenantId;
    }

    private function authorizeTenant(Request $request, SalesLink $salesLink): void
    {
        $salesLink->loadMissing('product');

        abort_unless($salesLink->product?->tenant_setting_id === $this->tenantId($request), 403);

        if ($request->user()->role === 'seller') {
            abort_unless($salesLink->user_id === $request->user()->id, 403);
        }
    }
}
