<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Repayments of weekly-scheduled cash advances, one row per payment against a schedule week
        Schema::create('cash_advance_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_id')->constrained('cash_advances')->cascadeOnDelete();
            $table->string('week_no');
            $table->decimal('amount', 12, 2);
            $table->date('paid_date');
            $table->string('reference', 50)->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['cash_advance_id', 'week_no']);
        });

        // Weekly cash advances are repaid by their schedule: total and balance are the principal
        DB::table('cash_advances')->whereNotNull('term_weeks')->whereIn('status', ['pending', 'approved', 'rejected'])
            ->update(['total_amount' => DB::raw('amount'), 'balance' => DB::raw('amount')]);
    }

    public function down()
    {
        Schema::dropIfExists('cash_advance_payments');
    }
};
