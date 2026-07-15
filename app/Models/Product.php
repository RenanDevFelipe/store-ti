<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'tenant_setting_id',
        'public_id',
        'sku',
        'type',
        'description',
        'image_url',
        'gallery_urls',
        'options',
        'requires_shipping',
        'shipping_weight_grams',
        'price_cents',
        'discount_type',
        'discount_value_cents',
        'discount_percent',
        'currency',
        'track_stock',
        'billing_cycle',
        'stock',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'track_stock' => 'boolean',
        'gallery_urls' => 'array',
        'options' => 'array',
        'requires_shipping' => 'boolean',
        'shipping_weight_grams' => 'integer',
        'price_cents' => 'integer',
        'discount_value_cents' => 'integer',
        'discount_percent' => 'decimal:2',
        'stock' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $product->public_id ??= (string) Str::uuid();
        });
    }

    public function salesLinks(): HasMany
    {
        return $this->hasMany(SalesLink::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(TenantSetting::class, 'tenant_setting_id');
    }

    public function discountAmountCents(): int
    {
        return match ($this->discount_type) {
            'fixed' => min($this->discount_value_cents, $this->price_cents),
            'percent' => (int) round($this->price_cents * min((float) $this->discount_percent, 100) / 100),
            default => 0,
        };
    }

    public function finalAmountCents(): int
    {
        return max($this->price_cents - $this->discountAmountCents(), 0);
    }

    public function publicUrl(): string
    {
        if (blank($this->public_id) && $this->exists) {
            $this->forceFill(['public_id' => (string) Str::uuid()])->save();
            $this->refresh();
        }

        return URL::route('public.product.page', $this->public_id);
    }
}
