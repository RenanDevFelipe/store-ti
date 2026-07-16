<?php

use App\Models\TenantSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'tenant_setting_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('tenant_setting_id')->nullable()->after('id')->constrained('tenant_settings')->nullOnDelete();
            });
        }

        $currentTenantId = DB::table('tenant_settings')->where('is_current', true)->value('id');

        if (! $currentTenantId) {
            $currentTenantId = DB::table('tenant_settings')->insertGetId([
                'name' => 'Store TI',
                'is_current' => true,
                'payment_providers' => json_encode(TenantSetting::defaultProviders()),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('users')
            ->whereNull('tenant_setting_id')
            ->where('role', '!=', 'superadmin')
            ->update(['tenant_setting_id' => $currentTenantId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_setting_id');
        });
    }
};
