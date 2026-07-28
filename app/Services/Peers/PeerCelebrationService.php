<?php

declare(strict_types=1);

namespace App\Services\Peers;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PeerCelebrationService
{
    public function getBirthdays(array $filters, int $page = 1): array
    {
        $period = (int) ($filters['period'] ?? 30);
        $perPage = (int) ($filters['per_page'] ?? 10);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = User::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotNull('dob')
            ->with(['city']);

        $this->applySearchFilter($query, $search);

        $peers = $query->get();
        $today = Carbon::today();

        $todayBirthdays = [];
        $upcomingBirthdays = [];

        foreach ($peers as $peer) {
            if (empty($peer->dob)) {
                continue;
            }

            $dob = Carbon::parse($peer->dob);
            $isToday = ($dob->month === $today->month && $dob->day === $today->day);

            if ($isToday) {
                $turningAge = $today->year - $dob->year;
                $todayBirthdays[] = [
                    'id' => (string) $peer->id,
                    'first_name' => (string) ($peer->first_name ?? ''),
                    'last_name' => (string) ($peer->last_name ?? ''),
                    'name' => $this->resolvePeerName($peer),
                    'email' => (string) ($peer->email ?? ''),
                    'phone' => (string) ($peer->phone ?? ''),
                    'company_name' => $this->resolveCompanyName($peer),
                    'city' => $this->resolveCityName($peer),
                    'profile_photo_image' => $this->resolveProfilePhotoImage($peer),
                    'dob' => $dob->format('Y-m-d'),
                    'upcoming_date' => $today->format('Y-m-d'),
                    'days_remaining' => 0,
                    'turning_age' => $turningAge > 0 ? $turningAge : null,
                    'event_type' => 'birthday',
                ];

                continue;
            }

            $upcomingData = $this->calculateUpcomingDate($dob, $today);
            $daysRemaining = $upcomingData['days_remaining'];
            $upcomingDate = $upcomingData['upcoming_date'];

            if ($daysRemaining >= 1 && $daysRemaining <= $period) {
                $turningAge = $upcomingDate->year - $dob->year;
                $upcomingBirthdays[] = [
                    'id' => (string) $peer->id,
                    'first_name' => (string) ($peer->first_name ?? ''),
                    'last_name' => (string) ($peer->last_name ?? ''),
                    'name' => $this->resolvePeerName($peer),
                    'email' => (string) ($peer->email ?? ''),
                    'phone' => (string) ($peer->phone ?? ''),
                    'company_name' => $this->resolveCompanyName($peer),
                    'city' => $this->resolveCityName($peer),
                    'profile_photo_image' => $this->resolveProfilePhotoImage($peer),
                    'dob' => $dob->format('Y-m-d'),
                    'upcoming_date' => $upcomingDate->format('Y-m-d'),
                    'days_remaining' => $daysRemaining,
                    'turning_age' => $turningAge > 0 ? $turningAge : null,
                    'event_type' => 'birthday',
                ];
            }
        }

        usort($upcomingBirthdays, fn (array $a, array $b): int => $a['days_remaining'] <=> $b['days_remaining']);

        $totalUpcoming = count($upcomingBirthdays);
        $paginatedUpcoming = $this->paginateArray($upcomingBirthdays, $totalUpcoming, $perPage, $page);

        return [
            'today_birthdays' => $todayBirthdays,
            'today_birthdays_count' => count($todayBirthdays),
            'upcoming_birthdays' => [
                'data' => $paginatedUpcoming->items(),
                'meta' => [
                    'current_page' => $paginatedUpcoming->currentPage(),
                    'last_page' => $paginatedUpcoming->lastPage(),
                    'per_page' => $paginatedUpcoming->perPage(),
                    'total' => $paginatedUpcoming->total(),
                    'period' => $period,
                    'search' => $search !== '' ? $search : null,
                ],
            ],
            'upcoming_birthdays_count' => $totalUpcoming,
            'total_count' => count($todayBirthdays) + $totalUpcoming,
        ];
    }

