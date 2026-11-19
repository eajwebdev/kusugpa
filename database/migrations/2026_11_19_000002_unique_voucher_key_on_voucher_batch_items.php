<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A planter can have several vouchers in a Week Bought group (one per quedan), so the guard against
     * generating twice is one row per voucher of the Week Bought instead of one row per planter and weeks.
     * Older rows (no voucher key) were already kept unique when they were generated.
     */
    public function up(): void
    {
        Schema::table('voucher_batch_items', function (Blueprint $table) {
            $table->dropUnique('voucher_batch_items_voucher_unique');
            $table->index(['crop_year', 'week_from', 'week_to', 'planter_code'], 'voucher_batch_items_weeks_planter_index');
            $table->unique(['crop_year', 'bought_week', 'voucher_key'], 'voucher_batch_items_bought_voucher_unique');
        });
    }

    public function down(): void
    {
        Schema::table('voucher_batch_items', function (Blueprint $table) {
            $table->dropUnique('voucher_batch_items_bought_voucher_unique');
            $table->dropIndex('voucher_batch_items_weeks_planter_index');
            $table->unique(['crop_year', 'week_from', 'week_to', 'planter_code'], 'voucher_batch_items_voucher_unique');
        });
    }
};
