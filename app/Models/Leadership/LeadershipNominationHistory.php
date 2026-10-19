<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadershipNominationHistory extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'web_leadership_nomination_history';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'nomination_id',
        'previous_status',
        'new_status',
        'from_status',
        'to_status',
        'action',
        'remarks',
        'changed_by',
        'actor_id',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }

            if (empty($model->previous_status) && ! empty($model->from_status)) {
                $model->previous_status = (string) $model->from_status;
            }

            if (empty($model->new_status) && ! empty($model->to_status)) {
                $model->new_status = (string) $model->to_status;
            }

            if (empty($model->previous_status)) {
                $model->previous_status = 'submitted';
            }

            if (empty($model->new_status)) {
                $model->new_status = 'shortlisted';
            }

            if (empty($model->changed_by) && ! empty($model->actor_id)) {
                $model->changed_by = $model->actor_id;
            }
        });
    }

    public function nomination(): BelongsTo
    {
        return $this->belongsTo(LeadershipNomination::class, 'nomination_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
