<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppIconAsset extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'app_icon_assets';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'app_instance_id', 'icon_key', 'icon_name', 'icon_url',
        'fallback_asset', 'description', 'is_active', 'sort_order',
    ];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];
}
