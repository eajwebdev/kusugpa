<?php

namespace Tests\Feature;

use App\Http\Middleware\SystemLockMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class TurnstileLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
            'services.turnstile.expected_hostname' => null,
            'services.google.client_id' => 'google-client',
            'services.google.client_secret' => 'google-secret',
            'services.google.redirect' => 'http://localhost/auth/google/callback',
            'app.admin_security_key' => 'server-only-admin-key',
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
    }

    private function createUser(string $role = 'User'): User
    {
        return User::create([
            'fname' => 'Turnstile',
            'lname' => 'Tester',
            'username' => 'turnstile-user',
            'password' => Hash::make('correct-password'),
            'role' => $role,
        ]);
    }

    private function validTokenResponse(string $action = 'login'): void
    {
        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true,
                'hostname' => 'localhost',
                'action' => $action,
            ]),
        ]);
    }

    public function test_login_pages_render_turnstile_without_exposing_secret_keys(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('class="cf-turnstile"', false)
            ->assertSee('data-action="login"', false)
            ->assertSee('name="cf-turnstile-response"', false)
            ->assertDontSee('test-secret-key');

        $this->get('/system/auth/admin-login')->assertOk()
            ->assertSee('data-action="admin_login"', false)
            ->assertDontSee('server-only-admin-key')
            ->assertDontSee('test-secret-key');
    }

    public function test_missing_token_blocks_every_public_sign_in_route(): void
    {
        $this->createUser();
        config(['demo.enabled' => true]);
        Http::fake();

        $this->post('/login', [
            'username' => 'turnstile-user', 'password' => 'correct-password',
        ])->assertRedirect(route('getLogin'));
        $this->post('/login/demo', ['username' => 'turnstile-user'])
            ->assertRedirect(route('getLogin'));
        $this->post('/auth/google/redirect')->assertRedirect(route('getLogin'));
        $this->post('/system/auth/admin-login', [
            'username' => 'turnstile-user', 'password' => 'correct-password',
            'security_key' => 'server-only-admin-key',
        ])->assertRedirect(route('admin.getLogin'));

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_valid_token_allows_password_login_and_sends_token_to_siteverify(): void
    {
        $user = $this->createUser();
        $this->validTokenResponse();

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'correct-password',
            'cf-turnstile-response' => 'valid-token',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        Http::assertSent(fn ($request) => $request->url() === 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'valid-token');
        Http::assertSentCount(1);
    }

    public function test_invalid_or_expired_token_blocks_password_login(): void
    {
        $user = $this->createUser();
        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => false,
                'error-codes' => ['timeout-or-duplicate'],
            ]),
        ]);

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'correct-password',
            'cf-turnstile-response' => 'expired-token',
        ])->assertRedirect(route('getLogin'));

        $this->assertGuest();
        $this->assertNull($user->fresh()->active_session_hash);
    }

    public function test_partial_configuration_fails_closed(): void
    {
        $this->createUser();
        config(['services.turnstile.secret_key' => null]);
        Http::fake();

        $this->post('/login', [
            'username' => 'turnstile-user', 'password' => 'correct-password',
            'cf-turnstile-response' => 'anything',
        ])->assertRedirect(route('getLogin'));

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_hostname_mismatch_blocks_login_when_expected_hostname_is_set(): void
    {
        $this->createUser();
        config(['services.turnstile.expected_hostname' => 'portal.example.com']);
        $this->validTokenResponse();

        $this->post('/login', [
            'username' => 'turnstile-user', 'password' => 'correct-password',
            'cf-turnstile-response' => 'token',
        ])->assertRedirect(route('getLogin'));

        $this->assertGuest();
    }

    public function test_token_from_a_different_action_cannot_be_used_for_admin_login(): void
    {
        $this->createUser('Administrator');
        $this->validTokenResponse('login');

        $this->post('/system/auth/admin-login', [
            'username' => 'turnstile-user', 'password' => 'correct-password',
            'security_key' => 'server-only-admin-key',
            'cf-turnstile-response' => 'token',
        ])->assertRedirect(route('admin.getLogin'));

        $this->assertGuest();
    }

    public function test_cloudflare_test_secret_supports_local_testing_but_is_rejected_in_production(): void
    {
        $user = $this->createUser();
        config(['services.turnstile.secret_key' => '1x0000000000000000000000000000000AA']);
        Http::fake([
            'challenges.cloudflare.com/turnstile/v0/siteverify' => Http::response([
                'success' => true, 'hostname' => 'example.com',
            ]),
        ]);

        $this->post('/login', [
            'username' => $user->username, 'password' => 'correct-password',
            'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
        ])->assertRedirect(route('dashboard'));

        $this->post('/logout');
        config(['app.env' => 'production']);
        $this->post('/login', [
            'username' => $user->username, 'password' => 'correct-password',
            'cf-turnstile-response' => 'XXXX.DUMMY.TOKEN.XXXX',
        ])->assertRedirect(route('getLogin'));

        $this->assertGuest();
        Http::assertSentCount(1);
    }

    public function test_siteverify_outage_blocks_login(): void
    {
        $this->createUser();
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('unavailable'));

        $this->post('/login', [
            'username' => 'turnstile-user', 'password' => 'correct-password',
            'cf-turnstile-response' => 'token',
        ])->assertRedirect(route('getLogin'));

        $this->assertGuest();
    }

    public function test_valid_token_allows_administrator_login(): void
    {
        $user = $this->createUser('Administrator');
        $this->validTokenResponse('admin_login');

        $this->post('/system/auth/admin-login', [
            'username' => $user->username,
            'password' => 'correct-password',
            'security_key' => 'server-only-admin-key',
            'cf-turnstile-response' => 'valid-token',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_valid_token_allows_google_sign_in_redirect(): void
    {
        $this->validTokenResponse();
        $provider = Mockery::mock();
        $provider->shouldReceive('redirect')->once()
            ->andReturn(redirect('https://accounts.google.test/authorize'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->post('/auth/google/redirect', [
            'cf-turnstile-response' => 'valid-token',
        ])->assertRedirect('https://accounts.google.test/authorize');
    }
}
