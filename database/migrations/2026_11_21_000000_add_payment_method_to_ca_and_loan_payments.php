<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How a cash advance / loan repayment was made: taken off the Check Voucher ('voucher'), or paid in cash
     * over the counter ('cash') when the week's proceeds are not enough. A cash payment gets a receipt number,
     * and it is no longer deducted on the voucher of its week.
     */
    public function up(): void
    {
        foreach (['cash_advance_payments', 'loan_payments'] as $table) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (!Schema::hasColumn($table, 'payment_method')) {
                    $t->string('payment_method', 20)->default('voucher')->after('amount');
                }
                if (!Schema::hasColumn($table, 'receipt_no')) {
                    $t->string('receipt_no', 30)->nullable()->after('payment_method');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['cash_advance_payments', 'loan_payments'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn(['payment_method', 'receipt_no']);
            });
        }
    }
};
