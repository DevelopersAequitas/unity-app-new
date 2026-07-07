<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'state',
        'district',
        'country',
        'country_code',
    ];

    protected $casts = [
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function circles(): HasMany
    {
        return $this->hasMany(Circle::class);
    }

    public function getStateAttribute(): ?string
    {
        return $this->attributes['state'] ?? $this->state_name ?? null;
    }

    public function getDistrictAttribute(): ?string
    {
        return $this->attributes['district'] ?? null;
    }

    public function getCountryAttribute(): ?string
    {
        return $this->attributes['country'] ?? $this->country_name ?? null;
    }

    public function getCountryCodeAttribute(): ?string
    {
        return $this->attributes['country_code'] ?? null;
    }

    public function getStateNameAttribute(): ?string
    {
        return $this->attributes['state_name'] ?? $this->attributes['state'] ?? null;
    }

    public function getCountryNameAttribute(): ?string
    {
        return $this->attributes['country_name'] ?? $this->attributes['country'] ?? null;
    }
}
