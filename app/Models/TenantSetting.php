<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;

class TenantSetting extends Model
{
    public const PAYMENT_PROVIDERS = [
        'mercado_pago' => 'Mercado Pago',
        'asaas' => 'Asaas',
        'abacate_pay' => 'Abacate Pay',
        'efi' => 'Efí / Gerencianet',
        'pagseguro' => 'PagSeguro',
        'pagarme' => 'Pagar.me',
        'stripe' => 'Stripe',
        'iugu' => 'Iugu',
        'paypal' => 'PayPal',
        'banco_do_brasil' => 'Banco do Brasil',
        'itau' => 'Itaú',
        'sicredi' => 'Sicredi',
    ];

    protected $fillable = [
        'name',
        'is_current',
        'active',
        'store_slug',
        'document',
        'support_phone',
        'support_email',
        'admin_primary_color',
        'admin_accent_color',
        'checkout_primary_color',
        'checkout_button_color',
        'active_payment_provider',
        'payment_providers',
        'payment_credentials',
        'store_theme',
        'store_title',
        'store_subtitle',
        'store_banner_label',
        'store_banner_image_url',
        'store_featured_image_url',
        'store_featured_label',
        'store_featured_title',
        'store_featured_subtitle',
        'store_featured_cta',
        'store_secure_image_url',
        'store_secure_label',
        'store_secure_title',
        'store_secure_subtitle',
        'store_secure_cta',
        'store_shipping_regions',
    ];

    protected $casts = [
        'payment_providers' => 'array',
        'payment_credentials' => 'encrypted:array',
        'store_shipping_regions' => 'array',
        'is_current' => 'boolean',
        'active' => 'boolean',
    ];

    public static function current(): self
    {
        $user = Auth::user();

        if ($user && ! $user->isSuperAdmin() && $user->tenant_setting_id) {
            $tenant = self::find($user->tenant_setting_id);

            if ($tenant) {
                return $tenant;
            }
        }

        if ($user && $user->isSuperAdmin() && session()->has('superadmin_tenant_id')) {
            $tenant = self::find(session('superadmin_tenant_id'));

            if ($tenant) {
                return $tenant;
            }
        }

        $current = self::where('is_current', true)->first();

        if ($current) {
            return $current;
        }

        $defaults = [
            'name' => 'Store TI',
            'is_current' => true,
            'active' => true,
            'payment_providers' => self::defaultProviders(),
        ];

        if (Schema::hasColumn('tenant_settings', 'store_slug')) {
            $defaults += [
                'store_slug' => 'store-ti',
                'store_title' => 'Store TI',
                'store_subtitle' => 'Conheca nossas ofertas e contrate online com seguranca.',
                'store_banner_label' => 'Ofertas em destaque',
                'store_shipping_regions' => self::defaultShippingRegions(),
            ];
        }

        $tenant = self::firstOrCreate([], $defaults);

        if (! $tenant->is_current) {
            $tenant->forceFill(['is_current' => true])->save();
        }

        return $tenant;
    }

    public static function selectedForSuperAdmin(): ?self
    {
        $user = Auth::user();

        if (! $user?->isSuperAdmin() || ! session()->has('superadmin_tenant_id')) {
            return null;
        }

        return self::find(session('superadmin_tenant_id'));
    }

    public static function defaultProviders(): array
    {
        return collect(self::PAYMENT_PROVIDERS)
            ->mapWithKeys(fn (string $label, string $provider) => [$provider => [
                'label' => $label,
                'enabled' => $provider === 'mercado_pago',
                'configured' => false,
                'credential_fields' => self::credentialFields($provider),
                'notes' => match ($provider) {
                    'mercado_pago' => 'Pix ativo no sistema quando o token estiver configurado.',
                    'asaas' => 'Suporta Pix, boleto e cartao via API Asaas.',
                    'efi' => 'Suporta Pix/boleto via Efí com certificado.',
                    'pagseguro' => 'Suporta Pix, boleto e cartao via PagSeguro.',
                    'pagarme' => 'Suporta Pix, boleto e cartao via Pagar.me.',
                    'stripe' => 'Suporta cartao e carteiras digitais via Stripe.',
                    'iugu' => 'Suporta boleto, Pix e cartao via Iugu.',
                    'paypal' => 'Suporta PayPal Checkout.',
                    default => 'Gateway preparado para receber credenciais da empresa.',
                },
            ]])
            ->all();
    }

    public static function credentialFields(string $provider): array
    {
        return match ($provider) {
            'mercado_pago' => ['access_token', 'public_key'],
            'asaas' => ['api_key', 'webhook_token'],
            'abacate_pay', 'iugu' => ['api_key'],
            'efi' => ['client_id', 'client_secret', 'certificate_path'],
            'pagseguro' => ['token', 'email'],
            'pagarme', 'stripe' => ['secret_key', 'public_key'],
            'paypal' => ['client_id', 'client_secret'],
            'banco_do_brasil', 'itau', 'sicredi' => ['client_id', 'client_secret', 'pix_key'],
            default => ['api_key'],
        };
    }

    public static function requiredCredentialFields(string $provider): array
    {
        return match ($provider) {
            'asaas' => ['api_key'],
            default => self::credentialFields($provider),
        };
    }

    public static function defaultShippingRegions(): array
    {
        return [
            [
                'region' => 'Retirada / Digital',
                'cep_prefix' => '',
                'price_cents' => 0,
                'eta' => 'Imediato',
            ],
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'tenant_setting_id');
    }

    public function storeThemes(): HasMany
    {
        return $this->hasMany(StoreTheme::class, 'tenant_setting_id');
    }
}
