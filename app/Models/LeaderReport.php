<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class LeaderReport extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'leader_reports';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'circle_id',
        'district_id',
        'industry_id',
        'submitted_by_user_id',
        'submitter_role',
        'report_type',
        'period',
        'attendance_percentage',
        'deals_closed_value',
        'total_revenue',
        'deals_amount',
        'content',
        'summary_text',
        'highlights',
        'challenges_faced',
        'action_items',
        'included_sections',
        'peers_roster',
        'status',
        'reviewed_by_user_id',
        'reviewed_at',
    ];

    protected $casts = [
        'attendance_percentage' => 'float',
        'deals_amount' => 'float',
        'included_sections' => 'array',
        'peers_roster' => 'array',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (LeaderReport $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
