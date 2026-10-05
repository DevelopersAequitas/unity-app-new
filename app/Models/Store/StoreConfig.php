<?php

namespace App\Models\Store;

use Illuminate\Database\Eloquent\Model;

class StoreConfig extends Model
{
    protected $table = 'store_config';

    protected $primaryKey = 'config_key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'config_key',
        'config_value',
        'description',
        'updated_at',
    ];

    protected $casts = [
        'config_value' => 'json',
        'updated_at' => 'datetime',
    ];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $item = static::where('config_key', $key)->first();
        if (! $item) {
            return $default;
        }

        return $item->config_value ?? $default;
    }
}
