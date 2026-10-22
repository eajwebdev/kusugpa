<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'user_name', 'user_role', 'action', 'module',
        'description', 'details', 'ip_address', 'user_agent'
    ];

    protected $casts = [
        'details' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->role === 'Administrator') {
            return $query;
        }

        $lowerRoles = match ($viewer->role) {
            'super_admin' => ['manager', 'loan_officer', 'User', 'Viewer'],
            'manager' => ['loan_officer', 'User', 'Viewer'],
            default => [],
        };

        return $query->where(function (Builder $visible) use ($viewer, $lowerRoles) {
            $visible->where('user_id', $viewer->getKey());

            if ($lowerRoles) {
                $visible->orWhere(function (Builder $subordinate) use ($lowerRoles) {
                    $subordinate->whereIn('user_role', $lowerRoles)
                        ->where(function (Builder $actor) use ($lowerRoles) {
                            $actor->whereNull('user_id')
                                ->orWhereHas('user', fn (Builder $user) => $user->whereIn('role', $lowerRoles));
                        });
                });
            }
        });
    }

    /**
     * Create an audit log entry
     */
    public static function log($action, $module, $description, $details = null)
    {
        $user = auth()->user();
        
        return self::create([
            'user_id' => $user ? $user->id : null,
            'user_name' => $user ? $user->fname . ' ' . $user->lname : 'System',
            'user_role' => $user ? $user->role : 'System',
            'action' => $action,
            'module' => $module,
            'description' => $description,
            'details' => $details,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
