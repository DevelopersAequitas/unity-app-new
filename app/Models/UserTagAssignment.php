<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTagAssignment extends Model
{
    use HasFactory;

    protected $table = 'user_tag_assignments';

    protected $fillable = [
        'user_id',
        'tag_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tag(): BelongsTo
    {
        return $this->belongsTo(UserTag::class, 'tag_id');
    }
}
