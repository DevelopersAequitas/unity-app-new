<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CircularReaction extends Model
{
    use HasFactory;

    public const TYPES = ['helpful', 'important'];

    protected $table = 'circular_reactions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circular_id',
        'user_id',
        'reaction_type',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $reaction): void {
            if (empty($reaction->id)) {
                $reaction->id = Str::uuid()->toString();
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
