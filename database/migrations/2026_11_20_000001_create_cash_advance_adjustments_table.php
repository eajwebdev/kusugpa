<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * History of a cash advance's balance changes: a week not paid on time and moved to a later week with
     * its late charge (1% per week late), an interest change or waiver, or another adjustment of the balance.
     */
    public function up(): void
    {
        if (Schema::hasTable('cash_advance_adjustments')) {
            return;
        }
        Schema::create('cash_advance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_advance_id')->constrained('cash_advances')->cascadeOnDelete();
            $table->string('type', 20); // late_charge, interest, adjustment
            $table->string('from_week', 10)->nullable();
            $table->string('to_week', 10)->nullable();
            $table->decimal('unpaid_amount', 12, 2)->default(0);
            $table->unsignedInteger('weeks_late')->default(0);
            $table->decimal('charge_amount', 12, 2)->default(0);
            $table->decimal('repayable_before', 12, 2)->default(0);
            $table->decimal('repayable_after', 12, 2)->default(0);
            $table->string('reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_adjustments');
    }
};
