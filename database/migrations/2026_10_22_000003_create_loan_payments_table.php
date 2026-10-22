<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Loan repayments, one row per payment against a schedule month (YYYY-MM)
        Schema::create('loan_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->string('month', 7);
            $table->decimal('amount', 12, 2);
            $table->date('paid_date');
            $table->string('reference', 50)->nullable();
            $table->string('remarks')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->timestamps();

            $table->index(['loan_id', 'month']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('loan_payments');
    }
};
