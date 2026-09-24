<?php

declare(strict_types=1);

namespace App\Models\Ask;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AskType extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'ask_types';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'flow_id',
        'parent_id',
        'code',
        'name',
        'description',
        'level',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'level' => 'integer',
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

    public function flow(): BelongsTo
    {
        return $this->belongsTo(AskFlow::class, 'flow_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    public function options(): HasMany
    {
        return $this->hasMany(AskOption::class, 'ask_type_id')->orderBy('sort_order');
    }

    public function asks(): HasMany
    {
        return $this->hasMany(Ask::class, 'type_id');
    }
}
