<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->boolean('is_current')->default(false)->after('id');
        });

        $firstTenant = DB::table('tenant_settings')->orderBy('id')->first();

        if ($firstTenant) {
            DB::table('tenant_settings')
                ->where('id', $firstTenant->id)
                ->update(['is_current' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('tenant_settings', function (Blueprint $table): void {
            $table->dropColumn('is_current');
        });
    }
};
