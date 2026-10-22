<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Permission;
use App\Models\AuditLog;
use App\Support\NavigationPermissions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserManagementController extends Controller
{
    public function __construct()
    {
        // Ensure only Administrator and super_admin can access this controller
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!in_array($user->role, ['Administrator', 'super_admin'])) {
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'You do not have permission to access User Management.'], 403);
                }
                return redirect()->route('dashboard')
                    ->with('error', 'You do not have permission to access User Management.');
            }
            return $next($request);
        });
    }

    public function userManagement()
    {
        $users = User::with('permissions')->orderBy('created_at', 'desc')->get();
        
        return view('user-management.index', [
            'users' => $users,
            'allPermissions' => Permission::all(),
            'permissionGroups' => NavigationPermissions::GROUPS,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users'],
            'password' => ['required', Password::defaults()],
            'role' => ['required', 'in:Administrator,super_admin,manager,loan_officer,User,Viewer'],
        ]);

        $currentUser = auth()->user();

        if ($request->role === 'Administrator' && $currentUser->role !== 'Administrator') {
            AuditLog::log('error', 'users', 'Unauthorized attempt to create Administrator account');
            return response()->json(['message' => 'Only an Administrator can create Administrator accounts.'], 403);
        }

        if ($request->role === 'super_admin' && !in_array($currentUser->role, ['Administrator', 'super_admin'])) {
            AuditLog::log('error', 'users', 'Unauthorized attempt to create Super Admin account');
            return response()->json(['message' => 'Only an Administrator or Super Admin can create Super Admin accounts.'], 403);
        }

        $user = User::create([
            'fname' => $request->fname,
            'mname' => $request->mname ?? null,
            'lname' => $request->lname ?? '',
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        AuditLog::log('create', 'users', 'Created user: ' . $user->username . ' with role ' . $user->role, [
            'user_id' => $user->id,
            'created_by' => $currentUser->username
        ]);

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->load('permissions')
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['nullable', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $user->id],
            'role' => ['required', 'in:Administrator,super_admin,manager,loan_officer,User,Viewer'],
        ]);

        $currentUser = auth()->user();
        $isOwnAccount = $user->id === $currentUser->id;
        $oldRole = $user->role;

        if ($isOwnAccount && $currentUser->role === 'Administrator') {
            if ($request->role !== 'Administrator') {
                AuditLog::log('error', 'users', 'Administrator attempted to change own role');
                return response()->json(['message' => 'You cannot change your own role from Administrator.'], 403);
            }
            
            $updateData = [
                'fname' => $request->fname,
                'mname' => $request->mname ?? $user->mname,
                'lname' => $request->lname ?? '',
                'username' => $request->username,
                'role' => $request->role,
            ];

            if ($request->filled('password')) {
                $request->validate(['password' => [Password::defaults()]]);
                $updateData['password'] = Hash::make($request->password);
            }

            $user->update($updateData);

            AuditLog::log('update', 'users', 'Administrator updated own account', [
                'changes' => array_keys($updateData)
            ]);

            return response()->json([
                'message' => 'Your account has been updated successfully',
                'user' => $user->fresh()->load('permissions')
            ]);
        }

        if ($isOwnAccount && $currentUser->role === 'super_admin') {
            if ($request->role !== 'super_admin') {
                AuditLog::log('error', 'users', 'Super admin attempted to change own role');
                return response()->json(['message' => 'You cannot change your own role.'], 403);
            }
        }

        if ($isOwnAccount && !in_array($currentUser->role, ['Administrator', 'super_admin'])) {
            AuditLog::log('error', 'users', 'User attempted to edit own account without permission');
            return response()->json(['message' => 'You cannot edit your own account.'], 403);
        }

        if ($user->role === 'Administrator' && $currentUser->role !== 'Administrator') {
            AuditLog::log('error', 'users', 'Unauthorized attempt to modify Administrator account: ' . $user->username);
            return response()->json(['message' => 'Only an Administrator can modify Administrator accounts.'], 403);
        }

        if ($request->role === 'Administrator' && $user->role !== 'Administrator' && $currentUser->role !== 'Administrator') {
            AuditLog::log('error', 'users', 'Unauthorized attempt to promote user to Administrator');
            return response()->json(['message' => 'Only an Administrator can promote users to Administrator.'], 403);
        }

        if ($user->role === 'Administrator' && $currentUser->role === 'super_admin') {
            AuditLog::log('error', 'users', 'Super admin attempted to modify Administrator account');
            return response()->json(['message' => 'Super admin cannot modify Administrator accounts.'], 403);
        }

        if ($user->role === 'super_admin' && $currentUser->role === 'super_admin' && !in_array($request->role, ['Administrator', 'super_admin'])) {
            AuditLog::log('error', 'users', 'Super admin attempted to demote another super_admin');
            return response()->json(['message' => 'You cannot demote other super_admin accounts. Only Administrator can do this.'], 403);
        }

        $updateData = [
            'fname' => $request->fname,
            'mname' => $request->mname ?? $user->mname,
            'lname' => $request->lname ?? '',
            'username' => $request->username,
            'role' => $request->role,
        ];

        if ($request->filled('password')) {
            $request->validate(['password' => [Password::defaults()]]);
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        AuditLog::log('update', 'users', 'Updated user: ' . $user->username, [
            'old_role' => $oldRole,
            'new_role' => $request->role,
            'updated_by' => $currentUser->username
        ]);

        return response()->json([
            'message' => 'User updated successfully',
            'user' => $user->fresh()->load('permissions')
        ]);
    }

    public function destroy(User $user)
    {
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id) {
            AuditLog::log('error', 'users', 'User attempted to delete own account');
            return response()->json(['message' => 'Cannot delete your own account'], 403);
        }

        if ($user->role === 'Administrator' && $currentUser->role !== 'Administrator') {
            AuditLog::log('error', 'users', 'Unauthorized attempt to delete Administrator: ' . $user->username);
            return response()->json(['message' => 'Only an Administrator can delete Administrator accounts.'], 403);
        }

        if ($user->role === 'Administrator' && $currentUser->role === 'super_admin') {
            AuditLog::log('error', 'users', 'Super admin attempted to delete Administrator account');
            return response()->json(['message' => 'Super admin cannot delete Administrator accounts.'], 403);
        }

        if ($user->role === 'super_admin' && $currentUser->role === 'super_admin') {
            AuditLog::log('error', 'users', 'Super admin attempted to delete another super_admin');
            return response()->json(['message' => 'Super admin cannot delete other super_admin accounts.'], 403);
        }

        $deletedUser = [
            'id' => $user->id,
            'username' => $user->username,
            'role' => $user->role,
            'name' => $user->fname . ' ' . $user->lname
        ];

        $user->permissions()->detach();
        $user->delete();

        AuditLog::log('delete', 'users', 'Deleted user: ' . $deletedUser['username'], [
            'deleted_user' => $deletedUser,
            'deleted_by' => $currentUser->username
        ]);

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function assignPermissions(Request $request)
{
    $request->validate([
        'user_id' => ['required', 'exists:users,id'],
        'permissions' => ['required', 'array'],
        'permissions.*.slug' => ['required', 'string', 'exists:permissions,slug'],
        'permissions.*.granted' => ['required', 'boolean'],
    ]);

    $currentUser = auth()->user();
    $user = User::findOrFail($request->user_id);

    if ($user->role === 'Administrator' || $user->role === 'super_admin') {
        return response()->json(['message' => $user->role . ' has all permissions by default'], 403);
    }

    if (!in_array($currentUser->role, ['Administrator', 'super_admin'])) {
        return response()->json(['message' => 'You do not have permission to manage permissions.'], 403);
    }

    if ($currentUser->role === 'super_admin' && !in_array($user->role, ['User', 'Viewer'], true)) {
        return response()->json(['message' => 'Only an Administrator can manage this user\'s permissions.'], 403);
    }

    // Get current permissions before update
    $currentPermissions = $user->permissions->pluck('slug')->toArray();
    
    $grantedSlugs = [];
    $revokedSlugs = [];
    
    foreach ($request->permissions as $permission) {
        if ($permission['granted']) {
            $grantedSlugs[] = $permission['slug'];
        } else {
            $revokedSlugs[] = $permission['slug'];
        }
    }

    // Find which permissions are newly granted
    $newlyGranted = array_diff($grantedSlugs, $currentPermissions);
    
    // Find which permissions are newly revoked
    $newlyRevoked = array_intersect($revokedSlugs, $currentPermissions);

    $user->syncPermissions($grantedSlugs);

    // Get permission names for readable log
    $grantedNames = Permission::whereIn('slug', $newlyGranted)->pluck('name')->toArray();
    $revokedNames = Permission::whereIn('slug', $newlyRevoked)->pluck('name')->toArray();

    // Build detailed description
    $description = 'Updated permissions for: ' . $user->username;
    $details = [
        'user_id' => $user->id,
        'updated_by' => $currentUser->username,
        'granted' => $grantedNames,
        'revoked' => $revokedNames,
        'total_permissions' => count($grantedSlugs),
    ];

    // Create a more readable description for the logs table
    if (!empty($newlyGranted) && !empty($newlyRevoked)) {
        $description .= ' | Granted: ' . implode(', ', $grantedNames) . ' | Revoked: ' . implode(', ', $revokedNames);
    } elseif (!empty($newlyGranted)) {
        $description .= ' | Granted: ' . implode(', ', $grantedNames);
    } elseif (!empty($newlyRevoked)) {
        $description .= ' | Revoked: ' . implode(', ', $revokedNames);
    } else {
        $description .= ' | No changes made';
    }

    AuditLog::log('update', 'permissions', $description, $details);

    return response()->json([
        'message' => 'Permissions updated successfully',
        'permissions' => $user->fresh()->permissions
    ]);
}

    public function permissionsList()
    {
        $permissions = Permission::all()->map(function($p) {
            return [
                'id' => $p->id,
                'slug' => $p->slug,
                'label' => $p->name,
                'description' => $p->description,
            ];
        });
        
        return response()->json($permissions);
    }

}
