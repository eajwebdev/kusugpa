<?php

use App\Support\NavigationPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->where('slug', 'view-reports')->update([
            'name' => 'All Reports',
            'description' => 'Can access every item in the Reports menu',
            'updated_at' => now(),
        ]);

        foreach (NavigationPermissionsSeeder::permissions() as $permission) {
            DB::table('permissions')->updateOrInsert(['slug' => $permission['slug']], [
                'name' => $permission['name'],
                'description' => $permission['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('slug', array_column(NavigationPermissionsSeeder::permissions(), 'slug'))->delete();
        DB::table('permissions')->where('slug', 'view-reports')->update([
            'name' => 'View Reports',
            'description' => 'Can view summary and other reports',
            'updated_at' => now(),
        ]);
    }
};
