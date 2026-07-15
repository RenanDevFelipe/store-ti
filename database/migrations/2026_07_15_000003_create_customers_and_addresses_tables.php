<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_setting_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('cpf')->nullable();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_setting_id', 'email']);
        });

        Schema::create('customer_addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('label')->default('Principal');
            $table->string('recipient_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('cep', 12);
            $table->string('street');
            $table->string('number');
            $table->string('complement')->nullable();
            $table->string('neighborhood');
            $table->string('city');
            $table->string('state', 2);
            $table->boolean('default')->default(false);
            $table->timestamps();
        });

        Schema::table('sales_links', function (Blueprint $table): void {
            $table->foreignId('customer_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->foreignId('customer_address_id')->nullable()->after('customer_id')->constrained('customer_addresses')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sales_links', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_address_id');
            $table->dropConstrainedForeignId('customer_id');
        });

        Schema::dropIfExists('customer_addresses');
        Schema::dropIfExists('customers');
    }
};
