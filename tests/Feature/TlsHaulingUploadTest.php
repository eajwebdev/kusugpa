<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TlsHaulingUploadTest extends TestCase
{
    private const HEADER = 'reference_no,planter_code,planter_name,assn_code,assn_name,vehicle_type,plate_no,tare_weight,gross_cane_weight,net_cane_wt,date_milled,trans_code,description,tdif_rate';

    // Rows from a real weekly TLS export: padded cells, an ANSI "Ñ", and a plate/tare that Excel turned into dates
    private const ROWS = [
        "4663230,29626,KUSUG PA                                ,15,KUSUG PA        ,T    ,RBR479    ,42.42,7.48,7.256,47:00.0,25,CAUAYAN TRANSLOADING        ,200",
        "4673318,42878,\"GEMOYA, EMILY                           \",15,KUSUG PA,T    ,RBR479    ,39.25,10.65,10.33,17:00.0,00:00.0,CAUAYAN TRANSLOADING   ,200",
        "4673312,42878,\"GEMOYA, EMILY\",15,KUSUG PA,T,RKK626,39.17,10.53,10.319,19:00.0,00:00.0,CAUAYAN TRANSLOADING,200",
        "4673317,42878,\"GEMOYA, EMILY\",15,KUSUG PA,T,RJG480,41.29,7.75,7.595,17:00.0,00:00.0,CAUAYAN TRANSLOADING,200",
        "4673319,42878,\"GEMOYA, EMILY\",15,KUSUG PA,T,RJG480    ,59.61,6.57,6.373,20:00.0,00:00.0,CAUAYAN TRANSLOADING,200",
        "4714343,52578,\"SUSPE\xD1E, MARILYN CALVARIO\",2,KABILOG-PA,T,060810BC  ,31.55,13.75,13.337,46:00.0,00:00.0,CANLAMAY TRANSLOADING,200",
        "4670801,52639,\"SABIO, BERNADETH TAMONAN\",15,KUSUG PA,T,Jan-38,Jan-00,13.9,13.483,35:00.0,00:00.0,CANLAMAY TRANSLOADING,200",
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
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
        Schema::create('week_no', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->dateTime('week_start_date')->nullable();
            $table->dateTime('week_end_date')->nullable();
            $table->string('user_id')->nullable();
            $table->timestamps();
        });
        Schema::create('planter_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('planter_code')->unique();
            $table->string('planter_name');
            $table->string('status')->default('active');
            $table->timestamps();
        });
        Schema::create('fuel_allowance', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->decimal('fuel_amount', 10, 2)->default(0);
            $table->string('user_id');
            $table->timestamps();
        });
        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->string('ca_number')->unique();
            $table->string('planter_code');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        Schema::create('cash_advance_deductions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cash_advance_id');
            $table->string('crop_year');
            $table->string('week_no');
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });

        // The real hauling migrations, including the starter "ISIO-CAUAYAN HAULER TLS LIQ." hauler
        foreach ([
            '2026_10_20_000000_create_hauling_liquidity_tables.php',
            '2026_10_20_000001_add_check_no_to_hauling_liquidations.php',
            '2026_10_20_000002_add_planter_code_to_hauling_liquidations.php',
            '2026_10_21_000000_create_tls_hauling_uploads_table.php',
        ] as $migration) {
            (require database_path('migrations/' . $migration))->up();
        }

        DB::table('week_no')->insert(['crop_year' => '20262027', 'week_no' => '3', 'week_start_date' => '2026-10-12', 'week_end_date' => '2026-10-18']);
        DB::table('planter_profiles')->insert([
            ['planter_code' => '29626', 'planter_name' => 'KUSUG PA'],
            ['planter_code' => '042878', 'planter_name' => 'GEMOYA, EMILY'], // zero-padded: must still match 42878
            ['planter_code' => '52578', 'planter_name' => 'SUSPEÑE, MARILYN CALVARIO'],
        ]);
        DB::table('fuel_allowance')->insert(['crop_year' => '20262027', 'week_no' => '3', 'planter_code' => '042878', 'planter_name' => 'GEMOYA, EMILY', 'fuel_amount' => 500, 'user_id' => '1']);
        $caId = DB::table('cash_advances')->insertGetId(['ca_number' => 'CA-0001', 'planter_code' => '042878', 'status' => 'approved']);
        DB::table('cash_advance_deductions')->insert(['cash_advance_id' => $caId, 'crop_year' => '20262027', 'week_no' => '3', 'amount' => 300]);

        $this->actingAs(new User(['fname' => 'Demo', 'role' => 'Administrator']));
    }

    private function upload(string $csv, string $weekNo = '3')
    {
        return $this->postJson(route('upload.csv', ['type' => 'tls']), [
            'crop_year' => '20262027',
            'week_no' => $weekNo,
            'file' => UploadedFile::fake()->createWithContent('tls_wk3.csv', $csv),
        ]);
    }

    /** @dataProvider withAndWithoutHeader */
    public function test_upload_creates_one_liquidation_per_truck(bool $withHeader): void
    {
        $csv = ($withHeader ? self::HEADER . "\r\n" : '') . implode("\r\n", self::ROWS) . "\r\n";

        $response = $this->upload($csv);

        // Trucks: CAUAYAN RBR479, RKK626, RJG480 and CANLAMAY 060810BC, JAN-38
        $response->assertOk()
            ->assertJsonPath('imported', 7)
            ->assertJsonPath('errors', 0)
            ->assertJsonPath('liquidations', 5);
        $this->assertStringContainsString('5 truck(s) need a payee', $response->json('message'));
        $this->assertStringContainsString('Check plate no. JAN-38', $response->json('message'));

        // Every uploaded row is linked to the entry generated from it
        $this->assertSame(0, DB::table('tls_hauling_uploads')->whereNull('hauling_liquidation_id')->count());
        $this->assertSame('SUSPEÑE, MARILYN CALVARIO', DB::table('tls_hauling_uploads')->where('planter_code', '52578')->value('planter_name'));

        // CAUAYAN rows go to the existing hauler; CANLAMAY gets a new one
        $cauayan = DB::table('haulers')->where('name', 'ISIO-CAUAYAN HAULER TLS LIQ.')->value('id');
        $canlamay = DB::table('haulers')->where('name', 'CANLAMAY HAULER TLS LIQ.')->value('id');
        $this->assertNotNull($canlamay);
        $this->assertSame(3, DB::table('hauling_liquidations')->where('hauler_id', $cauayan)->count());
        $this->assertSame(2, DB::table('hauling_liquidations')->where('hauler_id', $canlamay)->count());

        // RBR479 carried two planters' tickets: one slip, 7.256 + 10.33 = 17.586 t
        $rbr = DB::table('hauling_liquidations')->where('truck_code', 'RBR479')->first();
        $this->assertSame(2, DB::table('tls_hauling_uploads')->where('hauling_liquidation_id', $rbr->id)->count());
        $this->assertEqualsWithDelta(17.586, (float) $rbr->tonnage, 0.0001);
        $this->assertSame('2026-10-18', substr((string) $rbr->week_ending, 0, 10));

        // A truck seen for the first time waits for its payee; rate falls back to the TDIF rate
        $this->assertNull($rbr->planter_code);
        $this->assertSame('', $rbr->payee_name);
        $this->assertEqualsWithDelta(200, (float) $rbr->rate_per_ton, 0.001);
        $this->assertEqualsWithDelta(3517.20, (float) $rbr->gross_amount, 0.001);
        $this->assertEqualsWithDelta(0, (float) $rbr->total_less, 0.001);

        // Each slip carries the same ADD/LESS rows as the manual entry form
        $this->assertSame(
            ['Adjustment', 'EWT', 'MIS.', 'Fuel Account', 'Cash Adv.', 'Cable Sling/Tire/Battery'],
            DB::table('hauling_liquidation_items')->where('hauling_liquidation_id', $rbr->id)->orderBy('sort_order')->pluck('description')->all()
        );
    }

    public function test_truck_keeps_its_payee_and_rate_from_an_earlier_week(): void
    {
        // Week 2: RJG480 was paid to GEMOYA at 340 per ton
        DB::table('week_no')->insert(['crop_year' => '20262027', 'week_no' => '2', 'week_start_date' => '2026-10-05', 'week_end_date' => '2026-10-11']);
        $hauler = DB::table('haulers')->where('name', 'ISIO-CAUAYAN HAULER TLS LIQ.')->value('id');
        DB::table('hauling_liquidations')->insert([
            'hauler_id' => $hauler, 'crop_year' => '20262027', 'week_no' => '2', 'week_ending' => '2026-10-11',
            'planter_code' => '042878', 'payee_name' => 'GEMOYA, EMILY', 'truck_code' => 'RJG480',
            'tonnage' => 10, 'rate_per_ton' => 340, 'gross_amount' => 3400, 'net_amount' => 3400,
        ]);

        $this->upload(implode("\n", self::ROWS))->assertOk()->assertJsonPath('liquidations', 5);

        $rjg = DB::table('hauling_liquidations')->where('week_no', '3')->where('truck_code', 'RJG480')->first();
        $this->assertSame('042878', $rjg->planter_code);
        $this->assertSame('GEMOYA, EMILY', $rjg->payee_name);
        $this->assertEqualsWithDelta(13.968, (float) $rjg->tonnage, 0.0001);
        $this->assertEqualsWithDelta(340, (float) $rjg->rate_per_ton, 0.001);
        $this->assertEqualsWithDelta(4749.12, (float) $rjg->gross_amount, 0.001);
        // GEMOYA's week-3 fuel 500 + cash advance 300
        $this->assertEqualsWithDelta(800, (float) $rjg->total_less, 0.001);
        $this->assertEqualsWithDelta(3949.12, (float) $rjg->net_amount, 0.001);
    }

    public function test_regenerate_rebuilds_untouched_entries_and_keeps_edited_ones(): void
    {
        $this->upload(implode("\n", self::ROWS))->assertOk();
        // One slip was worked on: it gets a CV no.
        $kept = DB::table('hauling_liquidations')->where('truck_code', 'RKK626')->value('id');
        DB::table('hauling_liquidations')->where('id', $kept)->update(['cv_no' => 'CV-1']);

        $result = DB::transaction(fn () => app(\App\Services\TlsHaulingLiquidationGenerator::class)->regenerate('20262027', '3', null));

        $this->assertSame(4, $result['removed']);
        $this->assertSame(1, $result['kept']);
        $this->assertSame(4, $result['entries']);
        $this->assertSame(5, DB::table('hauling_liquidations')->count());
        $this->assertSame('CV-1', DB::table('hauling_liquidations')->where('id', $kept)->value('cv_no'));
        $this->assertSame(0, DB::table('tls_hauling_uploads')->whereNull('hauling_liquidation_id')->count());
    }

    public function withAndWithoutHeader(): array
    {
        return ['with header' => [true], 'without header' => [false]];
    }

    public function test_same_week_cannot_be_uploaded_twice(): void
    {
        $csv = implode("\n", self::ROWS);
        $this->upload($csv)->assertOk();

        $this->upload($csv)->assertStatus(400);
        $this->assertSame(7, DB::table('tls_hauling_uploads')->count());
        $this->assertSame(5, DB::table('hauling_liquidations')->count());
    }

    public function test_week_must_exist_in_crop_and_week_management(): void
    {
        $response = $this->upload(implode("\n", self::ROWS), '9');

        $response->assertStatus(422);
        $this->assertStringContainsString('Crop & Week Management', $response->json('error'));
        $this->assertSame(0, DB::table('tls_hauling_uploads')->count());
        $this->assertSame(0, DB::table('hauling_liquidations')->count());
    }
}
