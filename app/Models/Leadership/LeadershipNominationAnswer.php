<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadershipNominationAnswer extends Model
{
    use HasFactory;

    protected $table = 'leadership_nomination_answers';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'nomination_id',
        'question_id',
        'question_key',
        'question_snapshot',
        'answer',
    ];

    protected $casts = [
        'question_snapshot' => 'array',
        'answer' => 'array',
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

    public function question(): BelongsTo
    {
        return $this->belongsTo(LeadershipFormQuestion::class, 'question_id');
    }
}
