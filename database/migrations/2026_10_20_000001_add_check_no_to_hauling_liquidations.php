<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('hauling_liquidations', function (Blueprint $table) {
            $table->string('check_no')->nullable()->after('cv_no');
        });
    }

    public function down()
    {
        Schema::table('hauling_liquidations', function (Blueprint $table) {
            $table->dropColumn('check_no');
        });
    }
};
