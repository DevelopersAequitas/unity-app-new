<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImpactAction extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'impact_actions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
        'impact_score',
        'impact_coin',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'impact_score' => 'integer',
        'impact_coin' => 'integer',
    ];
}
