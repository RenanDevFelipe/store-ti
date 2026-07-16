<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'sales_link_id',
        'provider',
        'provider_payment_id',
        'mp_payment_id',
        'status',
        'status_detail',
        'payment_method_id',
        'payment_type_id',
        'amount_cents',
        'raw_payload',
        'paid_at',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
        'raw_payload' => 'array',
        'paid_at' => 'datetime',
    ];

    public function salesLink(): BelongsTo
    {
        return $this->belongsTo(SalesLink::class);
    }
}
