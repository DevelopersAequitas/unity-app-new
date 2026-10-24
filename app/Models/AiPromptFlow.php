<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiPromptFlow extends Model
{
    use HasFactory;

    protected $table = 'ai_prompt_flows';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'slug',
        'name',
        'description',
        'model',
        'system_instruction',
        'prompt_template',
        'temperature',
        'max_tokens',
        'is_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'temperature' => 'float',
        'max_tokens' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
