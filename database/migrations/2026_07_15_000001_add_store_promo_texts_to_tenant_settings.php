<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->string('store_featured_label')->nullable()->after('store_featured_image_url');
            $table->string('store_featured_title')->nullable()->after('store_featured_label');
            $table->string('store_featured_subtitle')->nullable()->after('store_featured_title');
            $table->string('store_featured_cta')->nullable()->after('store_featured_subtitle');
            $table->string('store_secure_label')->nullable()->after('store_secure_image_url');
            $table->string('store_secure_title')->nullable()->after('store_secure_label');
            $table->string('store_secure_subtitle')->nullable()->after('store_secure_title');
            $table->string('store_secure_cta')->nullable()->after('store_secure_subtitle');
        });
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'store_featured_label',
                'store_featured_title',
                'store_featured_subtitle',
                'store_featured_cta',
                'store_secure_label',
                'store_secure_title',
                'store_secure_subtitle',
                'store_secure_cta',
            ]);
        });
    }
};
