<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LeadershipGroupMember extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'leadership_group_members';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circle_id',
        'user_id',
        'leader_role',
        'title',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $member): void {
            if (blank($member->id)) {
                $member->id = (string) Str::uuid();
            }
        });
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
