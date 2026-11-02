<?php

declare(strict_types=1);

namespace App\Models\Finance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FinanceAcquisitionChannel extends Model
{
    use HasFactory;

    protected $table = 'finance_acquisition_channels';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'category_code',
        'channel_key',
        'name',
        'description',
        'conditions_list',
        'responsibilities_list',
        'priority_order',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'conditions_list' => 'array',
        'responsibilities_list' => 'array',
        'priority_order' => 'integer',
        'is_system' => 'boolean',
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

    public function commissionRules(): HasMany
    {
        return $this->hasMany(FinanceCommissionRule::class, 'channel_id');
    }
}
