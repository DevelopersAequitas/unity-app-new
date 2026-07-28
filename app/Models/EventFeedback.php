<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventFeedback extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'event_feedback';

    protected $keyType = 'string';

    public $incrementing = false;

    // Disabled standard timestamps since it has submitted_at instead of created_at/updated_at
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'respondent_user_id',
        'respondent_name',
        'overall_rating',
        'content_rating',
        'venue_rating',
        'networking_rating',
        'would_recommend',
        'what_worked',
        'what_to_improve',
        'additional_comments',
        'submitted_at',
    ];

    protected $casts = [
        'overall_rating' => 'integer',
        'content_rating' => 'integer',
        'venue_rating' => 'integer',
        'networking_rating' => 'integer',
        'would_recommend' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function respondent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'respondent_user_id');
    }
}
