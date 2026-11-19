<?php

namespace Tests\Feature;

use App\Models\PlanterDeduction;
use App\Models\User;
use App\Services\PlanterProceeds;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
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
            $table->timestamp('bought_at')->nullable();
            $table->unsignedBigInteger('bought_by')->nullable();
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
            $table->timestamp('bought_at')->nullable();
            $table->unsignedBigInteger('bought_by')->nullable();
            $table->decimal('sold_price', 12, 2)->nullable();
            $table->unsignedBigInteger('molasses_price_id')->nullable();
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
            '2026_10_22_000002_create_cash_advance_payments_table.php',
            '2026_10_24_000001_add_interest_amount_to_cash_advances.php',
            '2026_10_24_000002_create_planter_deductions_tables.php',
            '2026_11_09_000000_add_liters_to_fuel_deductions.php',
            '2026_11_09_000001_add_sold_price_to_quedans.php',
            '2026_11_13_000000_create_voucher_receivers_table.php',
            '2026_11_13_000001_create_voucher_batches_tables.php',
            '2026_11_19_000000_add_bought_week_to_voucher_batches.php',
            '2026_11_19_000001_add_voucher_key_to_voucher_batch_items.php',
            '2026_11_19_000002_unique_voucher_key_on_voucher_batch_items.php',
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

    /**
     * Pending quedans leave the planter's record to be started by the molasses row, and the
     * consolidated row is then added to it.
     */
    public function test_report_pdfs_generate_and_leave_out_pending_rows(): void
    {
        $row = ['crop_year' => '20262027', 'week_no' => '4', 'planter_code' => '00777', 'planter_name' => 'SANTOS, ANA'];
        DB::table('quedans')->insert($row + ['sugar_lkg' => 99, 'labor_lkg' => 0, 'status' => 'pending']);
        DB::table('molasses')->insert($row + ['mol_net' => 5, 'status' => 'bought']);
        DB::table('molasses')->insert($row + ['mol_net' => 50, 'status' => 'pending']);
        DB::table('consolidated_uploads')->insert($row + ['ta_wt' => 7, 'ta_amount' => 70, 'fuel_issuance_amt' => 1, 'total_summary' => 70]);

        $period = ['crop_year' => '20262027', 'week_from' => 4, 'week_to' => 4, 'week_no' => 4];
        foreach (['voucher.pdf', 'proceeds-sheet-report.pdf', 'planter-accounts-report.pdf', 'proceeds-deductions-report.pdf'] as $report) {
            $this->get(route($report, $period))->assertOk();
        }

        $record = PlanterProceeds::summary('20262027', 4, 4)->firstWhere('planter_code', '00777');
        $this->assertEqualsWithDelta(0, $record['sugar_lkg'], 0.001);
        $this->assertEqualsWithDelta(0, $record['quedan_b_lkg'], 0.001);
        $this->assertEqualsWithDelta(5, $record['mol_net'], 0.001);
        $this->assertEqualsWithDelta(70, $record['consolidated_ta_amount'], 0.001);

        // Excluded from the reports only: the rows stay pending for the Buy pages
        $this->assertSame(1, DB::table('quedans')->where('planter_code', '00777')->where('status', 'pending')->count());
        $this->assertSame(1, DB::table('molasses')->where('planter_code', '00777')->where('status', 'pending')->count());
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

    public function test_same_planter_same_week_warns_before_saving_a_second_deduction(): void
    {
        $this->createDeduction('fuel', 100, [3 => 100])->assertCreated();

        // Same planter under a code without the leading zeros, same week: warned, nothing saved
        $second = [
            'type' => 'fuel', 'planter_code' => '123', 'planter_name' => 'Dela Cruz, Juan', 'crop_year' => '20262027',
            'amount' => 50, 'schedule' => [['week_no' => '3', 'amount' => 50]],
        ];
        $this->postJson(route('planter-deductions.store'), $second)
            ->assertStatus(409)
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('matches.0.planter_code', '00123')
            ->assertJsonPath('matches.0.weeks', ['3']);
        $this->assertSame(1, DB::table('planter_deductions')->count());

        // Another week, or another type, is not a duplicate
        $this->postJson(route('planter-deductions.store'), ['schedule' => [['week_no' => '4', 'amount' => 50]]] + $second)->assertCreated();
        $this->postJson(route('planter-deductions.store'), ['type' => 'others'] + $second)->assertCreated();

        // Confirmed by the user: saved
        $this->postJson(route('planter-deductions.store'), $second + ['confirm_duplicate' => true])->assertCreated();
        $this->assertSame(4, DB::table('planter_deductions')->count());
    }

    public function test_quick_cash_advance_is_approved_with_one_percent_and_comes_off_the_week(): void
    {
        $quick = ['crop_year' => '20262027', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN',
            'amount' => 5000, 'week_no' => '4', 'date_created' => '2026-10-20'];

        // The week must be set up for the crop year
        $this->postJson(route('cash-advances.quick'), ['week_no' => '9'] + $quick)->assertStatus(422)->assertJsonValidationErrors('week_no');

        $this->postJson(route('cash-advances.quick'), $quick)->assertCreated();

        // Saved as an ordinary approved cash advance: 1% interest, the whole amount on week 4
        $ca = DB::table('cash_advances')->first();
        $this->assertSame('approved', $ca->status);
        $this->assertEquals(1, (float) $ca->interest_rate);
        $this->assertEquals(50, (float) $ca->interest_amount);
        $this->assertEquals(5050, (float) $ca->total_amount);
        $this->assertSame('2026-10-20', substr((string) $ca->application_date, 0, 10));
        $this->assertSame([['week_no' => '4', 'amount' => 5050.0]], DB::table('cash_advance_deductions')->get()
            ->map(fn ($d) => ['week_no' => (string) $d->week_no, 'amount' => (float) $d->amount])->all());

        // On the week 4 voucher and Planter Accnt. Applied, not week 3's
        $this->assertEqualsWithDelta(5050, PlanterProceeds::summary('20262027', 4, 4)->firstWhere('planter_code', '00123')['ca_deduction'], 0.001);
        $this->assertEqualsWithDelta(0, PlanterProceeds::summary('20262027', 3, 3)->firstWhere('planter_code', '00123')['ca_deduction'], 0.001);
        $rows = $this->captureView('pdf.planter-accounts', 'planter-accounts-report.pdf', ['crop_year' => '20262027', 'week_from' => 4, 'week_to' => 4])['rows'];
        $this->assertEqualsWithDelta(5050, $rows->firstWhere('code', '00123')['ca'], 0.001);
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

    public function test_proceeds_and_deductions_week_bought_tallies_with_the_registry(): void
    {
        DB::table('week_no')->where('week_no', '4')->update(['week_start_date' => '2026-10-19']);
        $week3 = DB::table('quedans')->where('week_no', '3')->value('id');
        $week4 = DB::table('quedans')->where('week_no', '4')->value('id');
        // Week 3 quedan sold at a week 10 price; week 4 quedan at a week 4 price
        $this->postJson(route('quedans.bulk-update'), ['ids' => [$week3], 'status' => 'bought', 'quedan_price_id' => DB::table('quedan_price')->where('week_no', '10')->value('id')])->assertOk();
        $this->postJson(route('quedans.bulk-update'), ['ids' => [$week4], 'status' => 'bought', 'quedan_price_id' => DB::table('quedan_price')->where('week_no', '4')->value('id')])->assertOk();
        // Another planter with only a week 4 purchase (molasses bought inside week 4) is left out of bought week 10
        DB::table('molasses')->insert(['crop_year' => '20262027', 'week_no' => '4', 'planter_code' => '00777', 'planter_name' => 'SANTOS, ANA', 'mol_net' => 5, 'status' => 'bought', 'bought_at' => '2026-10-20 08:00:00']);

        $report = fn ($boughtWeek) => $this->captureView('pdf.proceeds-deductions', 'proceeds-deductions-report.pdf', [
            'crop_year' => '20262027', 'week_no' => 4, 'bought_week' => $boughtWeek,
        ]);
        $registry = fn ($week, $boughtWeek) => array_column($this->getJson(route('registry.data', [
            'type' => 'quedan', 'crop_year' => '20262027', 'week_no' => $week, 'bought_week' => $boughtWeek, 'page' => 1,
        ]))->assertOk()->json('data'), 'id');

        $data = $report(10);
        $this->assertSame(['00123'], $data['planters']->pluck('code')->all());
        $this->assertEqualsWithDelta(0, $data['totals']['week']['quedan'], 0.001);      // week 4 quedan was bought in week 4
        $this->assertEqualsWithDelta(10000, $data['totals']['to_date']['quedan'], 0.001); // week 3 quedan, bought in week 10
        $this->assertSame([], $registry('4', '10'));
        $this->assertSame([$week3], $registry('3', '10'));

        $data = $report(4);
        $this->assertEqualsCanonicalizing(['00123', '00777'], $data['planters']->pluck('code')->all());
        $this->assertEqualsWithDelta(10000, $data['totals']['week']['quedan'], 0.001);
        $this->assertEqualsWithDelta(10000, $data['totals']['to_date']['quedan'], 0.001);
        $this->assertSame([$week4], $registry('4', '4'));

        // The consolidated report and the page's dropdown use the same week bought
        $this->get(route('proceeds-deductions-report.consolidated', ['crop_year' => '20262027', 'week_no' => 4, 'bought_week' => 10]))->assertOk();
        $registryOptions = $this->getJson(route('registry.data', ['type' => 'quedan', 'crop_year' => '20262027', 'page' => 1]))
            ->assertOk()->json('extra.bought_weeks');
        $this->assertEquals($registryOptions, \App\Models\WeekNo::boughtWeekOptions('20262027', true)->all());
    }

    public function test_consolidated_proceeds_and_deductions_adds_up_the_week(): void
    {
        $this->createDeduction('fuel', 300, [3 => 100, 4 => 200])->assertCreated();

        $planter = $this->captureView('pdf.proceeds-deductions', 'proceeds-deductions-report.pdf', [
            'crop_year' => '20262027', 'week_no' => 4,
        ])['totals']['week'];
        $data = $this->captureView('pdf.proceeds-deductions-consolidated', 'proceeds-deductions-report.consolidated', [
            'crop_year' => '20262027', 'week_no' => 4,
        ]);
        $t = $data['t'];

        // Same week totals as the per planter report
        $this->assertEqualsWithDelta($planter['quedan'], $t['sugar'], 0.01);
        $this->assertEqualsWithDelta($planter['molasses'], $t['molasses'], 0.01);
        $this->assertEqualsWithDelta(200, $t['fuel_ac'], 0.001);
        // Quedan & molasses total - deductions/liens & other charges + net trucking allowance - fuel A/C - transloading S.C
        $this->assertEqualsWithDelta($t['gross'] - $t['deductions'] + $t['trucking'] - $t['fuel_ac'] - $t['additional'], $t['net'], 0.01);
        $this->assertEqualsWithDelta($data['volumes']['sugar_gross'] - $data['volumes']['labor'], $data['volumes']['sugar_net'], 0.001);
    }

    /** Marks the quedans bought at the week's price: their Week Bought */
    private function buyAtWeek(string $week, array $where = []): int
    {
        $price = DB::table('quedan_price')->where('crop_year', '20262027')->where('week_no', $week)->value('id');
        DB::table('quedans')->where($where)->update(['quedan_price_id' => $price, 'status' => 'bought']);

        return $price;
    }

    public function test_generate_saves_a_group_and_never_generates_a_planter_twice_for_its_weeks(): void
    {
        DB::table('quedans')->insert([
            'crop_year' => '20262027', 'week_no' => '4', 'planter_code' => '00456', 'planter_name' => 'ABATAYO, EDDIE',
            'sugar_lkg' => 5, 'labor_lkg' => 0, 'status' => 'bought',
        ]);
        // DELA CRUZ's week 3 and week 4 quedans and ABATAYO's week 4 quedan are all bought in week 4
        $this->buyAtWeek('4');
        DB::table('consolidated_uploads')->insert(['crop_year' => '20262027', 'week_no' => '3', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'ta_amount' => 300, 'total_summary' => 300]);
        DB::table('consolidated_uploads')->insert(['crop_year' => '20262027', 'week_no' => '10', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN', 'ta_amount' => 999, 'total_summary' => 999]);
        $week4 = ['crop_year' => '20262027', 'bought_week' => 4];

        // Week Bought 4: one voucher per quedan. The week 3 quedan's voucher has the week 3 trucking allowance
        // (not week 10's); the week 4 quedan's voucher has none.
        // Deductions follow their schedule: fuel set for week 3 comes off the week 3 quedan's voucher and fuel set
        // for week 4 off the week 4 one, both deducted now that the quedans are bought (week 4); week 10's is not yet.
        $this->createDeduction('fuel', 350, [3 => 100, 4 => 200, 10 => 50])->assertCreated();
        $vouchers = PlanterProceeds::summaryByBoughtWeek('20262027', 4)->where('planter_code', '00123')->values();
        $this->assertCount(2, $vouchers);
        $this->assertSame([3, 4], $vouchers->pluck('quedan_week')->all());
        $this->assertSame([1, 1], $vouchers->pluck('quedan_count')->all());
        $this->assertEqualsWithDelta(10, $vouchers[0]['sugar_lkg'], 0.001);
        $this->assertEqualsWithDelta(300, $vouchers[0]['consolidated_total'], 0.001);
        $this->assertEqualsWithDelta(0, $vouchers[1]['consolidated_total'], 0.001);
        $this->assertEqualsWithDelta(100, $vouchers[0]['fuel_deduction'], 0.001);
        $this->assertEqualsWithDelta(200, $vouchers[1]['fuel_deduction'], 0.001);

        // Only DELA CRUZ first
        $first = $this->postJson(route('voucher.batches.store'), $week4 + ['planter_name' => ['00123']])
            ->assertCreated()->assertJsonPath('batch.vouchers', 2)->assertJsonPath('batch.quedans', 2)->assertJsonPath('batch.bought_week', 4)->json('batch.id');

        // Then all of Week Bought 4: DELA CRUZ is already in a group, so only ABATAYO is generated
        $second = $this->postJson(route('voucher.batches.store'), $week4)
            ->assertCreated()->assertJsonPath('batch.vouchers', 1)->assertJsonPath('skipped', 1)->json('batch.id');
        $this->assertSame(['ABATAYO, EDDIE'], $this->getJson(route('voucher.batches.show', $second))->json('items.*.planter_name'));

        // Print Vouchers' planter list leaves out generated planters
        $names = fn ($extra) => $this->getJson(route('get.planter.names', $week4 + $extra))->assertOk()->json('*.planter_code');
        $this->assertSame([], $names(['exclude_generated' => 1]));
        $this->assertEqualsCanonicalizing(['00123', '00456'], $names([]));

        // Nothing left in Week Bought 4, and nothing was bought in week 3
        $this->postJson(route('voucher.batches.store'), $week4)->assertStatus(422);
        $this->postJson(route('voucher.batches.store'), ['crop_year' => '20262027', 'bought_week' => 3])->assertStatus(422);
        $this->getJson(route('voucher.batches.index', ['crop_year' => '20262027']))->assertOk()->assertJsonCount(2);

        // Deleting a group lets its planters be generated again
        $this->deleteJson(route('voucher.batches.destroy', $first))->assertOk();
        $this->assertSame(0, DB::table('voucher_batch_items')->where('voucher_batch_id', $first)->count());
        $this->assertSame(['00123'], $names(['exclude_generated' => 1]));
        $this->postJson(route('voucher.batches.store'), $week4)
            ->assertCreated()->assertJsonPath('batch.vouchers', 2);
        $this->assertSame(3, DB::table('voucher_batch_items')->count());

        // A planter in an older group (by week range) covering week 4 is not generated again by Week Bought 4
        DB::table('voucher_batch_items')->delete();
        $old = DB::table('voucher_batches')->insertGetId(['crop_year' => '20262027', 'week_from' => 4, 'week_to' => 4, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('voucher_batch_items')->insert(['voucher_batch_id' => $old, 'crop_year' => '20262027', 'week_from' => 4, 'week_to' => 4, 'planter_code' => '00456', 'planter_name' => 'ABATAYO, EDDIE', 'net_paid' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson(route('voucher.batches.store'), $week4)->assertCreated()->assertJsonPath('batch.vouchers', 2)->assertJsonPath('skipped', 1);
    }

    public function test_group_voucher_prints_the_authorized_receiver_in_place_of_the_planter(): void
    {
        $this->buyAtWeek('4');
        $period = ['crop_year' => '20262027', 'bought_week' => 4];
        $batch = $this->postJson(route('voucher.batches.store'), $period)->assertCreated()->json('batch.id');
        $item = $this->getJson(route('voucher.batches.show', $batch))->assertOk()
            ->assertJsonPath('items.0.planter_name', 'DELA CRUZ, JUAN')
            ->assertJsonPath('items.0.receiver_name', '')
            ->json('items.0.id');

        $this->patchJson(route('voucher.batches.receiver', [$batch, $item]), ['receiver_name' => '  Edwin   Abril '])
            ->assertOk()->assertJsonPath('receiver_name', 'Edwin Abril');
        $this->getJson(route('voucher.batches.index'))->assertJsonPath('0.receivers', 1);

        // The receiver prints on that voucher (week 3 quedan) only; the week 4 quedan's voucher keeps the planter
        $all = $this->captureView('pdf.voucher', 'voucher.pdf', ['batch_id' => $batch]);
        $this->assertCount(2, $all['summaryData']);
        $html = view('pdf.voucher', $all)->render();
        $this->assertSame(1, substr_count($html, 'Edwin Abril'));
        $this->assertStringContainsString('DELA CRUZ, JUAN', $html);

        // One voucher of the group prints alone
        $one = $this->captureView('pdf.voucher', 'voucher.pdf', ['batch_id' => $batch, 'item_id' => $item]);
        $this->assertCount(1, $one['summaryData']);
        $this->assertEqualsWithDelta(10, $one['summaryData'][0]['sugar_lkg'], 0.001);
        $html = view('pdf.voucher', $one)->render();
        $this->assertStringContainsString('Edwin Abril', $html);
        $this->assertStringNotContainsString('DELA CRUZ, JUAN', $html);

        // A blank name prints the planter again
        $this->patchJson(route('voucher.batches.receiver', [$batch, $item]), ['receiver_name' => ''])->assertOk();
        $html = view('pdf.voucher', $this->captureView('pdf.voucher', 'voucher.pdf', ['batch_id' => $batch, 'item_id' => $item]))->render();
        $this->assertStringContainsString('DELA CRUZ, JUAN', $html);

        // A quedan bought in week 3 is its own group; an item of that group cannot be changed through the week 4 group
        DB::table('quedans')->insert([
            'crop_year' => '20262027', 'week_no' => '3', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN',
            'sugar_lkg' => 1, 'labor_lkg' => 0, 'status' => 'bought',
            'quedan_price_id' => DB::table('quedan_price')->where('week_no', '3')->value('id'),
        ]);
        $other = $this->postJson(route('voucher.batches.store'), ['crop_year' => '20262027', 'bought_week' => 3])
            ->assertCreated()->json('batch.id');
        $otherItem = $this->getJson(route('voucher.batches.show', $other))->json('items.0.id');
        $this->patchJson(route('voucher.batches.receiver', [$batch, $otherItem]), ['receiver_name' => 'X'])->assertNotFound();
    }

    private function uploadFuel(string $csv, string $week = '3')
    {
        if (!Schema::hasTable('fuel_allowance')) {
            Schema::create('fuel_allowance', function (Blueprint $table) {
                $table->id();
                $table->string('crop_year');
                $table->string('week_no');
                $table->string('planter_code');
                $table->string('planter_name');
                $table->decimal('liters', 12, 3)->nullable();
                $table->decimal('fuel_amount', 10, 2)->default(0);
                $table->string('user_id');
                $table->timestamps();
            });
        }

        return $this->postJson(route('upload.csv', ['type' => 'fuel']), [
            'crop_year' => '20262027',
            'week_no' => $week,
            'file' => UploadedFile::fake()->createWithContent('fuel.csv', $csv),
        ]);
    }

    public function test_fuel_upload_creates_fuel_deductions_with_liters_and_reference_numbers(): void
    {
        $this->createDeduction('fuel', 50, [4 => 50])->assertCreated(); // FD...0001 made by hand

        $this->uploadFuel("crop_year,week_no,planter_code,planter_name,liters,fuel_amount\n"
            . "20262027,3,00123,\"DELA CRUZ, JUAN\",20.5,\"1,230.00\"\n"
            . ",,00456,SANTOS MARIA,10,600\n")
            ->assertOk()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('fuel_deductions', 2);

        // Stored as 5-digit codes with leading zeros, like every other upload
        $this->assertSame('00123', DB::table('fuel_allowance')->orderBy('id')->value('planter_code'));
        $this->assertEquals(20.5, (float) DB::table('fuel_allowance')->orderBy('id')->value('liters'));

        $uploaded = PlanterDeduction::where('source', 'upload')->with('weeks')->orderBy('id')->get();
        $this->assertCount(2, $uploaded);
        $year = date('Y');
        $this->assertSame(["FD{$year}0002", "FD{$year}0003"], $uploaded->pluck('reference_no')->all());
        $this->assertSame('00123', $uploaded[0]->planter_code);
        $this->assertSame('DELA CRUZ, JUAN', $uploaded[0]->planter_name);

        // Still comes off the voucher of the planter whose quedan code is 00123
        $p = PlanterProceeds::compute(PlanterProceeds::summary('20262027', 3, 3)->firstWhere('planter_code', '00123'));
        $this->assertEqualsWithDelta(1230, $p['fuelPayment'], 0.001);
        $this->assertEquals(1230, (float) $uploaded[0]->amount);
        $this->assertEquals(20.5, (float) $uploaded[0]->liters);
        $this->assertSame('3', (string) $uploaded[0]->weeks->first()->week_no);
        $this->assertEquals(1230, (float) $uploaded[0]->weeks->first()->amount);

        // Shows on the Fuel & Others Deduction page for that week
        $this->getJson(route('planter-deductions.index', ['type' => 'fuel', 'crop_year' => '20262027', 'week_no' => 3]))
            ->assertOk()
            ->assertJsonPath('extra.amount', 1830)
            ->assertJsonPath('extra.liters', 30.5);

        // Same week again is refused
        $this->uploadFuel("20262027,3,00789,REYES ANA,5,300\n")->assertStatus(422);
        $this->assertSame(2, PlanterDeduction::where('source', 'upload')->count());
    }

    public function test_fuel_upload_refuses_rows_of_another_crop_year_or_week(): void
    {
        $this->uploadFuel("20262027,4,00123,DELA CRUZ JUAN,20,1000\n")
            ->assertStatus(422)
            ->assertJsonFragment(['error' => 'The file has rows for a different crop year / week than Crop Year 20262027 Week 3: line 1 (20262027 / Wk 4). Fix the file or select the matching crop year and week.']);

        $this->uploadFuel("20252026,3,00123,DELA CRUZ JUAN,20,1000\n")->assertStatus(422);
        $this->uploadFuel("20262027,99,00123,DELA CRUZ JUAN,20,1000\n", '99')->assertStatus(422); // week not set up

        $this->assertSame(0, DB::table('fuel_allowance')->count());
        $this->assertSame(0, PlanterDeduction::count());
    }

    public function test_unused_template_starter_row_is_ignored(): void
    {
        // Downloaded template row with only crop year / week, then the planter rows typed below it
        $this->uploadFuel("crop_year,week_no,planter_code,planter_name,liters,fuel_amount\n"
            . "20262027,3,,,,\n"
            . "20262027,3,00123,DELA CRUZ JUAN,20,1000\n")
            ->assertOk()
            ->assertJsonPath('imported', 1)
            ->assertJsonPath('errors', 0);

        // Only the starter row: nothing to import
        PlanterDeduction::query()->delete();
        DB::table('fuel_allowance')->delete();
        $this->uploadFuel("crop_year,week_no,planter_code,planter_name,liters,fuel_amount\n20262027,3,,,,\n")->assertStatus(422);
    }

    public function test_quedans_are_bought_at_the_picked_price_and_the_voucher_uses_it(): void
    {
        $priceB = DB::table('quedan_price')->insertGetId(['crop_year' => '20262027', 'week_no' => '4', 'quedan_type' => 'B', 'quedan_price' => 1500]);
        $priceA = DB::table('quedan_price')->insertGetId(['crop_year' => '20262027', 'week_no' => '4', 'quedan_type' => 'a', 'quedan_price' => 3000]);
        $week3 = DB::table('quedans')->where('week_no', '3')->value('id');
        $week4 = DB::table('quedans')->where('week_no', '4')->value('id');

        // A price must be picked, and it must be of the quedans' crop year
        $this->postJson(route('quedans.bulk-update'), ['ids' => [$week3], 'status' => 'bought'])->assertStatus(422);
        $otherYear = DB::table('quedan_price')->insertGetId(['crop_year' => '20252026', 'week_no' => '4', 'quedan_type' => 'B', 'quedan_price' => 900]);
        $this->postJson(route('quedans.bulk-update'), ['ids' => [$week3], 'status' => 'bought', 'quedan_price_id' => $otherYear])->assertStatus(422);

        $this->postJson(route('quedans.bulk-update'), ['ids' => [$week3], 'status' => 'bought', 'quedan_price_id' => $priceB])->assertOk();
        $this->postJson(route('quedans.bulk-update'), ['ids' => [$week4], 'status' => 'bought', 'quedan_price_id' => $priceA])->assertOk();

        $q3 = DB::table('quedans')->find($week3);
        $this->assertEquals(1500, (float) $q3->sold_price);
        $this->assertSame('B', $q3->sold_price_type);
        $this->assertSame('A', DB::table('quedans')->find($week4)->sold_price_type);

        // Week 3 quedan at B ₱1,500 and week 4 quedan at A ₱3,000; not the weeks' ₱1,000 prices
        $p = PlanterProceeds::compute(PlanterProceeds::summary('20262027', 3, 4)->first());
        $this->assertEqualsWithDelta(10, $p['quedan_b_lkg'], 0.001);
        $this->assertEqualsWithDelta(1500, $p['quedan_b_price'], 0.001);
        $this->assertEqualsWithDelta(15000, $p['quedan_b_gross'], 0.001);
        $this->assertEqualsWithDelta(150, $p['quedan_b_tax'], 0.001);
        $this->assertEqualsWithDelta(10, $p['quedan_a_lkg'], 0.001);
        $this->assertEqualsWithDelta(30000, $p['quedan_a_gross'], 0.001);
        $this->assertEqualsWithDelta(0, $p['quedan_d_lkg'], 0.001);
        $this->assertEqualsWithDelta(45000, $p['quedanGrossProceeds'], 0.001);

        // Back to pending: no sold price and off the voucher
        $this->postJson(route('quedans.bulk-update'), ['ids' => [$week4], 'status' => 'pending'])->assertOk();
        $this->assertNull(DB::table('quedans')->find($week4)->sold_price);
        $p = PlanterProceeds::compute(PlanterProceeds::summary('20262027', 3, 4)->first());
        $this->assertEqualsWithDelta(15000, $p['quedanGrossProceeds'], 0.001);
    }

    public function test_molasses_are_bought_at_the_picked_price_and_its_week_is_the_week_bought(): void
    {
        $row = ['crop_year' => '20262027', 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN'];
        $week3 = DB::table('molasses')->insertGetId($row + ['week_no' => '3', 'mol_net' => 2, 'status' => 'pending']);
        $legacy = DB::table('molasses')->insertGetId($row + ['week_no' => '4', 'mol_net' => 1, 'status' => 'bought']);
        DB::table('mol_price')->insert(['crop_year' => '20262027', 'week_no' => '3', 'mol_price' => 10000]);
        DB::table('mol_price')->insert(['crop_year' => '20262027', 'week_no' => '4', 'mol_price' => 12000]);
        $week5Price = DB::table('mol_price')->insertGetId(['crop_year' => '20262027', 'week_no' => '5', 'mol_price' => 19100]);

        // A price must be picked, and it must be of the molasses' crop year
        $this->postJson(route('molasses.bulk-update'), ['ids' => [$week3], 'status' => 'bought'])->assertStatus(422);
        $otherYear = DB::table('mol_price')->insertGetId(['crop_year' => '20252026', 'week_no' => '5', 'mol_price' => 900]);
        $this->postJson(route('molasses.bulk-update'), ['ids' => [$week3], 'status' => 'bought', 'molasses_price_id' => $otherYear])->assertStatus(422);

        // The picker lists the week's molasses prices
        $this->getJson(route('molasses-buy.prices', ['crop_year' => '20262027', 'week_no' => '5']))
            ->assertOk()->assertJsonPath('prices.0.id', $week5Price)->assertJsonPath('prices.0.price', 19100);

        $this->postJson(route('molasses.bulk-update'), ['ids' => [$week3], 'status' => 'bought', 'molasses_price_id' => $week5Price])->assertOk();
        $this->assertEquals(19100, (float) DB::table('molasses')->find($week3)->sold_price);

        // Week Bought: the picked price's week (5), not the molasses week (3); bought before prices were picked: its own week
        $boughtIn = fn ($week) => \App\Models\Molass::boughtInWeek('20262027', $week)->pluck('id')->all();
        $this->assertSame([$week3], $boughtIn(5));
        $this->assertSame([], $boughtIn(3));
        $this->assertSame([$legacy], $boughtIn(4));

        // Voucher: week 3 molasses at the picked ₱19,100; the legacy one at its week's ₱12,000
        $p = PlanterProceeds::compute(PlanterProceeds::summary('20262027', 3, 4)->firstWhere('planter_code', '00123'));
        $this->assertEqualsWithDelta(3, $p['mol_net'], 0.001);
        $this->assertEqualsWithDelta(2 * 19100 + 1 * 12000, $p['molasses_gross'], 0.01);
        $this->assertEqualsWithDelta((2 * 19100 + 1 * 12000) * 0.01, $p['molasses_tax'], 0.01);

        // Back to pending: no sold price and off the voucher
        $this->postJson(route('molasses.bulk-update'), ['ids' => [$week3], 'status' => 'pending'])->assertOk();
        $this->assertNull(DB::table('molasses')->find($week3)->molasses_price_id);
    }

    public function test_sold_price_picker_loads_weeks_then_searchable_prices(): void
    {
        DB::table('quedan_price')->insert(['crop_year' => '20262027', 'week_no' => '4', 'quedan_type' => 'd', 'quedan_price' => 2750]);
        DB::table('quedan_price')->insert(['crop_year' => '20262027', 'week_no' => '4', 'quedan_type' => 'A', 'quedan_price' => 0]); // no ₱0 choice
        DB::table('quedan_price')->insert(['crop_year' => '20252026', 'week_no' => '4', 'quedan_type' => 'B', 'quedan_price' => 900]);

        // 1. Crop year → its weeks with the number of prices each
        $weeks = $this->getJson(route('quedan-buy.prices', ['crop_year' => '20262027']))->assertOk()->json('weeks');
        $this->assertSame([['week_no' => '3', 'prices' => 1], ['week_no' => '4', 'prices' => 2], ['week_no' => '10', 'prices' => 1]], $weeks);

        // 2. Week → that week's prices only
        $prices = $this->getJson(route('quedan-buy.prices', ['crop_year' => '20262027', 'week_no' => '4']))->assertOk()->json('prices');
        $this->assertSame(['B', 'D'], array_column($prices, 'type'));
        $this->assertEquals([1000, 2750], array_column($prices, 'price'));
        $this->assertSame(['4', '4'], array_column($prices, 'week_no'));

        // 3. Search by type or amount
        $search = fn ($s) => array_column($this->getJson(route('quedan-buy.prices', ['crop_year' => '20262027', 'week_no' => '4', 'search' => $s]))->assertOk()->json('prices'), 'type');
        $this->assertSame(['D'], $search('d'));
        $this->assertSame(['D'], $search('Type D'));
        $this->assertSame(['D'], $search('2,750'));
        $this->assertSame(['B'], $search('₱1000'));
        $this->assertSame([], $search('zzz'));

        $this->getJson(route('quedan-buy.prices'))->assertStatus(422); // crop year required
    }

    public function test_quedans_sold_at_different_prices_add_up_exactly(): void
    {
        $low = DB::table('quedan_price')->insertGetId(['crop_year' => '20262027', 'week_no' => '3', 'quedan_type' => 'B', 'quedan_price' => 1200]);
        $high = DB::table('quedan_price')->insertGetId(['crop_year' => '20262027', 'week_no' => '4', 'quedan_type' => 'B', 'quedan_price' => 1800]);
        $this->postJson(route('quedans.bulk-update'), ['ids' => [DB::table('quedans')->where('week_no', '3')->value('id')], 'status' => 'bought', 'quedan_price_id' => $low])->assertOk();
        $this->postJson(route('quedans.bulk-update'), ['ids' => [DB::table('quedans')->where('week_no', '4')->value('id')], 'status' => 'bought', 'quedan_price_id' => $high])->assertOk();

        $p = PlanterProceeds::compute(PlanterProceeds::summary('20262027', 3, 4)->first());
        $this->assertEqualsWithDelta(20, $p['quedan_b_lkg'], 0.001);
        $this->assertEqualsWithDelta(30000, $p['quedan_b_gross'], 0.001); // 10 x 1,200 + 10 x 1,800
        $this->assertEqualsWithDelta(1500, $p['quedan_b_price'], 0.001); // average shown on the voucher
        $this->assertEqualsWithDelta(300, $p['quedan_b_tax'], 0.001);
    }

    public function test_week_bought_is_the_week_of_the_sold_price(): void
    {
        DB::table('week_no')->where('week_no', '4')->update(['week_start_date' => '2026-10-19']);
        $week3 = DB::table('quedans')->where('week_no', '3')->value('id');
        $week4 = DB::table('quedans')->where('week_no', '4')->value('id');

        // Week 3 quedan sold at a week 10 price, marked bought today
        $week10Price = DB::table('quedan_price')->where('week_no', '10')->value('id');
        $this->postJson(route('quedans.bulk-update'), ['ids' => [$week3], 'status' => 'bought', 'quedan_price_id' => $week10Price])->assertOk();
        // Week 4 quedan bought before sold prices were recorded: no price, so its bought_at date counts
        DB::table('quedans')->where('id', $week4)->update(['bought_at' => '2026-10-20 10:00:00']);

        $boughtIn = fn ($week) => array_column($this->getJson(route('registry.data', [
            'type' => 'quedan', 'crop_year' => '20262027', 'bought_week' => $week, 'page' => 1,
        ]))->assertOk()->json('data'), 'id');

        $this->assertSame([$week3], $boughtIn('10'));
        $this->assertSame([], $boughtIn('3'));
        $this->assertSame([$week4], $boughtIn('4'));

        // Week 10 has a price, so it is offered even before it starts
        $options = $this->getJson(route('registry.data', ['type' => 'quedan', 'crop_year' => '20262027', 'page' => 1]))
            ->assertOk()->json('extra.bought_weeks');
        $this->assertContains('10', array_column($options, 'week_no'));

        // The table shows each quedan's bought week: the price's week, or the bought_at week for an old quedan
        $rows = collect($this->getJson(route('registry.data', ['type' => 'quedan', 'crop_year' => '20262027', 'page' => 1]))
            ->assertOk()->json('data'))->keyBy('id');
        $this->assertSame('10', (string) $rows[$week3]['bought_week']);
        $this->assertSame('4', (string) $rows[$week4]['bought_week']);
        $this->assertEquals(1000, $rows[$week3]['sold_price']);

        // Back to pending: no longer bought in week 10
        $this->postJson(route('quedans.bulk-update'), ['ids' => [$week3], 'status' => 'pending'])->assertOk();
        $this->assertSame([], $boughtIn('10'));
    }

    public function test_old_five_column_fuel_file_still_uploads(): void
    {
        $this->uploadFuel("20262027,3,00123,DELA CRUZ JUAN,750\n")->assertOk()->assertJsonPath('fuel_deductions', 1);

        $deduction = PlanterDeduction::where('source', 'upload')->first();
        $this->assertEquals(750, (float) $deduction->amount);
        $this->assertNull($deduction->liters);
    }
}
