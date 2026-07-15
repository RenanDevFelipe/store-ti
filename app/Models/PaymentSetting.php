<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = [
        'provider',
        'access_token',
        'public_key',
        'sandbox',
        'statement_descriptor',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'sandbox' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
    ];

    public static function mercadoPago(): self
    {
        return self::firstOrCreate([
            'provider' => 'mercado_pago',
        ], [
            'access_token' => config('mercadopago.access_token'),
            'public_key' => config('mercadopago.public_key'),
            'sandbox' => config('mercadopago.sandbox'),
            'statement_descriptor' => config('mercadopago.statement_descriptor'),
        ]);
    }

    public function configured(): bool
    {
        return filled($this->access_token);
    }
}
