<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique()->after('id');
            $table->string('discount_type')->default('none')->after('price_cents');
            $table->unsignedInteger('discount_value_cents')->default(0)->after('discount_type');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_value_cents');
        });

        DB::table('products')
            ->whereNull('public_id')
            ->orderBy('id')
            ->get(['id'])
            ->each(fn ($product) => DB::table('products')
                ->where('id', $product->id)
                ->update(['public_id' => (string) Str::uuid()]));
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['public_id', 'discount_type', 'discount_value_cents', 'discount_percent']);
        });
    }
};
