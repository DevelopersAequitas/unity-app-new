<?php

declare(strict_types=1);

namespace App\Models\Ask;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AskFlow extends Model
{
    use HasFactory;

    protected $table = 'ask_flows';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'code',
        'name',
        'description',
        'icon',
        'sort_order',
        'is_active',
        'metadata',
    ];

    protected $casts = [
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

    public function types(): HasMany
    {
        return $this->hasMany(AskType::class, 'flow_id')->orderBy('sort_order');
    }

    public function asks(): HasMany
    {
        return $this->hasMany(Ask::class, 'flow_id');
    }
}
