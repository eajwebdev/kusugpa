<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TableFeed;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Tables load in batches as they scroll: every row exactly once, in a stable order
 */
class TableFeedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('quedans', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->string('qdn_no')->nullable();
            $table->string('tin_no')->nullable();
            $table->decimal('total_liens', 12, 3)->default(0);
            $table->decimal('sugar_lkg', 12, 3)->default(0);
            $table->decimal('labor_lkg', 12, 3)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        // 250 rows; many share a planter name, so the id tie-breaker decides their order
        DB::table('quedans')->insert(array_map(fn ($i) => [
            'crop_year' => $i <= 200 ? '20262027' : '20252026',
            'week_no' => (string) (($i % 12) + 1),
            'planter_code' => (string) $i,
            'planter_name' => 'PLANTER ' . chr(65 + $i % 5),
            'status' => $i % 3 === 0 ? 'bought' : 'pending',
        ], range(1, 250)));
    }

    private function batch(array $params)
    {
        $request = Request::create('/x', 'GET', $params);
        return TableFeed::respond($request, DB::table('quedans'), ['planter_name' => 'planter_name', 'week_no' => ['raw' => 'CAST(week_no AS UNSIGNED)']], [['planter_name', 'asc']])
            ->getData(true);
    }

    public function test_batches_cover_every_row_once_and_report_the_total_first(): void
    {
        $first = $this->batch(['page' => 1]);
        $this->assertCount(100, $first['data']);
        $this->assertTrue($first['has_more']);
        $this->assertSame(250, $first['total']);

        $second = $this->batch(['page' => 2]);
        $this->assertNull($second['total'], 'The total is only counted with the first batch');
        $third = $this->batch(['page' => 3]);
        $this->assertCount(50, $third['data']);
        $this->assertFalse($third['has_more']);

        $ids = collect([$first, $second, $third])->flatMap(fn ($b) => collect($b['data'])->pluck('id'));
        $this->assertSame(250, $ids->count());
        $this->assertSame(250, $ids->unique()->count());
    }

    public function test_sorting_only_uses_allowed_fields(): void
    {
        $byWeek = collect($this->batch(['page' => 1, 'sort' => 'week_no', 'dir' => 'desc'])['data'])->pluck('week_no')->map(fn ($w) => (int) $w);
        $this->assertSame(12, $byWeek->first(), 'Week numbers sort as numbers (12 before 9)');

        // An unknown field falls back to the default order instead of reaching the SQL
        $data = $this->batch(['page' => 1, 'sort' => 'id; DROP TABLE quedans', 'dir' => 'asc'])['data'];
        $this->assertSame('PLANTER A', $data[0]['planter_name']);
        $this->assertSame(250, DB::table('quedans')->count());
    }

    public function test_registry_endpoint_filters_in_the_database(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\SystemLockMiddleware::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleSession::class);
        $this->actingAs(new User(['fname' => 'Admin', 'role' => 'Administrator']));

        $response = $this->getJson(route('registry.data', ['type' => 'quedan', 'crop_year' => '20262027', 'status' => 'bought', 'page' => 1]))
            ->assertOk()->json();

        $this->assertSame(66, $response['total']); // ids 3, 6, ... 198 in crop year 20262027
        $this->assertTrue(collect($response['data'])->every(fn ($r) => $r['status'] === 'bought' && $r['crop_year'] === '20262027'));
        // Status counts for the buy pages cover the crop year whatever the status filter
        $this->assertSame(['total' => 200, 'bought' => 66, 'pending' => 134], $response['extra']['stats']);

        $search = $this->getJson(route('registry.data', ['type' => 'quedan', 'search' => 'PLANTER B', 'page' => 1]))->json();
        $this->assertSame(50, $search['total']);
    }

    private function signInToRegistry(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\SystemLockMiddleware::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleSession::class);
        $this->actingAs(new User(['fname' => 'Admin', 'role' => 'Administrator']));
        DB::table('quedans')->update(['total_liens' => 1.5, 'sugar_lkg' => 2, 'labor_lkg' => 0.25]);
    }

    private function ledgerData(array $params): array
    {
        $captured = null;
        view()->composer('pdf.ledger', function ($view) use (&$captured) {
            $captured = $view->getData();
        });
        $this->get(route('quedan-registry.export', $params))->assertOk();

        return $captured;
    }

    public function test_registry_totals_cover_every_filtered_row_not_just_the_loaded_batch(): void
    {
        $this->signInToRegistry();

        $first = $this->getJson(route('registry.data', ['type' => 'quedan', 'crop_year' => '20262027', 'page' => 1]))->assertOk()->json();
        $this->assertCount(100, $first['data']);
        $this->assertEquals(['total_liens' => 300, 'sugar_lkg' => 400, 'labor_lkg' => 50], $first['extra']['sums']);

        $search = $this->getJson(route('registry.data', ['type' => 'quedan', 'search' => 'PLANTER B', 'page' => 1]))->json();
        $this->assertEquals(100, $search['extra']['sums']['sugar_lkg']); // 50 rows

        $second = $this->getJson(route('registry.data', ['type' => 'quedan', 'crop_year' => '20262027', 'page' => 2]))->json();
        $this->assertSame([], $second['extra']);
    }

    public function test_registry_totals_separate_pending_from_the_other_statuses(): void
    {
        $this->signInToRegistry();
        DB::table('quedans')->where('id', 1)->update(['status' => 'rejected']); // was pending
        $feed = fn (array $params) => $this->getJson(route('registry.data', ['type' => 'quedan', 'crop_year' => '20262027', 'page' => 1] + $params))
            ->assertOk()->json();

        // 200 rows in the crop year at 2 LKg each: 66 bought, 133 pending, 1 rejected
        $this->assertEquals(400, $feed([])['extra']['sums']['sugar_lkg']);
        $this->assertEquals(266, $feed(['status' => 'pending'])['extra']['sums']['sugar_lkg']);
        $this->assertEquals(132, $feed(['status' => 'bought'])['extra']['sums']['sugar_lkg']);
        $this->assertEquals(2, $feed(['status' => 'rejected'])['extra']['sums']['sugar_lkg']);

        $pending = $feed(['status' => 'pending']);
        $this->assertSame(133, $pending['total']);
        $this->assertTrue(collect($pending['data'])->every(fn ($r) => $r['status'] === 'pending'));
    }

    public function test_registry_rows_carry_their_status_and_sort_by_it(): void
    {
        $this->signInToRegistry();
        $feed = fn (string $dir) => $this->getJson(route('registry.data', [
            'type' => 'quedan', 'crop_year' => '20262027', 'page' => 1, 'sort' => 'status', 'dir' => $dir,
        ]))->assertOk()->json('data');

        $this->assertSame('bought', $feed('asc')[0]['status']);
        $this->assertSame('pending', $feed('desc')[0]['status']);
    }

    public function test_selecting_all_stays_inside_the_chosen_status(): void
    {
        $this->signInToRegistry();

        $ids = $this->getJson(route('registry.data', [
            'type' => 'quedan', 'crop_year' => '20262027', 'status' => 'pending', 'page' => 1, 'all_ids' => 1,
        ]))->assertOk()->json('ids');

        $this->assertCount(134, $ids);
        $this->assertSame(0, DB::table('quedans')->whereIn('id', $ids)->where('status', '!=', 'pending')->count());
    }

    public function test_registry_pdf_follows_the_chosen_status_and_names_it(): void
    {
        $this->signInToRegistry();
        $period = ['crop_year' => '20252026', 'show_totals' => 1];
        $bought = DB::table('quedans')->where('crop_year', '20252026')->where('status', 'bought')->count();

        $data = $this->ledgerData($period + ['status' => 'bought']);
        $this->assertSame('Quedan Registry - Bought', $data['title']);
        $this->assertCount($bought, $data['rows']);
        $this->assertEqualsWithDelta($bought * 2, $data['totals']['sugar_lkg'], 0.001);

        $all = $this->ledgerData($period);
        $this->assertSame('Quedan Registry', $all['title']);
        $this->assertCount(50, $all['rows']);

        // Anything that is not a status is ignored rather than reaching the title or the query
        $this->assertSame('Quedan Registry', $this->ledgerData($period + ['status' => '<b>x</b>'])['title']);
    }

    public function test_molasses_registry_totals_its_net(): void
    {
        $this->signInToRegistry();
        Schema::create('molasses', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->string('tin_no')->nullable();
            $table->string('mc_no')->nullable();
            $table->decimal('mol_net', 12, 3)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        $row = ['crop_year' => '20262027', 'week_no' => '1', 'planter_code' => '1', 'planter_name' => 'A'];
        DB::table('molasses')->insert([$row + ['mol_net' => 1.25], $row + ['mol_net' => 2.5]]);

        $response = $this->getJson(route('registry.data', ['type' => 'molasses', 'crop_year' => '20262027', 'page' => 1]))->assertOk()->json();
        $this->assertEquals(['mol_net' => 3.75], $response['extra']['sums']);
    }

    public function test_registry_pdf_prints_totals_only_when_asked(): void
    {
        $this->signInToRegistry();
        $period = ['crop_year' => '20252026', 'week_no' => '1'];

        $plain = $this->ledgerData($period);
        $this->assertNull($plain['totals']);
        $this->assertStringNotContainsString('Total of all', view('pdf.ledger', $plain)->render());

        $rows = DB::table('quedans')->where($period)->count();
        $withTotals = $this->ledgerData($period + ['show_totals' => 1]);
        $html = view('pdf.ledger', $withTotals)->render();
        $this->assertEqualsWithDelta($rows * 2, $withTotals['totals']['sugar_lkg'], 0.001);
        $this->assertStringContainsString('Total of all ' . $rows . ' records', $html);
        $this->assertStringContainsString(number_format($rows * 1.5, 2), $html);
        $this->assertStringContainsString(number_format($rows * 2, 3), $html);
    }

    public function test_a_split_registry_pdf_prints_the_grand_total_on_its_last_part(): void
    {
        $this->signInToRegistry();
        DB::table('quedans')->insert(array_map(fn ($i) => [
            'crop_year' => '20262027', 'week_no' => '20', 'planter_code' => (string) $i, 'planter_name' => 'PLANTER',
            'total_liens' => 1.5, 'sugar_lkg' => 2, 'labor_lkg' => 0.25,
        ], range(1, 600)));

        $last = $this->ledgerData(['show_totals' => 1, 'part' => 2]); // 850 rows, 800 per part
        $this->assertCount(50, $last['rows']);
        $this->assertEqualsWithDelta(1700, $last['totals']['sugar_lkg'], 0.001);
        $this->assertStringContainsString('Total of all 850 records', view('pdf.ledger', $last)->render());
    }

    public function test_totals_row_adds_no_pdf_page(): void
    {
        $columns = [
            ['key' => 'name', 'label' => 'Planter Name'],
            ['key' => 'sugar_lkg', 'label' => 'Sugar Lkg', 'decimals' => 3, 'total' => true],
        ];
        $pages = function (int $count, ?array $totals) use ($columns) {
            $rows = collect(range(1, $count))->map(fn ($i) => (object) ['name' => 'PLANTER ' . $i, 'sugar_lkg' => 1]);
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.ledger', [
                'title' => 'Quedan Registry', 'rows' => $rows, 'totalRows' => $count, 'columns' => $columns,
                'generatedDate' => 'today', 'totals' => $totals,
            ])->setPaper('a4', 'landscape');
            $pdf->output();

            return $pdf->getDomPDF()->getCanvas()->get_page_count();
        };

        foreach ([1, 30, 60] as $count) {
            $this->assertSame($pages($count, null), $pages($count, ['sugar_lkg' => $count]), "$count rows");
        }
    }

    public function test_registry_can_select_every_filtered_id_beyond_the_loaded_batch(): void
    {
        $this->withoutMiddleware(\App\Http\Middleware\SystemLockMiddleware::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleSession::class);
        $this->actingAs(new User(['fname' => 'Admin', 'role' => 'Administrator']));

        $response = $this->getJson(route('registry.data', [
            'type' => 'quedan',
            'crop_year' => '20262027',
            'page' => 1,
            'all_ids' => 1,
        ]))->assertOk()->json();

        $this->assertSame(200, $response['total']);
        $this->assertCount(200, $response['ids']);
        $this->assertSame(range(1, 200), $response['ids']);
    }
}
