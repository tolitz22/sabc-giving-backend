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

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_TREASURER = 'treasurer';
    public const ROLE_VIEWER = 'viewer';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_TREASURER,
        self::ROLE_VIEWER,
    ];

    private const ROLE_PERMISSIONS = [
        self::ROLE_SUPER_ADMIN => [
            'users.view',
            'users.create',
            'users.update',
            'users.disable',
            'users.assign_roles',
            'donations.view',
            'donations.verify',
            'donations.reject',
            'donations.delete_proof',
            'proofs.view',
            'audit.view',
        ],
        self::ROLE_TREASURER => [
            'donations.view',
            'donations.verify',
            'donations.reject',
            'donations.delete_proof',
            'proofs.view',
            'audit.view',
        ],
        self::ROLE_VIEWER => [
            'donations.view',
            'proofs.view',
            'audit.view',
        ],
    ];

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
        'last_login_at',
        'created_by',
        'disabled_at',
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'disabled_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasPermission(string $permission): bool
    {
        $role = $this->role === 'admin' ? self::ROLE_SUPER_ADMIN : $this->role;

        return in_array($permission, self::ROLE_PERMISSIONS[$role] ?? [], true);
    }

    public function permissions(): array
    {
        $role = $this->role === 'admin' ? self::ROLE_SUPER_ADMIN : $this->role;

        return self::ROLE_PERMISSIONS[$role] ?? [];
    }

    public function isActiveAdmin(): bool
    {
        return ! $this->disabled_at && in_array($this->role, array_merge(self::ROLES, ['admin']), true);
    }

    public function creator()
    {
        return $this->belongsTo(self::class, 'created_by');
    }
}
