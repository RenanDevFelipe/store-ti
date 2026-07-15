<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Product::where('tenant_setting_id', $this->tenantId($request))
                ->latest()
                ->get()
                ->map(fn (Product $product) => $this->present($product))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:products,sku'],
            'type' => ['required', 'in:physical,internet_plan,service,subscription,other'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'gallery_urls' => ['array'],
            'gallery_urls.*' => ['nullable', 'url', 'max:500'],
            'options' => ['array'],
            'options.sizes' => ['array'],
            'options.sizes.*' => ['nullable', 'string', 'max:40'],
            'options.colors' => ['array'],
            'options.colors.*' => ['nullable', 'string', 'max:40'],
            'options.variants' => ['array'],
            'options.variants.*.size' => ['nullable', 'string', 'max:40'],
            'options.variants.*.color' => ['nullable', 'string', 'max:40'],
            'options.variants.*.price_cents' => ['nullable', 'integer', 'min:0'],
            'options.variants.*.image_url' => ['nullable', 'url', 'max:500'],
            'requires_shipping' => ['boolean'],
            'shipping_weight_grams' => ['nullable', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'discount_type' => ['nullable', 'in:none,fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'track_stock' => ['boolean'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'billing_cycle' => ['nullable', 'in:none,monthly,quarterly,semiannual,annual,one_time'],
            'active' => ['boolean'],
        ]);

        $trackStock = (bool) ($data['track_stock'] ?? $data['type'] === 'physical');
        $discountType = $data['discount_type'] ?? 'none';
        $discountValue = (float) ($data['discount_value'] ?? 0);

        $product = Product::create([
            ...$data,
            'tenant_setting_id' => $this->tenantId($request),
            'gallery_urls' => array_values(array_filter($data['gallery_urls'] ?? [])),
            'options' => $this->cleanOptions($data['options'] ?? []),
            'requires_shipping' => (bool) ($data['requires_shipping'] ?? $data['type'] === 'physical'),
            'discount_type' => $discountType,
            'price_cents' => (int) round($data['price'] * 100),
            'discount_value_cents' => $discountType === 'fixed' ? (int) round($discountValue * 100) : 0,
            'discount_percent' => $discountType === 'percent' ? $discountValue : 0,
            'currency' => 'BRL',
            'track_stock' => $trackStock,
            'stock' => $trackStock ? (int) ($data['stock'] ?? 0) : null,
            'billing_cycle' => $data['billing_cycle'] ?? ($data['type'] === 'physical' ? 'one_time' : 'monthly'),
            'active' => $data['active'] ?? true,
        ]);

        return response()->json($this->present($product), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        if (blank($product->tenant_setting_id)) {
            $product->forceFill(['tenant_setting_id' => $this->tenantId($request)])->save();
        }

        abort_unless($product->tenant_setting_id === $this->tenantId($request), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'sku' => ['nullable', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product)],
            'type' => ['required', 'in:physical,internet_plan,service,subscription,other'],
            'description' => ['nullable', 'string', 'max:2000'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'gallery_urls' => ['array'],
            'gallery_urls.*' => ['nullable', 'url', 'max:500'],
            'options' => ['array'],
            'options.sizes' => ['array'],
            'options.sizes.*' => ['nullable', 'string', 'max:40'],
            'options.colors' => ['array'],
            'options.colors.*' => ['nullable', 'string', 'max:40'],
            'options.variants' => ['array'],
            'options.variants.*.size' => ['nullable', 'string', 'max:40'],
            'options.variants.*.color' => ['nullable', 'string', 'max:40'],
            'options.variants.*.price_cents' => ['nullable', 'integer', 'min:0'],
            'options.variants.*.image_url' => ['nullable', 'url', 'max:500'],
            'requires_shipping' => ['boolean'],
            'shipping_weight_grams' => ['nullable', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0.01'],
            'discount_type' => ['nullable', 'in:none,fixed,percent'],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'track_stock' => ['boolean'],
            'stock' => ['nullable', 'integer', 'min:0'],
            'billing_cycle' => ['nullable', 'in:none,monthly,quarterly,semiannual,annual,one_time'],
            'active' => ['boolean'],
        ]);

        $trackStock = (bool) ($data['track_stock'] ?? false);
        $discountType = $data['discount_type'] ?? 'none';
        $discountValue = (float) ($data['discount_value'] ?? 0);

        $product->update([
            ...$data,
            'gallery_urls' => array_values(array_filter($data['gallery_urls'] ?? [])),
            'options' => $this->cleanOptions($data['options'] ?? []),
            'requires_shipping' => (bool) ($data['requires_shipping'] ?? false),
            'discount_type' => $discountType,
            'price_cents' => (int) round($data['price'] * 100),
            'discount_value_cents' => $discountType === 'fixed' ? (int) round($discountValue * 100) : 0,
            'discount_percent' => $discountType === 'percent' ? $discountValue : 0,
            'track_stock' => $trackStock,
            'stock' => $trackStock ? (int) ($data['stock'] ?? 0) : null,
            'billing_cycle' => $data['billing_cycle'] ?? ($data['type'] === 'physical' ? 'one_time' : 'monthly'),
            'active' => $data['active'] ?? false,
        ]);

        return response()->json($this->present($product->refresh()));
    }

    public function destroy(Product $product): JsonResponse
    {
        if (blank($product->tenant_setting_id)) {
            $product->forceFill(['tenant_setting_id' => $this->tenantId(request())])->save();
        }

        abort_unless($product->tenant_setting_id === $this->tenantId(request()), 403);

        $product->delete();

        return response()->json(status: 204);
    }

    private function present(Product $product): array
    {
        return [
            ...$product->toArray(),
            'discount_amount_cents' => $product->discountAmountCents(),
            'final_amount_cents' => $product->finalAmountCents(),
            'public_url' => $product->publicUrl(),
        ];
    }

    private function cleanOptions(array $options): array
    {
        return [
            'sizes' => array_values(array_filter($options['sizes'] ?? [])),
            'colors' => array_values(array_filter($options['colors'] ?? [])),
            'variants' => collect($options['variants'] ?? [])
                ->filter(fn (array $variant) => filled($variant['size'] ?? null) || filled($variant['color'] ?? null))
                ->map(fn (array $variant) => [
                    'size' => $variant['size'] ?? null,
                    'color' => $variant['color'] ?? null,
                    'price_cents' => isset($variant['price_cents']) ? (int) $variant['price_cents'] : null,
                    'image_url' => $variant['image_url'] ?? null,
                ])
                ->values()
                ->all(),
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
}
