<?php

namespace Tests\Feature;

use App\Http\Middleware\SystemLockMiddleware;
use App\Http\Middleware\NavigationAccess;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Permission;
use App\Models\User;
use App\Support\NavigationPermissions;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class NavigationPermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->withoutMiddleware(SystemLockMiddleware::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fname');
            $table->string('lname');
            $table->string('username');
            $table->string('password');
            $table->string('role');
            $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->timestamps();
        });
        Schema::create('permission_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('permission_id');
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
        Schema::create('crop_year', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
            $table->timestamps();
        });
    }

    private function userWith(array $slugs, string $role = 'User'): User
    {
        $user = User::create([
            'fname' => 'Test', 'lname' => 'User', 'username' => uniqid('user'),
            'password' => 'unused', 'role' => $role,
        ]);
        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug]);
            $user->permissions()->attach($permission);
        }
        return $user->load('permissions');
    }

    public function test_one_report_permission_does_not_grant_sibling_reports(): void
    {
        $user = $this->userWith(['view-tls-report']);
        $this->assertTrue(NavigationPermissions::canSeeGroup($user, 'reports'));
        $this->assertTrue($user->hasPermission('view-tls-report'));
        $this->assertFalse($user->hasPermission('view-summary-report'));
        $this->assertFalse($user->hasPermission('view-reports'));
    }

    public function test_all_reports_grants_every_report_but_no_other_dropdown(): void
    {
        $user = $this->userWith(['view-reports']);
        foreach (NavigationPermissions::GROUPS['reports']['items'] as $slug) {
            $this->assertTrue($user->hasPermission($slug));
        }
        $this->assertFalse($user->hasPermission('view-loan-reports'));
    }

    public function test_direct_report_routes_require_the_selected_report(): void
    {
        Route::middleware(['web', 'login_auth', 'navigation.access'])
            ->get('/permission-probe/tls', fn () => response('TLS available'))
            ->name('tls-report.probe');
        Route::middleware(['web', 'login_auth', 'navigation.access'])
            ->get('/permission-probe/summary', fn () => response('Summary available'))
            ->name('summaryReport.probe');

        $this->actingAs($this->userWith(['view-tls-report']));
        $this->get('/permission-probe/tls')->assertOk();
        $this->getJson('/permission-probe/summary')->assertForbidden();
    }

    public function test_dropdown_children_follow_their_own_permissions(): void
    {
        $user = $this->userWith(['buy-quedan', 'upload-summary', 'set-week-number']);
        $this->assertTrue($user->hasPermission('buy-quedan'));
        $this->assertFalse($user->hasPermission('buy-molasses'));
        $this->assertTrue($user->hasPermission('upload-summary'));
        $this->assertFalse($user->hasPermission('upload-tls'));
        $this->assertTrue($user->hasPermission('set-week-number'));
        $this->assertFalse($user->hasPermission('set-crop-year'));
    }

    public function test_sidebar_shows_only_the_granted_report_and_upload_items(): void
    {
        $this->actingAs($this->userWith(['view-tls-report', 'upload-summary']));
        $html = view('layouts.sidebar-items')->render();

        $this->assertStringContainsString('TLS Reports', $html);
        $this->assertStringNotContainsString('Summary Reports', $html);
        $this->assertStringNotContainsString('Consolidated Report', $html);
        $this->assertStringContainsString('openUploadModal(\'summary\')', $html);
        $this->assertStringNotContainsString('openUploadModal(\'tls\')', $html);
    }

    public function test_report_exports_and_data_endpoints_keep_their_report_permission(): void
    {
        $cases = [
            'summaryReport.data' => 'view-summary-report',
            'summary.downloadPDF' => 'view-summary-report',
            'consolidated-report.export' => 'view-consolidated-report',
            'trucking-allowance-report.filters' => 'view-trucking-allowance-report',
            'certification-report.pdf' => 'view-certification-report',
            'tls-report.pdf' => 'view-tls-report',
            'proceeds-sheet-report.pdf' => 'view-proceeds-sheet-report',
            'loans.monthly-report-pdf' => 'view-loan-reports',
        ];
        foreach ($cases as $route => $expected) {
            $this->assertSame($expected, NavigationPermissions::requiredForRoute($route));
        }
    }

    public function test_every_dropdown_grants_only_selected_children_for_staff_roles(): void
    {
        foreach (['manager', 'loan_officer', 'User', 'Viewer'] as $role) {
            foreach (NavigationPermissions::GROUPS as $groupName => $group) {
                $first = $group['items'][0];
                $last = $group['items'][count($group['items']) - 1];
                $specific = $this->userWith([$first], $role);
                $this->assertTrue(NavigationPermissions::canSeeGroup($specific, $groupName));
                $this->assertTrue($specific->hasPermission($first));
                $this->assertFalse($specific->hasPermission($last), "$role received an unrelated $groupName item");

                $all = $this->userWith([$group['all']], $role);
                foreach ($group['items'] as $slug) {
                    $this->assertTrue($all->hasPermission($slug), "$role cannot access $slug with All enabled");
                }
            }
        }
    }

    public function test_administrative_roles_have_full_access_without_assignments(): void
    {
        foreach (['Administrator', 'super_admin'] as $role) {
            $user = $this->userWith([], $role);
            foreach (NavigationPermissions::GROUPS as $groupName => $group) {
                $this->assertTrue(NavigationPermissions::canSeeGroup($user, $groupName));
                foreach ($group['items'] as $slug) {
                    $this->assertTrue($user->hasPermission($slug));
                }
            }
        }
    }

    public function test_administrator_can_save_a_single_report_permission_and_revoke_it(): void
    {
        $admin = $this->userWith([], 'Administrator');
        $target = $this->userWith([]);
        Permission::firstOrCreate(['slug' => 'view-tls-report'], ['name' => 'TLS Reports']);
        Permission::firstOrCreate(['slug' => 'view-summary-report'], ['name' => 'Summary Reports']);

        $payload = [
            'user_id' => $target->id,
            'permissions' => [
                ['slug' => 'view-tls-report', 'granted' => true],
                ['slug' => 'view-summary-report', 'granted' => false],
            ],
        ];
        $this->actingAs($admin)->postJson('/user-management/assign-permissions', $payload)->assertOk();
        $this->assertTrue($target->fresh()->hasPermission('view-tls-report'));
        $this->assertFalse($target->fresh()->hasPermission('view-summary-report'));

        $payload['permissions'][0]['granted'] = false;
        $this->postJson('/user-management/assign-permissions', $payload)->assertOk();
        $this->assertFalse($target->fresh()->hasPermission('view-tls-report'));
    }

    public function test_super_admin_can_manage_viewer_but_not_manager_permissions(): void
    {
        $admin = $this->userWith([], 'super_admin');
        $viewer = $this->userWith([], 'Viewer');
        $manager = $this->userWith([], 'manager');
        Permission::firstOrCreate(['slug' => 'view-tls-report'], ['name' => 'TLS Reports']);
        $payload = ['permissions' => [['slug' => 'view-tls-report', 'granted' => true]]];

        $this->actingAs($admin)->postJson('/user-management/assign-permissions', [
            ...$payload, 'user_id' => $viewer->id,
        ])->assertOk();
        $this->postJson('/user-management/assign-permissions', [
            ...$payload, 'user_id' => $manager->id,
        ])->assertForbidden();
        $this->assertFalse($manager->fresh()->hasPermission('view-tls-report'));
    }

    public function test_non_admin_cannot_assign_permissions(): void
    {
        $actor = $this->userWith([], 'manager');
        $target = $this->userWith([]);
        Permission::firstOrCreate(['slug' => 'view-tls-report'], ['name' => 'TLS Reports']);
        $this->actingAs($actor)->postJson('/user-management/assign-permissions', [
            'user_id' => $target->id,
            'permissions' => [['slug' => 'view-tls-report', 'granted' => true]],
        ])->assertForbidden();
        $this->assertFalse($target->fresh()->hasPermission('view-tls-report'));
    }

    public function test_all_report_routes_are_registered_with_the_access_middleware(): void
    {
        $reportPrefixes = ['summaryReport', 'summary.', 'consolidated-report',
            'trucking-allowance-report', 'certification-report', 'tls-report',
            'proceeds-sheet-report'];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (!$name || !Str::startsWith($name, $reportPrefixes)) {
                continue;
            }
            $this->assertNotNull(NavigationPermissions::requiredForRoute($name), "$name has no permission mapping");
            $middleware = $route->gatherMiddleware();
            $this->assertTrue(in_array('navigation.access', $middleware, true)
                || in_array(NavigationAccess::class, $middleware, true), "$name bypasses access checks");
        }
    }

    public function test_every_report_direct_url_is_limited_to_the_granted_report(): void
    {
        $reports = [
            'summaryReport.probe' => 'view-summary-report',
            'consolidated-report.probe' => 'view-consolidated-report',
            'trucking-allowance-report.probe' => 'view-trucking-allowance-report',
            'certification-report.probe' => 'view-certification-report',
            'tls-report.probe' => 'view-tls-report',
            'proceeds-sheet-report.probe' => 'view-proceeds-sheet-report',
        ];
        foreach (array_keys($reports) as $index => $name) {
            Route::middleware(['web', 'login_auth', 'navigation.access'])
                ->get("/permission-matrix/{$index}", fn () => response('allowed'))
                ->name($name);
        }

        $specific = $this->userWith(['view-tls-report'], 'Viewer');
        $this->actingAs($specific);
        foreach (array_keys($reports) as $index => $name) {
            $this->getJson("/permission-matrix/{$index}")
                ->assertStatus($name === 'tls-report.probe' ? 200 : 403);
        }

        $this->actingAs($this->userWith(['view-reports'], 'User'));
        foreach (array_keys($reports) as $index => $name) {
            $this->getJson("/permission-matrix/{$index}")->assertOk();
        }
    }

    public function test_upload_type_permission_is_enforced_on_direct_requests(): void
    {
        Route::middleware(['web', 'login_auth', 'navigation.access'])
            ->get('/permission-upload/{type}', fn () => response('allowed'))
            ->name('upload.template');

        $this->actingAs($this->userWith(['upload-summary'], 'manager'));
        $this->getJson('/permission-upload/summary')->assertOk();
        $this->getJson('/permission-upload/tls')->assertForbidden();

        $this->actingAs($this->userWith(['upload-all'], 'Viewer'));
        $this->getJson('/permission-upload/summary')->assertOk();
        $this->getJson('/permission-upload/tls')->assertOk();
    }

    public function test_other_dropdown_routes_respect_specific_and_all_permissions(): void
    {
        $cases = [
            ['quedan-molasses', 'buy-quedan', 'quedan-buy.index', 'molasses-buy.index'],
            ['updates', 'set-crop-year', 'updates.addCropYear', 'updates.addWeekNumber'],
            ['loans', 'view-loan-reports', 'loans.reports', 'loans.settings'],
            ['cash-advances', 'view-cash-advances', 'cash-advances.index', 'cash-advances.settings'],
        ];
        foreach ($cases as $index => [$group, $specificSlug, $allowedRoute, $siblingRoute]) {
            Route::middleware(['web', 'login_auth', 'navigation.access'])
                ->get("/permission-other/{$index}/allowed", fn () => response('allowed'))
                ->name($allowedRoute);
            Route::middleware(['web', 'login_auth', 'navigation.access'])
                ->get("/permission-other/{$index}/sibling", fn () => response('allowed'))
                ->name($siblingRoute);

            $this->actingAs($this->userWith([$specificSlug], 'manager'));
            $this->getJson("/permission-other/{$index}/allowed")->assertOk();
            $this->getJson("/permission-other/{$index}/sibling")->assertForbidden();

            $this->actingAs($this->userWith([NavigationPermissions::GROUPS[$group]['all']], 'Viewer'));
            $this->getJson("/permission-other/{$index}/allowed")->assertOk();
            $this->getJson("/permission-other/{$index}/sibling")->assertOk();
        }
    }

    public function test_permission_seeder_contains_every_dropdown_choice(): void
    {
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder'])->assertExitCode(0);
        foreach (NavigationPermissions::GROUPS as $group) {
            foreach ([$group['all'], ...$group['items']] as $slug) {
                $this->assertDatabaseHas('permissions', ['slug' => $slug]);
            }
        }
    }

    public function test_administrator_can_open_the_grouped_permission_editor(): void
    {
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder'])->assertExitCode(0);
        $this->actingAs($this->userWith([], 'Administrator'));

        $this->get('/user-management')
            ->assertOk()
            ->assertSee('Choose All to allow every item in a menu')
            ->assertSee('view-summary-report')
            ->assertSee('access-all-cash-advances')
            ->assertSee('access-all-hauler-tls-liq')
            ->assertSee('View Hauler TLS LIQ');
    }

    public function test_hauler_tls_liq_rights_control_menu_and_direct_routes(): void
    {
        Route::middleware(['web', 'login_auth', 'navigation.access'])
            ->get('/permission-hauler/view', fn () => response('view'))
            ->name('hauling-liquidity.index');
        Route::middleware(['web', 'login_auth', 'navigation.access'])
            ->get('/permission-hauler/manage', fn () => response('manage'))
            ->name('hauling-liquidity.auto-deductions');

        $manager = $this->userWith([], 'manager');
        $this->actingAs($manager);
        $this->assertStringNotContainsString('<span>Hauler TLS LIQ</span>', view('layouts.sidebar-items')->render());
        $this->getJson('/permission-hauler/view')->assertForbidden();
        $this->getJson('/permission-hauler/manage')->assertForbidden();

        $this->actingAs($this->userWith(['view-hauling-liquidity'], 'User'));
        $this->assertStringContainsString('<span>Hauler TLS LIQ</span>', view('layouts.sidebar-items')->render());
        $this->getJson('/permission-hauler/view')->assertOk();
        $this->getJson('/permission-hauler/manage')->assertForbidden();

        $this->actingAs($this->userWith(['manage-hauling-liquidity'], 'Viewer'));
        $this->getJson('/permission-hauler/view')->assertOk();
        $this->getJson('/permission-hauler/manage')->assertOk();

        $this->actingAs($this->userWith(['access-all-hauler-tls-liq'], 'loan_officer'));
        $this->getJson('/permission-hauler/view')->assertOk();
        $this->getJson('/permission-hauler/manage')->assertOk();
    }

    public function test_every_hauler_tls_liq_route_has_a_permission_mapping(): void
    {
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            if (!$name || !str_starts_with($name, 'hauling-liquidity.')) {
                continue;
            }
            $this->assertNotNull(NavigationPermissions::requiredForRoute($name));
            $this->assertContains('navigation.access', $route->gatherMiddleware());
        }
    }

}
