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

    private static array $stateCodes = [
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
        $cityName = $this->name;
        $stateName = $this->state;
        $stateKey = strtolower(trim($stateName ?? ''));
        $stateCode = self::$stateCodes[$stateKey] ?? strtoupper(substr($stateName ?? '', 0, 2));
        $countryCode = strtoupper($this->country_code ?? 'IN');

        return "{$cityName}, {$stateCode}, {$countryCode}";
    }

    public function getCountryNameAttribute(): ?string
    {
        return $this->attributes['country_name'] ?? $this->attributes['country'] ?? null;
    }
}
