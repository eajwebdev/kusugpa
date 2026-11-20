<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Late charges and balance adjustments of a cash advance, on top of the advance and its interest:
     * the 1% per week charged when a scheduled week is not paid, or any change the user makes to the balance.
     * Amount to repay = amount + interest_amount + adjustment_amount.
     */
    public function up(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_advances', 'adjustment_amount')) {
                $table->decimal('adjustment_amount', 12, 2)->default(0)->after('interest_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropColumn('adjustment_amount');
        });
    }
};
