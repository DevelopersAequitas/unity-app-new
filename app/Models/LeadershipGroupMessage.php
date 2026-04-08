<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LeadershipGroupMessage extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'leadership_group_messages';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circle_id',
        'sender_user_id',
        'message_type',
        'message_text',
        'reply_to_message_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            if (blank($message->id)) {
                $message->id = (string) Str::uuid();
            }
        });
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(LeadershipGroupMessageRead::class, 'message_id');
    }

    public function deletions(): HasMany
    {
        return $this->hasMany(LeadershipGroupMessageDeletion::class, 'message_id');
    }
}
