<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('type')->default('physical')->after('sku');
            $table->boolean('track_stock')->default(true)->after('currency');
            $table->string('billing_cycle')->nullable()->after('track_stock');
            $table->unsignedInteger('stock')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('stock')->nullable(false)->default(0)->change();
            $table->dropColumn(['type', 'track_stock', 'billing_cycle']);
        });
    }
};
