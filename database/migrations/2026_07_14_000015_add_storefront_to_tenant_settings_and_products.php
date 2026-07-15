<?php

use App\Models\TenantSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->string('store_slug')->nullable()->unique()->after('name');
            $table->string('store_theme')->default('default')->after('payment_providers');
            $table->string('store_title')->nullable()->after('store_theme');
            $table->text('store_subtitle')->nullable()->after('store_title');
            $table->string('store_banner_label')->nullable()->after('store_subtitle');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('tenant_setting_id')->nullable()->after('id')->constrained('tenant_settings')->nullOnDelete();
        });

        $currentTenant = TenantSetting::current();

        DB::table('tenant_settings')->orderBy('id')->get()->each(function ($tenant): void {
            DB::table('tenant_settings')
                ->where('id', $tenant->id)
                ->update([
                    'store_slug' => Str::slug($tenant->name).'-'.$tenant->id,
                    'store_title' => $tenant->name,
                    'store_subtitle' => 'Conheca nossas ofertas e contrate online com seguranca.',
                    'store_banner_label' => 'Ofertas em destaque',
                ]);
        });

        DB::table('products')
            ->whereNull('tenant_setting_id')
            ->update(['tenant_setting_id' => $currentTenant->id]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_setting_id');
        });

        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->dropColumn(['store_slug', 'store_theme', 'store_title', 'store_subtitle', 'store_banner_label']);
        });
    }
};
