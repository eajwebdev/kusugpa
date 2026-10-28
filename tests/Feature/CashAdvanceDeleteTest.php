<?php

namespace Tests\Feature;

use App\Models\CashAdvance;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Only administrators delete cash advances, in any status; the weekly schedule, recorded payments
 * and monthly installments go with it, and Hauling Liquidity slips that deducted it are flagged
 */
class CashAdvanceDeleteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutMiddleware(\App\Http\Middleware\SystemLockMiddleware::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleSession::class);

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fname');
            $table->string('lname');
            $table->string('username');
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });
        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('permission_id');
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_role')->nullable();
            $table->string('action');
            $table->string('module');
            $table->string('description');
            $table->text('details')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
        });

        foreach ([
            '2026_05_30_151501_create_cash_advances_table.php',
            '2026_10_20_000000_create_hauling_liquidity_tables.php',
            '2026_10_20_000001_add_check_no_to_hauling_liquidations.php',
            '2026_10_20_000002_add_planter_code_to_hauling_liquidations.php',
            '2026_10_20_000003_add_receipt_fields_and_deductions_to_cash_advances.php',
            '2026_10_22_000000_add_weekly_schedule_to_cash_advances.php',
            '2026_10_22_000001_add_receipt_signatories_to_cash_advances.php',
            '2026_10_22_000002_create_cash_advance_payments_table.php',
            '2026_10_24_000001_add_interest_amount_to_cash_advances.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }
    }

    private function user(string $role): User
    {
        return User::create(['fname' => 'Test', 'lname' => $role, 'username' => uniqid('user'), 'password' => 'unused', 'role' => $role])
            ->load('permissions');
    }

    /** An active weekly advance with weeks 3 and 4 scheduled and week 3 paid */
    private function activeAdvance(): int
    {
        $id = DB::table('cash_advances')->insertGetId([
            'ca_number' => 'CA20260001', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'crop_year' => '20262027',
            'amount' => 1000, 'interest_rate' => 0, 'term_months' => 1, 'term_weeks' => 2, 'monthly_amortization' => 1000,
            'total_amount' => 1000, 'balance' => 500, 'status' => 'active', 'application_date' => now(), 'created_by' => 1,
        ]);
        DB::table('cash_advance_deductions')->insert([
            ['cash_advance_id' => $id, 'crop_year' => '20262027', 'week_no' => '3', 'amount' => 500],
            ['cash_advance_id' => $id, 'crop_year' => '20262027', 'week_no' => '4', 'amount' => 500],
        ]);
        DB::table('cash_advance_payments')->insert(['cash_advance_id' => $id, 'week_no' => '3', 'amount' => 500, 'paid_date' => '2026-10-18']);
        DB::table('cash_advance_amortizations')->insert([
            'cash_advance_id' => $id, 'payment_number' => 1, 'due_date' => '2026-11-01', 'amount_due' => 1000, 'status' => 'pending',
        ]);

        return $id;
    }

    public function test_non_admins_cannot_delete(): void
    {
        $id = $this->activeAdvance();

        foreach (['manager', 'loan_officer', 'User'] as $role) {
            $this->actingAs($this->user($role));
            $this->deleteJson(route('cash-advances.destroy', $id))->assertForbidden();
        }

        $this->assertNotNull(CashAdvance::find($id));
        $this->assertSame(2, DB::table('cash_advance_deductions')->count());
        $this->assertSame(1, DB::table('cash_advance_payments')->count());
    }

    public function test_admin_deletes_an_active_advance_with_its_schedule_payments_and_installments(): void
    {
        $id = $this->activeAdvance();
        $this->actingAs($this->user('Administrator'));

        $this->deleteJson(route('cash-advances.destroy', $id))
            ->assertOk()
            ->assertJsonPath('warning', null);

        $this->assertNull(CashAdvance::find($id));
        $this->assertSame(0, DB::table('cash_advance_deductions')->count());
        $this->assertSame(0, DB::table('cash_advance_payments')->count());
        $this->assertSame(0, DB::table('cash_advance_amortizations')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'delete')->count());
    }

    public function test_hauling_slips_that_deducted_the_advance_are_flagged(): void
    {
        $id = $this->activeAdvance();
        $haulerId = DB::table('haulers')->value('id');
        $slip = DB::table('hauling_liquidations')->insertGetId([
            'hauler_id' => $haulerId, 'crop_year' => '20262027', 'week_no' => '3', 'week_ending' => '2026-10-18',
            'planter_code' => '123', 'payee_name' => 'DELA CRUZ, JUAN', 'truck_code' => 'RJG480',
        ]);
        DB::table('hauling_liquidation_items')->insert(['hauling_liquidation_id' => $slip, 'type' => 'less', 'description' => 'Cash Adv.', 'amount' => 500]);

        $this->actingAs($this->user('super_admin'));
        $this->deleteJson(route('cash-advances.destroy', $id))
            ->assertOk()
            ->assertJsonPath('hauling_slips', ['Wk 3 · ISIO-CAUAYAN HAULER TLS LIQ. · RJG480']);

        // The slip itself is left for the user to review
        $this->assertSame(1, DB::table('hauling_liquidation_items')->where('amount', 500)->count());
    }
}
