<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UploadHeaderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutMiddleware(\App\Http\Middleware\SystemLockMiddleware::class);

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('summary', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->string('planter_code');
            $table->string('planter_name');
            $table->decimal('net_cane', 12, 2);
            $table->decimal('net_amount', 12, 2);
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

    public function test_upload_skips_the_first_row_even_when_it_looks_numeric(): void
    {
        $csv = "20232024,1,111,Skip Me,10,100\n20232024,1,222,Import Me,20,200\n";

        $response = $this->postJson(route('upload.csv', ['type' => 'summary']), [
            'crop_year' => '20232024',
            'week_no' => '1',
            'file' => UploadedFile::fake()->createWithContent('summary.csv', $csv),
        ]);

        $response->assertOk()->assertJsonPath('imported', 1);
        $this->assertSame(['222'], DB::table('summary')->pluck('planter_code')->all());
    }

    public function test_header_only_csv_is_rejected(): void
    {
        $response = $this->postJson(route('upload.csv', ['type' => 'summary']), [
            'crop_year' => '20232024',
            'week_no' => '1',
            'file' => UploadedFile::fake()->createWithContent(
                'summary.csv',
                "crop_year,week_no,planter_code,planter_name,net_cane,net_amount\n"
            ),
        ]);

        $response->assertStatus(422)->assertJsonPath('error', 'The CSV has no data rows. Add records below the header and try again.');
        $this->assertSame(0, DB::table('summary')->count());
    }
}
