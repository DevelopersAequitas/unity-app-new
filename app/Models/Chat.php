<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Chat extends Model
{
    use HasFactory;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user1_id',
        'user2_id',
        'user_one_id',
        'user_two_id',
        'chat_type',
        'last_message_at',
        'last_message_id',
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    public function user1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_participants', 'chat_id', 'user_id')->withTimestamps();
    }

    public function canAccessChat(User $user): bool
    {
        return (string) $this->user1_id === (string) $user->id
            || (string) $this->user2_id === (string) $user->id
            || (string) ($this->user_one_id ?? '') === (string) $user->id
            || (string) ($this->user_two_id ?? '') === (string) $user->id;
    }
}
