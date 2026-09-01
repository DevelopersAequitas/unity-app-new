<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AdminModule extends Model
{
    use HasFactory;

    protected $table = 'admin_modules';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'icon',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (AdminModule $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function pages(): HasMany
    {
        return $this->hasMany(AdminPage::class, 'module_id')->orderBy('sort_order');
    }

    public function roleModuleAccess(): HasMany
    {
        return $this->hasMany(RoleModuleAccess::class, 'module_id');
    }

    public function workflowApprovalRules(): HasMany
    {
        return $this->hasMany(WorkflowApprovalRule::class, 'module_id');
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
