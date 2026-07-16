<?php

namespace App\Http\Controllers;

use App\Models\PaymentSetting;
use App\Models\Product;
use App\Models\StoreTheme;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;

class PublicStorefrontController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $tenant = TenantSetting::where('store_slug', $slug)
            ->where('active', true)
            ->firstOrFail();
        $products = Product::where('tenant_setting_id', $tenant->id)
            ->where('active', true)
            ->latest()
            ->get()
            ->map(fn (Product $product) => [
                ...$product->only([
                    'id',
                    'name',
                    'sku',
                    'type',
                    'description',
                    'image_url',
                    'gallery_urls',
                    'options',
                    'requires_shipping',
                    'shipping_weight_grams',
                    'price_cents',
                    'discount_type',
                    'discount_value_cents',
                    'discount_percent',
                    'currency',
                    'track_stock',
                    'billing_cycle',
                    'stock',
                    'active',
                ]),
                'discount_amount_cents' => $product->discountAmountCents(),
                'final_amount_cents' => $product->finalAmountCents(),
                'public_url' => $product->publicUrl(),
            ]);

        return response()->json([
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'store_slug' => $tenant->store_slug,
                'store_title' => $tenant->store_title ?: $tenant->name,
                'store_subtitle' => $tenant->store_subtitle,
                'store_banner_label' => $tenant->store_banner_label,
                'store_banner_image_url' => $tenant->store_banner_image_url,
                'store_featured_image_url' => $tenant->store_featured_image_url,
                'store_featured_label' => $tenant->store_featured_label,
                'store_featured_title' => $tenant->store_featured_title,
                'store_featured_subtitle' => $tenant->store_featured_subtitle,
                'store_featured_cta' => $tenant->store_featured_cta,
                'store_secure_image_url' => $tenant->store_secure_image_url,
                'store_secure_label' => $tenant->store_secure_label,
                'store_secure_title' => $tenant->store_secure_title,
                'store_secure_subtitle' => $tenant->store_secure_subtitle,
                'store_secure_cta' => $tenant->store_secure_cta,
                'store_shipping_regions' => $tenant->store_shipping_regions ?: TenantSetting::defaultShippingRegions(),
                'store_theme' => $tenant->store_theme ?: 'default',
                'custom_theme' => StoreTheme::where('tenant_setting_id', $tenant->id)
                    ->where('slug', $tenant->store_theme)
                    ->where('active', true)
                    ->first(),
                'support_phone' => $tenant->support_phone,
                'support_email' => $tenant->support_email,
                'checkout_primary_color' => $tenant->checkout_primary_color,
                'checkout_button_color' => $tenant->checkout_button_color,
                'payment_provider_label' => TenantSetting::PAYMENT_PROVIDERS[$tenant->active_payment_provider] ?? $tenant->active_payment_provider,
                'payment_configured' => $tenant->active_payment_provider === 'mercado_pago'
                    && PaymentSetting::mercadoPago()->configured(),
            ],
            'products' => $products,
        ]);
    }
}
