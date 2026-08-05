<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class AdminUser extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    protected $table = 'admin_users';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
        'email',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_user_roles', 'user_id', 'role_id');
    }

    /**
     * Returns all role IDs assigned to this admin user.
     *
     * @return array<string>
     */
    public function roleIds(): array
    {
        return $this->roles->pluck('id')->all();
    }

    /**
     * Returns all role keys assigned to this admin user.
     *
     * @return array<string>
     */
    public function roleKeys(): array
    {
        return $this->roles->pluck('key')->all();
    }

    public function hasRole(string $roleKey): bool
    {
        return in_array($roleKey, $this->roleKeys(), true);
    }

    public function isGlobalAdmin(): bool
    {
        return $this->hasRole('global_admin');
    }
}
