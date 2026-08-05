<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RoleModuleAccess extends Model
{
    use HasFactory;

    protected $table = 'role_module_access';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'role_id',
        'module_id',
        'is_visible',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (RoleModuleAccess $access): void {
            if (empty($access->id)) {
                $access->id = (string) Str::uuid();
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AdminModule::class, 'module_id');
    }
}
