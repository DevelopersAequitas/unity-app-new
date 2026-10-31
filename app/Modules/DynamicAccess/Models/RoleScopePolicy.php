<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Models;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RoleScopePolicy extends Model
{
    use HasFactory;

    protected $table = 'role_scope_policies';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'role_id',
        'resource_key',
        'scope_type',
        'include_assigned_circles',
        'include_child_circles',
        'include_same_district',
        'include_same_industry',
        'include_all_peers_in_scope',
        'allow_cross_circle_read',
        'allow_cross_circle_write',
        'allow_export',
        'custom_filter_config',
    ];

    protected $casts = [
        'include_assigned_circles' => 'boolean',
        'include_child_circles' => 'boolean',
        'include_same_district' => 'boolean',
        'include_same_industry' => 'boolean',
        'include_all_peers_in_scope' => 'boolean',
        'allow_cross_circle_read' => 'boolean',
        'allow_cross_circle_write' => 'boolean',
        'allow_export' => 'boolean',
        'custom_filter_config' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }
}

