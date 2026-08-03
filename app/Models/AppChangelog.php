<?php

declare(strict_types=1);

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

    /**
     * Get the platform attribute as an array.
     */
    public function getPlatformAttribute($value): array
    {
        if (is_null($value)) {
            return [];
        }

        if (is_array($value)) {
            return array_values($value);
        }

        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) {
            return array_values($decoded);
        }

        if (is_string($value) && trim($value) !== '') {
            return [trim($value)];
        }

        return [];
    }

    /**
     * Set the platform attribute.
     */
    public function setPlatformAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['platform'] = json_encode(array_values($value));
        } elseif (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $this->attributes['platform'] = json_encode(array_values($decoded));
            } else {
                $this->attributes['platform'] = json_encode([trim($value)]);
            }
        } else {
            $this->attributes['platform'] = json_encode([]);
        }
    }
}
