<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The price a molasses record was sold at, picked from Price Management when it is marked bought
     * (like quedans). Its week is the molasses' "Week Bought"; the Check Voucher and the proceeds
     * reports use the price instead of the molasses week's price.
     */
    public function up(): void
    {
        Schema::table('molasses', function (Blueprint $table) {
            if (!Schema::hasColumn('molasses', 'sold_price')) {
                $table->decimal('sold_price', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('molasses', 'molasses_price_id')) {
                $table->unsignedBigInteger('molasses_price_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('molasses', function (Blueprint $table) {
            $table->dropIndex(['molasses_price_id']);
            $table->dropColumn(['sold_price', 'molasses_price_id']);
        });
    }
};
