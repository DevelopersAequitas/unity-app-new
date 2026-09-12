<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class City extends Model
{
    use HasFactory;

    protected $table = 'cities';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected static function booted(): void
    {
        static::creating(function (self $city): void {
            if (empty($city->id)) {
                $city->id = (string) Str::uuid();
            }
        });
    }

    protected $fillable = [
        'id',
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

    public static array $stateCodes = [
        'andhra pradesh' => 'AP',
        'arunachal pradesh' => 'AR',
        'assam' => 'AS',
        'bihar' => 'BR',
        'chhattisgarh' => 'CG',
        'goa' => 'GA',
        'gujarat' => 'GJ',
        'haryana' => 'HR',
        'himachal pradesh' => 'HP',
        'jharkhand' => 'JH',
        'karnataka' => 'KA',
        'kerala' => 'KL',
        'madhya pradesh' => 'MP',
        'maharashtra' => 'MH',
        'manipur' => 'MN',
        'meghalaya' => 'ML',
        'mizoram' => 'MZ',
        'nagaland' => 'NL',
        'odisha' => 'OD',
        'punjab' => 'PB',
        'rajasthan' => 'RJ',
        'sikkim' => 'SK',
        'tamil nadu' => 'TN',
        'telangana' => 'TG',
        'tripura' => 'TR',
        'uttarakhand' => 'UT',
        'uttar pradesh' => 'UP',
        'west bengal' => 'WB',
        'andaman and nicobar islands' => 'AN',
        'chandigarh' => 'CH',
        'dadra and nagar haveli and daman and diu' => 'DN',
        'delhi' => 'DL',
        'jammu and kashmir' => 'JK',
        'ladakh' => 'LA',
        'lakshadweep' => 'LD',
        'puducherry' => 'PY',
    ];

    public function getFormattedLocationAttribute(): string
    {
        $cityName = trim((string) ($this->name ?? $this->city_name ?? ''));
        $stateName = trim((string) ($this->state ?? ''));
        $countryCode = strtoupper(trim((string) ($this->country_code ?? '')));

        if ($countryCode === '' && ! empty($this->country)) {
            $countryCode = strtoupper(substr(trim((string) $this->country), 0, 2));
        }

        $stateCode = null;
        if ($stateName !== '') {
            $stateKey = strtolower($stateName);
            if (isset(self::$stateCodes[$stateKey])) {
                $stateCode = self::$stateCodes[$stateKey];
            } elseif (strlen($stateName) <= 3) {
                $stateCode = strtoupper($stateName);
            } else {
                $stateCode = $stateName;
            }
        }

        $parts = array_values(array_filter([
            $cityName,
            $stateCode,
            $countryCode ?: null,
        ], fn ($val) => $val !== null && $val !== ''));

        return implode(', ', $parts);
    }

    public function getCountryNameAttribute(): ?string
    {
        return $this->attributes['country_name'] ?? $this->attributes['country'] ?? null;
    }
}
