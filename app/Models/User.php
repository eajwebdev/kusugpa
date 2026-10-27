<?php

namespace App\Models;

use App\Support\NavigationPermissions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'fname',
        'mname',
        'lname',
        'username',
        'password',
        'role',
        'must_change_password',
        'password_changed_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'active_session_hash',
        'google_id',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'must_change_password' => 'boolean',
        'password_changed_at' => 'datetime',
    ];

      public function permissions()
    {
        return $this->belongsToMany(Permission::class);
    }
    public function hasPermission($permissionSlug)
{
    // Only Administrator and super_admin have automatic full access
    if ($this->role === 'Administrator' || $this->role === 'super_admin') {
        return true;
    }
    
    // Manager and other roles must have explicit permissions assigned
    $parent = NavigationPermissions::parentFor($permissionSlug);
    return $this->permissions->contains('slug', $permissionSlug)
        || ($parent && $this->permissions->contains('slug', $parent));
}


    public function hasAnyPermission(array $permissions)
{
    // Only Administrator and super_admin have automatic full access
    if ($this->role === 'Administrator' || $this->role === 'super_admin') {
        return true;
    }
    
    // Manager and other roles must have explicit permissions assigned
    foreach ($permissions as $permission) {
        if ($this->hasPermission($permission)) {
            return true;
        }
    }
    return false;
}

    public function givePermission($permissionSlug)
    {
        $permission = Permission::where('slug', $permissionSlug)->first();
        
        if ($permission && !$this->permissions->contains($permission->id)) {
            $this->permissions()->attach($permission->id);
        }
        
        return $this;
    }

    public function removePermission($permissionSlug)
    {
        $permission = Permission::where('slug', $permissionSlug)->first();
        
        if ($permission) {
            $this->permissions()->detach($permission->id);
        }
        
        return $this;
    }

    public function syncPermissions(array $slugs)
    {
        $permissionIds = Permission::whereIn('slug', $slugs)->pluck('id');
        $this->permissions()->sync($permissionIds);
        
        return $this;
    }

    public function isAdmin()
    {
        return in_array($this->role, ['Administrator', 'super_admin']);
    }

    public function hasLoginRole(): bool
    {
        return in_array($this->role, ['Administrator', 'super_admin', 'manager', 'loan_officer', 'User', 'Viewer'], true);
    }

    /**
     * Demo accounts share a known password; only exempt them while demo mode is on
     */
    public function isDemoAccount(): bool
    {
        return config('demo.enabled')
            && collect(config('demo.accounts'))->contains('username', $this->username);
    }

    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password && !$this->isDemoAccount();
    }

    public function isManager()
    {
        return $this->role === 'manager';
    }

    public function isLoanOfficer()
    {
        return $this->role === 'loan_officer';
    }

    public function canManageUsers()
    {
        return $this->role === 'Administrator';
    }

    public function canViewUsers()
    {
        return in_array($this->role, ['Administrator', 'super_admin', 'manager']);
    }

    public function canApproveLoans()
    {
        return in_array($this->role, ['Administrator', 'super_admin', 'manager']);
    }

    public function canProcessLoans()
    {
        return in_array($this->role, ['Administrator', 'super_admin', 'manager', 'loan_officer']);
    }
}
