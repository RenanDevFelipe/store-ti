<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->default('Store TI');
            $table->string('document')->nullable();
            $table->string('support_phone')->nullable();
            $table->string('support_email')->nullable();
            $table->string('admin_primary_color')->default('#111c22');
            $table->string('admin_accent_color')->default('#0f766e');
            $table->string('checkout_primary_color')->default('#3b82f6');
            $table->string('checkout_button_color')->default('#43c97b');
            $table->string('active_payment_provider')->default('mercado_pago');
            $table->json('payment_providers')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
