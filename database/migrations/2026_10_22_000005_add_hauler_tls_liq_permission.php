<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'access-all-hauler-tls-liq'],
            [
                'name' => 'All Hauler TLS LIQ',
                'description' => 'Access every Hauler TLS LIQ operation',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('permissions')->where('slug', 'view-hauling-liquidity')->update([
            'name' => 'View Hauler TLS LIQ',
            'description' => 'Can view haulers and hauling liquidation entries in Hauler TLS LIQ',
            'updated_at' => now(),
        ]);
        DB::table('permissions')->where('slug', 'manage-hauling-liquidity')->update([
            'name' => 'Manage Hauler TLS LIQ',
            'description' => 'Can add haulers and create, edit, and delete Hauler TLS LIQ entries',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'access-all-hauler-tls-liq')->delete();
        DB::table('permissions')->where('slug', 'view-hauling-liquidity')->update([
            'name' => 'View Hauling Liquidity',
            'description' => 'Can view haulers and hauling liquidation entries',
            'updated_at' => now(),
        ]);
        DB::table('permissions')->where('slug', 'manage-hauling-liquidity')->update([
            'name' => 'Manage Hauling Liquidity',
            'description' => 'Can add haulers and create, edit, and delete hauling liquidation entries',
            'updated_at' => now(),
        ]);
    }
};
