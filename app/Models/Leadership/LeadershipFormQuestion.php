<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeadershipFormQuestion extends Model
{
    use HasFactory;

    protected $table = 'leadership_form_questions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'section_id',
        'question_key',
        'label',
        'description',
        'field_type',
        'placeholder',
        'is_required',
        'is_active',
        'sort_order',
        'validation_rules',
        'field_configuration',
        'visibility_rules',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'validation_rules' => 'array',
        'field_configuration' => 'array',
        'visibility_rules' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(LeadershipFormSection::class, 'section_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(LeadershipFormOption::class, 'question_id')->orderBy('sort_order');
    }
}
