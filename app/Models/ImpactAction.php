<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImpactAction extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'impact_actions';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'is_active',
        'sort_order',
        'impact_score',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'impact_score' => 'integer',
    ];
}
