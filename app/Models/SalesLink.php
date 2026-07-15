<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class SalesLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'public_id',
        'product_id',
        'user_id',
        'customer_id',
        'customer_address_id',
        'title',
        'customer_email',
        'quantity',
        'discount_type',
        'discount_value_cents',
        'discount_percent',
        'original_amount_cents',
        'discount_amount_cents',
        'final_amount_cents',
        'status',
        'mp_preference_id',
        'checkout_url',
        'expires_at',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'discount_value_cents' => 'integer',
        'discount_percent' => 'decimal:2',
        'original_amount_cents' => 'integer',
        'discount_amount_cents' => 'integer',
        'final_amount_cents' => 'integer',
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (SalesLink $salesLink): void {
            $salesLink->public_id ??= (string) Str::uuid();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function publicUrl(): string
    {
        return URL::route('public.sales-link.page', $this);
    }
}
