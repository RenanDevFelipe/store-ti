<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesLink;
use App\Models\NotificationSetting;
use App\Models\TenantNotificationSetting;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->user()->isSuperAdmin()) {
            $companies = TenantSetting::latest()->get();
            $users = User::query();
            $selectedTenant = TenantSetting::selectedForSuperAdmin();
            $selectedTenantId = $selectedTenant?->id;

            $providerConfigured = NotificationSetting::evolution()->configured();

            return response()->json([
                'mode' => 'superadmin',
                'companies_total' => $companies->count(),
                'active_company' => $selectedTenant ? $this->presentCompany($selectedTenant, $selectedTenantId) : null,
                'companies' => $companies->map(fn (TenantSetting $tenant) => $this->presentCompany($tenant, $selectedTenantId))->values(),
                'users_total' => User::count(),
                'active_users' => User::where('active', true)->count(),
                'inactive_users' => User::where('active', false)->count(),
                'admins' => (clone $users)->where('role', 'admin')->count(),
                'sellers' => User::where('role', 'seller')->count(),
                'superadmins' => User::where('role', 'superadmin')->count(),
                'platform_revenue_cents' => Payment::where('status', 'approved')->sum('amount_cents'),
                'platform_sales' => SalesLink::count(),
                'notifications_configured' => $selectedTenant
                    ? $providerConfigured && TenantNotificationSetting::forTenant($selectedTenant->id)->enabled
                    : $providerConfigured,
            ]);
        }

        $tenantId = $request->user()->tenant_setting_id;
        abort_unless($tenantId, 403, 'Usuario sem empresa vinculada.');

        $productQuery = Product::where('tenant_setting_id', $tenantId);
        $salesQuery = SalesLink::whereHas('product', fn ($query) => $query->where('tenant_setting_id', $tenantId));
        $paymentQuery = Payment::whereHas('salesLink.product', fn ($query) => $query->where('tenant_setting_id', $tenantId));

        $revenue = (clone $paymentQuery)->where('status', 'approved')->sum('amount_cents');
        $paidSales = (clone $salesQuery)->where('status', 'paid')->count();

        return response()->json([
            'mode' => 'company',
            'products' => (clone $productQuery)->count(),
            'active_products' => (clone $productQuery)->where('active', true)->count(),
            'internet_plans' => (clone $productQuery)->where('type', 'internet_plan')->count(),
            'links' => (clone $salesQuery)->count(),
            'ready_links' => (clone $salesQuery)->where('status', 'ready')->count(),
            'pending_links' => (clone $salesQuery)->where('status', 'pending')->count(),
            'paid_links' => (clone $salesQuery)->where('status', 'paid')->count(),
            'cancelled_links' => (clone $salesQuery)->where('status', 'cancelled')->count(),
            'revenue_cents' => $revenue,
            'average_ticket_cents' => $paidSales > 0 ? (int) round($revenue / $paidSales) : 0,
            'notifications_configured' => NotificationSetting::evolution()->configured()
                && TenantNotificationSetting::forTenant($tenantId)->enabled,
        ]);
    }

    private function presentCompany(TenantSetting $tenant, ?int $selectedTenantId = null): array
    {
        $providers = $tenant->payment_providers ?: TenantSetting::defaultProviders();

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'document' => $tenant->document,
            'support_email' => $tenant->support_email,
            'support_phone' => $tenant->support_phone,
            'is_current' => $tenant->id === $selectedTenantId,
            'active_payment_provider' => $tenant->active_payment_provider,
            'active_payment_provider_label' => TenantSetting::PAYMENT_PROVIDERS[$tenant->active_payment_provider] ?? $tenant->active_payment_provider,
            'enabled_providers' => collect($providers)->filter(fn (array $provider) => $provider['enabled'] ?? false)->count(),
            'admins_count' => User::where('tenant_setting_id', $tenant->id)->where('role', 'admin')->count(),
            'sellers_count' => User::where('tenant_setting_id', $tenant->id)->where('role', 'seller')->count(),
            'active_users_count' => User::where('tenant_setting_id', $tenant->id)->where('active', true)->count(),
            'admin_primary_color' => $tenant->admin_primary_color,
            'checkout_primary_color' => $tenant->checkout_primary_color,
            'created_at' => $tenant->created_at,
        ];
    }
}
