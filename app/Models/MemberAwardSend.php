<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemberAwardSend extends Model
{
    use HasFactory;

    protected $table = 'member_award_sends';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'award_track',
        'award_title',
        'milestone_count',
        'template_name',
        'language',
        'phone_number',
        'creative_image_url',
        'referral_link',
        'status',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'read_at',
        'provider_message_id',
        'payload_json',
        'provider_response_json',
        'error_message',
        'is_test',
    ];

    protected $casts = [
        'milestone_count' => 'integer',
        'is_test' => 'boolean',
        'payload_json' => 'array',
        'provider_response_json' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    public function scopeGrowth(Builder $query): Builder
    {
        return $query->where('award_track', 'GROWTH');
    }

    public function scopeLifeImpact(Builder $query): Builder
    {
        return $query->where('award_track', 'LIFE_IMPACT');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->whereIn('status', ['sent', 'delivered', 'read']);
    }

    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }
}
