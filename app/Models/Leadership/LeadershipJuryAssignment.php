<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class LeadershipJuryAssignment extends Model
{
    use HasFactory;

    protected $table = 'leadership_jury_assignments';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'nomination_id',
        'juror_user_id',
        'status',
        'invitation_sent_at',
        'due_at',
        'started_at',
        'completed_at',
        'conflict_declared',
        'conflict_details',
        'assignment_remarks',
        'assigned_by',
    ];

    protected $casts = [
        'conflict_declared' => 'boolean',
        'invitation_sent_at' => 'datetime',
        'due_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(LeadershipNomination::class, 'nomination_id');
    }

    public function juror(): BelongsTo
    {
        return $this->belongsTo(User::class, 'juror_user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(LeadershipJuryScore::class, 'assignment_id');
    }

    public function report(): HasOne
    {
        return $this->hasOne(LeadershipJuryReport::class, 'assignment_id');
    }
}
