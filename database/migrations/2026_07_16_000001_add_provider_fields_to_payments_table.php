<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->string('provider')->default('mercado_pago')->after('sales_link_id');
            $table->string('provider_payment_id')->nullable()->after('provider');
            $table->unique(['provider', 'provider_payment_id']);
        });

        DB::table('payments')->whereNotNull('mp_payment_id')->update([
            'provider' => 'mercado_pago',
            'provider_payment_id' => DB::raw('mp_payment_id'),
        ]);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['provider', 'provider_payment_id']);
            $table->dropColumn(['provider', 'provider_payment_id']);
        });
    }
};
