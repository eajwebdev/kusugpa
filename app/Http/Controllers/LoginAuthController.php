<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\SingleSession;

class LoginAuthController extends Controller
{
    // Failed sign-ins allowed per lockout window: per username from one address, and per address over all usernames
    public const MAX_TRIES_PER_ACCOUNT = 5;
    public const MAX_TRIES_PER_ADDRESS = 20;
    public const LOCKOUT_SECONDS = 900;

    private function throttleKeys(Request $request, string $portal): array
    {
        return [
            [$portal . '|' . Str::lower(trim((string) $request->input('username'))) . '|' . $request->ip(), self::MAX_TRIES_PER_ACCOUNT],
            [$portal . '-ip|' . $request->ip(), self::MAX_TRIES_PER_ADDRESS],
        ];
    }

    /**
     * "Try again in N minutes" while either limit is reached, otherwise null
     */
    private function lockoutMessage(Request $request, string $portal): ?string
    {
        foreach ($this->throttleKeys($request, $portal) as [$key, $max]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));
                return 'Too many failed sign-in attempts. Try again in ' . $minutes . ' minute' . ($minutes > 1 ? 's' : '') . '.';
            }
        }
        return null;
    }

    private function recordFailedLogin(Request $request, string $portal): void
    {
        foreach ($this->throttleKeys($request, $portal) as [$key, $max]) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);
            // Logged once, when the attempt that reaches the limit is made
            if (RateLimiter::attempts($key) === $max) {
                AuditLog::log('error', 'auth', 'Sign-in locked after ' . $max . ' failed attempts', [
                    'portal' => $portal,
                    'username' => Str::limit((string) $request->input('username'), 100),
                    'scope' => str_contains($key, '-ip|') ? 'address' : 'account',
                ]);
            }
        }
    }

    private function clearFailedLogins(Request $request, string $portal): void
    {
        RateLimiter::clear($this->throttleKeys($request, $portal)[0][0]);
    }

    public function getLogin()
    {
        return view('login.login');
    }

    public function postLogin(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        if ($locked = $this->lockoutMessage($request, 'login')) {
            return redirect()->back()->with('error', $locked)->withInput($request->only('username'));
        }

        // First, try to authenticate with just username and password
        $credentials = [
            'username' => $request->username,
            'password' => $request->password,
        ];

        if (auth()->guard('web')->attempt($credentials)) {
            // Get the authenticated user
            $user = auth()->user();
            
            // Check if user has a valid role
            if ($user->hasLoginRole()) {
                $this->clearFailedLogins($request, 'login');
                $request->session()->regenerate();
                SingleSession::establish($user, $request);
                PasswordPolicy::flagIfWeak($user, $request->password);
                AuditLog::log('login', 'auth', 'Signed in');
                return redirect()->route('dashboard')->with('success', 'You have successfully logged in.');
            }
            
            // If role is invalid, logout and show error
            auth()->logout();
            return redirect()->back()->with('error', 'Your account does not have a valid role.');
        }

        $this->recordFailedLogin($request, 'login');

        return redirect()->back()->with('error', 'Invalid username or password. Please try again.')
            ->withInput($request->only('username'));
    }

    /**
     * One-click login for demo mode (DEMO=true in .env).
     * Only accounts listed in config/demo.php can be used.
     */
    public function demoLogin(Request $request)
    {
        abort_unless(config('demo.enabled'), 404);

        $request->validate(['username' => 'required|string']);
        $account = collect(config('demo.accounts'))->firstWhere('username', $request->username);
        abort_unless($account, 403);

        $user = User::where('username', $account['username'])->first();

        if (!$user || $user->fname !== $account['fname'] || $user->lname !== $account['lname'] || $user->role !== $account['role']) {
            return redirect()->route('getLogin')->with('error', 'This demo account is unavailable. Ask an administrator to seed the demo users.');
        }

        auth()->guard('web')->login($user);
        $request->session()->regenerate();
        SingleSession::establish($user, $request);
        AuditLog::log('login', 'auth', 'Signed in with demo mode');

        return redirect()->route('dashboard')->with('success', 'Signed in as demo ' . $user->role . '.');
    }

    /**
     * Sign out whoever is signed in, whatever their role; an expired session just lands on the start page
     */
    public function logout(Request $request)
    {
        if (auth()->guard('web')->check()) {
            SingleSession::clearIfCurrent(auth()->guard('web')->user(), $request);
            AuditLog::log('logout', 'auth', 'Signed out');
            auth()->guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You have successfully logged out.']);
        }

        return redirect('/')->with('success', 'You have successfully logged out.');
    }

    public function getAdminLogin()
{
    // If already logged in as admin, redirect to admin dashboard
    if (auth()->guard('web')->check() && 
        auth()->user()->role === 'Administrator') {
        return redirect()->route('admin.dashboard');
    }
    
    // If logged in but not Administrator, force logout
    if (auth()->guard('web')->check()) {
        auth()->logout();
    }
    
    return view('login.admin-login');
}


public function postAdminLogin(Request $request)
{
    $request->validate([
        'username' => 'required',
        'password' => 'required',
        'security_key' => 'required',
    ]);

    if ($locked = $this->lockoutMessage($request, 'admin-login')) {
        return redirect()->back()
            ->with('admin_error', $locked)
            ->withInput($request->except(['password', 'security_key']));
    }

    // Verify security key first (constant-time compare; an unset key never matches)
    $securityKey = (string) config('app.admin_security_key');
    if ($securityKey === '' || !hash_equals($securityKey, (string) $request->security_key)) {
        $this->recordFailedLogin($request, 'admin-login');
        Log::warning('Invalid admin security key attempt from IP: ' . $request->ip());
        return redirect()->back()
            ->with('admin_error', 'Invalid security credentials. Access denied.')
            ->withInput($request->except(['password', 'security_key']));
    }

    // Attempt login
    $validated = auth()->guard('web')->attempt([
        'username' => $request->username,
        'password' => $request->password,
    ]);

    if ($validated) {
        $user = auth()->user();
        
        if ($user->role !== 'Administrator') {
            auth()->logout();
            $this->recordFailedLogin($request, 'admin-login');
            return redirect()->back()
                ->with('admin_error', 'Access denied. Administrator privileges required.')
                ->withInput($request->except(['password', 'security_key']));
        }
        
        Log::info('Admin login successful: ' . $user->username . ' from IP: ' . $request->ip());
        $this->clearFailedLogins($request, 'admin-login');
        $request->session()->regenerate();
        SingleSession::establish($user, $request);
        PasswordPolicy::flagIfWeak($user, $request->password);
        AuditLog::log('login', 'auth', 'Signed in through the admin portal');
        
        return redirect()->route('admin.dashboard')
            ->with('success', 'Welcome to the admin panel, ' . $user->fname . '.');
    }

    $this->recordFailedLogin($request, 'admin-login');
    Log::warning('Failed admin login from IP: ' . $request->ip());

    return redirect()->back()
        ->with('admin_error', 'Invalid administrator credentials.')
        ->withInput($request->except(['password', 'security_key']));
}






}
