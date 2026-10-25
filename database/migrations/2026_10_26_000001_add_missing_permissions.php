<?php

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds every permission the app uses that the database does not have yet (e.g. Upload TLS
 * Hauling, the Cash Advances and Hauler TLS LIQ permissions), so User Management can grant
 * them. Permissions already there, and the users they are assigned to, are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        $existing = DB::table('permissions')->pluck('slug')->flip();

        foreach (PermissionSeeder::permissions() as $permission) {
            if (isset($existing[$permission['slug']])) {
                continue;
            }
            DB::table('permissions')->insert([
                'name' => $permission['name'],
                'slug' => $permission['slug'],
                'description' => $permission['description'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $existing[$permission['slug']] = true;
        }
    }

    public function down(): void
    {
        // Permissions may already be assigned to users by then; they are kept
    }
};
