<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationSetting extends Model
{
    public const DEFAULT_SALE_CREATED_MESSAGE = "🛒 Nova venda iniciada\n\n👤 Cliente: {cliente}\n📧 Email: {email}\n📱 Telefone: {telefone}\n🪪 CPF: {cpf}\n📦 Produto: {produto}\n💰 Valor: {valor}\n📌 Status: {status}\n🧾 Pedido: {pedido}";
    public const DEFAULT_PAYMENT_APPROVED_MESSAGE = "✅ Pagamento aprovado\n\n👤 Cliente: {cliente}\n📧 Email: {email}\n📱 Telefone: {telefone}\n🪪 CPF: {cpf}\n📦 Produto: {produto}\n💰 Valor: {valor}\n🧾 Pedido: {pedido}\n💳 Pagamento: {pagamento}";

    protected $fillable = [
        'provider',
        'enabled',
        'base_url',
        'instance',
        'api_key',
        'dynamic_customer_enabled',
        'notify_sale_created',
        'notify_payment_approved',
        'sale_created_message',
        'payment_approved_message',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'enabled' => 'boolean',
        'dynamic_customer_enabled' => 'boolean',
        'notify_sale_created' => 'boolean',
        'notify_payment_approved' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
    ];

    public static function evolution(): self
    {
        return self::firstOrCreate([
            'provider' => 'evolution',
        ], [
            'enabled' => false,
            'dynamic_customer_enabled' => false,
            'notify_sale_created' => true,
            'notify_payment_approved' => true,
            'sale_created_message' => self::DEFAULT_SALE_CREATED_MESSAGE,
            'payment_approved_message' => self::DEFAULT_PAYMENT_APPROVED_MESSAGE,
        ]);
    }

    public function configured(): bool
    {
        return $this->enabled
            && filled($this->base_url)
            && filled($this->instance)
            && filled($this->api_key);
    }
}
