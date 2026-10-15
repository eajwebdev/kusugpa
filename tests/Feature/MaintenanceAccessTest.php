<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MaintenanceAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
        });

        SystemSetting::set('maintenance_mode', '1', 'boolean');
        SystemSetting::set('maintenance_message', 'Maintenance in progress.');

        Route::middleware(['web', 'login_auth'])
            ->get('/maintenance-probe', fn () => response('Portal available'));
        Route::middleware(['web', 'login_auth', 'admin.role'])
            ->get('/system/maintenance-probe', fn () => response('Admin route available'));
    }

    public function test_administrator_can_use_portal_and_system_routes_during_maintenance(): void
    {
        $this->actingAs(new User(['role' => 'Administrator']));

        $this->get('/maintenance-probe')->assertOk()->assertSee('Portal available');
        $this->get('/system/maintenance-probe')->assertOk()->assertSee('Admin route available');
    }

    /** @dataProvider blockedRoles */
    public function test_other_roles_see_maintenance_splash_screen(string $role): void
    {
        $this->actingAs(new User(['role' => $role]));

        $this->get('/maintenance-probe')->assertStatus(503)->assertSee('Maintenance in progress.');
        $this->get('/system/maintenance-probe')->assertStatus(503)->assertSee('Maintenance in progress.');
    }

    public function test_ajax_requests_receive_a_json_maintenance_response(): void
    {
        $this->actingAs(new User(['role' => 'super_admin']));

        $this->getJson('/maintenance-probe')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Maintenance in progress.');
    }

    public function blockedRoles(): array
    {
        return [
            'super admin' => ['super_admin'],
            'manager' => ['manager'],
            'loan officer' => ['loan_officer'],
            'user' => ['User'],
            'viewer' => ['Viewer'],
        ];
    }
}
