<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fuel & Others Deduction: amounts a planter owes on their fuel or other accounts, set like a
     * cash advance on a weekly schedule (no approval). Each week's amount comes off that week's
     * Check Voucher as FUEL PAYMENT / OTHERS ACCOUNT.
     */
    public function up(): void
    {
        Schema::create('planter_deductions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no')->unique();
            $table->string('type', 20); // fuel | others
            $table->string('planter_code');
            $table->string('planter_name');
            $table->string('crop_year');
            $table->decimal('amount', 12, 2);
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['type', 'crop_year']);
            $table->index('planter_code');
        });

        Schema::create('planter_deduction_weeks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planter_deduction_id')->constrained('planter_deductions')->cascadeOnDelete();
            $table->string('crop_year');
            $table->string('week_no');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['crop_year', 'week_no']);
        });

        foreach ([
            ['access-all-planter-deductions', 'All Fuel & Others Deduction', 'Access every Fuel & Others Deduction operation'],
            ['view-planter-deductions', 'View Fuel & Others Deduction', 'Can view fuel and other planter deductions'],
            ['manage-planter-deductions', 'Manage Fuel & Others Deduction', 'Can add, edit and delete fuel and other planter deductions'],
            ['view-planter-accounts-report', 'Planter Accnt. Applied', 'Access to Planter Accnt. Applied in Reports'],
            ['view-proceeds-deductions-report', 'Proceeds & Deductions', 'Access to Proceeds & Deductions in Reports'],
        ] as [$slug, $name, $description]) {
            DB::table('permissions')->updateOrInsert(['slug' => $slug], [
                'name' => $name,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('permissions')->whereIn('slug', [
            'access-all-planter-deductions', 'view-planter-deductions', 'manage-planter-deductions',
            'view-planter-accounts-report', 'view-proceeds-deductions-report',
        ])->delete();
        Schema::dropIfExists('planter_deduction_weeks');
        Schema::dropIfExists('planter_deductions');
    }
};
