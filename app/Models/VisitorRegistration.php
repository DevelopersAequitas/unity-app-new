<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class VisitorRegistration extends Model
{
    protected $table = 'visitor_registrations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'event_type',
        'event_name',
        'event_date',
        'visitor_full_name',
        'visitor_mobile',
        'visitor_email',
        'visitor_city',
        'visitor_business',
        'visitor_business_category_id',
        'visitor_business_category',
        'visitor_business_website',
        'invited_by_type',
        'invited_by_user_id',
        'how_known',
        'note',
        'status',
        'reviewed_by_admin_user_id',
        'reviewed_at',
        'coins_awarded',
        'coins_awarded_at',
    ];

    protected $casts = [
        'visitor_business_category_id' => 'integer',
        'event_date' => 'datetime',
        'reviewed_at' => 'datetime',
        'coins_awarded' => 'boolean',
        'coins_awarded_at' => 'datetime',
    ];

    protected $appends = [
        'visitor_designation',
        'visitor_business_brief',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->id) {
                $model->id = (string) Str::uuid();
            }
            if (empty($model->status)) {
                $model->status = 'pending';
            }
        });
    }

    public function getVisitorDesignationAttribute(): ?string
    {
        return $this->attributes['how_known'] ?? null;
    }

    public function setVisitorDesignationAttribute(?string $value): void
    {
        $this->attributes['how_known'] = $value;
    }

    public function getVisitorBusinessBriefAttribute(): ?string
    {
        return $this->attributes['note'] ?? null;
    }

    public function setVisitorBusinessBriefAttribute(?string $value): void
    {
        $this->attributes['note'] = $value;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by_admin_user_id');
    }

    public function invitedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }
}
