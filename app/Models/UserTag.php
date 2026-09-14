<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserTag extends Model
{
    use HasFactory;

    public const SLUG_TEAM_MEMBER = 'team_member';

    public const PROTECTED_SLUGS = [
        self::SLUG_TEAM_MEMBER,
    ];

    protected $table = 'user_tags';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_tag_assignments',
            'tag_id',
            'user_id'
        )->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UserTagAssignment::class, 'tag_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function isSystemTag(): bool
    {
        return in_array($this->slug, self::PROTECTED_SLUGS, true);
    }
}
