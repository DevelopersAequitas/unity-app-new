<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PageGroup extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (PageGroup $group): void {
            if (empty($group->id)) {
                $group->id = (string) Str::uuid();
            }
        });
    }

    public function pages(): BelongsToMany
    {
        return $this->belongsToMany(AdminPage::class, 'page_group_items', 'group_id', 'page_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PageGroupItem::class, 'group_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_page_groups', 'group_id', 'role_id');
    }
}
