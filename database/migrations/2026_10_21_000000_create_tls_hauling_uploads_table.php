<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Raw rows of the weekly TLS (transloading) hauling upload. Each row is linked to the
        // Hauling Liquidity entry generated from it (one entry per hauler + planter + plate no.).
        Schema::create('tls_hauling_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('reference_no')->nullable();
            $table->string('planter_code');
            $table->string('planter_name')->nullable();
            $table->string('assn_code')->nullable();
            $table->string('assn_name')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('plate_no')->nullable();
            $table->decimal('tare_weight', 12, 3)->default(0);
            $table->decimal('gross_cane_weight', 12, 3)->default(0);
            $table->decimal('net_cane_wt', 12, 3)->default(0);
            $table->string('date_milled')->nullable();
            $table->string('trans_code')->nullable();
            $table->string('description')->nullable();
            $table->decimal('tdif_rate', 12, 2)->default(0);
            $table->foreignId('hauling_liquidation_id')->nullable()->constrained('hauling_liquidations')->nullOnDelete();
            $table->string('user_id')->nullable();
            $table->timestamps();

            $table->index(['crop_year', 'week_no']);
            $table->index('planter_code');
        });
    }

    public function down()
    {
        Schema::dropIfExists('tls_hauling_uploads');
    }
};
