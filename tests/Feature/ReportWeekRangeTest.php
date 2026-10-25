<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReportWeekRangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\SystemLockMiddleware::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleSession::class);

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });
        Schema::create('planter_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('planter_code');
            $table->string('planter_name');
            $table->timestamps();
        });
        Schema::create('consolidated_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->string('ta_wt')->nullable();
            $table->timestamps();
        });
        Schema::create('quedans', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->decimal('sugar_lkg', 12, 2)->default(0);
            $table->decimal('labor_lkg', 12, 2)->default(0);
            $table->timestamps();
        });
        Schema::create('molasses', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->decimal('mol_net', 12, 3)->default(0);
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

        // Weeks 1, 2 and 10 of one crop year; week 10 must sort after week 2
        foreach (['1' => [10, 100, 5, 2], '2' => [20, 200, 10, 4], '10' => [40, 400, 20, 8]] as $week => [$tons, $lkg, $labor, $mol]) {
            $row = ['crop_year' => '20252026', 'week_no' => $week, 'planter_code' => '00123', 'planter_name' => 'DELA CRUZ, JUAN'];
            DB::table('consolidated_uploads')->insert($row + ['ta_wt' => $tons]);
            DB::table('quedans')->insert($row + ['sugar_lkg' => $lkg, 'labor_lkg' => $labor]);
            DB::table('molasses')->insert($row + ['mol_net' => $mol]);
            DB::table('trucking_allowance')->insert($row + ['net_cane' => $tons, 'ta_amount' => $tons * 10]);
        }

        $this->actingAs(new User(['fname' => 'Demo', 'role' => 'Administrator']));
    }

    public function test_certification_totals_follow_the_week_range(): void
    {
        $rows = $this->getJson(route('certification-report.planters', [
            'crop_year' => '20252026', 'week_from' => '2', 'week_to' => '10',
        ]))->assertOk()->json('data');

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(60, $rows[0]['net_tons'], 0.001);
        $this->assertEqualsWithDelta(570, $rows[0]['net_lkg'], 0.001); // (200 - 10) + (400 - 20)
        $this->assertEqualsWithDelta(12, $rows[0]['net_mol'], 0.001);
        $this->assertArrayNotHasKey('gross_lkg', $rows[0]);
    }

    public function test_certification_without_weeks_covers_the_whole_crop_year(): void
    {
        $rows = $this->getJson(route('certification-report.planters', ['crop_year' => '20252026']))->json('data');

        $this->assertEqualsWithDelta(70, $rows[0]['net_tons'], 0.001);
    }

    public function test_certification_pdf_has_no_gross_sugar_and_states_the_weeks(): void
    {
        $captured = null;
        view()->composer('pdf.certification', function ($view) use (&$captured) {
            $captured = $view->getData();
        });

        $this->get(route('certification-report.pdf', [
            'crop_year' => '20252026', 'week_from' => '1', 'week_to' => '2', 'all' => 1,
        ]))->assertOk();

        $html = view('pdf.certification', $captured)->render();
        $this->assertStringNotContainsString('GROSS', $html);
        $this->assertStringContainsString('for Week 1 to Week 2', $html);
        $this->assertStringContainsString('30.00', $html); // net tons of weeks 1-2
    }

    public function test_trucking_allowance_filters_by_crop_year_and_week_range(): void
    {
        $data = $this->getJson(route('trucking-allowance-report', [
            'crop_year' => '20252026', 'week_from' => '2', 'week_to' => '10',
        ]))->assertOk()->json();

        $this->assertSame(2, $data['total']);
        $this->assertEqualsCanonicalizing(['2', '10'], array_map('strval', array_column($data['data'], 'week_no')));
    }
}
