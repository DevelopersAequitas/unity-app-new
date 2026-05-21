<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ActivityCreative extends Model
{
    use HasFactory;

    protected $table = 'activity_creatives';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'activity_type',
        'activity_id',
        'user_id',
        'creative_title',
        'creative_text',
        'creative_image_path',
        'creative_image_url',
        'downloaded_count',
        'last_downloaded_at',
    ];

    protected $casts = [
        'downloaded_count' => 'integer',
        'last_downloaded_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
