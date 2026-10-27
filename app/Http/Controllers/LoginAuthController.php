<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log; 
use App\Models\AuditLog;
use App\Models\User;
use App\Support\PasswordPolicy;
use App\Support\SingleSession;

class LoginAuthController extends Controller
{
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

    // Verify security key first
    if ($request->security_key !== config('app.admin_security_key')) {
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
            return redirect()->back()
                ->with('admin_error', 'Access denied. Administrator privileges required.')
                ->withInput($request->except(['password', 'security_key']));
        }
        
        Log::info('Admin login successful: ' . $user->username . ' from IP: ' . $request->ip());
        $request->session()->regenerate();
        SingleSession::establish($user, $request);
        PasswordPolicy::flagIfWeak($user, $request->password);
        AuditLog::log('login', 'auth', 'Signed in through the admin portal');
        
        return redirect()->route('admin.dashboard')
            ->with('success', 'Welcome to the admin panel, ' . $user->fname . '.');
    }

    Log::warning('Failed admin login from IP: ' . $request->ip());
    
    return redirect()->back()
        ->with('admin_error', 'Invalid administrator credentials.')
        ->withInput($request->except(['password', 'security_key']));
}






}
