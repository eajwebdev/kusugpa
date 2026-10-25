<?php

namespace Tests\Feature;

use App\Models\CashAdvance;
use App\Models\Loan;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cash advances and loans below ₱50,000 are approved on creation; ₱50,000 and up wait for a
 * Manager / Administrator. Cash advance interest is a flat amount x rate% repaid with the advance.
 */
class ApprovalThresholdTest extends TestCase
{
    private int $loanTypeId;

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
        Schema::create('week_no', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->dateTime('week_start_date')->nullable();
            $table->dateTime('week_end_date')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamps();
        });

        foreach ([
            '2026_05_10_093216_create_loans_table.php',
            '2026_05_30_151501_create_cash_advances_table.php',
            '2026_10_20_000003_add_receipt_fields_and_deductions_to_cash_advances.php',
            '2026_10_22_000000_add_weekly_schedule_to_cash_advances.php',
            '2026_10_22_000001_add_receipt_signatories_to_cash_advances.php',
            '2026_10_22_000002_create_cash_advance_payments_table.php',
            '2026_10_22_000003_create_loan_payments_table.php',
            '2026_10_24_000001_add_interest_amount_to_cash_advances.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        DB::table('week_no')->insert([
            ['crop_year' => '20262027', 'week_no' => '3', 'week_start_date' => '2026-10-12', 'week_end_date' => '2026-10-18'],
            ['crop_year' => '20262027', 'week_no' => '4', 'week_start_date' => '2026-10-19', 'week_end_date' => '2026-10-25'],
        ]);
        $this->loanTypeId = DB::table('loan_types')->insertGetId([
            'name' => 'Crop Loan', 'default_interest_rate' => 12, 'default_term_months' => 6, 'max_amount' => 500000,
        ]);
    }

    private function userWith(array $slugs, string $role = 'User'): User
    {
        $user = User::create([
            'fname' => 'Test', 'lname' => $role, 'username' => uniqid('user'), 'password' => 'unused', 'role' => $role,
        ]);
        foreach ($slugs as $slug) {
            $user->permissions()->attach(Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]));
        }

        return $user->load('permissions');
    }

    private function createCashAdvance(float $amount, float $rate, array $weekAmounts)
    {
        return $this->postJson(route('cash-advances.store'), [
            'planter_code' => '123', 'planter_name' => 'DELA CRUZ, JUAN', 'crop_year' => '20262027',
            'amount' => $amount, 'interest_rate' => $rate, 'term_weeks' => count($weekAmounts),
            'schedule' => collect($weekAmounts)->map(fn ($a, $week) => ['week_no' => (string) $week, 'amount' => $a])->values()->all(),
        ]);
    }

    private function createLoan(float $principal)
    {
        return $this->postJson(route('loans.store'), [
            'planter_code' => '123', 'planter_name' => 'DELA CRUZ, JUAN', 'crop_year' => '20262027',
            'loan_type_id' => $this->loanTypeId, 'principal_amount' => $principal, 'interest_rate' => 12, 'term_months' => 6,
        ]);
    }

    public function test_cash_advance_below_threshold_is_approved_with_flat_interest(): void
    {
        $this->actingAs($this->userWith(['create-cash-advances']));

        // ₱10,000 at 3% → ₱300 interest, ₱10,300 repaid over the weeks
        $this->createCashAdvance(10000, 3, [3 => 5150, 4 => 5150])
            ->assertCreated()
            ->assertJson(['auto_approved' => true]);

        $ca = CashAdvance::first();
        $this->assertSame('approved', $ca->status);
        $this->assertEqualsWithDelta(300, $ca->interest_amount, 0.001);
        $this->assertEqualsWithDelta(10300, $ca->total_amount, 0.001);
        $this->assertEqualsWithDelta(10300, $ca->balance, 0.001);
        $this->assertNotNull($ca->approved_date);
    }

    public function test_cash_advance_schedule_must_cover_the_interest(): void
    {
        $this->actingAs($this->userWith(['create-cash-advances']));

        $this->createCashAdvance(10000, 3, [3 => 5000, 4 => 5000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schedule');
    }

    public function test_receipt_details_can_update_the_interest_percentage_and_schedule(): void
    {
        $this->actingAs($this->userWith(['create-cash-advances']));
        $this->createCashAdvance(10000, 3, [3 => 5150, 4 => 5150])->assertCreated();

        $ca = CashAdvance::firstOrFail();
        $this->postJson(route('cash-advances.receipt.update', $ca), [
            'interest_rate' => 5,
            'cv_no' => '00016625',
            'check_no' => 'CHK-42',
            'deductions' => [
                ['week_no' => '3', 'amount' => 5250],
                ['week_no' => '4', 'amount' => 5250],
            ],
            'signatories' => [],
        ])->assertOk()->assertJsonPath('cash_advance.interest_rate', 5)
            ->assertJsonPath('cash_advance.interest_amount', 500);

        $ca->refresh();
        $this->assertEqualsWithDelta(5, $ca->interest_rate, 0.001);
        $this->assertEqualsWithDelta(500, $ca->interest_amount, 0.001);
        $this->assertEqualsWithDelta(10500, $ca->total_amount, 0.001);
        $this->assertEqualsWithDelta(10500, $ca->balance, 0.001);
        $this->assertEqualsWithDelta(10500, $ca->deductions()->sum('amount'), 0.001);
    }

    public function test_receipt_interest_edit_rejects_a_schedule_using_the_old_total(): void
    {
        $this->actingAs($this->userWith(['create-cash-advances']));
        $this->createCashAdvance(10000, 3, [3 => 5150, 4 => 5150])->assertCreated();

        $ca = CashAdvance::firstOrFail();
        $this->postJson(route('cash-advances.receipt.update', $ca), [
            'interest_rate' => 5,
            'deductions' => [
                ['week_no' => '3', 'amount' => 5150],
                ['week_no' => '4', 'amount' => 5150],
            ],
            'signatories' => [],
        ])->assertStatus(422)->assertJsonValidationErrors('deductions');

        $this->assertEqualsWithDelta(3, $ca->fresh()->interest_rate, 0.001);
    }

    public function test_cash_advance_of_50000_waits_for_a_manager(): void
    {
        $this->actingAs($this->userWith(['create-cash-advances', 'approve-cash-advances']));
        $this->createCashAdvance(50000, 0, [3 => 25000, 4 => 25000])
            ->assertCreated()
            ->assertJson(['auto_approved' => false]);
        $ca = CashAdvance::first();
        $this->assertSame('pending', $ca->status);

        // Staff, even with the approve permission, cannot approve ₱50,000 or more
        $this->post(route('cash-advances.approve', $ca))->assertForbidden();
        $this->post(route('cash-advances.reject', $ca))->assertForbidden();

        // Managers still need cash advance access assigned to reach the module
        $this->actingAs($this->userWith(['approve-cash-advances'], 'manager'));
        $this->post(route('cash-advances.approve', $ca))->assertRedirect();
        $this->assertSame('approved', $ca->fresh()->status);
    }

    public function test_staff_can_approve_a_pending_cash_advance_below_threshold(): void
    {
        // e.g. one filed before the rule existed
        $id = DB::table('cash_advances')->insertGetId([
            'ca_number' => 'CA20260001', 'planter_code' => '123', 'planter_name' => 'DELA CRUZ, JUAN', 'crop_year' => '20262027',
            'amount' => 20000, 'interest_rate' => 0, 'term_months' => 1, 'term_weeks' => 1, 'monthly_amortization' => 20000,
            'total_amount' => 20000, 'balance' => 20000, 'status' => 'pending', 'application_date' => now(), 'created_by' => 1,
        ]);
        DB::table('cash_advance_deductions')->insert(['cash_advance_id' => $id, 'crop_year' => '20262027', 'week_no' => '3', 'amount' => 20000]);

        $this->actingAs($this->userWith(['create-cash-advances']));
        $this->post(route('cash-advances.approve', $id))->assertRedirect();
        $this->assertSame('approved', CashAdvance::find($id)->status);
    }

    public function test_loan_below_threshold_is_approved_on_creation(): void
    {
        $this->actingAs($this->userWith(['create-loans']));

        $this->createLoan(49999.99)->assertCreated()->assertJson(['auto_approved' => true]);

        $loan = Loan::first();
        $this->assertSame('approved', $loan->status);
        $this->assertNotNull($loan->end_date);
        $this->assertSame(6, $loan->amortizations()->count());
    }

    public function test_loan_of_50000_or_more_needs_a_manager_or_admin(): void
    {
        $this->actingAs($this->userWith(['create-loans', 'approve-loans']));
        $this->createLoan(60000)->assertCreated()->assertJson(['auto_approved' => false]);
        $loan = Loan::first();
        $this->assertSame('pending', $loan->status);

        $this->post(route('loans.approve', $loan))->assertForbidden();

        $this->actingAs($this->userWith([], 'Administrator'));
        $this->post(route('loans.approve', $loan))->assertRedirect();
        $this->assertSame('approved', $loan->fresh()->status);
    }
}
