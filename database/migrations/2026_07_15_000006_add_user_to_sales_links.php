<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_links', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_links', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'status']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
