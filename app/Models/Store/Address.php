<?php

declare(strict_types=1);

namespace App\Models\Store;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class Address extends Model
{
    use HasUuids;

    protected $table = 'addresses';

    protected static ?bool $hasFullNameColumn = null;

    protected static ?bool $hasNameColumn = null;

    protected $fillable = [
        'user_id',
        'name',
        'full_name',
        'phone',
        'line1',
        'line2',
        'landmark',
        'city',
        'state',
        'pincode',
        'country',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    protected $appends = [
        'full_name',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $address): void {
            $nameVal = $address->attributes['full_name'] ?? ($address->attributes['name'] ?? '');

            if (static::$hasFullNameColumn === null) {
                try {
                    static::$hasFullNameColumn = Schema::hasTable('addresses') && Schema::hasColumn('addresses', 'full_name');
                    static::$hasNameColumn = Schema::hasTable('addresses') && Schema::hasColumn('addresses', 'name');
                } catch (\Throwable) {
                    static::$hasFullNameColumn = true;
                    static::$hasNameColumn = true;
                }
            }

            if (static::$hasFullNameColumn && filled($nameVal)) {
                $address->attributes['full_name'] = $nameVal;
            } elseif (! static::$hasFullNameColumn) {
                unset($address->attributes['full_name']);
            }

            if (static::$hasNameColumn && filled($nameVal)) {
                $address->attributes['name'] = $nameVal;
            } elseif (! static::$hasNameColumn) {
                unset($address->attributes['name']);
            }
        });
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes['full_name'] ?? ($attributes['name'] ?? ''),
            set: fn ($value) => [
                'full_name' => $value,
                'name' => $value,
            ]
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeActive($query)
    {
        return $query;
    }
}
