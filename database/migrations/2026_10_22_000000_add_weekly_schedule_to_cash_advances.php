<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Term picked on the New Cash Advance form (weeks of the crop year)
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->unsignedInteger('term_weeks')->nullable()->after('term_months');
        });

        // Due date of each weekly payment (defaults to the week's end date)
        Schema::table('cash_advance_deductions', function (Blueprint $table) {
            $table->date('due_date')->nullable()->after('week_no');
        });
    }

    public function down()
    {
        Schema::table('cash_advance_deductions', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropColumn('term_weeks');
        });
    }
};
