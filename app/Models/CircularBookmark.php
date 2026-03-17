<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CircularBookmark extends Model
{
    use HasFactory;

    protected $table = 'circular_bookmarks';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circular_id',
        'user_id',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $bookmark): void {
            if (empty($bookmark->id)) {
                $bookmark->id = Str::uuid()->toString();
            }
        });
    }

    public function circular(): BelongsTo
    {
        return $this->belongsTo(Circular::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
