<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The person authorized to receive a planter's Check Voucher for a crop year and week range.
 * When set, the voucher prints this name in place of the planter's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_receivers', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->unsignedInteger('week_from');
            $table->unsignedInteger('week_to');
            $table->string('planter_code');
            $table->string('planter_name')->nullable();
            $table->string('receiver_name');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['crop_year', 'week_from', 'week_to', 'planter_code'], 'voucher_receivers_voucher_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_receivers');
    }
};
