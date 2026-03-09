<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CircleChatMessageDeletion extends Model
{
    use HasFactory;

    protected $table = 'circle_chat_message_deletions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'message_id',
        'user_id',
        'deleted_at',
    ];

    protected $casts = [
        'deleted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $deletion): void {
            if (blank($deletion->id)) {
                $deletion->id = (string) Str::uuid();
            }
        });
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(CircleChatMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
