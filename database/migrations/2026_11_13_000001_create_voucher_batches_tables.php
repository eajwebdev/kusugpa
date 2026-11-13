<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generated voucher groups. Each Generate on Print Vouchers saves one group (crop year, week range,
 * date and time) with its planters; a planter in a group is not generated again for those weeks
 * until the group is deleted. Each planter can have a person authorized to receive the voucher,
 * printed in place of the planter's name. Replaces voucher_receivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_batches', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->unsignedInteger('week_from');
            $table->unsignedInteger('week_to');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('generated_by')->nullable();
            $table->timestamps();

            $table->index(['crop_year', 'week_from', 'week_to']);
        });

        Schema::create('voucher_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_batch_id')->constrained('voucher_batches')->cascadeOnDelete();
            $table->string('crop_year');
            $table->unsignedInteger('week_from');
            $table->unsignedInteger('week_to');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->decimal('net_paid', 15, 2)->default(0);
            $table->string('receiver_name')->nullable();
            $table->timestamps();

            // The same planter can never be in two groups for the same weeks
            $table->unique(['crop_year', 'week_from', 'week_to', 'planter_code'], 'voucher_batch_items_voucher_unique');
            $table->index(['crop_year', 'planter_code']);
        });

        Schema::dropIfExists('voucher_receivers');
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher_batch_items');
        Schema::dropIfExists('voucher_batches');

        if (!Schema::hasTable('voucher_receivers')) {
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
    }
};
