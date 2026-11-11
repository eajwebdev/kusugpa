<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Consolidated Report is now called Trucking Allowance, and the Trucking Allowance report
     * Trucking Summary (matching the upload names). Only the names change; slugs and routes stay.
     */
    private array $names = [
        'view-consolidated-report' => ['View Trucking Allowance Report', 'View Consolidated Report'],
        'view-trucking-allowance-report' => ['Trucking Summary Report', 'Trucking Allowance Report'],
    ];

    public function up(): void
    {
        $this->rename(0);
    }

    public function down(): void
    {
        $this->rename(1);
    }

    private function rename(int $which): void
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }
        foreach ($this->names as $slug => $names) {
            DB::table('permissions')->where('slug', $slug)->update(['name' => $names[$which]]);
        }
    }
};
