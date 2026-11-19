<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CelebrationPublishLog extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'celebration_publish_logs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'celebration_schedule_id',
        'user_id',
        'event_type',
        'event_year',
        'action',
        'status',
        'attempt_number',
        'post_id',
        'creative_file_id',
        'notification_id',
        'message',
        'error_details',
        'context',
        'created_at',
    ];

    protected $casts = [
        'event_year' => 'integer',
        'attempt_number' => 'integer',
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(CelebrationSchedule::class, 'celebration_schedule_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'post_id')->withTrashed();
    }
}
