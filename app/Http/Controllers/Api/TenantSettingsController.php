<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentSetting;
use App\Models\StoreTheme;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class TenantSettingsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        $selectedTenantId = TenantSetting::selectedForSuperAdmin()?->id;

        return response()->json(
            TenantSetting::latest()->get()->map(fn (TenantSetting $tenant) => $this->present($tenant, selectedTenantId: $selectedTenantId))
        );
    }

    public function show(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'seller', 403);

        $selectedTenant = $request->user()?->isSuperAdmin()
            ? TenantSetting::selectedForSuperAdmin()
            : TenantSetting::current();

        if (! $selectedTenant) {
            return response()->json([
                'id' => null,
                'name' => 'Plataforma',
                'is_current' => false,
                'active' => true,
                'store_slug' => '',
                'store_url' => null,
                'store_theme' => 'default',
                'store_title' => 'Plataforma',
                'store_subtitle' => '',
                'store_banner_label' => '',
                'store_banner_image_url' => '',
                'store_featured_image_url' => '',
                'store_featured_label' => '',
                'store_featured_title' => '',
                'store_featured_subtitle' => '',
                'store_featured_cta' => '',
                'store_secure_image_url' => '',
                'store_secure_label' => '',
                'store_secure_title' => '',
                'store_secure_subtitle' => '',
                'store_secure_cta' => '',
                'store_shipping_regions' => TenantSetting::defaultShippingRegions(),
                'document' => '',
                'support_phone' => '',
                'support_email' => '',
                'admin_primary_color' => '#111c22',
                'admin_accent_color' => '#0f766e',
                'checkout_primary_color' => '#3b82f6',
                'checkout_button_color' => '#43c97b',
                'active_payment_provider' => 'mercado_pago',
                'payment_providers' => TenantSetting::defaultProviders(),
                'payment_credentials' => [],
            ]);
        }

        return response()->json($this->present($selectedTenant, selectedTenantId: $selectedTenant->id));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $this->validatedTenant($request);
        $tenant = TenantSetting::create($this->tenantPayload($data));

        return response()->json($this->present($tenant), 201);
    }

    public function update(Request $request): JsonResponse
    {
        abort_if($request->user()->role === 'seller', 403);

        $tenant = TenantSetting::current();
        $data = $this->validatedTenant($request, $tenant);

        $tenant->update($this->tenantPayload($data, $tenant));

        return response()->json($this->present($tenant->refresh(), selectedTenantId: $tenant->id));
    }

    public function updateCompany(Request $request, TenantSetting $tenant): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $this->validatedTenant($request, $tenant);
        $tenant->update($this->tenantPayload($data, $tenant));

        return response()->json($this->present($tenant->refresh(), selectedTenantId: $tenant->id));
    }

    public function destroy(Request $request, TenantSetting $tenant): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        if ((int) session('superadmin_tenant_id') === $tenant->id) {
            session()->forget('superadmin_tenant_id');
        }

        $tenant->delete();

        return response()->json(status: 204);
    }

    public function activate(Request $request, TenantSetting $tenant): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);
        abort_unless($tenant->active, 422, 'Empresa inativa. Ative a empresa antes de visualizar.');

        session(['superadmin_tenant_id' => $tenant->id]);

        return response()->json($this->present($tenant->refresh(), selectedTenantId: $tenant->id));
    }

    public function deactivate(Request $request): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        session()->forget('superadmin_tenant_id');

        return response()->json([
            'ok' => true,
        ]);
    }

    public function status(Request $request, TenantSetting $tenant): JsonResponse
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $data = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $tenant->update(['active' => $data['active']]);

        if (! $tenant->active && (int) session('superadmin_tenant_id') === $tenant->id) {
            session()->forget('superadmin_tenant_id');
        }

        return response()->json($this->present($tenant->refresh(), selectedTenantId: TenantSetting::selectedForSuperAdmin()?->id));
    }

    public function public(): JsonResponse
    {
        return response()->json($this->present(TenantSetting::current(), public: true));
    }

    private function validatedTenant(Request $request, ?TenantSetting $tenant = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'active' => ['nullable', 'boolean'],
            'store_slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('tenant_settings', 'store_slug')->ignore($tenant),
            ],
            'store_theme' => ['required', 'string', 'max:100'],
            'store_title' => ['required', 'string', 'max:160'],
            'store_subtitle' => ['nullable', 'string', 'max:500'],
            'store_banner_label' => ['nullable', 'string', 'max:80'],
            'store_banner_image_url' => ['nullable', 'url', 'max:500'],
            'store_featured_image_url' => ['nullable', 'url', 'max:500'],
            'store_featured_label' => ['nullable', 'string', 'max:80'],
            'store_featured_title' => ['nullable', 'string', 'max:120'],
            'store_featured_subtitle' => ['nullable', 'string', 'max:180'],
            'store_featured_cta' => ['nullable', 'string', 'max:40'],
            'store_secure_image_url' => ['nullable', 'url', 'max:500'],
            'store_secure_label' => ['nullable', 'string', 'max:80'],
            'store_secure_title' => ['nullable', 'string', 'max:120'],
            'store_secure_subtitle' => ['nullable', 'string', 'max:180'],
            'store_secure_cta' => ['nullable', 'string', 'max:40'],
            'store_shipping_regions' => ['array'],
            'store_shipping_regions.*.region' => ['nullable', 'string', 'max:80'],
            'store_shipping_regions.*.cep_prefix' => ['nullable', 'string', 'max:12'],
            'store_shipping_regions.*.price' => ['nullable', 'numeric', 'min:0'],
            'store_shipping_regions.*.price_cents' => ['nullable', 'integer', 'min:0'],
            'store_shipping_regions.*.eta' => ['nullable', 'string', 'max:80'],
            'document' => ['nullable', 'string', 'max:40'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'support_email' => ['nullable', 'email', 'max:160'],
            'admin_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'admin_accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'checkout_primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'checkout_button_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'active_payment_provider' => ['required', Rule::in(array_keys(TenantSetting::PAYMENT_PROVIDERS))],
            'payment_providers' => ['array'],
            'payment_providers.*.enabled' => ['required', 'boolean'],
            'payment_credentials' => ['array'],
            'payment_credentials.*' => ['array'],
        ]);
    }

    private function tenantPayload(array $data, ?TenantSetting $tenant = null): array
    {
        $providers = TenantSetting::defaultProviders();

        $existingCredentials = $tenant?->payment_credentials ?: [];
        $credentials = collect($data['payment_credentials'] ?? [])
            ->mapWithKeys(fn ($values, $provider) => [$provider => collect($values)
                ->mapWithKeys(function ($value, $field) use ($existingCredentials, $provider) {
                    $value = is_string($value) ? trim($value) : $value;

                    return [$field => $value === '********' ? data_get($existingCredentials, "$provider.$field") : $value];
                })
                ->all()])
            ->all();

        foreach ($providers as $key => $provider) {
            $providers[$key]['enabled'] = (bool) data_get($data, "payment_providers.$key.enabled", $provider['enabled']);
            $providers[$key]['configured'] = $key === 'mercado_pago'
                ? PaymentSetting::mercadoPago()->configured()
                : collect(TenantSetting::requiredCredentialFields($key))
                    ->every(fn (string $field) => filled(data_get($credentials, "$key.$field")));
        }

        if (! $providers[$data['active_payment_provider']]['enabled']) {
            throw new HttpResponseException(response()->json([
                'message' => 'Ative o provedor antes de torna-lo o metodo principal.',
            ], 422));
        }

        return [
            ...$data,
            'active' => (bool) ($data['active'] ?? true),
            'store_slug' => Str::slug($data['store_slug']),
            'store_shipping_regions' => collect($data['store_shipping_regions'] ?? TenantSetting::defaultShippingRegions())
                ->filter(fn (array $region) => filled($region['region'] ?? null))
                ->map(fn (array $region) => [
                    'region' => $region['region'],
                    'cep_prefix' => preg_replace('/\D+/', '', $region['cep_prefix'] ?? ''),
                    'price_cents' => isset($region['price_cents'])
                        ? (int) $region['price_cents']
                        : (int) round((float) ($region['price'] ?? 0) * 100),
                    'eta' => $region['eta'] ?? null,
                ])
                ->values()
                ->all(),
            'payment_providers' => $providers,
            'payment_credentials' => $credentials,
        ];
    }

    private function present(TenantSetting $tenant, bool $public = false, ?int $selectedTenantId = null): array
    {
        $providers = array_replace_recursive(TenantSetting::defaultProviders(), $tenant->payment_providers ?: []);
        $providers['mercado_pago']['configured'] = PaymentSetting::mercadoPago()->configured();

        $payload = [
            'name' => $tenant->name,
            'id' => $tenant->id,
            'is_current' => $selectedTenantId !== null && $tenant->id === $selectedTenantId,
            'active' => (bool) $tenant->active,
            'store_slug' => $tenant->store_slug,
            'store_url' => $tenant->store_slug ? url('/loja/'.$tenant->store_slug) : null,
            'store_theme' => $tenant->store_theme,
            'store_themes' => StoreTheme::where('tenant_setting_id', $tenant->id)->latest()->get(),
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
            'document' => $tenant->document,
            'support_phone' => $tenant->support_phone,
            'support_email' => $tenant->support_email,
            'admin_primary_color' => $tenant->admin_primary_color,
            'admin_accent_color' => $tenant->admin_accent_color,
            'checkout_primary_color' => $tenant->checkout_primary_color,
            'checkout_button_color' => $tenant->checkout_button_color,
            'active_payment_provider' => $tenant->active_payment_provider,
            'payment_providers' => $providers,
            'payment_credentials' => $this->presentCredentialState($tenant),
        ];

        return $public ? [
            'name' => $payload['name'],
            'store_title' => $payload['store_title'],
            'store_subtitle' => $payload['store_subtitle'],
            'store_banner_label' => $payload['store_banner_label'],
            'store_banner_image_url' => $payload['store_banner_image_url'],
            'store_featured_image_url' => $payload['store_featured_image_url'],
            'store_featured_label' => $payload['store_featured_label'],
            'store_featured_title' => $payload['store_featured_title'],
            'store_featured_subtitle' => $payload['store_featured_subtitle'],
            'store_featured_cta' => $payload['store_featured_cta'],
            'store_secure_image_url' => $payload['store_secure_image_url'],
            'store_secure_label' => $payload['store_secure_label'],
            'store_secure_title' => $payload['store_secure_title'],
            'store_secure_subtitle' => $payload['store_secure_subtitle'],
            'store_secure_cta' => $payload['store_secure_cta'],
            'store_shipping_regions' => $payload['store_shipping_regions'],
            'store_theme' => $payload['store_theme'],
            'support_phone' => $payload['support_phone'],
            'support_email' => $payload['support_email'],
            'checkout_primary_color' => $payload['checkout_primary_color'],
            'checkout_button_color' => $payload['checkout_button_color'],
            'active_payment_provider' => $payload['active_payment_provider'],
            'payment_provider_label' => TenantSetting::PAYMENT_PROVIDERS[$payload['active_payment_provider']],
        ] : $payload;
    }

    private function presentCredentialState(TenantSetting $tenant): array
    {
        $credentials = $tenant->payment_credentials ?: [];

        return collect(TenantSetting::PAYMENT_PROVIDERS)
            ->mapWithKeys(fn (string $label, string $provider) => [$provider => collect(TenantSetting::credentialFields($provider))
                ->mapWithKeys(fn (string $field) => [$field => filled(data_get($credentials, "$provider.$field")) ? '********' : ''])
                ->all()])
            ->all();
    }
}
