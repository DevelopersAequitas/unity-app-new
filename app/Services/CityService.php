<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\City;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class CityService
{
    /**
     * Build the query for cities with global filtering.
     *
     * @param  array{
     *     country_code?: ?string,
     *     country?: ?string,
     *     state_code?: ?string,
     *     state?: ?string,
     *     search?: ?string
     * }  $filters
     * @return Builder<City>
     */
    public function getCitiesQuery(array $filters = []): Builder
    {
        $query = City::query();

        // 1. Country Code Filtering
        $countryCode = $filters['country_code'] ?? $filters['country'] ?? null;
        if (is_string($countryCode) && trim($countryCode) !== '') {
            $cleanCountry = trim($countryCode);
            if (strlen($cleanCountry) === 2) {
                $query->whereRaw('UPPER(country_code) = ?', [strtoupper($cleanCountry)]);
            } else {
                $query->where(function (Builder $sub) use ($cleanCountry): void {
                    $sub->whereRaw('LOWER(country) = ?', [strtolower($cleanCountry)])
                        ->orWhereRaw('UPPER(country_code) = ?', [strtoupper($cleanCountry)]);
                });
            }
        }

        // 2. State Code / State Filtering
        $stateCode = $filters['state_code'] ?? null;
        $stateName = $filters['state'] ?? null;

        if (is_string($stateCode) && trim($stateCode) !== '') {
            $cleanStateCode = trim($stateCode);
            $query->where(function (Builder $sub) use ($cleanStateCode): void {
                $sub->whereRaw('UPPER(state_code) = ?', [strtoupper($cleanStateCode)])
                    ->orWhereRaw('LOWER(state) = ?', [strtolower($cleanStateCode)]);
            });
        } elseif (is_string($stateName) && trim($stateName) !== '') {
            $cleanStateName = trim($stateName);
            $query->where(function (Builder $sub) use ($cleanStateName): void {
                $sub->whereRaw('LOWER(state) = ?', [strtolower($cleanStateName)])
                    ->orWhereRaw('UPPER(state_code) = ?', [strtoupper($cleanStateName)]);
            });
        }

        // 3. Case-insensitive Search
        $search = $filters['search'] ?? null;
        if (is_string($search) && trim($search) !== '') {
            $likeTerm = '%'.strtolower(trim($search)).'%';
            $query->where(function (Builder $sub) use ($likeTerm): void {
                $sub->whereRaw('LOWER(name) LIKE ?', [$likeTerm])
                    ->orWhereRaw('LOWER(state) LIKE ?', [$likeTerm]);
            });
        }

        return $query->orderBy('name', 'asc');
    }

    /**
     * Get paginated cities using filters.
     *
     * @param  array{
     *     country_code?: ?string,
     *     country?: ?string,
     *     state_code?: ?string,
     *     state?: ?string,
     *     search?: ?string
     * }  $filters
     * @return LengthAwarePaginator<City>
     */
    public function getPaginatedCities(array $filters = [], int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        $perPage = max(1, min($perPage, 100));
        $page = max(1, $page);

        return $this->getCitiesQuery($filters)->paginate($perPage, ['*'], 'page', $page);
    }
}
