<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Printed on the Cash / Check Voucher part of the CA receipt
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->string('cv_no')->nullable()->after('crop_year');
            $table->string('check_no')->nullable()->after('cv_no');
        });

        // Weeks the cash advance is deducted from the planter's proceeds (e.g. Week 3 = 10,000).
        // Hauling Liquidity reads these to fill its "Cash Adv." deduction.
        Schema::create('cash_advance_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_id')->constrained('cash_advances')->cascadeOnDelete();
            $table->string('crop_year');
            $table->string('week_no');
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['crop_year', 'week_no']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('cash_advance_deductions');
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropColumn(['cv_no', 'check_no']);
        });
    }
};
