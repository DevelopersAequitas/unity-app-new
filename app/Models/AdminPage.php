<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AdminPage extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'module_id',
        'page_name',
        'route_name',
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
        static::creating(function (AdminPage $page): void {
            if (empty($page->id)) {
                $page->id = (string) Str::uuid();
            }
        });
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(AdminModule::class, 'module_id');
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(PageGroup::class, 'page_group_items', 'page_id', 'group_id');
    }

    public function rolePagePermissions(): HasMany
    {
        return $this->hasMany(RolePagePermission::class, 'page_id');
    }
}
