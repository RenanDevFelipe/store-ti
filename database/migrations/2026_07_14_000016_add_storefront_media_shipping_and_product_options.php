<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->string('store_banner_image_url')->nullable()->after('store_banner_label');
            $table->string('store_featured_image_url')->nullable()->after('store_banner_image_url');
            $table->json('store_shipping_regions')->nullable()->after('store_featured_image_url');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->string('image_url')->nullable()->after('description');
            $table->json('gallery_urls')->nullable()->after('image_url');
            $table->json('options')->nullable()->after('gallery_urls');
            $table->boolean('requires_shipping')->default(false)->after('options');
            $table->unsignedInteger('shipping_weight_grams')->nullable()->after('requires_shipping');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['image_url', 'gallery_urls', 'options', 'requires_shipping', 'shipping_weight_grams']);
        });

        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->dropColumn(['store_banner_image_url', 'store_featured_image_url', 'store_shipping_regions']);
        });
    }
};
