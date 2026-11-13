<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PlanterProfileSync;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A Consolidated upload adds its new planters to Planter Profiles, and no planter is ever added twice
 */
class PlanterProfileSyncTest extends TestCase
{
    private const AMOUNTS = ',0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0,0'; // ta_wt .. adj_amt after the name columns

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutMiddleware(\App\Http\Middleware\SystemLockMiddleware::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureSingleSession::class);

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('consolidated_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year')->nullable();
            $table->string('week_no')->nullable();
            $table->string('planter_code');
            $table->string('assn_code')->nullable();
            $table->string('planter_name');
            $table->string('assn_name')->nullable();
            foreach (['ta_wt', 'ta_amount', 'emi_wt', 'emi_amount', 'pat_wt', 'pat_amount', 'cci_fa_wt', 'cci_fa_amt',
                'cci_fb_wt', 'cci_fb_amt', 'cci_fc_wt', 'cci_fc_amt', 'fuel_issuance_amt', 'rental_amt',
                'underload_amt', 'mudpress_amt', 'adj_amt', 'total_summary'] as $column) {
                $table->decimal($column, 14, 2)->default(0);
            }
            $table->string('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('planter_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('planter_code')->unique();
            $table->string('planter_name');
            $table->string('status')->default('active');
            $table->string('crop_year')->nullable();
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

        $this->actingAs(new User(['fname' => 'Demo', 'role' => 'Administrator']));
    }

    private function upload(string $week, array $planters)
    {
        $csv = collect($planters)->map(fn ($name, $code) => "{$code},A1,\"{$name}\",ASSN" . self::AMOUNTS)->implode("\n") . "\n";

        return $this->postJson(route('upload.csv', ['type' => 'consolidated']), [
            'crop_year' => '20262027',
            'week_no' => $week,
            'file' => UploadedFile::fake()->createWithContent('consolidated.csv', $csv),
        ]);
    }

    private function profiles(): array
    {
        return DB::table('planter_profiles')->orderBy('planter_code')->pluck('planter_name', 'planter_code')->all();
    }

    public function test_consolidated_upload_adds_new_planters_to_profiles(): void
    {
        $this->upload('5', ['00123' => 'CRUZ, JUAN', '00456' => 'SANTOS, ANA'])
            ->assertOk()
            ->assertJsonPath('imported', 2)
            ->assertJsonPath('planters_added', 2);

        $this->assertSame(['00123' => 'CRUZ, JUAN', '00456' => 'SANTOS, ANA'], $this->profiles());
    }

    public function test_planters_already_in_profiles_are_not_added_again(): void
    {
        // Same planters under other spellings of the code: leading zeros, case and spacing
        DB::table('planter_profiles')->insert([
            ['planter_code' => '123', 'planter_name' => 'CRUZ, JUAN (OLD)', 'status' => 'active'],
            ['planter_code' => 'ab 12', 'planter_name' => 'REYES, LEA', 'status' => 'active'],
        ]);

        $this->upload('5', ['00123' => 'CRUZ, JUAN', 'AB12' => 'REYES, LEA', '00456' => 'SANTOS, ANA'])
            ->assertOk()
            ->assertJsonPath('planters_added', 1);
        // The next week's upload has the same planters: nothing new
        $this->upload('6', ['00123' => 'CRUZ, JUAN', '00456' => 'SANTOS, ANA'])
            ->assertOk()
            ->assertJsonPath('planters_added', 0);

        // Existing profiles keep their code and name; only SANTOS is new
        $this->assertSame(['00456' => 'SANTOS, ANA', '123' => 'CRUZ, JUAN (OLD)', 'ab 12' => 'REYES, LEA'], $this->profiles());
    }

    public function test_a_planter_listed_twice_in_one_upload_is_added_once(): void
    {
        $csv = "00789,A1,\"LIM, ROY\",ASSN" . self::AMOUNTS . "\n789,A1,\"LIM, ROY\",ASSN" . self::AMOUNTS . "\n";
        $this->postJson(route('upload.csv', ['type' => 'consolidated']), [
            'crop_year' => '20262027',
            'week_no' => '5',
            'file' => UploadedFile::fake()->createWithContent('consolidated.csv', $csv),
        ])->assertOk()->assertJsonPath('planters_added', 1);

        $this->assertCount(1, $this->profiles());
    }

    public function test_sync_planters_button_still_adds_from_every_week(): void
    {
        $this->upload('5', ['00123' => 'CRUZ, JUAN']);
        DB::table('planter_profiles')->delete();

        $result = app(PlanterProfileSync::class)->sync();

        $this->assertSame(1, $result['imported']);
        $this->assertSame(['00123' => 'CRUZ, JUAN'], $this->profiles());
        $this->assertSame(0, app(PlanterProfileSync::class)->sync()['imported']);
    }
}
