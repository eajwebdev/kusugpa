<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flat interest charged on a cash advance (amount x rate%). The weekly schedule repays
     * amount + interest. Existing advances keep 0: their schedules were set without interest.
     */
    public function up(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->decimal('interest_amount', 12, 2)->default(0)->after('interest_rate');
        });
    }

    public function down(): void
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropColumn('interest_amount');
        });
    }
};
