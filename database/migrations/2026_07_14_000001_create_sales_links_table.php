<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('customer_email')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('discount_type')->default('none');
            $table->unsignedInteger('discount_value_cents')->default(0);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->unsignedInteger('original_amount_cents');
            $table->unsignedInteger('discount_amount_cents')->default(0);
            $table->unsignedInteger('final_amount_cents');
            $table->string('status')->default('draft');
            $table->string('mp_preference_id')->nullable()->index();
            $table->text('checkout_url')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_links');
    }
};
