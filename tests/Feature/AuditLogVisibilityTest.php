<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSingleSession;
use App\Http\Middleware\SystemLockMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\AuditLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class AuditLogVisibilityTest extends TestCase
{
    private array $users = [];
    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->withoutMiddleware(SystemLockMiddleware::class);
        $this->withoutMiddleware(EnsureSingleSession::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fname');
            $table->string('lname');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });
        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('permission_id');
            $table->timestamps();
        });
        Schema::create('crop_year', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->timestamps();
        });
        // The audit log page lists crop year weeks for its date filter
        Schema::create('week_no', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->string('week_no');
            $table->dateTime('week_start_date')->nullable();
            $table->dateTime('week_end_date')->nullable();
            $table->timestamps();
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable();
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

        foreach ([
            'admin' => 'Administrator',
            'super' => 'super_admin',
            'other_super' => 'super_admin',
            'manager' => 'manager',
            'other_manager' => 'manager',
            'officer' => 'loan_officer',
            'user' => 'User',
            'viewer' => 'Viewer',
        ] as $key => $role) {
            $this->users[$key] = User::create([
                'fname' => $key,
                'lname' => 'Account',
                'username' => $key,
                'password' => 'unused',
                'role' => $role,
            ]);
            $this->logs[$key] = AuditLog::create([
                'user_id' => $this->users[$key]->id,
                'user_name' => $key,
                'user_role' => $role,
                'action' => 'view',
                'module' => $key,
                'description' => $key . ' activity',
            ]);
        }

        $this->logs['system'] = AuditLog::create([
            'user_name' => 'System', 'user_role' => 'System',
            'action' => 'error', 'module' => 'system', 'description' => 'system activity',
        ]);
        $this->logs['deleted_viewer'] = AuditLog::create([
            'user_name' => 'Former viewer', 'user_role' => 'Viewer',
            'action' => 'view', 'module' => 'deleted_viewer', 'description' => 'former viewer activity',
        ]);
    }

    /** @dataProvider visibleRoles */
    public function test_each_role_loads_only_allowed_logs(string $role, array $expected): void
    {
        $this->actingAs($this->users[$role]);

        $response = $this->getJson('/audit-logs/load-more')->assertOk();
        $actual = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $expectedIds = collect($expected)->map(fn ($key) => $this->logs[$key]->id)->sort()->values()->all();

        $this->assertSame($expectedIds, $actual);
        $response->assertJsonPath('total', count($expected));
    }

    public function visibleRoles(): array
    {
        return [
            'administrator' => ['admin', ['admin', 'super', 'other_super', 'manager', 'other_manager', 'officer', 'user', 'viewer', 'system', 'deleted_viewer']],
            'super admin' => ['super', ['super', 'manager', 'other_manager', 'officer', 'user', 'viewer', 'deleted_viewer']],
            'manager' => ['manager', ['manager', 'officer', 'user', 'viewer', 'deleted_viewer']],
            'loan officer' => ['officer', ['officer']],
            'user' => ['user', ['user']],
            'viewer' => ['viewer', ['viewer']],
        ];
    }

    public function test_list_metadata_and_statistics_exclude_other_users(): void
    {
        $this->actingAs($this->users['viewer']);

        $this->get('/audit-logs')->assertOk()
            ->assertViewHas('logs', fn ($logs) => $logs->total() === 1 && $logs->items()[0]->id === $this->logs['viewer']->id)
            ->assertViewHas('modules', fn ($modules) => $modules->all() === ['viewer'])
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1 && $stats['errors'] === 0)
            ->assertSee('Your account activity.')
            ->assertSee('My Audit Logs');
    }

    public function test_log_details_are_scoped_even_when_id_is_guessed(): void
    {
        $this->actingAs($this->users['manager']);

        $this->getJson('/audit-logs/' . $this->logs['user']->id)->assertOk();
        $this->getJson('/audit-logs/' . $this->logs['other_manager']->id)->assertNotFound();
        $this->getJson('/audit-logs/' . $this->logs['super']->id)->assertNotFound();
        $this->getJson('/audit-logs/' . $this->logs['admin']->id)->assertNotFound();

        $this->actingAs($this->users['super']);
        $this->getJson('/audit-logs/' . $this->logs['manager']->id)->assertOk();
        $this->getJson('/audit-logs/' . $this->logs['officer']->id)->assertOk();
        $this->getJson('/audit-logs/' . $this->logs['other_super']->id)->assertNotFound();
        $this->getJson('/audit-logs/' . $this->logs['admin']->id)->assertNotFound();
    }

    public function test_export_contains_only_visible_logs(): void
    {
        $this->actingAs($this->users['viewer']);
        $ids = [];
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('PDF'));
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use (&$ids) {
            $ids = $data['logs']->pluck('id')->all();
            return $view === 'pdf.audit-logs';
        })->andReturn($pdf);

        $this->get('/audit-logs/export/pdf')->assertOk();
        $this->assertSame([$this->logs['viewer']->id], $ids);
    }

    public function test_only_administrator_can_clear_audit_logs(): void
    {
        foreach (['super', 'manager', 'officer', 'user', 'viewer'] as $role) {
            $this->actingAs($this->users[$role]);
            $this->postJson('/audit-logs/clear', ['days' => 30])->assertForbidden();
        }

        $this->assertSame(10, AuditLog::count());
        $this->actingAs($this->users['admin']);
        $this->postJson('/audit-logs/clear', ['days' => 0])->assertStatus(422);
        // The last 90 days are always kept
        $this->postJson('/audit-logs/clear', ['days' => 30])->assertStatus(422);

        DB::table('audit_logs')->where('id', $this->logs['system']->id)
            ->update(['created_at' => now()->subDays(91)]);
        $this->postJson('/audit-logs/clear', ['days' => 90])
            ->assertOk()
            ->assertJsonPath('message', 'Cleared 1 audit logs older than 90 days.');
        // One removed, and the clear itself recorded
        $this->assertSame(10, AuditLog::count());
        $this->assertSame(1, AuditLog::where('module', 'audit_logs')->count());
    }

    public function test_historical_admin_log_is_not_exposed_after_actor_demoted(): void
    {
        $historical = AuditLog::create([
            'user_id' => $this->users['manager']->id,
            'user_name' => 'manager', 'user_role' => 'Administrator',
            'action' => 'update', 'module' => 'settings', 'description' => 'former admin action',
        ]);

        $this->actingAs($this->users['super']);
        $this->getJson('/audit-logs/' . $historical->id)->assertNotFound();
        $this->actingAs($this->users['manager']);
        $this->getJson('/audit-logs/' . $historical->id)->assertOk();
    }
}
