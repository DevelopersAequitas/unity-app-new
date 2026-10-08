<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LeadershipWinnerCreative extends Model
{
    use HasFactory;

    protected $table = 'leadership_winner_creatives';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'final_decision_id',
        'template_name',
        'version',
        'storage_disk',
        'file_path',
        'preview_path',
        'generation_status',
        'generation_error',
        'publication_status',
        'generated_by',
        'generated_at',
        'published_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'generated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function finalDecision(): BelongsTo
    {
        return $this->belongsTo(LeadershipFinalDecision::class, 'final_decision_id');
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
