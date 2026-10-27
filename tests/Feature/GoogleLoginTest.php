<?php

namespace Tests\Feature;

use App\Http\Middleware\SystemLockMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
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
            $table->string('google_id')->nullable()->unique();
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
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
        });
        Schema::create('permission_user', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('user_id');
        });
    }

    private function createUser(string $username, string $role = 'User', ?string $googleId = null): User
    {
        $user = User::create([
            'fname' => 'Google',
            'lname' => 'Tester',
            'username' => $username,
            'password' => Hash::make('Correct-Passw0rd!'),
            'role' => $role,
        ]);
        $user->google_id = $googleId;
        $user->save();

        return $user;
    }

    private function mockGoogle(string $googleId = 'google-123'): void
    {
        $profile = (new GoogleUser)->map([
            'id' => $googleId,
            'email' => 'google@example.test',
            'name' => 'Google Tester',
        ]);
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.test/authorize'));
        $provider->shouldReceive('user')->andReturn($profile);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    private function keepBrowserSession(): void
    {
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
    }

    public function test_login_page_keeps_password_form_and_google_option(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('name="username"', false)
            ->assertSee('name="password"', false)
            ->assertSee(route('google.redirect'));

        config(['services.google.client_id' => null]);
        $this->get('/login')->assertOk()->assertSee('Google sign-in will be available once it is configured');
        $this->post('/auth/google/redirect')->assertRedirect(route('getLogin'));
    }

    public function test_unlinked_google_account_cannot_sign_in_or_take_over_matching_username(): void
    {
        $user = $this->createUser('google@example.test');
        $this->mockGoogle();

        $this->post('/auth/google/redirect')->assertRedirect('https://accounts.google.test/authorize');
        $this->keepBrowserSession();
        $this->get('/auth/google/callback')->assertRedirect(route('getLogin'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->google_id);
        $this->assertNull($user->fresh()->active_session_hash);
    }

    public function test_signed_in_user_can_link_google_and_then_sign_in_with_it(): void
    {
        $user = $this->createUser('linked-user', 'manager');
        $this->mockGoogle();

        $this->post('/login', ['username' => $user->username, 'password' => 'Correct-Passw0rd!'])
            ->assertRedirect(route('dashboard'));
        $this->keepBrowserSession();
        $this->get('/auth/google/link')->assertRedirect('https://accounts.google.test/authorize');
        $this->keepBrowserSession();
        $this->get('/auth/google/callback')->assertRedirect(route('account.google'));
        $this->assertSame('google-123', $user->fresh()->google_id);
        $this->assertSame(1, DB::table('audit_logs')->where('user_id', $user->id)->where('description', 'Connected Google sign-in')->count());

        $this->post('/logout');
        $this->keepBrowserSession();
        $this->post('/auth/google/redirect')->assertRedirect('https://accounts.google.test/authorize');
        $this->keepBrowserSession();
        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame('manager', $user->fresh()->role);
        $this->assertSame(hash('sha256', session()->getId()), $user->fresh()->active_session_hash);
        $this->assertSame(1, DB::table('audit_logs')->where('user_id', $user->id)->where('description', 'Signed in with Google')->count());
    }

    public function test_google_login_replaces_older_account_session(): void
    {
        $user = $this->createUser('replaced-user', 'Viewer', 'google-123');
        $oldHash = hash('sha256', 'old-browser');
        $user->newQuery()->whereKey($user->id)->update(['active_session_hash' => $oldHash]);
        $this->mockGoogle();

        $this->post('/auth/google/redirect')->assertRedirect();
        $this->keepBrowserSession();
        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldHash, $user->fresh()->active_session_hash);
        $this->assertSame(hash('sha256', session()->getId()), $user->fresh()->active_session_hash);
    }

    /** @dataProvider loginRoles */
    public function test_linked_google_sign_in_accepts_each_existing_role(string $role): void
    {
        $user = $this->createUser('google-role-user', $role, 'google-123');
        $this->mockGoogle();

        $this->post('/auth/google/redirect')->assertRedirect();
        $this->keepBrowserSession();
        $this->get('/auth/google/callback')->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($role, $user->fresh()->role);
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

    public function test_one_google_account_cannot_be_linked_to_two_users(): void
    {
        $owner = $this->createUser('owner', 'User', 'google-123');
        $other = $this->createUser('other');
        $this->mockGoogle();
        $this->post('/login', ['username' => $other->username, 'password' => 'Correct-Passw0rd!']);
        $this->keepBrowserSession();

        $this->get('/auth/google/link')->assertRedirect();
        $this->keepBrowserSession();
        $this->get('/auth/google/callback')->assertRedirect(route('account.google'));

        $this->assertSame('google-123', $owner->fresh()->google_id);
        $this->assertNull($other->fresh()->google_id);
    }

    public function test_callback_without_an_initiated_flow_is_rejected(): void
    {
        $this->get('/auth/google/callback')->assertRedirect(route('getLogin'));
        $this->assertGuest();
    }

    public function test_google_callback_rejects_invalid_oauth_state(): void
    {
        $this->post('/auth/google/redirect')->assertRedirect();
        $this->keepBrowserSession();
        $this->get('/auth/google/callback?state=invalid&code=fake')
            ->assertRedirect(route('getLogin'));

        $this->assertGuest();
    }
}
