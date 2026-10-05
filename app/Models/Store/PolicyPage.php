<?php

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyPage extends Model
{
    use HasUuids;

    protected $table = 'policy_pages';

    protected $fillable = [
        'key',
        'version',
        'title',
        'content',
        'body',
        'status',
        'published_at',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $page): void {
            if (empty($page->body) && ! empty($page->content)) {
                $page->body = $page->content;
            }
            if (empty($page->content) && ! empty($page->body)) {
                $page->content = $page->body;
            }
        });
    }

    protected $casts = [
        'version' => 'integer',
        'published_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
