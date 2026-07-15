<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $firstUser = DB::table('users')->orderBy('id')->first();

        if ($firstUser) {
            DB::table('users')
                ->where('id', $firstUser->id)
                ->update([
                    'role' => 'superadmin',
                    'active' => true,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        //
    }
};
