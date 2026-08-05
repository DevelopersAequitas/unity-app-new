<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RolePageGroup extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'role_id',
        'group_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (RolePageGroup $rpg): void {
            if (empty($rpg->id)) {
                $rpg->id = (string) Str::uuid();
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(PageGroup::class, 'group_id');
    }
}
