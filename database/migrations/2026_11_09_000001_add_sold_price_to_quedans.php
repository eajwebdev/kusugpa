<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The price a quedan was sold at, picked from Price Management when it is marked bought.
     * The Check Voucher and the proceeds reports use it instead of the week's price.
     */
    public function up(): void
    {
        Schema::table('quedans', function (Blueprint $table) {
            if (!Schema::hasColumn('quedans', 'sold_price')) {
                $table->decimal('sold_price', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('quedans', 'sold_price_type')) {
                $table->string('sold_price_type', 10)->nullable();
            }
            if (!Schema::hasColumn('quedans', 'quedan_price_id')) {
                $table->unsignedBigInteger('quedan_price_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quedans', function (Blueprint $table) {
            $table->dropColumn(['sold_price', 'sold_price_type', 'quedan_price_id']);
        });
    }
};
