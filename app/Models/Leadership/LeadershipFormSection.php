<?php

declare(strict_types=1);

namespace App\Models\Leadership;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeadershipFormSection extends Model
{
    use HasFactory;

    protected $table = 'leadership_form_sections';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'form_template_id',
        'title',
        'description',
        'sort_order',
        'is_required',
        'is_active',
        'visibility_rules',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
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

    public function formTemplate(): BelongsTo
    {
        return $this->belongsTo(LeadershipFormTemplate::class, 'form_template_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(LeadershipFormQuestion::class, 'section_id')->orderBy('sort_order');
    }
}
