<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppChangelog extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'app_changelogs';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'version',
        'platform',
        'title',
        'description',
        'features',
        'is_released',
        'released_at',
    ];

    protected $casts = [
        'features' => 'array',
        'is_released' => 'boolean',
        'released_at' => 'datetime',
    ];
}
