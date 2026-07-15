<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    protected $fillable = [
        'tenant_setting_id',
        'event',
        'sales_link_id',
        'payment_id',
        'recipient_name',
        'recipient_phone',
        'recipient_type',
        'status',
        'message',
        'response_payload',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'response_payload' => 'array',
        'sent_at' => 'datetime',
    ];

    public function salesLink(): BelongsTo
    {
        return $this->belongsTo(SalesLink::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantSetting::class, 'tenant_setting_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
