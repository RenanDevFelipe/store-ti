<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreTheme;
use App\Models\TenantSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreThemeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        return response()->json(
            StoreTheme::where('tenant_setting_id', $tenantId)
                ->latest()
                ->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $data = $this->validated($request, $tenantId);

        $theme = StoreTheme::create([
            ...$data,
            'tenant_setting_id' => $tenantId,
            'slug' => Str::slug($data['slug'] ?: $data['name']),
            'active' => (bool) ($data['active'] ?? true),
        ]);

        return response()->json($theme, 201);
    }

    public function update(Request $request, StoreTheme $storeTheme): JsonResponse
    {
        $this->authorizeTenant($request, $storeTheme);
        $data = $this->validated($request, $storeTheme->tenant_setting_id, $storeTheme);

        $storeTheme->update([
            ...$data,
            'slug' => Str::slug($data['slug'] ?: $data['name']),
            'active' => (bool) ($data['active'] ?? true),
        ]);

        return response()->json($storeTheme->refresh());
    }

    public function destroy(Request $request, StoreTheme $storeTheme): JsonResponse
    {
        $this->authorizeTenant($request, $storeTheme);

        $tenant = TenantSetting::find($storeTheme->tenant_setting_id);
        if ($tenant?->store_theme === $storeTheme->slug) {
            $tenant->update(['store_theme' => 'default']);
        }

        $storeTheme->delete();

        return response()->json(status: 204);
    }

    private function validated(Request $request, int $tenantId, ?StoreTheme $theme = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[a-z0-9-]+$/',
                Rule::unique('store_themes', 'slug')->where('tenant_setting_id', $tenantId)->ignore($theme),
            ],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'banner_label' => ['nullable', 'string', 'max:80'],
            'banner_image_url' => ['nullable', 'url', 'max:500'],
            'featured_image_url' => ['nullable', 'url', 'max:500'],
            'featured_title' => ['nullable', 'string', 'max:120'],
            'featured_subtitle' => ['nullable', 'string', 'max:180'],
            'active' => ['nullable', 'boolean'],
        ]);
    }

    private function tenantId(Request $request): int
    {
        $tenantId = $request->user()->isSuperAdmin()
            ? TenantSetting::current()->id
            : $request->user()->tenant_setting_id;

        abort_unless($tenantId, 403, 'Usuario sem empresa vinculada.');

        return $tenantId;
    }

    private function authorizeTenant(Request $request, StoreTheme $theme): void
    {
        abort_unless($theme->tenant_setting_id === $this->tenantId($request), 403);
    }
}
