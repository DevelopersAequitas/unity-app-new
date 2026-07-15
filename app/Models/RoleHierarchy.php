<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RoleHierarchy extends Model
{
    use HasFactory;

    protected $table = 'role_hierarchies';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'parent_role_id',
        'child_role_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (RoleHierarchy $hierarchy): void {
            if (empty($hierarchy->id)) {
                $hierarchy->id = (string) Str::uuid();
            }
        });
    }

    public function parentRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'parent_role_id');
    }

    public function childRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'child_role_id');
    }
}
