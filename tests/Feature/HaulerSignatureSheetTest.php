<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HaulerSignatureSheetTest extends TestCase
{
    private array $haulers = [];

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
        Schema::create('planter_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('planter_code')->unique();
            $table->string('planter_name');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        foreach ([
            '2026_10_20_000000_create_hauling_liquidity_tables.php',
            '2026_10_20_000001_add_check_no_to_hauling_liquidations.php',
            '2026_10_20_000002_add_planter_code_to_hauling_liquidations.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        foreach (['CANLAMAY HAULER TLS LIQ.', 'INAPOY HAULER TLS LIQ.'] as $name) {
            $this->haulers[$name] = DB::table('haulers')->insertGetId(['name' => $name, 'is_active' => true]);
        }

        $this->actingAs(new User(['fname' => 'Demo', 'role' => 'Administrator']));
    }

    private function entry(string $hauler, string $payee, string $week, array $values = []): void
    {
        DB::table('hauling_liquidations')->insert($values + [
            'hauler_id' => $this->haulers[$hauler],
            'crop_year' => '20262027',
            'week_no' => $week,
            'week_ending' => '2026-10-18',
            'payee_name' => $payee,
            'truck_code' => 'ABC123',
            'check_no' => '213497',
            'tonnage' => 10.5,
            'rate_per_ton' => 200,
            'gross_amount' => 2100,
            'total_add' => 0,
            'total_less' => 100,
            'net_amount' => 2000,
        ]);
    }

    public function test_sheet_lists_entries_per_hauler_location_for_the_week_range(): void
    {
        $this->entry('CANLAMAY HAULER TLS LIQ.', 'ZAMORA, JUAN', '3');
        $this->entry('CANLAMAY HAULER TLS LIQ.', 'ABAD, PEDRO', '4', ['check_no' => '213498', 'truck_code' => 'XYZ789']);
        $this->entry('INAPOY HAULER TLS LIQ.', 'CRUZ, ANA', '3');
        $this->entry('INAPOY HAULER TLS LIQ.', 'OUTSIDE RANGE', '9');
        $this->entry('INAPOY HAULER TLS LIQ.', 'NOTHING TO RECEIVE', '3', ['net_amount' => 0]);

        $html = view('pdf.hauler-signature-sheet', $this->buildSheets(['week_from' => '3', 'week_to' => '4']))->render();

        $this->assertStringContainsString('CANLAMAY HAULER TLS LIQ.', $html);
        $this->assertStringContainsString('INAPOY HAULER TLS LIQ.', $html);
        $this->assertStringContainsString('213498', $html);
        $this->assertStringContainsString('XYZ789', $html);
        $this->assertStringNotContainsString('OUTSIDE RANGE', $html);
        $this->assertStringNotContainsString('NOTHING TO RECEIVE', $html);
        // Sorted by hauler name within a location
        $this->assertLessThan(strpos($html, 'ZAMORA, JUAN'), strpos($html, 'ABAD, PEDRO'));
    }

    public function test_sheet_can_be_limited_to_one_location(): void
    {
        $this->entry('CANLAMAY HAULER TLS LIQ.', 'ZAMORA, JUAN', '3');
        $this->entry('INAPOY HAULER TLS LIQ.', 'CRUZ, ANA', '3');

        $html = view('pdf.hauler-signature-sheet', $this->buildSheets([
            'week_from' => '3', 'week_to' => '3', 'hauler_id' => [$this->haulers['INAPOY HAULER TLS LIQ.']],
        ]))->render();

        $this->assertStringContainsString('CRUZ, ANA', $html);
        $this->assertStringNotContainsString('ZAMORA, JUAN', $html);
    }

    public function test_payee_set_from_the_table_shows_on_the_sheet(): void
    {
        $this->entry('INAPOY HAULER TLS LIQ.', '', '3', ['truck_code' => 'ABK3654']);
        $id = DB::table('hauling_liquidations')->value('id');

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->postJson(route('hauling-liquidity.entries.payee', $id), ['payee_name' => '  REYES,   PEDRO '])
            ->assertOk()
            ->assertJsonPath('entry.payee_name', 'REYES, PEDRO');

        $html = view('pdf.hauler-signature-sheet', $this->buildSheets(['week_from' => '3', 'week_to' => '3']))->render();
        $this->assertStringContainsString('REYES, PEDRO', $html);
        $this->assertStringNotContainsString('no hauler\'s name yet', $html);
    }

    public function test_missing_payees_print_a_line_and_a_note(): void
    {
        $this->entry('INAPOY HAULER TLS LIQ.', '', '3', ['truck_code' => 'ABK3654']);
        $this->entry('INAPOY HAULER TLS LIQ.', 'CRUZ, ANA', '3');

        $html = view('pdf.hauler-signature-sheet', $this->buildSheets(['week_from' => '3', 'week_to' => '3']))->render();
        $this->assertStringContainsString('1 of 2 line(s) have no hauler\'s name yet', $html);
    }

    public function test_pdf_endpoint_streams_a_pdf(): void
    {
        $this->entry('CANLAMAY HAULER TLS LIQ.', 'ZAMORA, JUAN', '3');

        $this->get(route('hauler-signature-report.pdf', ['crop_year' => '20262027', 'week_from' => '3', 'week_to' => '3']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    /** Capture the data the controller hands to the PDF view */
    private function buildSheets(array $query): array
    {
        $captured = null;
        view()->composer('pdf.hauler-signature-sheet', function ($view) use (&$captured) {
            $captured = $view->getData();
        });
        $this->get(route('hauler-signature-report.pdf', $query + ['crop_year' => '20262027']))->assertOk();

        return $captured;
    }
}
