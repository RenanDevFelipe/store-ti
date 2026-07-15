<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationContact extends Model
{
    protected $fillable = [
        'tenant_setting_id',
        'name',
        'phone',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantSetting::class, 'tenant_setting_id');
    }
}
