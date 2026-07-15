<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_setting_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->boolean('dynamic_customer_enabled')->default(false);
            $table->boolean('notify_sale_created')->default(true);
            $table->boolean('notify_payment_approved')->default(true);
            $table->text('sale_created_message')->nullable();
            $table->text('payment_approved_message')->nullable();
            $table->timestamps();
        });

        Schema::table('notification_contacts', function (Blueprint $table): void {
            $table->foreignId('tenant_setting_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->index('tenant_setting_id');
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->foreignId('tenant_setting_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->index('tenant_setting_id');
        });

        $currentTenantId = DB::table('tenant_settings')->where('is_current', true)->value('id')
            ?: DB::table('tenant_settings')->orderBy('id')->value('id');

        if ($currentTenantId) {
            DB::table('notification_contacts')
                ->whereNull('tenant_setting_id')
                ->update(['tenant_setting_id' => $currentTenantId]);

            DB::table('notification_logs')
                ->whereNull('tenant_setting_id')
                ->update(['tenant_setting_id' => $currentTenantId]);

            $global = DB::table('notification_settings')->where('provider', 'evolution')->first();

            DB::table('tenant_notification_settings')->updateOrInsert(
                ['tenant_setting_id' => $currentTenantId],
                [
                    'enabled' => (bool) ($global->enabled ?? false),
                    'dynamic_customer_enabled' => (bool) ($global->dynamic_customer_enabled ?? false),
                    'notify_sale_created' => (bool) ($global->notify_sale_created ?? true),
                    'notify_payment_approved' => (bool) ($global->notify_payment_approved ?? true),
                    'sale_created_message' => $global->sale_created_message ?? null,
                    'payment_approved_message' => $global->payment_approved_message ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_setting_id');
        });

        Schema::table('notification_contacts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('tenant_setting_id');
        });

        Schema::dropIfExists('tenant_notification_settings');
    }
};
