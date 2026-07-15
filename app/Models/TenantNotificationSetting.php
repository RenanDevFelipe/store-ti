<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantNotificationSetting extends Model
{
    protected $fillable = [
        'tenant_setting_id',
        'enabled',
        'dynamic_customer_enabled',
        'notify_sale_created',
        'notify_payment_approved',
        'sale_created_message',
        'payment_approved_message',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'dynamic_customer_enabled' => 'boolean',
        'notify_sale_created' => 'boolean',
        'notify_payment_approved' => 'boolean',
    ];

    public static function forTenant(int $tenantId): self
    {
        return self::firstOrCreate([
            'tenant_setting_id' => $tenantId,
        ], [
            'enabled' => false,
            'dynamic_customer_enabled' => false,
            'notify_sale_created' => true,
            'notify_payment_approved' => true,
            'sale_created_message' => NotificationSetting::DEFAULT_SALE_CREATED_MESSAGE,
            'payment_approved_message' => NotificationSetting::DEFAULT_PAYMENT_APPROVED_MESSAGE,
        ]);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantSetting::class, 'tenant_setting_id');
    }
}
