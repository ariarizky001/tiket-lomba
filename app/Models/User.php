<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    protected $appends = ['permissions'];

    /**
     * Cached permissions for the current request to avoid N+1 queries
     *
     * @var array|null
     */
    protected $cachedPermissions = null;

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Load all permissions for this user once and cache them for the request
     *
     * @return array
     */
    protected function loadPermissions(): array
    {
        if ($this->cachedPermissions !== null) {
            return $this->cachedPermissions;
        }

        if ($this->isSuperAdmin()) {
            return $this->cachedPermissions = Permission::pluck('name')->toArray();
        }

        $rolePermissions = Permission::whereHas('rolePermissions', fn($q) =>
            $q->where('role', $this->role)
        )->pluck('name')->toArray();

        $userPermissions = UserPermission::where('user_id', $this->id)
            ->with('permission')
            ->get();

        foreach ($userPermissions as $up) {
            if ($up->granted && !in_array($up->permission->name, $rolePermissions)) {
                $rolePermissions[] = $up->permission->name;
            } elseif (!$up->granted) {
                $rolePermissions = array_filter($rolePermissions, fn($p) => $p !== $up->permission->name);
            }
        }

        return $this->cachedPermissions = array_values($rolePermissions);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $permissions = $this->loadPermissions();
        
        return in_array($permission, $permissions);
    }

    public function getAllPermissions()
    {
        return collect($this->loadPermissions());
    }

    public function getPermissionsAttribute()
    {
        return $this->loadPermissions();
    }
}
