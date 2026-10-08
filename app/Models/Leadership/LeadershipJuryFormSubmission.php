<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeadershipJuryFormSubmission extends Model
{
    use HasFactory;

    protected $table = 'leadership_jury_form_submissions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'nomination_id',
        'form_template_id',
        'submitted_by',
        'status',
        'version',
        'submitted_at',
        'revision_requested_at',
        'revision_remarks',
    ];

    protected $casts = [
        'version' => 'integer',
        'submitted_at' => 'datetime',
        'revision_requested_at' => 'datetime',
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

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(LeadershipFormTemplate::class, 'form_template_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(LeadershipJuryFormAnswer::class, 'submission_id');
    }
}
