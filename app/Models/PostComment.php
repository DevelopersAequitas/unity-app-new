<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PostComment extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static ?array $existingColumns = null;

    protected static function booted(): void
    {
        static::creating(function (self $comment): void {
            if (empty($comment->id)) {
                $comment->id = (string) Str::uuid();
            }
        });

        static::saving(function (self $comment): void {
            if (static::$existingColumns === null) {
                try {
                    static::$existingColumns = Schema::hasTable('post_comments')
                        ? Schema::getColumnListing('post_comments')
                        : [];
                } catch (\Throwable) {
                    static::$existingColumns = [];
                }
            }

            if (! empty(static::$existingColumns)) {
                foreach (array_keys($comment->attributes) as $attr) {
                    if (! in_array($attr, static::$existingColumns, true)) {
                        unset($comment->attributes[$attr]);
                    }
                }
            }
        });
    }

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'content',
        'mentions',
        'tagged_peer_ids',
    ];

    protected $casts = [
        'mentions' => 'array',
        'tagged_peer_ids' => 'array',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PostComment::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(PostComment::class, 'parent_id');
    }
}
