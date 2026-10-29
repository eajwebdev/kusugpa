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
