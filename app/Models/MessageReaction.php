<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MessageReaction extends Model
{
    use HasFactory;

    protected $table = 'message_reactions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'message_id',
        'user_id',
        'emoji',
        'message_type',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reaction): void {
            if (blank($reaction->id)) {
                $reaction->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function directMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'message_id');
    }

    public function circleMessage(): BelongsTo
    {
        return $this->belongsTo(CircleChatMessage::class, 'message_id');
    }
}
