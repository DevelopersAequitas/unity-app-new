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

    protected $table = 'web_leadership_nomination_answers';

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

    protected $appends = [
        'answer_text',
        'question_label',
    ];

    public function getAnswerTextAttribute(): ?string
    {
        $ans = $this->answer;
        if (is_array($ans)) {
            $val = $ans['value'] ?? $ans['text'] ?? null;
            if ($val !== null) {
                if (is_bool($val)) {
                    return $val ? 'true' : 'false';
                }
                return is_string($val) ? $val : json_encode($val);
            }
            return json_encode($ans);
        }
        if (is_bool($ans)) {
            return $ans ? 'true' : 'false';
        }
        return $ans !== null ? (string) $ans : null;
    }

    public function getQuestionLabelAttribute(): ?string
    {
        if ($this->relationLoaded('question') && $this->question?->label) {
            return $this->question->label;
        }

        $snapshot = $this->question_snapshot;
        if (is_array($snapshot) && ! empty($snapshot['label'])) {
            return (string) $snapshot['label'];
        }

        if (! empty($this->question_key)) {
            $clean = preg_replace('/^q_/', '', (string) $this->question_key);
            return ucwords(str_replace('_', ' ', (string) $clean));
        }

        return null;
    }

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
