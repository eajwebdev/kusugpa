<?php

namespace Tests\Feature;

use App\Http\Controllers\AuditLogController;
use App\Models\AuditLog;
use App\Support\NavigationPermissions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Feature\Concerns\SecurityTestSchema;
use Tests\TestCase;

/**
 * Every route that changes data checks who is asking, so a new route cannot ship without a permission
 */
class RouteAuthorizationTest extends TestCase
{
    use SecurityTestSchema;

    // Sign-in and sign-out: open to guests by design (Turnstile + lockout guard the sign-ins)
    private const PUBLIC_WRITES = ['logout', 'postLogin', 'demoLogin', 'google.redirect', 'admin.postLogin'];

    // Signed-in users change their own password; checked against the current password in the controller
    private const SELF_SERVICE_WRITES = ['account.password.update'];

    // Gated inside the controller; test_controller_gated_writes_refuse_a_viewer proves each one
    private const CONTROLLER_GATED_WRITES = [
        'user-management.store', 'user-management.update', 'user-management.destroy',
        'assign-permissions', 'audit-logs.clear',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSecuritySchema();
    }

    public function test_every_write_route_requires_sign_in_and_a_permission(): void
    {
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            $name = (string) $route->getName();
            if (!array_diff($route->methods(), ['GET', 'HEAD']) || Str::startsWith($route->uri(), '_ignition')
                || in_array($name, self::PUBLIC_WRITES, true)) {
                continue;
            }
            $middleware = array_filter($route->gatherMiddleware(), 'is_string');
            $signedIn = (bool) preg_grep('/^login_auth(:|$)/', $middleware);
            $this->assertTrue($signedIn, "{$route->uri()} ({$name}) can be called without signing in");

            if (in_array($name, self::SELF_SERVICE_WRITES, true) || in_array($name, self::CONTROLLER_GATED_WRITES, true)) {
                continue;
            }
            // Upload routes need a permission for each file type they accept
            $types = isset($route->wheres['type']) ? explode('|', $route->wheres['type']) : [null];
            $mapped = collect($types)->every(fn ($type) => NavigationPermissions::requiredForRoute($name, $type));
            $gated = in_array('admin.role', $middleware, true)
                || (bool) preg_grep('/^login_auth:/', $middleware)
                || (in_array('navigation.access', $middleware, true) && $mapped);
            $this->assertTrue($gated, "{$route->uri()} ({$name}) changes data without a permission check: map it in NavigationPermissions::requiredForRoute");
            $checked++;
        }

        $this->assertGreaterThan(50, $checked);
    }

    public function test_controller_gated_writes_refuse_a_viewer(): void
    {
        $other = $this->makeUser('User');
        $this->actingAs($this->makeUser('Viewer'));

        $this->postJson(route('user-management.store'), [
            'fname' => 'New', 'username' => 'new-admin', 'password' => 'Strong-Pass-2026!', 'role' => 'Administrator',
        ])->assertForbidden();
        $this->putJson(route('user-management.update', $other), ['fname' => 'X', 'username' => $other->username, 'role' => 'Administrator'])
            ->assertForbidden();
        $this->deleteJson(route('user-management.destroy', $other))->assertForbidden();
        $this->postJson(route('assign-permissions'), ['user_id' => $other->id, 'permissions' => []])->assertForbidden();
        $this->postJson(route('audit-logs.clear'))->assertForbidden();

        $this->assertSame('User', $other->fresh()->role);
        $this->assertSame(2, DB::table('users')->count());
    }

    public function test_clearing_audit_logs_keeps_ninety_days_and_is_recorded(): void
    {
        $this->actingAs($this->makeUser('Administrator'));
        foreach ([200, 100, 89, 1] as $daysAgo) {
            DB::table('audit_logs')->insert([
                'action' => 'update', 'module' => 'loans', 'description' => "{$daysAgo} days ago",
                'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo),
            ]);
        }

        $this->postJson(route('audit-logs.clear'), ['days' => 30])->assertStatus(422)->assertJsonValidationErrors('days');

        $this->postJson(route('audit-logs.clear'))->assertOk();
        $this->assertEqualsCanonicalizing(['89 days ago', '1 days ago'],
            AuditLog::where('module', 'loans')->pluck('description')->all());
        $this->assertSame(1, AuditLog::where('module', 'audit_logs')
            ->where('description', 'Cleared 2 audit logs older than ' . AuditLogController::MIN_RETENTION_DAYS . ' days')->count());
    }
}
