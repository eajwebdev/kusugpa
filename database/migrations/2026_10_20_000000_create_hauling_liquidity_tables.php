<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('haulers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hauling_liquidations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hauler_id')->constrained('haulers')->cascadeOnDelete();
            $table->string('crop_year');
            $table->string('week_no');
            $table->date('week_ending');
            $table->date('date_released')->nullable();
            $table->string('payee_name');
            $table->string('cv_no')->nullable();
            $table->string('truck_code')->nullable();
            $table->decimal('tonnage', 12, 3)->default(0);
            $table->decimal('rate_per_ton', 12, 2)->default(0);
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('total_add', 14, 2)->default(0);
            $table->decimal('total_less', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['hauler_id', 'crop_year', 'week_no']);
        });

        Schema::create('hauling_liquidation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hauling_liquidation_id')->constrained('hauling_liquidations')->cascadeOnDelete();
            $table->enum('type', ['add', 'less']);
            $table->string('description');
            $table->decimal('amount', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Starter hauler so the module is usable right away
        DB::table('haulers')->insert([
            'name' => 'ISIO-CAUAYAN HAULER TLS LIQ.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down()
    {
        Schema::dropIfExists('hauling_liquidation_items');
        Schema::dropIfExists('hauling_liquidations');
        Schema::dropIfExists('haulers');
    }
};
