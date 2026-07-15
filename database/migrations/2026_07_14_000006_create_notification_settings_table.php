<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('evolution')->unique();
            $table->boolean('enabled')->default(false);
            $table->text('base_url')->nullable();
            $table->string('instance')->nullable();
            $table->text('api_key')->nullable();
            $table->boolean('dynamic_customer_enabled')->default(false);
            $table->boolean('notify_sale_created')->default(true);
            $table->boolean('notify_payment_approved')->default(true);
            $table->text('sale_created_message')->nullable();
            $table->text('payment_approved_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
