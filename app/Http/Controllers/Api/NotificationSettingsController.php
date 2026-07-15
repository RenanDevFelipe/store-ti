<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationContact;
use App\Models\NotificationLog;
use App\Models\NotificationSetting;
use App\Models\TenantNotificationSetting;
use App\Models\TenantSetting;
use App\Services\EvolutionNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public function show(): JsonResponse
    {
        $provider = NotificationSetting::evolution();
        $tenant = TenantSetting::current();
        $settings = TenantNotificationSetting::forTenant($tenant->id);
        $canManageProvider = request()->user()->isSuperAdmin() && ! session()->has('superadmin_tenant_id');

        return response()->json([
            'settings' => [
                'enabled' => $settings->enabled,
                'base_url' => $canManageProvider ? $provider->base_url : null,
                'instance' => $canManageProvider ? $provider->instance : null,
                'provider_enabled' => $provider->enabled,
                'provider_configured' => $provider->configured(),
                'can_manage_provider' => $canManageProvider,
                'dynamic_customer_enabled' => $settings->dynamic_customer_enabled,
                'notify_sale_created' => $settings->notify_sale_created,
                'notify_payment_approved' => $settings->notify_payment_approved,
                'sale_created_message' => $settings->sale_created_message ?: NotificationSetting::DEFAULT_SALE_CREATED_MESSAGE,
                'payment_approved_message' => $settings->payment_approved_message ?: NotificationSetting::DEFAULT_PAYMENT_APPROVED_MESSAGE,
                'configured' => $settings->enabled && $provider->configured(),
            ],
            'contacts' => NotificationContact::where('tenant_setting_id', $tenant->id)->latest()->get(),
            'logs' => NotificationLog::where('tenant_setting_id', $tenant->id)->latest()->limit(20)->get(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'provider_enabled' => ['nullable', 'boolean'],
            'base_url' => ['nullable', 'url', 'max:500'],
            'instance' => ['nullable', 'string', 'max:120'],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'dynamic_customer_enabled' => ['required', 'boolean'],
            'notify_sale_created' => ['required', 'boolean'],
            'notify_payment_approved' => ['required', 'boolean'],
            'sale_created_message' => ['required', 'string', 'max:1000'],
            'payment_approved_message' => ['required', 'string', 'max:1000'],
            'contacts' => ['array'],
            'contacts.*.id' => ['nullable', 'integer', 'exists:notification_contacts,id'],
            'contacts.*.name' => ['required', 'string', 'max:120'],
            'contacts.*.phone' => ['required', 'string', 'max:30'],
            'contacts.*.active' => ['required', 'boolean'],
        ]);

        $tenant = TenantSetting::current();
        $provider = NotificationSetting::evolution();
        $settings = TenantNotificationSetting::forTenant($tenant->id);

        if ($request->user()->isSuperAdmin() && ! session()->has('superadmin_tenant_id')) {
            $provider->fill([
                'enabled' => (bool) ($data['provider_enabled'] ?? $provider->enabled),
                'base_url' => $data['base_url'] ?? null,
                'instance' => $data['instance'] ?? null,
            ]);

            if (filled($data['api_key'] ?? null)) {
                $provider->api_key = $data['api_key'];
            }

            $provider->save();
        }

        $settings->fill([
            'enabled' => $data['enabled'],
            'dynamic_customer_enabled' => $data['dynamic_customer_enabled'],
            'notify_sale_created' => $data['notify_sale_created'],
            'notify_payment_approved' => $data['notify_payment_approved'],
            'sale_created_message' => $data['sale_created_message'],
            'payment_approved_message' => $data['payment_approved_message'],
        ]);

        $settings->save();

        $keptIds = [];

        foreach ($data['contacts'] ?? [] as $contactData) {
            $contact = filled($contactData['id'] ?? null)
                ? NotificationContact::where('tenant_setting_id', $tenant->id)->find($contactData['id'])
                : new NotificationContact(['tenant_setting_id' => $tenant->id]);

            if (! $contact) {
                $contact = new NotificationContact(['tenant_setting_id' => $tenant->id]);
            }

            $contact->fill([
                'tenant_setting_id' => $tenant->id,
                'name' => $contactData['name'],
                'phone' => $contactData['phone'],
                'active' => $contactData['active'],
            ])->save();

            $keptIds[] = $contact->id;
        }

        NotificationContact::where('tenant_setting_id', $tenant->id)
            ->when($keptIds !== [], fn ($query) => $query->whereNotIn('id', $keptIds))
            ->when($keptIds === [], fn ($query) => $query)
            ->delete();

        return $this->show();
    }

    public function test(Request $request, EvolutionNotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $log = $notifications->sendTest($data['phone'], $data['name'] ?? null);

        return response()->json($log);
    }
}
