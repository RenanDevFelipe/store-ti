<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\CustomerManagementController;
use App\Http\Controllers\Api\NotificationSettingsController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportsController;
use App\Http\Controllers\Api\SalesLinkController;
use App\Http\Controllers\Api\StoreThemeController;
use App\Http\Controllers\Api\StorefrontMediaController;
use App\Http\Controllers\Api\TenantSettingsController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\MercadoPagoWebhookController;
use App\Http\Controllers\PublicProductCheckoutController;
use App\Http\Controllers\PublicCustomerAuthController;
use App\Http\Controllers\PublicSalesLinkController;
use App\Http\Controllers\PublicStorefrontController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/mercado-pago', MercadoPagoWebhookController::class)->name('mercadopago.webhook');
Route::get('/auth/session', fn () => response()->json([
    'authenticated' => auth('customer')->check(),
    'customer' => auth('customer')->check() ? [
        ...auth('customer')->user()->only(['id', 'tenant_setting_id', 'name', 'email', 'phone', 'cpf', 'active']),
        'addresses' => auth('customer')->user()->addresses()->latest()->get(),
    ] : null,
]))->name('auth.session');
Route::get('/customer/session', [PublicCustomerAuthController::class, 'session']);
Route::post('/customer/register', [PublicCustomerAuthController::class, 'register']);
Route::post('/customer/login', [PublicCustomerAuthController::class, 'login']);
Route::post('/customer/logout', [PublicCustomerAuthController::class, 'logout']);
Route::put('/customer/profile', [PublicCustomerAuthController::class, 'updateProfile']);
Route::get('/customer/orders', [PublicCustomerAuthController::class, 'orders']);
Route::post('/customer/addresses', [PublicCustomerAuthController::class, 'storeAddress']);
Route::put('/customer/addresses/{address}', [PublicCustomerAuthController::class, 'updateAddress']);
Route::delete('/customer/addresses/{address}', [PublicCustomerAuthController::class, 'destroyAddress']);
Route::get('/tenant-settings/public', [TenantSettingsController::class, 'public'])->name('tenant-settings.public');
Route::get('/checkout/resultado', fn () => view('app'))->name('checkout.result');
Route::get('/loja/{slug}/entrar', fn () => view('app'))->name('public.customer.login');
Route::get('/loja/{slug}/criar-conta', fn () => view('app'))->name('public.customer.register');
Route::get('/loja/{slug}/buscar', fn () => view('app'))->name('public.storefront.search');
Route::get('/loja/{slug}', [PublicStorefrontController::class, 'page'])->name('public.storefront.page');
Route::get('/loja/{slug}/data', [PublicStorefrontController::class, 'show'])->name('public.storefront.show');
Route::get('/p/{publicId}', fn () => view('app'))->name('public.product.page');
Route::get('/p/{publicId}/data', [PublicProductCheckoutController::class, 'show'])->name('public.product.show');
Route::post('/p/{publicId}/pix', [PublicProductCheckoutController::class, 'pix'])->name('public.product.pix');
Route::get('/p/{publicId}/status', [PublicProductCheckoutController::class, 'status'])->name('public.product.status');
Route::get('/v/{salesLink}', fn () => view('app'))->name('public.sales-link.page');
Route::get('/v/{salesLink}/data', [PublicSalesLinkController::class, 'show'])->name('public.sales-link.show');
Route::post('/v/{salesLink}/pix', [PublicSalesLinkController::class, 'pix'])->name('public.sales-link.pix');
Route::get('/v/{salesLink}/status', [PublicSalesLinkController::class, 'status'])->name('public.sales-link.status');
Route::get('/v/{salesLink}/checkout', [PublicSalesLinkController::class, 'checkout'])->name('public.sales-link.checkout');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', fn () => view('app'))->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy']);

    Route::prefix('api')->group(function (): void {
        Route::get('me', fn () => response()->json([
            ...request()->user()->only(['id', 'tenant_setting_id', 'name', 'email', 'role']),
            'tenant' => request()->user()->tenant ? [
                'id' => request()->user()->tenant->id,
                'name' => request()->user()->tenant->name,
            ] : null,
        ]));
        Route::get('payment-settings', [\App\Http\Controllers\Api\PaymentSettingsController::class, 'show']);
        Route::put('payment-settings', [\App\Http\Controllers\Api\PaymentSettingsController::class, 'update']);
        Route::get('tenant-settings', [TenantSettingsController::class, 'show']);
        Route::put('tenant-settings', [TenantSettingsController::class, 'update']);
        Route::get('companies', [TenantSettingsController::class, 'index']);
        Route::post('companies', [TenantSettingsController::class, 'store']);
        Route::put('companies/{tenant}', [TenantSettingsController::class, 'updateCompany']);
        Route::patch('companies/{tenant}/status', [TenantSettingsController::class, 'status']);
        Route::delete('companies/{tenant}', [TenantSettingsController::class, 'destroy']);
        Route::post('companies/{tenant}/activate', [TenantSettingsController::class, 'activate']);
        Route::post('companies/deactivate', [TenantSettingsController::class, 'deactivate']);
        Route::apiResource('users', UserManagementController::class)->except(['show']);
        Route::apiResource('customers', CustomerManagementController::class)->only(['index', 'update', 'destroy']);
        Route::apiResource('store-themes', StoreThemeController::class)->except(['show']);
        Route::get('notification-settings', [NotificationSettingsController::class, 'show']);
        Route::put('notification-settings', [NotificationSettingsController::class, 'update']);
        Route::post('notification-settings/test', [NotificationSettingsController::class, 'test']);
        Route::get('dashboard', DashboardController::class);
        Route::get('reports', ReportsController::class);
        Route::apiResource('products', ProductController::class)->except(['show']);
        Route::post('storefront-media', [StorefrontMediaController::class, 'store']);
        Route::get('sales-links', [SalesLinkController::class, 'index']);
        Route::post('sales-links', [SalesLinkController::class, 'store']);
        Route::patch('sales-links/{salesLink}', [SalesLinkController::class, 'update']);
        Route::delete('sales-links/{salesLink}', [SalesLinkController::class, 'destroy']);
        Route::post('sales-links/{salesLink}/refresh', [SalesLinkController::class, 'refresh']);
    });

    Route::get('/{any?}', fn () => view('app'))->where('any', '.*');
});
