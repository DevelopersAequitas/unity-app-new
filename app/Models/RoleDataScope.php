<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RoleDataScope extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'role_id',
        'scope_type',
        'scope_value',
    ];

    /**
     * scope_type: circle | district | industry | country | global
     * scope_value: null means "all of that type"; a UUID means one specific record.
     */
    protected static function booted(): void
    {
        static::creating(function (RoleDataScope $scope): void {
            if (empty($scope->id)) {
                $scope->id = (string) Str::uuid();
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function isGlobal(): bool
    {
        return $this->scope_type === 'global';
    }
}
