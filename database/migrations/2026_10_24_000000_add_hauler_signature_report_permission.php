<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['slug' => 'view-hauler-signature-report'],
            [
                'name' => 'Hauler TLS Signature',
                'description' => 'Access to Hauler TLS Signature in Reports',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('slug', 'view-hauler-signature-report')->delete();
    }
};
