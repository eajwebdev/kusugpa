<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PlanterProceeds;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Fuel & Others Deduction → Check Voucher (FUEL PAYMENT / OTHERS ACCOUNT) → Planter Accnt. Applied
 * and Proceeds & Deductions reports.
 */
class PlanterDeductionsTest extends TestCase
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
        Schema::create('quedans', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->string('tin_no')->nullable();
            $table->decimal('total_liens', 12, 2)->default(0);
            $table->decimal('sugar_lkg', 12, 3)->default(0);
            $table->decimal('labor_lkg', 12, 3)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('quedan_price', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('quedan_type');
            $table->decimal('quedan_price', 12, 2);
            $table->timestamps();
        });
        Schema::create('molasses', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->string('tin_no')->nullable();
            $table->decimal('mol_net', 12, 3)->default(0);
            $table->string('status')->default('pending');
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
            $table->decimal('ta_wt', 12, 3)->default(0);
            $table->decimal('ta_amount', 12, 2)->default(0);
            $table->decimal('fuel_issuance_amt', 12, 2)->default(0);
            $table->decimal('total_summary', 12, 2)->default(0);
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

        foreach ([
            '2026_05_10_093216_create_loans_table.php',
            '2026_05_30_151501_create_cash_advances_table.php',
            '2026_10_20_000003_add_receipt_fields_and_deductions_to_cash_advances.php',
            '2026_10_22_000000_add_weekly_schedule_to_cash_advances.php',
            '2026_10_24_000002_create_planter_deductions_tables.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        foreach (['3' => '2026-10-18', '4' => '2026-10-25', '10' => '2026-12-06'] as $week => $end) {
            DB::table('week_no')->insert(['crop_year' => '20262027', 'week_no' => $week, 'week_end_date' => $end]);
            DB::table('quedan_price')->insert(['crop_year' => '20262027', 'week_no' => $week, 'quedan_type' => 'B', 'quedan_price' => 1000]);
        }
        // Sugar in weeks 3 and 4: 10 LKg x ₱1,000 each week
        foreach (['3', '4'] as $week) {
            DB::table('quedans')->insert([
                'crop_year' => '20262027', 'week_no' => $week, 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN',
                'sugar_lkg' => 10, 'labor_lkg' => 0, 'status' => 'bought',
            ]);
        }

        $this->actingAs(User::create(['fname' => 'Admin', 'role' => 'Administrator']));
    }

    private function createDeduction(string $type, float $amount, array $weekAmounts)
    {
        return $this->postJson(route('planter-deductions.store'), [
            'type' => $type, 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'crop_year' => '20262027',
            'amount' => $amount, 'description' => 'Test',
            'schedule' => collect($weekAmounts)->map(fn ($a, $w) => ['week_no' => (string) $w, 'amount' => $a])->values()->all(),
        ]);
    }

    private function captureView(string $view, string $route, array $query): array
    {
        $captured = null;
        view()->composer($view, function ($v) use (&$captured) {
            $captured = $v->getData();
        });
        $this->get(route($route, $query))->assertOk();

        return $captured;
    }

    public function test_deduction_schedule_must_equal_the_amount_and_needs_no_approval(): void
    {
        $this->createDeduction('fuel', 300, [3 => 100, 4 => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('schedule');

        $this->createDeduction('fuel', 300, [3 => 100, 4 => 200])
            ->assertCreated()
            ->assertJsonPath('deduction.reference_no', 'FD' . date('Y') . '0001');
        $this->createDeduction('others', 50, [4 => 50])
            ->assertCreated()
            ->assertJsonPath('deduction.reference_no', 'OD' . date('Y') . '0001');

        $this->assertSame(2, DB::table('planter_deductions')->count());
        $this->assertSame(3, DB::table('planter_deduction_weeks')->count());
    }

    public function test_voucher_deducts_fuel_and_others_of_its_weeks(): void
    {
        $this->createDeduction('fuel', 300, [3 => 100, 4 => 200])->assertCreated();
        $this->createDeduction('others', 50, [4 => 50])->assertCreated();

        $p = PlanterProceeds::compute(PlanterProceeds::summary('20262027', 4, 4)->first());

        $this->assertEqualsWithDelta(200, $p['fuelPayment'], 0.001);
        $this->assertEqualsWithDelta(50, $p['othersPayment'], 0.001);
        $this->assertEqualsWithDelta($p['totalNetProceeds'] - 250, $p['netProceedsPaid'], 0.001);

        // The voucher prints them on its OTHERS ACCOUNT and FUEL PAYMENT lines
        $data = $this->captureView('pdf.voucher', 'voucher.pdf', ['crop_year' => '20262027', 'week_from' => 4, 'week_to' => 4]);
        $html = view('pdf.voucher', $data)->render();
        $this->assertStringContainsString('200.00', $html);
        $this->assertStringContainsString('50.00', $html);
    }

    public function test_week_ranges_compare_week_numbers_as_numbers(): void
    {
        $this->createDeduction('fuel', 100, [3 => 100])->assertCreated();

        // Week 3 is inside 3-10 (as text "3" > "10", which used to drop it)
        $p = PlanterProceeds::compute(PlanterProceeds::summary('20262027', 3, 10)->first());
        $this->assertEqualsWithDelta(100, $p['fuelPayment'], 0.001);
        $this->assertEqualsWithDelta(20000, $p['quedanGrossProceeds'], 0.001);
    }

    public function test_prices_entered_in_lowercase_or_twice_are_used_once(): void
    {
        // Week 4's quedan price typed as "b", and two molasses prices for week 4 (the later one wins)
        DB::table('quedan_price')->where('week_no', '4')->update(['quedan_type' => 'b', 'quedan_price' => 2605]);
        DB::table('mol_price')->insert([
            ['crop_year' => '20262027', 'week_no' => '4', 'mol_price' => 16000],
            ['crop_year' => '20262027', 'week_no' => '4', 'mol_price' => 17000],
        ]);
        DB::table('molasses')->insert(['crop_year' => '20262027', 'week_no' => '4', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'mol_net' => 1.3, 'status' => 'bought']);

        $p = PlanterProceeds::compute(PlanterProceeds::summary('20262027', 4, 4)->first());

        $this->assertEqualsWithDelta(2605, $p['quedan_b_price'], 0.001);
        $this->assertEqualsWithDelta(26050, $p['quedan_b_gross'], 0.001);
        $this->assertEqualsWithDelta(1.3, $p['mol_net'], 0.0001);
        $this->assertEqualsWithDelta(22100, $p['molasses_gross'], 0.001);
    }

    public function test_pending_quedans_and_molasses_are_left_off_the_voucher(): void
    {
        // Week 4: 5 more LKg of sugar and 2 t of molasses, still pending in Buy Quedan / Buy Molasses
        DB::table('mol_price')->insert(['crop_year' => '20262027', 'week_no' => '4', 'mol_price' => 10000]);
        DB::table('quedans')->insert(['crop_year' => '20262027', 'week_no' => '4', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'sugar_lkg' => 5, 'total_liens' => 99]);
        DB::table('molasses')->insert(['crop_year' => '20262027', 'week_no' => '4', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'mol_net' => 2]);
        // A planter with nothing bought yet gets no voucher
        DB::table('quedans')->insert(['crop_year' => '20262027', 'week_no' => '4', 'planter_code' => '00456', 'planter_name' => 'SANTOS, MARIA', 'sugar_lkg' => 8]);
        DB::table('molasses')->insert(['crop_year' => '20262027', 'week_no' => '4', 'planter_code' => '00456', 'planter_name' => 'SANTOS, MARIA', 'mol_net' => 1]);

        $summary = PlanterProceeds::summary('20262027', 4, 4);
        $this->assertSame(['00123'], $summary->pluck('planter_code')->all());

        $p = PlanterProceeds::compute($summary->first());
        $this->assertEqualsWithDelta(10, $p['quedan_b_lkg'], 0.001);
        $this->assertEqualsWithDelta(10000, $p['quedan_b_gross'], 0.001);
        $this->assertEqualsWithDelta(0, $p['quedan_b_liens'], 0.001);
        $this->assertEqualsWithDelta(0, $p['mol_net'], 0.001);
        $this->assertEqualsWithDelta(0, $p['molasses_gross'], 0.001);

        // The voucher's planter list skips them too
        $this->getJson(route('get.planter.names', ['crop_year' => '20262027', 'week_from' => 4, 'week_to' => 4]))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.planter_code', '00123');

        // Once bought they count
        DB::table('quedans')->update(['status' => 'bought']);
        DB::table('molasses')->update(['status' => 'bought']);
        $p = PlanterProceeds::compute(PlanterProceeds::summary('20262027', 4, 4)->firstWhere('planter_code', '00123'));
        $this->assertEqualsWithDelta(15, $p['quedan_b_lkg'], 0.001);
        $this->assertEqualsWithDelta(2, $p['mol_net'], 0.001);
        $this->assertEqualsWithDelta(20000, $p['molasses_gross'], 0.001);
    }

    public function test_planter_accounts_applied_lists_each_account_with_totals(): void
    {
        $this->createDeduction('fuel', 300, [3 => 100, 4 => 200])->assertCreated();
        $this->createDeduction('others', 50, [4 => 50])->assertCreated();

        $data = $this->captureView('pdf.planter-accounts', 'planter-accounts-report.pdf', [
            'crop_year' => '20262027', 'week_from' => 3, 'week_to' => 4,
        ]);

        $row = $data['rows']->first();
        $this->assertSame('00123', $row['code']);
        $this->assertEqualsWithDelta(300, $row['fuel'], 0.001);
        $this->assertEqualsWithDelta(50, $row['others'], 0.001);
        $this->assertEqualsWithDelta(350, $row['total'], 0.001);
        $this->assertEqualsWithDelta(350, $data['rows']->sum('total'), 0.001);
    }

    public function test_proceeds_and_deductions_shows_the_week_and_to_date(): void
    {
        $this->createDeduction('fuel', 300, [3 => 100, 4 => 200])->assertCreated();

        $data = $this->captureView('pdf.proceeds-deductions', 'proceeds-deductions-report.pdf', [
            'crop_year' => '20262027', 'week_no' => 4,
        ]);

        $planter = $data['planters']->first();
        $this->assertEqualsWithDelta(200, $planter['week']['fuel'], 0.001);
        $this->assertEqualsWithDelta(300, $planter['to_date']['fuel'], 0.001);
        $this->assertEqualsWithDelta(10000, $planter['week']['quedan'], 0.001);
        $this->assertEqualsWithDelta(20000, $planter['to_date']['quedan'], 0.001);
        // Net paid = net proceeds - accounts applied, each period
        foreach (['week', 'to_date'] as $period) {
            $line = $planter[$period];
            $this->assertEqualsWithDelta($line['net'] - $line['loan'] - $line['ca'] - $line['others'] - $line['fuel'], $line['net_paid'], 0.01);
        }
    }
}
