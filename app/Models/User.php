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

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $hasRolePermission = RolePermission::where('role', $this->role)
            ->whereHas('permission', fn($q) => $q->where('name', $permission))
            ->exists();

        $userPermission = UserPermission::where('user_id', $this->id)
            ->whereHas('permission', fn($q) => $q->where('name', $permission))
            ->first();

        if ($userPermission) {
            return $userPermission->granted;
        }

        return $hasRolePermission;
    }

    public function getAllPermissions()
    {
        if ($this->isSuperAdmin()) {
            return Permission::pluck('name');
        }

        $rolePermissions = Permission::whereHas('rolePermissions', fn($q) =>
            $q->where('role', $this->role)
        )->pluck('name');

        $userPermissions = UserPermission::where('user_id', $this->id)
            ->with('permission')
            ->get();

        foreach ($userPermissions as $up) {
            if ($up->granted && !$rolePermissions->contains($up->permission->name)) {
                $rolePermissions->push($up->permission->name);
            } elseif (!$up->granted) {
                $rolePermissions = $rolePermissions->reject(fn($p) => $p === $up->permission->name);
            }
        }

        return $rolePermissions;
    }
}
