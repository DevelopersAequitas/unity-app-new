<?php

declare(strict_types=1);

namespace App\Models\Ask;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AskAnswer extends Model
{
    use HasFactory;

    protected $table = 'ask_answers';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'ask_id',
        'option_group_id',
        'option_id',
        'field_key',
        'value_text',
        'value_number',
        'value_boolean',
        'value_json',
        'sort_order',
    ];

    protected $casts = [
        'value_number' => 'decimal:6',
        'value_boolean' => 'boolean',
        'value_json' => 'array',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function ask(): BelongsTo
    {
        return $this->belongsTo(Ask::class, 'ask_id');
    }

    public function optionGroup(): BelongsTo
    {
        return $this->belongsTo(AskOptionGroup::class, 'option_group_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(AskOption::class, 'option_id');
    }
}
