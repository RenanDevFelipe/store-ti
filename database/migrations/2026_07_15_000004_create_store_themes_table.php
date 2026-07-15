<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('store_themes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_setting_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('primary_color')->default('#3b82f6');
            $table->string('accent_color')->default('#43c97b');
            $table->string('background_color')->default('#eef2f4');
            $table->string('banner_label')->nullable();
            $table->string('banner_image_url')->nullable();
            $table->string('featured_image_url')->nullable();
            $table->string('featured_title')->nullable();
            $table->string('featured_subtitle')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_setting_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_themes');
    }
};
