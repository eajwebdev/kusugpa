<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureSingleSession;
use App\Http\Middleware\SystemLockMiddleware;
use App\Http\Middleware\VerifyCsrfToken;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    private const STRONG = 'Sugar-Cane#2026';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'services.turnstile.site_key' => null,
            'services.turnstile.secret_key' => null,
            'services.turnstile.expected_hostname' => null,
            'demo.enabled' => false,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        $this->withoutMiddleware(SystemLockMiddleware::class);
        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withoutMiddleware(EnsureSingleSession::class);
        $this->withoutMiddleware(ThrottleRequests::class);

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fname');
            $table->string('mname')->nullable();
            $table->string('lname');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('role');
            $table->string('google_id')->nullable()->unique();
            $table->string('active_session_hash', 64)->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('password_changed_at')->nullable();
            $table->rememberToken();
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
            $table->string('name')->nullable();
        });
        Schema::create('permission_user', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('user_id');
        });
        // The main layout lists crop years
        Schema::create('crop_year', function (Blueprint $table) {
            $table->id();
            $table->string('crop_year');
        });

        Route::middleware(['web', 'login_auth'])
            ->get('/password-probe', fn () => response()->json(['user_id' => auth()->id()]));
    }

    private function createUser(string $password, string $role = 'User', string $username = 'planter-clerk', bool $mustChange = false): User
    {
        return User::create([
            'fname' => 'Pass',
            'lname' => 'Word',
            'username' => $username,
            'password' => Hash::make($password),
            'role' => $role,
            'must_change_password' => $mustChange,
        ]);
    }

    private function keepBrowserSession(): void
    {
        $this->withCookie(config('session.cookie'), session()->getId())->withCredentials();
    }

    public function test_signing_in_with_a_weak_password_forces_a_change_before_anything_else(): void
    {
        $user = $this->createUser('password');

        $this->post('/login', ['username' => $user->username, 'password' => 'password'])
            ->assertRedirect(route('dashboard'));
        $this->assertTrue($user->fresh()->must_change_password);

        $this->keepBrowserSession();
        $this->get('/password-probe')->assertRedirect(route('account.password.edit'));
        $this->getJson('/password-probe')->assertStatus(403)
            ->assertJsonPath('redirect', route('account.password.edit'));
    }

    public function test_signing_in_with_a_strong_password_does_not_force_a_change(): void
    {
        $user = $this->createUser(self::STRONG);

        $this->post('/login', ['username' => $user->username, 'password' => self::STRONG])
            ->assertRedirect(route('dashboard'));
        $this->assertFalse($user->fresh()->must_change_password);

        $this->keepBrowserSession();
        $this->getJson('/password-probe')->assertOk();
    }

    public function test_every_role_can_open_the_change_password_page(): void
    {
        foreach (['Viewer', 'User', 'loan_officer', 'manager', 'super_admin', 'Administrator'] as $role) {
            $user = $this->createUser(self::STRONG, $role, 'user-' . strtolower($role));
            $this->actingAs($user)->get(route('account.password.edit'))
                ->assertOk()
                ->assertSee('name="current_password"', false);
        }
    }

    public function test_new_password_must_be_strong_and_confirmed(): void
    {
        $user = $this->createUser('password', 'User', 'planter-clerk', true);
        $this->actingAs($user);

        $cases = [
            'too short' => 'Abc1!',
            'no symbol' => 'Abcdefgh1',
            'no number' => 'Abcdefgh!',
            'no uppercase' => 'abcdefg1!',
            'no lowercase' => 'ABCDEFG1!',
            'has username' => 'Planter-Clerk1!',
            'too long' => str_repeat('Aa1!', 17),
        ];

        foreach ($cases as $label => $weak) {
            $this->from(route('account.password.edit'))->put(route('account.password.update'), [
                'current_password' => 'password',
                'password' => $weak,
                'password_confirmation' => $weak,
            ])->assertSessionHasErrors('password', null, 'default');
            $this->assertTrue(Hash::check('password', $user->fresh()->password), $label);
        }

        $this->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => self::STRONG,
            'password_confirmation' => 'Something-Else#1',
        ])->assertSessionHasErrors('password');
    }

    public function test_current_password_is_required_and_must_differ(): void
    {
        $user = $this->createUser(self::STRONG);
        $this->actingAs($user);

        $this->put(route('account.password.update'), [
            'current_password' => 'Wrong-Passw0rd!',
            'password' => 'Brand-New#2026',
            'password_confirmation' => 'Brand-New#2026',
        ])->assertSessionHasErrors('current_password');

        $this->put(route('account.password.update'), [
            'current_password' => self::STRONG,
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check(self::STRONG, $user->fresh()->password));
    }

    public function test_changing_the_required_password_unlocks_the_account(): void
    {
        $user = $this->createUser('password', 'Viewer', 'planter-clerk', true);

        $this->post('/login', ['username' => $user->username, 'password' => 'password']);
        $this->keepBrowserSession();
        $this->put(route('account.password.update'), [
            'current_password' => 'password',
            'password' => self::STRONG,
            'password_confirmation' => self::STRONG,
        ])->assertRedirect(route('dashboard'));

        $fresh = $user->fresh();
        $this->assertFalse($fresh->must_change_password);
        $this->assertNotNull($fresh->password_changed_at);
        $this->assertTrue(Hash::check(self::STRONG, $fresh->password));
        $this->assertSame(hash('sha256', session()->getId()), $fresh->active_session_hash);
        $this->assertDatabaseHas('audit_logs', ['description' => 'Changed required default password']);

        $this->keepBrowserSession();
        $this->getJson('/password-probe')->assertOk()->assertJsonPath('user_id', $user->id);
    }

    public function test_admin_issued_passwords_must_be_strong_and_are_temporary(): void
    {
        $admin = $this->createUser(self::STRONG, 'Administrator', 'site-admin');
        $this->actingAs($admin);

        $this->postJson('/user-management', [
            'fname' => 'New', 'lname' => 'Clerk', 'username' => 'new-clerk', 'role' => 'User', 'password' => 'password',
        ])->assertStatus(422)->assertJsonValidationErrors('password');

        $this->postJson('/user-management', [
            'fname' => 'New', 'lname' => 'Clerk', 'username' => 'new-clerk', 'role' => 'User', 'password' => self::STRONG,
        ])->assertOk();
        $created = User::where('username', 'new-clerk')->first();
        $this->assertTrue($created->must_change_password);

        $created->forceFill(['must_change_password' => false])->save();
        $this->putJson('/user-management/' . $created->id, [
            'fname' => 'New', 'lname' => 'Clerk', 'username' => 'new-clerk', 'role' => 'User', 'password' => 'Reset-Temp#2026',
        ])->assertOk();
        $this->assertTrue($created->fresh()->must_change_password);

        // Editing other details without a password leaves the user's own password alone
        $created->fresh()->forceFill(['must_change_password' => false])->save();
        $this->putJson('/user-management/' . $created->id, [
            'fname' => 'Renamed', 'lname' => 'Clerk', 'username' => 'new-clerk', 'role' => 'User',
        ])->assertOk();
        $this->assertFalse($created->fresh()->must_change_password);
    }

    public function test_demo_accounts_are_not_forced_while_demo_mode_is_on(): void
    {
        config(['demo.enabled' => true]);
        $user = $this->createUser('password', 'Viewer', 'viewer');

        $this->post('/login', ['username' => 'viewer', 'password' => 'password']);
        $this->assertFalse($user->fresh()->must_change_password);
        $this->keepBrowserSession();
        $this->getJson('/password-probe')->assertOk();
    }
}
