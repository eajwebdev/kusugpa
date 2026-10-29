<?php

namespace Tests\Feature;

use App\Http\Controllers\LoginAuthController;
use App\Models\AuditLog;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\Feature\Concerns\SecurityTestSchema;
use Tests\TestCase;

/**
 * Failed sign-ins lock out, sign-out needs a POST, and every response carries the browser security headers
 */
class LoginHardeningTest extends TestCase
{
    use SecurityTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useSecuritySchema();
    }

    private function failLogin(string $username, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->from('/login')->post('/login', ['username' => $username, 'password' => 'wrong-password'])
                ->assertSessionHas('error', 'Invalid username or password. Please try again.');
        }
    }

    public function test_wrong_passwords_lock_the_account_and_even_the_right_password_waits(): void
    {
        $user = $this->makeUser('User', 'juan');
        $this->failLogin('juan', LoginAuthController::MAX_TRIES_PER_ACCOUNT);

        // Same username in other casing / spacing is the same account
        $this->from('/login')->post('/login', ['username' => ' JUAN', 'password' => 'Strong-Pass-2026!'])
            ->assertSessionHas('error', fn ($message) => str_starts_with($message, 'Too many failed sign-in attempts. Try again in 15 minutes'));
        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('description', 'like', 'Sign-in locked%')->count());

        // The lockout ends after its window
        $this->travel(LoginAuthController::LOCKOUT_SECONDS + 1)->seconds();
        $this->post('/login', ['username' => 'juan', 'password' => 'Strong-Pass-2026!'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_successful_sign_in_resets_the_count(): void
    {
        $this->makeUser('User', 'maria');
        $this->failLogin('maria', LoginAuthController::MAX_TRIES_PER_ACCOUNT - 1);
        $this->post('/login', ['username' => 'maria', 'password' => 'Strong-Pass-2026!'])->assertRedirect(route('dashboard'));
        $this->post('/logout');

        $this->failLogin('maria', LoginAuthController::MAX_TRIES_PER_ACCOUNT - 1);
        $this->post('/login', ['username' => 'maria', 'password' => 'Strong-Pass-2026!'])->assertRedirect(route('dashboard'));
    }

    public function test_one_address_cannot_spray_many_usernames(): void
    {
        $this->makeUser('User', 'pedro');
        foreach (range(1, LoginAuthController::MAX_TRIES_PER_ADDRESS) as $i) {
            $this->from('/login')->post('/login', ['username' => 'guess' . $i, 'password' => 'wrong-password']);
        }

        $this->from('/login')->post('/login', ['username' => 'pedro', 'password' => 'Strong-Pass-2026!'])
            ->assertSessionHas('error', fn ($message) => str_starts_with($message, 'Too many failed sign-in attempts'));
        $this->assertGuest();
    }

    public function test_wrong_admin_security_keys_lock_the_admin_portal(): void
    {
        $this->makeUser('Administrator', 'root-admin');
        foreach (range(1, LoginAuthController::MAX_TRIES_PER_ACCOUNT) as $i) {
            $this->from('/system/auth/admin-login')->post('/system/auth/admin-login', [
                'username' => 'root-admin', 'password' => 'Strong-Pass-2026!', 'security_key' => 'guess-' . $i,
            ])->assertSessionHas('admin_error', 'Invalid security credentials. Access denied.');
        }

        $this->from('/system/auth/admin-login')->post('/system/auth/admin-login', [
            'username' => 'root-admin', 'password' => 'Strong-Pass-2026!', 'security_key' => 'server-only-admin-key',
        ])->assertSessionHas('admin_error', fn ($message) => str_starts_with($message, 'Too many failed sign-in attempts'));
        $this->assertGuest();
    }

    public function test_an_unset_admin_security_key_never_matches(): void
    {
        config(['app.admin_security_key' => null]);
        $this->makeUser('Administrator', 'root-admin');

        $this->from('/system/auth/admin-login')->post('/system/auth/admin-login', [
            'username' => 'root-admin', 'password' => 'Strong-Pass-2026!', 'security_key' => ' ',
        ])->assertSessionHasErrors('security_key');
        $this->from('/system/auth/admin-login')->post('/system/auth/admin-login', [
            'username' => 'root-admin', 'password' => 'Strong-Pass-2026!', 'security_key' => 'anything',
        ])->assertSessionHas('admin_error', 'Invalid security credentials. Access denied.');
        $this->assertGuest();
    }

    public function test_sign_out_needs_a_post(): void
    {
        $this->actingAs($this->makeUser('User'));

        $this->get('/logout')->assertStatus(405);
        $this->assertAuthenticated();

        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_sign_out_with_an_expired_token_goes_to_the_start_page(): void
    {
        $request = Request::create('/logout', 'POST');
        $request->setRouteResolver(fn () => Route::getRoutes()->match($request));

        $response = app(ExceptionHandler::class)->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertTrue($response->isRedirect(url('/')));
    }

    public function test_responses_carry_security_headers(): void
    {
        config(['app.url' => 'https://kusugpa.example.com']);

        $this->get('/login')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000')
            ->assertHeaderMissing('X-Powered-By');
    }
}
