<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('hauling_liquidations', function (Blueprint $table) {
            // Payee is picked from Planter Profiles; payee_name keeps the name as printed on the slip
            $table->string('planter_code')->nullable()->after('date_released')->index();
        });
    }

    public function down()
    {
        Schema::table('hauling_liquidations', function (Blueprint $table) {
            $table->dropIndex(['planter_code']);
            $table->dropColumn('planter_code');
        });
    }
};
