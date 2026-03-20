<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'app_versions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'platform',
        'latest_version',
        'min_version',
        'update_type',
        'playstore_url',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
