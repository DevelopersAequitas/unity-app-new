<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntroVideoLike extends Model
{
    use HasFactory;

    protected $table = 'intro_video_likes';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'video_owner_id',
        'intro_video_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $like): void {
            if (empty($like->id)) {
                $like->id = (string) Str::uuid();
            }
            if (empty($like->created_at)) {
                $like->created_at = now();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function videoOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'video_owner_id');
    }
}
