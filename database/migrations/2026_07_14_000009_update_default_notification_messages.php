<?php

use App\Models\NotificationSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notification_settings')
            ->where('provider', 'evolution')
            ->update([
                'sale_created_message' => NotificationSetting::DEFAULT_SALE_CREATED_MESSAGE,
                'payment_approved_message' => NotificationSetting::DEFAULT_PAYMENT_APPROVED_MESSAGE,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('notification_settings')
            ->where('provider', 'evolution')
            ->update([
                'sale_created_message' => "Nova venda iniciada\nCliente: {cliente}\nProduto: {produto}\nValor: {valor}\nStatus: {status}",
                'payment_approved_message' => "Pagamento aprovado\nCliente: {cliente}\nProduto: {produto}\nValor: {valor}\nPedido: {pedido}",
                'updated_at' => now(),
            ]);
    }
};
