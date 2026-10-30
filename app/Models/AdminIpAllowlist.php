<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AdminIpAllowlist extends Model
{
    use HasFactory;

    protected $table = 'admin_ip_allowlists';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'environment',
        'ip_address',
        'ip_version',
        'label',
        'is_active',
        'created_by_user_id',
        'created_by_email',
        'updated_by_user_id',
        'updated_by_email',
    ];

    protected $casts = [
        'ip_version' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}

