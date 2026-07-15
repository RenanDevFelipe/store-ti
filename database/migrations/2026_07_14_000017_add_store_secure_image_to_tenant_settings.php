<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->string('store_secure_image_url')->nullable()->after('store_featured_image_url');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->dropColumn('store_secure_image_url');
        });
    }
};