    public function getAnniversaries(array $filters, int $page = 1): array
    {
        $period = (int) ($filters['period'] ?? 30);
        $perPage = (int) ($filters['per_page'] ?? 10);
        $search = trim((string) ($filters['search'] ?? ''));

        $query = User::query()
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereNotNull('anniversary_date')
            ->with(['city']);

        $this->applySearchFilter($query, $search);

        $peers = $query->get();
        $today = Carbon::today();

        $todayAnniversaries = [];
        $upcomingAnniversaries = [];

        foreach ($peers as $peer) {
            if (empty($peer->anniversary_date)) {
                continue;
            }

            $anniv = Carbon::parse($peer->anniversary_date);
            $isToday = ($anniv->month === $today->month && $anniv->day === $today->day);

            if ($isToday) {
                $completedYears = $today->year - $anniv->year;
                $todayAnniversaries[] = [
                    'id' => (string) $peer->id,
                    'first_name' => (string) ($peer->first_name ?? ''),
                    'last_name' => (string) ($peer->last_name ?? ''),
                    'name' => $this->resolvePeerName($peer),
                    'email' => (string) ($peer->email ?? ''),
                    'phone' => (string) ($peer->phone ?? ''),
                    'company_name' => $this->resolveCompanyName($peer),
                    'city' => $this->resolveCityName($peer),
                    'profile_photo_image' => $this->resolveProfilePhotoImage($peer),
                    'anniversary_date' => $anniv->format('Y-m-d'),
                    'upcoming_date' => $today->format('Y-m-d'),
                    'days_remaining' => 0,
                    'completed_years' => $completedYears > 0 ? $completedYears : null,
                    'anniversary_type' => 'Wedding Anniversary',
                    'event_type' => 'anniversary',
                ];

                continue;
            }

            $upcomingData = $this->calculateUpcomingDate($anniv, $today);
            $daysRemaining = $upcomingData['days_remaining'];
            $upcomingDate = $upcomingData['upcoming_date'];

            if ($daysRemaining >= 1 && $daysRemaining <= $period) {
                $completedYears = $upcomingDate->year - $anniv->year;
                $upcomingAnniversaries[] = [
                    'id' => (string) $peer->id,
                    'first_name' => (string) ($peer->first_name ?? ''),
                    'last_name' => (string) ($peer->last_name ?? ''),
                    'name' => $this->resolvePeerName($peer),
                    'email' => (string) ($peer->email ?? ''),
                    'phone' => (string) ($peer->phone ?? ''),
                    'company_name' => $this->resolveCompanyName($peer),
                    'city' => $this->resolveCityName($peer),
                    'profile_photo_image' => $this->resolveProfilePhotoImage($peer),
                    'anniversary_date' => $anniv->format('Y-m-d'),
                    'upcoming_date' => $upcomingDate->format('Y-m-d'),
                    'days_remaining' => $daysRemaining,
                    'completed_years' => $completedYears > 0 ? $completedYears : null,
                    'anniversary_type' => 'Wedding Anniversary',
                    'event_type' => 'anniversary',
                ];
            }
        }

        usort($upcomingAnniversaries, fn (array $a, array $b): int => $a['days_remaining'] <=> $b['days_remaining']);

        $totalUpcoming = count($upcomingAnniversaries);
        $paginatedUpcoming = $this->paginateArray($upcomingAnniversaries, $totalUpcoming, $perPage, $page);

        return [
            'today_anniversaries' => $todayAnniversaries,
            'today_anniversaries_count' => count($todayAnniversaries),
            'upcoming_anniversaries' => [
                'data' => $paginatedUpcoming->items(),
                'meta' => [
                    'current_page' => $paginatedUpcoming->currentPage(),
                    'last_page' => $paginatedUpcoming->lastPage(),
                    'per_page' => $paginatedUpcoming->perPage(),
                    'total' => $paginatedUpcoming->total(),
                    'period' => $period,
                    'search' => $search !== '' ? $search : null,
                ],
            ],
            'upcoming_anniversaries_count' => $totalUpcoming,
            'total_count' => count($todayAnniversaries) + $totalUpcoming,
        ];
    }

