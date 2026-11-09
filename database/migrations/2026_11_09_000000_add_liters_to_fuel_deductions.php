<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fuel upload carries liters; each uploaded fuel row also becomes a Fuel deduction
     * (planter_deductions, source = upload) linked back to its fuel_allowance row.
     */
    public function up(): void
    {
        if (Schema::hasTable('fuel_allowance') && !Schema::hasColumn('fuel_allowance', 'liters')) {
            Schema::table('fuel_allowance', function (Blueprint $table) {
                $table->decimal('liters', 12, 3)->nullable()->after('planter_name');
            });
        }

        Schema::table('planter_deductions', function (Blueprint $table) {
            if (!Schema::hasColumn('planter_deductions', 'liters')) {
                $table->decimal('liters', 12, 3)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('planter_deductions', 'source')) {
                $table->string('source', 20)->nullable()->after('type'); // null = manual | upload
            }
            if (!Schema::hasColumn('planter_deductions', 'fuel_allowance_id')) {
                $table->unsignedBigInteger('fuel_allowance_id')->nullable()->after('source');
                $table->index('fuel_allowance_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('planter_deductions', function (Blueprint $table) {
            $table->dropIndex(['fuel_allowance_id']);
            $table->dropColumn(['liters', 'source', 'fuel_allowance_id']);
        });

        if (Schema::hasTable('fuel_allowance') && Schema::hasColumn('fuel_allowance', 'liters')) {
            Schema::table('fuel_allowance', function (Blueprint $table) {
                $table->dropColumn('liters');
            });
        }
    }
};
