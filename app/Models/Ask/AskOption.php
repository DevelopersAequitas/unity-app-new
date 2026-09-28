<?php

declare(strict_types=1);

namespace App\Models\Ask;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AskOption extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ask_options';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'option_group_id',
        'flow_id',
        'ask_type_id',
        'parent_option_id',
        'code',
        'label',
        'description',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(AskOptionGroup::class, 'option_group_id');
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AskFlow::class, 'flow_id');
    }

    public function askType(): BelongsTo
    {
        return $this->belongsTo(AskType::class, 'ask_type_id');
    }

    public function parentOption(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_option_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_option_id')->orderBy('sort_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AskAnswer::class, 'option_id');
    }
}
