<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RegistryTinTest extends TestCase
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
        foreach (['quedans', 'molasses'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('crop_year');
                $table->string('week_no');
                $table->string('planter_code');
                $table->string('planter_name');
                $table->string('qdn_no')->nullable();
                $table->string('mc_no')->nullable();
                $table->string('tin_no')->nullable();
                $table->decimal('total_liens', 12, 3)->default(0);
                $table->decimal('sugar_lkg', 12, 3)->default(0);
                $table->decimal('labor_lkg', 12, 3)->default(0);
                $table->decimal('mol_net', 12, 3)->default(0);
                $table->string('status')->default('pending');
                $table->timestamps();
            });
        }

        $this->actingAs(new User(['fname' => 'Demo', 'role' => 'Administrator']));
    }

    private function quedan(array $values): int
    {
        return DB::table('quedans')->insertGetId($values + [
            'crop_year' => '20252026', 'week_no' => '3', 'planter_code' => '123', 'planter_name' => 'DELA CRUZ, JUAN',
        ]);
    }

    public function test_missing_tin_is_saved_on_the_planters_blank_rows_only(): void
    {
        $blank = $this->quedan(['tin_no' => null]);
        $nullText = $this->quedan(['tin_no' => 'NULL', 'week_no' => '4']);
        $empty = $this->quedan(['tin_no' => '  ', 'week_no' => '5']);
        $other = $this->quedan(['tin_no' => null, 'planter_code' => '999', 'planter_name' => 'OTHER']);
        DB::table('molasses')->insert(['crop_year' => '20252026', 'week_no' => '3', 'planter_code' => '123', 'planter_name' => 'DELA CRUZ, JUAN']);

        $this->postJson(route('registry.tin.update', ['type' => 'quedan', 'id' => $blank]), ['tin_no' => '123-456-789-000'])
            ->assertOk()
            ->assertJson(['tin_no' => '123-456-789-000', 'planter_code' => '123']);

        foreach ([$blank, $nullText, $empty] as $id) {
            $this->assertSame('123-456-789-000', DB::table('quedans')->where('id', $id)->value('tin_no'));
        }
        $this->assertNull(DB::table('quedans')->where('id', $other)->value('tin_no'));
        $this->assertNull(DB::table('molasses')->value('tin_no'), 'Molasses registry is updated from its own tab');
    }

    public function test_existing_tin_is_not_overwritten(): void
    {
        $id = $this->quedan(['tin_no' => '111-222-333']);

        $this->postJson(route('registry.tin.update', ['type' => 'quedan', 'id' => $id]), ['tin_no' => '999-999-999'])
            ->assertStatus(422);
        $this->assertSame('111-222-333', DB::table('quedans')->where('id', $id)->value('tin_no'));
    }

    public function test_tin_must_be_digits_and_dashes(): void
    {
        $id = $this->quedan(['tin_no' => null]);

        $this->postJson(route('registry.tin.update', ['type' => 'quedan', 'id' => $id]), ['tin_no' => 'abc'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tin_no');
    }

    public function test_molasses_tin_can_be_filled(): void
    {
        $id = DB::table('molasses')->insertGetId(['crop_year' => '20252026', 'week_no' => '3', 'planter_code' => '123', 'planter_name' => 'DELA CRUZ, JUAN']);

        $this->postJson(route('registry.tin.update', ['type' => 'molasses', 'id' => $id]), ['tin_no' => '123456789'])->assertOk();
        $this->assertSame('123456789', DB::table('molasses')->where('id', $id)->value('tin_no'));
    }

    public function test_registry_pdf_follows_the_crop_year_and_week_filter(): void
    {
        $this->quedan(['planter_name' => 'IN WEEK THREE']);
        $this->quedan(['planter_name' => 'IN WEEK FOUR', 'week_no' => '4']);

        $captured = null;
        view()->composer('pdf.ledger', function ($view) use (&$captured) {
            $captured = $view->getData();
        });
        $this->get(route('quedan-registry.export', ['crop_year' => '20252026', 'week_no' => '3', 'preview' => 1]))->assertOk();

        $this->assertSame(['IN WEEK THREE'], $captured['rows']->pluck('planter_name')->all());
    }
}
