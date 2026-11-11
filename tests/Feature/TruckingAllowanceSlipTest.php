<?php

namespace Tests\Feature;

use App\Http\Controllers\ConsolidatedReportController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Trucking Allowance slip of one consolidated (Trucking Allowance) row, opened from the report table.
 */
class TruckingAllowanceSlipTest extends TestCase
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
        Schema::create('consolidated_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            foreach (['ta_wt', 'emi_wt', 'pat_wt', 'cci_fa_wt', 'cci_fb_wt', 'cci_fc_wt'] as $column) {
                $table->decimal($column, 12, 3)->default(0);
            }
            foreach (['ta_amount', 'emi_amount', 'pat_amount', 'cci_fa_amt', 'cci_fb_amt', 'cci_fc_amt', 'fuel_issuance_amt',
                'rental_amt', 'underload_amt', 'mudpress_amt', 'adj_amt', 'total_summary'] as $column) {
                $table->decimal($column, 12, 2)->default(0);
            }
            $table->timestamps();
        });

        $this->actingAs(new User(['fname' => 'Demo', 'role' => 'Administrator']));
    }

    public function test_slip_opens_as_a_pdf_for_the_row(): void
    {
        $id = DB::table('consolidated_uploads')->insertGetId([
            'crop_year' => '20262027', 'week_no' => '5', 'planter_code' => '00123', 'planter_name' => 'ABELLO, DONATO JR. MARIANO',
            'ta_wt' => 8.934, 'ta_amount' => 2903.55, 'cci_fa_amt' => 100, 'mudpress_amt' => 50,
            'fuel_issuance_amt' => 200, 'rental_amt' => 25, 'underload_amt' => 10, 'total_summary' => 2818.55,
        ]);

        $response = $this->get(route('consolidated-report.slip', $id))->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->get(route('consolidated-report.slip', 999))->assertNotFound();
    }

    public function test_checked_rows_print_one_slip_per_page(): void
    {
        $ids = [];
        foreach (['SANTOS, MARIA', 'ABELLO, DONATO', 'CRUZ, JUAN'] as $i => $name) {
            $ids[] = DB::table('consolidated_uploads')->insertGetId([
                'crop_year' => '20262027', 'week_no' => '5', 'planter_code' => '0010' . $i, 'planter_name' => $name,
                'ta_wt' => 10, 'ta_amount' => 3250,
            ]);
        }

        $response = $this->get(route('consolidated-report.slips', ['ids' => implode(',', $ids)]))->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
        // One page per checked row
        $this->assertSame(3, preg_match_all('/\/Type\s*\/Page[^s]/', $response->getContent()));

        $this->get(route('consolidated-report.slips', ['ids' => '']))->assertStatus(422);
        $this->get(route('consolidated-report.slips', ['ids' => '998,999']))->assertNotFound();
    }

    public function test_amount_in_words_reads_like_the_printed_slip(): void
    {
        $this->assertSame('TWO THOUSANDS NINE HUNDRED THREE & 55 CENTAVOS', ConsolidatedReportController::slipWords(2903.55));
        $this->assertSame('ONE THOUSAND & 00 CENTAVOS', ConsolidatedReportController::slipWords(1000));
        $this->assertSame('ZERO & 05 CENTAVOS', ConsolidatedReportController::slipWords(0.05));
        $this->assertSame('TWELVE THOUSANDS THREE HUNDRED FORTY FIVE & 60 CENTAVOS', ConsolidatedReportController::slipWords(12345.6));
    }

    public function test_slip_needs_the_trucking_allowance_report_permission(): void
    {
        $this->assertSame('view-consolidated-report', \App\Support\NavigationPermissions::requiredForRoute('consolidated-report.slip'));
    }
}