    private function calculateUpcomingDate(Carbon $origDate, Carbon $today): array
    {
        $currentYear = $today->year;
        $origMonth = $origDate->month;
        $origDay = $origDate->day;

        $isFeb29 = ($origMonth === 2 && $origDay === 29);

        $dayInCurrentYear = ($isFeb29 && ! Carbon::createFromDate($currentYear, 1, 1)->isLeapYear()) ? 28 : $origDay;
        $upcomingDate = Carbon::create($currentYear, $origMonth, $dayInCurrentYear)->startOfDay();

        if ($upcomingDate->lte($today)) {
            $nextYear = $currentYear + 1;
            $dayInNextYear = ($isFeb29 && ! Carbon::createFromDate($nextYear, 1, 1)->isLeapYear()) ? 28 : $origDay;
            $upcomingDate = Carbon::create($nextYear, $origMonth, $dayInNextYear)->startOfDay();
        }

        $daysRemaining = (int) $today->diffInDays($upcomingDate);

        return [
            'upcoming_date' => $upcomingDate,
            'days_remaining' => $daysRemaining,
        ];
    }

    private function applySearchFilter($query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $words = array_filter(explode(' ', $search));
        $query->where(function ($q) use ($words): void {
            foreach ($words as $word) {
                $like = "%{$word}%";
                $q->where(function ($sub) use ($like): void {
                    $searchableColumns = [
                        'first_name',
                        'last_name',
                        'display_name',
                        'email',
                        'phone',
                        'company_name',
                        'business_name',
                        'city',
                    ];
                    $hasFirst = false;
                    foreach ($searchableColumns as $col) {
                        if (! Schema::hasColumn('users', $col)) {
                            continue;
                        }
                        if (! $hasFirst) {
                            $sub->where($col, 'ILIKE', $like);
                            $hasFirst = true;
                        } else {
                            $sub->orWhere($col, 'ILIKE', $like);
                        }
                    }
                    $driver = DB::connection()->getDriverName();
                    if ($driver === 'sqlite') {
                        $sub->orWhereRaw("LOWER(COALESCE(first_name, '') || ' ' || COALESCE(last_name, '')) LIKE ?", [strtolower($like)]);
                    } else {
                        $sub->orWhereRaw("TRIM(CONCAT_WS(' ', COALESCE(first_name, ''), COALESCE(last_name, ''))) ILIKE ?", [$like]);
                    }
                    $sub->orWhereHas('city', function ($cityQuery) use ($like): void {
                        $cityQuery->where('name', 'ILIKE', $like);
                    });
                });
            }
        });
    }

    private function resolvePeerName(User $peer): string
    {
        if (! empty($peer->display_name)) {
            return (string) $peer->display_name;
        }

        $fullName = trim(($peer->first_name ?? '').' '.($peer->last_name ?? ''));

        return $fullName !== '' ? $fullName : 'Peer';
    }

    private function resolveCompanyName(User $peer): ?string
    {
        $comp = $peer->company_name ?? ($peer->company ?? ($peer->business_name ?? null));

        return filled($comp) ? (string) $comp : null;
    }

    private function resolveCityName(User $peer): ?string
    {
        $cityName = is_string($peer->city) ? $peer->city : ($peer->city?->name ?? null);

        if (is_string($cityName) && str_starts_with(trim($cityName), '{')) {
            $decoded = json_decode($cityName, true);
            $cityName = $decoded['name'] ?? $decoded['label'] ?? $cityName;
        }

        return filled($cityName) ? (string) $cityName : null;
    }

    private function resolveProfilePhotoImage(User $peer): ?string
    {
        return $peer->profile_photo_url ?? ($peer->profile_photo_file_id ? url('/api/v1/files/'.$peer->profile_photo_file_id) : null);
    }

    private function paginateArray(array $items, int $total, int $perPage, int $currentPage): LengthAwarePaginator
    {
        $offset = ($currentPage - 1) * $perPage;
        $pageItems = array_slice($items, $offset, $perPage);

        return new LengthAwarePaginator(
            $pageItems,
            $total,
            $perPage,
            $currentPage
        );
    }
}
