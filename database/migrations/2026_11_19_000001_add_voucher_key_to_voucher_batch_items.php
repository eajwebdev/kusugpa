<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Week Bought groups hold one voucher per quedan (and one per planter-week of molasses without a quedan),
     * so a group can be checked against the Registry: its quedans and molasses counts match the bought records.
     */
    public function up(): void
    {
        Schema::table('voucher_batch_items', function (Blueprint $t) {
            if (!Schema::hasColumn('voucher_batch_items', 'voucher_key')) {
                $t->string('voucher_key', 64)->nullable()->after('planter_name');
            }
            if (!Schema::hasColumn('voucher_batch_items', 'quedan_week')) {
                $t->unsignedInteger('quedan_week')->nullable()->after('voucher_key');
            }
            if (!Schema::hasColumn('voucher_batch_items', 'qdn_no')) {
                $t->string('qdn_no', 100)->nullable()->after('quedan_week');
            }
            if (!Schema::hasColumn('voucher_batch_items', 'quedan_count')) {
                $t->unsignedInteger('quedan_count')->default(0)->after('qdn_no');
            }
            if (!Schema::hasColumn('voucher_batch_items', 'molasses_count')) {
                $t->unsignedInteger('molasses_count')->default(0)->after('quedan_count');
            }
        });
    }

    public function down(): void
    {
        Schema::table('voucher_batch_items', function (Blueprint $t) {
            $t->dropColumn(['voucher_key', 'quedan_week', 'qdn_no', 'quedan_count', 'molasses_count']);
        });
    }
};
