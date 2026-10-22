<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Names printed on the CA receipt: committee, co_maker, prepared, approved, received.
        // Blank committee / prepared / approved fall back to the Cash Advance Settings defaults.
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->json('receipt_signatories')->nullable()->after('check_no');
        });
    }

    public function down()
    {
        Schema::table('cash_advances', function (Blueprint $table) {
            $table->dropColumn('receipt_signatories');
        });
    }
};
