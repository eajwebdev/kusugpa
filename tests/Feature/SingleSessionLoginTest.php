<?php

namespace Tests\Feature;

use App\Http\Middleware\SystemLockMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SingleSessionLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
            'services.turnstile.expected_hostname' => null,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->withoutMiddleware(SystemLockMiddleware::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fname');
            $table->string('lname');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('active_session_hash', 64)->nullable();
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

        Route::middleware(['web', 'login_auth'])
            ->get('/single-session-probe', fn () => response()->json(['user_id' => auth()->id()]));
    }

    private function createUser(string $role = 'User', string $username = 'single-session-user'): User
    {
        return User::create([
            'fname' => 'Single',
            'lname' => 'Session',
            'username' => $username,
            'password' => Hash::make('correct-password'),
            'role' => $role,
        ]);
    }

    private function keepBrowserSession(): void
    {
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
    }

    public function test_newer_login_replaces_older_session(): void
    {
        $user = $this->createUser();

        $this->post('/login', ['username' => $user->username, 'password' => 'correct-password'])
            ->assertRedirect(route('dashboard'));
        $this->keepBrowserSession();

        $firstHash = $user->fresh()->active_session_hash;
        $this->assertSame(hash('sha256', session()->getId()), $firstHash);
        $this->assertArrayNotHasKey('active_session_hash', $user->fresh()->toArray());
        $this->getJson('/single-session-probe')->assertOk()->assertJsonPath('user_id', $user->id);

        // Another device has completed login and replaced the account's session hash.
        $newHash = hash('sha256', 'second-device-session');
        $user->newQuery()->whereKey($user->id)->update(['active_session_hash' => $newHash]);

        $this->getJson('/single-session-probe')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Your account was signed in on another device. Please sign in again.');
        $this->assertGuest();
        $this->assertSame($newHash, $user->fresh()->active_session_hash);

        $this->post('/login', ['username' => $user->username, 'password' => 'correct-password'])
            ->assertRedirect(route('dashboard'));
        $this->keepBrowserSession();
        $this->assertNotSame($firstHash, $user->fresh()->active_session_hash);
        $this->getJson('/single-session-probe')->assertOk();
    }

    /** @dataProvider loginRoles */
    public function test_each_user_role_registers_a_single_session(string $role): void
    {
        $user = $this->createUser($role);

        $this->post('/login', ['username' => $user->username, 'password' => 'correct-password'])
            ->assertRedirect(route('dashboard'));
        $this->keepBrowserSession();

        $this->assertSame(hash('sha256', session()->getId()), $user->fresh()->active_session_hash);
        $this->getJson('/single-session-probe')->assertOk()->assertJsonPath('user_id', $user->id);
        $this->assertSame(1, DB::table('audit_logs')->where('user_id', $user->id)->where('action', 'login')->count());
    }

    public function loginRoles(): array
    {
        return [
            'administrator' => ['Administrator'],
            'super admin' => ['super_admin'],
            'manager' => ['manager'],
            'loan officer' => ['loan_officer'],
            'user' => ['User'],
            'viewer' => ['Viewer'],
        ];
    }

    public function test_second_browser_login_replaces_the_first_browser(): void
    {
        $user = $this->createUser();
        $cookieName = config('session.cookie');

        $this->post('/login', ['username' => $user->username, 'password' => 'correct-password'])
            ->assertRedirect(route('dashboard'));
        $firstSessionId = session()->getId();
        $firstHash = $user->fresh()->active_session_hash;

        // Simulate a separate browser in this PHPUnit application instance.
        Auth::forgetGuards();
        session()->flush();
        $this->withCookie($cookieName, str_repeat('B', 40))->withCredentials();
        $this->post('/login', ['username' => $user->username, 'password' => 'correct-password'])
            ->assertRedirect(route('dashboard'));
        $secondSessionId = session()->getId();
        $this->assertNotSame($firstHash, $user->fresh()->active_session_hash);

        Auth::forgetGuards();
        session()->flush();
        $this->withCookie($cookieName, $firstSessionId);
        $this->getJson('/single-session-probe')->assertStatus(401);

        Auth::forgetGuards();
        session()->flush();
        $this->withCookie($cookieName, $secondSessionId);
        $this->getJson('/single-session-probe')->assertOk()->assertJsonPath('user_id', $user->id);
    }

    public function test_logout_clears_only_the_current_account_session(): void
    {
        $user = $this->createUser();
        $this->post('/login', ['username' => $user->username, 'password' => 'correct-password']);
        $this->keepBrowserSession();

        $this->post('/logout')->assertRedirect('/');
        $this->assertNull($user->fresh()->active_session_hash);
        $this->getJson('/single-session-probe')->assertStatus(401);
    }

    public function test_replaced_browser_cannot_clear_the_newer_login_on_logout(): void
    {
        $user = $this->createUser();
        $this->post('/login', ['username' => $user->username, 'password' => 'correct-password']);
        $this->keepBrowserSession();

        $newHash = hash('sha256', 'newer-browser-session');
        $user->newQuery()->whereKey($user->id)->update(['active_session_hash' => $newHash]);

        $this->post('/logout')->assertRedirect(route('getLogin'));
        $this->assertSame($newHash, $user->fresh()->active_session_hash);
    }

    public function test_admin_login_registers_a_single_session(): void
    {
        $user = $this->createUser('Administrator', 'single-session-admin');
        config(['app.admin_security_key' => 'test-security-key']);

        $this->post('/system/auth/admin-login', [
            'username' => $user->username,
            'password' => 'correct-password',
            'security_key' => 'test-security-key',
        ])->assertRedirect(route('admin.dashboard'));
        $this->keepBrowserSession();

        $this->assertSame(hash('sha256', session()->getId()), $user->fresh()->active_session_hash);
        $this->getJson('/single-session-probe')->assertOk();
        $this->assertSame(1, DB::table('audit_logs')->where('user_id', $user->id)->where('action', 'login')->count());
    }

    public function test_demo_login_registers_a_single_session(): void
    {
        $user = $this->createUser('Viewer', 'single-session-demo');
        config([
            'demo.enabled' => true,
            'demo.accounts' => [[
                'username' => $user->username,
                'fname' => $user->fname,
                'lname' => $user->lname,
                'role' => $user->role,
            ]],
        ]);

        $this->post('/login/demo', ['username' => $user->username])
            ->assertRedirect(route('dashboard'));
        $this->keepBrowserSession();

        $this->assertSame(hash('sha256', session()->getId()), $user->fresh()->active_session_hash);
        $this->getJson('/single-session-probe')->assertOk();
        $this->assertSame(1, DB::table('audit_logs')->where('user_id', $user->id)->where('action', 'login')->count());
    }

    public function test_failed_login_does_not_replace_an_existing_session(): void
    {
        $user = $this->createUser();
        $existingHash = hash('sha256', 'existing-device-session');
        $user->newQuery()->whereKey($user->id)->update(['active_session_hash' => $existingHash]);

        $this->post('/login', ['username' => $user->username, 'password' => 'wrong-password'])
            ->assertRedirect();

        $this->assertSame($existingHash, $user->fresh()->active_session_hash);
    }
}
