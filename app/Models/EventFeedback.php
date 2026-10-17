<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EventFeedback extends Model
{
    use HasFactory;

    protected $table = 'event_feedbacks';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = true;

    protected $fillable = [
        'id',
        'event_id',
        'occurrence_id',
        'user_id',
        'rating',
        'feedback',
        'tags',
        'would_recommend',
        'notification_id',
    ];

    protected $casts = [
        'rating' => 'integer',
        'tags' => 'array',
        'would_recommend' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            if ($model->tags === null) {
                $model->tags = [];
            }
            if ($model->would_recommend === null) {
                $model->would_recommend = true;
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }
}
