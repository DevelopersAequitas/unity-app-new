<?php

declare(strict_types=1);

namespace App\Models\Web;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WebBlog extends Model
{
    use HasFactory;

    protected $table = 'web_blogs';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'title',
        'slug',
        'excerpt',
        'content',
        'cover_image',
        'author_name',
        'author_role',
        'category',
        'tags',
        'read_time',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebBlog $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->slug) && ! empty($model->title)) {
                $model->slug = Str::slug($model->title);
            }
        });
    }
}
