<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\User;
use App\Services\DashboardAnalytics;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Dashboard tabs and the Balance Check that compares one module's data with the next.
 */
class DashboardAnalyticsTest extends TestCase
{
    private const CY = '20262027';

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
            $table->string('lname')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
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
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('user_id');
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
        Schema::create('crop_year', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->timestamps();
        });
        Schema::create('week_no', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->dateTime('week_start_date')->nullable();
            $table->dateTime('week_end_date')->nullable();
            $table->timestamps();
        });
        foreach (['quedans' => 'sugar_lkg', 'molasses' => 'mol_net'] as $name => $qty) {
            Schema::create($name, function (Blueprint $table) use ($qty) {
                $table->id();
                $table->string('crop_year');
                $table->string('week_no');
                $table->string('planter_code');
                $table->string('planter_name');
                $table->string('tin_no')->nullable();
                $table->decimal('total_liens', 12, 2)->default(0);
                $table->decimal($qty, 12, 3)->default(0);
                $table->decimal('labor_lkg', 12, 3)->default(0);
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }
        Schema::create('quedan_price', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('quedan_type');
            $table->decimal('quedan_price', 12, 2);
            $table->timestamps();
        });
        Schema::create('mol_price', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->decimal('mol_price', 12, 2);
            $table->timestamps();
        });
        Schema::create('consolidated_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            foreach (['ta_wt', 'ta_amount', 'emi_amount', 'pat_amount', 'cci_fa_amt', 'cci_fb_amt', 'cci_fc_amt',
                'rental_amt', 'underload_amt', 'mudpress_amt', 'adj_amt', 'fuel_issuance_amt', 'total_summary'] as $col) {
                $table->decimal($col, 12, 3)->default(0);
            }
            $table->timestamps();
        });
        Schema::create('trucking_allowance', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->string('trans_code')->nullable();
            $table->decimal('net_cane', 12, 3)->default(0);
            $table->decimal('ta_amount', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('planter_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('planter_code')->unique();
            $table->string('planter_name');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        foreach ([
            '2026_05_10_093216_create_loans_table.php',
            '2026_05_30_151501_create_cash_advances_table.php',
            '2026_10_20_000000_create_hauling_liquidity_tables.php',
            '2026_10_20_000001_add_check_no_to_hauling_liquidations.php',
            '2026_10_20_000002_add_planter_code_to_hauling_liquidations.php',
            '2026_10_20_000003_add_receipt_fields_and_deductions_to_cash_advances.php',
            '2026_10_21_000000_create_tls_hauling_uploads_table.php',
            '2026_10_22_000000_add_weekly_schedule_to_cash_advances.php',
            '2026_10_22_000002_create_cash_advance_payments_table.php',
            '2026_10_22_000003_create_loan_payments_table.php',
            '2026_10_24_000001_add_interest_amount_to_cash_advances.php',
            '2026_10_24_000002_create_planter_deductions_tables.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        DB::table('crop_year')->insert(['crop_year' => self::CY]);
        DB::table('week_no')->insert(['crop_year' => self::CY, 'week_no' => '3', 'week_end_date' => '2026-10-18']);
        DB::table('quedan_price')->insert(['crop_year' => self::CY, 'week_no' => '3', 'quedan_type' => 'B', 'quedan_price' => 1000]);
        DB::table('planter_profiles')->insert([
            ['planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN'],
            ['planter_code' => '00456', 'planter_name' => 'SANTOS, MARIA'],
        ]);
        // Juan: quedan and molasses in week 3, consolidated uploaded with a zero-padded week ("03")
        DB::table('quedans')->insert(['crop_year' => self::CY, 'week_no' => '3', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'sugar_lkg' => 10, 'status' => 'bought']);
        DB::table('molasses')->insert(['crop_year' => self::CY, 'week_no' => '3', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'mol_net' => 1, 'status' => 'bought']);
        DB::table('consolidated_uploads')->insert(['crop_year' => self::CY, 'week_no' => '03', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'ta_wt' => 9]);
    }

    private function admin(): User
    {
        return User::create(['fname' => 'Admin', 'role' => 'Administrator']);
    }

    private function check(array $balance, string $key): array
    {
        return collect($balance['checks'])->firstWhere('key', $key);
    }

    private function balance(): array
    {
        return $this->actingAs($this->admin())
            ->getJson(route('dashboard.tab', ['tab' => 'balance', 'crop_year' => self::CY]))
            ->assertOk()
            ->json();
    }

    public function test_every_tab_loads_for_an_administrator(): void
    {
        $this->actingAs($this->admin());

        foreach (['modules', 'production', 'finance', 'proceeds', 'hauling', 'balance'] as $tab) {
            $this->getJson(route('dashboard.tab', ['tab' => $tab, 'crop_year' => self::CY]))
                ->assertOk()
                ->assertJsonPath('scope.crop_year', self::CY);
        }
        $this->getJson(route('dashboard.tab', ['tab' => 'production', 'crop_year' => self::CY, 'week_no' => 3]))
            ->assertOk()
            ->assertJsonPath('stats.caneTons', 9)
            ->assertJsonPath('stats.sugarLkg', 10);
    }

    public function test_balanced_uploads_pass_and_padded_weeks_match(): void
    {
        $balance = $this->balance();

        $this->assertSame('ok', $this->check($balance, 'quedan_vs_consolidated')['status']);
        $this->assertSame('ok', $this->check($balance, 'molasses_vs_quedan')['status']);
        $this->assertSame('ok', $this->check($balance, 'week_setup')['status']);
    }

    public function test_voucher_includes_rows_uploaded_with_a_zero_padded_week(): void
    {
        $juan = \App\Services\PlanterProceeds::summary(self::CY, 3, 3)->firstWhere('planter_code', '00123');

        $this->assertEqualsWithDelta(9, $juan['consolidated_ta_wt'], 0.001);
    }

    public function test_planter_missing_from_consolidated_and_unpriced_molasses_are_flagged(): void
    {
        DB::table('quedans')->insert(['crop_year' => self::CY, 'week_no' => '3', 'planter_code' => '00456', 'planter_name' => 'SANTOS, MARIA', 'sugar_lkg' => 5]);
        DB::table('quedans')->insert(['crop_year' => self::CY, 'week_no' => '4', 'planter_code' => '00456', 'planter_name' => 'SANTOS, MARIA', 'sugar_lkg' => 5]);

        $balance = $this->balance();

        $match = $this->check($balance, 'quedan_vs_consolidated');
        $this->assertSame('warning', $match['status']);
        $this->assertSame(2, $match['count']);
        $this->assertSame('Consolidated', $match['rows'][0]['Missing in']);

        // Molasses delivered in week 3 has no molasses price; week 4 has no price and no week setup
        $prices = $this->check($balance, 'prices_missing');
        $this->assertSame('danger', $prices['status']);
        $this->assertContains(['Week' => 3, 'Missing' => 'Molasses price'], $prices['rows']);
        $this->assertContains(['Week' => 4, 'Missing' => 'Quedan price'], $prices['rows']);
        $this->assertSame('danger', $this->check($balance, 'week_setup')['status']);
        $this->assertGreaterThan(0, $balance['issues']);
    }

    public function test_cash_advance_balance_out_of_line_with_schedule_and_payments_is_flagged(): void
    {
        $id = DB::table('cash_advances')->insertGetId([
            'ca_number' => 'CA-0001', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'amount' => 1000,
            'interest_amount' => 0, 'total_amount' => 1000, 'balance' => 1000, 'status' => 'active',
            'application_date' => '2026-10-01', 'crop_year' => self::CY,
        ]);
        DB::table('cash_advance_deductions')->insert([
            ['cash_advance_id' => $id, 'crop_year' => self::CY, 'week_no' => '3', 'due_date' => '2026-10-18', 'amount' => 500],
            ['cash_advance_id' => $id, 'crop_year' => self::CY, 'week_no' => '4', 'due_date' => '2099-10-25', 'amount' => 500],
        ]);
        DB::table('cash_advance_payments')->insert(['cash_advance_id' => $id, 'week_no' => '3', 'amount' => 200, 'paid_date' => '2026-10-18']);

        $balance = $this->balance();

        $schedule = $this->check($balance, 'ca_schedule');
        $this->assertSame('danger', $schedule['status']);
        $this->assertSame('₱800.00', $schedule['rows'][0]['Expected']);

        // Week 3 is past due with ₱300 of its ₱500 unpaid; week 4 is not due yet
        $overdue = $this->check($balance, 'ca_overdue');
        $this->assertSame('danger', $overdue['status']);
        $this->assertEqualsWithDelta(300, $overdue['amount'], 0.001);

        DB::table('cash_advances')->where('id', $id)->update(['balance' => 800]);
        $this->assertSame('ok', $this->check($this->balance(), 'ca_schedule')['status']);
    }

    public function test_accounts_larger_than_proceeds_show_as_over_deducted(): void
    {
        $id = DB::table('cash_advances')->insertGetId([
            'ca_number' => 'CA-0002', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'amount' => 50000,
            'interest_amount' => 0, 'total_amount' => 50000, 'balance' => 50000, 'status' => 'active',
            'application_date' => '2026-10-01', 'crop_year' => self::CY,
        ]);
        // ₱10,000 of sugar against a ₱50,000 week
        DB::table('cash_advance_deductions')->insert(['cash_advance_id' => $id, 'crop_year' => self::CY, 'week_no' => '3', 'due_date' => '2099-01-01', 'amount' => 50000]);

        $over = $this->check($this->balance(), 'over_deducted');

        $this->assertSame('danger', $over['status']);
        $this->assertSame(1, $over['count']);
    }

    public function test_collections_tracker_totals_every_account_kind(): void
    {
        $admin = $this->admin();
        $typeId = DB::table('loan_types')->insertGetId(['name' => 'Crop Loan']);
        $loanId = DB::table('loans')->insertGetId([
            'loan_number' => 'LN-0001', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'loan_type_id' => $typeId,
            'principal_amount' => 1000, 'interest_rate' => 10, 'term_months' => 2, 'monthly_amortization' => 550,
            'total_amount' => 1100, 'balance' => 600, 'status' => 'active', 'application_date' => '2026-08-01',
            'crop_year' => self::CY, 'created_by' => $admin->id,
        ]);
        DB::table('loan_amortizations')->insert([
            ['loan_id' => $loanId, 'payment_number' => 1, 'due_date' => '2026-09-01', 'amount_due' => 550, 'amount_paid' => 500, 'balance_after' => 550, 'status' => 'partial'],
            ['loan_id' => $loanId, 'payment_number' => 2, 'due_date' => now()->addDays(10)->toDateString(), 'amount_due' => 550, 'amount_paid' => 0, 'balance_after' => 0, 'status' => 'pending'],
        ]);
        DB::table('loan_payments')->insert(['loan_id' => $loanId, 'month' => '2026-09', 'amount' => 500, 'paid_date' => '2026-09-01']);

        DB::table('cash_advances')->insert([
            'ca_number' => 'CA-0003', 'planter_code' => '00456', 'planter_name' => 'SANTOS, MARIA', 'amount' => 2000,
            'interest_amount' => 0, 'total_amount' => 2000, 'balance' => 2000, 'status' => 'approved',
            'application_date' => '2026-10-01', 'crop_year' => self::CY,
        ]);

        // Fuel: week 3 comes off Juan's voucher (he delivered); week 4 is past due with no delivery
        $fuelId = DB::table('planter_deductions')->insertGetId([
            'reference_no' => 'FD-1', 'type' => 'fuel', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN',
            'crop_year' => self::CY, 'amount' => 300,
        ]);
        DB::table('planter_deduction_weeks')->insert([
            ['planter_deduction_id' => $fuelId, 'crop_year' => self::CY, 'week_no' => '3', 'due_date' => '2026-10-18', 'amount' => 100],
            ['planter_deduction_id' => $fuelId, 'crop_year' => self::CY, 'week_no' => '4', 'due_date' => '2026-10-25', 'amount' => 200],
        ]);

        $c = $this->actingAs($admin)->getJson(route('dashboard.tab', ['tab' => 'collections']))->assertOk()->json();
        $type = fn ($key) => collect($c['types'])->firstWhere('key', $key);

        $this->assertSame(['loan', 'ca', 'fuel', 'others'], array_column($c['types'], 'key'));
        $this->assertEquals(['released' => 1000, 'interest' => 100, 'collected' => 500, 'remaining' => 600, 'overdue' => 50, 'due30' => 550],
            collect($type('loan'))->only(['released', 'interest', 'collected', 'remaining', 'overdue', 'due30'])->all());
        $this->assertFalse($type('loan')['mismatch']);
        $this->assertEquals(2000, $type('ca')['remaining']);
        $this->assertEquals(100, $type('fuel')['collected']);
        $this->assertEquals(200, $type('fuel')['remaining']);
        $this->assertEquals(200, $type('fuel')['overdue']);

        $this->assertEquals(2800, $c['total']['remaining']);
        $this->assertEquals(250, $c['total']['overdue']);
        $this->assertSame(2, $c['planterCount']);
        $this->assertEquals(['loan' => 600, 'fuel' => 200, 'total' => 800],
            collect($c['planters'])->firstWhere('planter_code', '00123') ? collect(collect($c['planters'])->firstWhere('planter_code', '00123'))->only(['loan', 'fuel', 'total'])->all() : []);
    }

    public function test_tabs_and_checks_follow_the_users_permissions(): void
    {
        $user = User::create(['fname' => 'Clerk', 'role' => 'User']);
        $permission = Permission::create(['name' => 'View Cash Advances', 'slug' => 'view-cash-advances']);
        $user->permissions()->attach($permission);
        $this->actingAs($user);

        $this->getJson(route('dashboard.tab', ['tab' => 'hauling']))->assertForbidden();
        $this->getJson(route('dashboard.tab', ['tab' => 'proceeds']))->assertForbidden();
        $this->getJson(route('dashboard.tab', ['tab' => 'finance']))->assertOk()->assertJsonPath('loans', null);
        $this->assertSame(['ca'], array_column($this->getJson(route('dashboard.tab', ['tab' => 'collections']))->assertOk()->json('types'), 'key'));

        $keys = collect($this->getJson(route('dashboard.tab', ['tab' => 'balance']))->assertOk()->json('checks'))->pluck('key');
        $this->assertContains('ca_schedule', $keys);
        $this->assertNotContains('loan_balance', $keys);
        $this->assertNotContains('tls_unliquidated', $keys);
        $this->assertNotContains('over_deducted', $keys);

        $tabs = collect(DashboardAnalytics::tabsFor(DashboardAnalytics::accessFor($user->fresh())))->pluck('key')->all();
        $this->assertSame(['overview', 'production', 'finance', 'balance'], $tabs);
    }
}
