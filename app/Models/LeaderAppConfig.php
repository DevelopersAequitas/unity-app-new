<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class LeaderAppConfig extends Model
{
    use HasFactory;

    protected $table = 'leader_app_configs';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'platform',
        'min_required_version',
        'latest_version',
        'store_url_android',
        'store_url_ios',
        'force_update_title',
        'force_update_message',
        'optional_update_title',
        'optional_update_message',
        'is_maintenance_mode',
        'maintenance_title',
        'maintenance_message',
        'allowed_bypass_roles',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_maintenance_mode' => 'boolean',
            'is_active' => 'boolean',
            'allowed_bypass_roles' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
