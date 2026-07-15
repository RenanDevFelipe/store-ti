<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreTheme extends Model
{
    protected $fillable = [
        'tenant_setting_id',
        'name',
        'slug',
        'primary_color',
        'accent_color',
        'background_color',
        'banner_label',
        'banner_image_url',
        'featured_image_url',
        'featured_title',
        'featured_subtitle',
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
