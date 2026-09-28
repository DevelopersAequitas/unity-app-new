<?php

declare(strict_types=1);

namespace App\Models\Ask;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AskOptionGroup extends Model
{
    use HasFactory;

    protected $table = 'ask_option_groups';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name',
        'description',
        'input_type',
        'is_multi_select',
        'is_required',
        'is_active',
        'sort_order',
        'metadata',
    ];

    protected $casts = [
        'is_multi_select' => 'boolean',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
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

    public function options(): HasMany
    {
        return $this->hasMany(AskOption::class, 'option_group_id')->orderBy('sort_order');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AskAnswer::class, 'option_group_id');
    }
}
