<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'key',
        'name',
        'description',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Permission $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });

        static::saved(function (): void {
            Cache::forget('permissions:all');
            Cache::forget('permissions:key_map');
        });

        static::deleted(function (): void {
            Cache::forget('permissions:all');
            Cache::forget('permissions:key_map');
        });
    }

    public function rolePagePermissions(): HasMany
    {
        return $this->hasMany(RolePagePermission::class, 'permission_id');
    }

    public static function idByKey(string $key): ?string
    {
        $map = Cache::remember('permissions:key_map', 300, function () {
            return static::query()->pluck('id', 'key')->all();
        });

        return $map[$key] ?? null;
    }
}
