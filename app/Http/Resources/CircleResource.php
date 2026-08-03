<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class CircleResource extends JsonResource
{
    public function toArray($request): array
    {
        $founder = $this->relationLoaded('circleFounder') ? $this->circleFounder : ($this->relationLoaded('founder') ? $this->founder : null);
        $director = $this->relationLoaded('circleDirector') ? $this->circleDirector : ($this->relationLoaded('director') ? $this->director : null);
        $industryDirector = $this->relationLoaded('industryDirector') ? $this->industryDirector : null;
        $ded = $this->relationLoaded('ded') ? $this->ded : null;
        $eed = $this->relationLoaded('eed') ? $this->eed : null;
        $city = $this->relationLoaded('city') ? $this->city : null;
        $currentMember = $this->relationLoaded('currentMember') ? $this->currentMember : null;

        $resolveUserCity = static function ($user): ?string {
            if (! $user) {
                return null;
            }

            $city = trim((string) ($user->city ?? ''));

            if ($city !== '') {
                return $city;
            }

            if ($user->relationLoaded('cityRelation')) {
                return $user->cityRelation?->name;
            }

            return null;
        };

        $userMini = static function ($user) use ($resolveUserCity) {
            if (! $user) {
                return null;
            }

            return [
                'id' => $user->id,
                'name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'profile_photo_url' => $user->profile_photo_url,
                'email' => $user->email,
                'phone' => $user->phone ?? null,
                'city' => $resolveUserCity($user),
                'company_name' => $user->company_name,
            ];
        };

        $categories = $this->relationLoaded('categories')
            ? $this->categories
                ->map(static function ($category): array {
                    return [
                        'id' => $category->id,
                        'category_id' => $category->id,
                        'category_name' => $category->category_name ?? $category->name,
                        'name' => $category->name ?? $category->category_name,
                        'slug' => $category->slug ?? null,
                        'circle_key' => $category->circle_key ?? null,
                        'level' => $category->level ?? null,
                        'sector' => $category->sector ?? null,
                        'remarks' => $category->remarks ?? null,
                        'pivot' => $category->pivot ? [
                            'id' => $category->pivot->id ?? null,
                            'circle_id' => $category->pivot->circle_id ?? null,
                            'category_id' => $category->pivot->category_id ?? null,
                            'created_at' => $category->pivot->created_at ?? null,
                            'updated_at' => $category->pivot->updated_at ?? null,
                        ] : null,
                    ];
                })
                ->values()
                ->all()
            : [];

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'purpose' => $this->purpose,
            'announcement' => $this->announcement,
            'status' => $this->status,
            'type' => $this->type,
            'country' => $this->country,
            'referral_score' => $this->referral_score,
            'visitor_count' => $this->visitor_count,
            'industry_tags' => $this->industry_tags,
            'calendar' => $this->calendar,
            'meeting_mode' => $this->meeting_mode,
            'meeting_frequency' => $this->meeting_frequency,
            'meeting_repeat' => $this->meeting_repeat,
            'launch_date' => $this->launch_date,
            'circle_stage' => $this->circle_stage,
            'circle_ranking' => $this->getCircleRanking(),
            'city_id' => $this->city_id,
            'city' => $city ? [
                'id' => $city->id,
                'name' => $city->name,
                'state' => $city->state,
                'district' => $city->district,
                'country' => $city->country,
                'country_code' => $city->country_code,
            ] : null,
            'founder_user_id' => $this->circle_founder_user_id,
            'director_user_id' => $this->circle_director_user_id,
            'circle_founder_user_id' => $this->circle_founder_user_id,
            'circle_director_user_id' => $this->circle_director_user_id,
            'industry_director_user_id' => $this->industry_director_user_id,
            'ded_user_id' => $this->ded_user_id,
            'eed_user_id' => $this->eed_user_id,
            'founder' => $founder ? [
                'id' => $founder->id,
                'display_name' => $founder->display_name,
                'first_name' => $founder->first_name,
                'last_name' => $founder->last_name,
                'profile_photo_url' => $founder->profile_photo_url,
                'email' => $founder->email,
                'phone' => $founder->phone ?? null,
                'city' => $resolveUserCity($founder),
                'company_name' => $founder->company_name,
            ] : null,
            'director' => $userMini($director),
            'industry_director' => $userMini($industryDirector),
            'ded' => $userMini($ded),
            'eed' => $userMini($eed),
            'categories' => $categories,
            'cover_file_id' => $this->cover_file_id,
            'cover_image_url' => $this->cover_file_id
                ? url("/api/v1/files/{$this->cover_file_id}")
                : null,
            'members_count' => $this->members_count ?? null,
            'peers_count' => $this->peers_count ?? $this->members_count ?? null,
            'is_member' => $currentMember ? true : false,
            'member_status' => $currentMember->status ?? null,
            'circle_leaders' => $this->resolveCircleLeaders(),
            'regional_leaders' => $this->resolveRegionalLeaders(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function resolveCircleLeaders(): array
    {
        $formatLeader = static function ($data, string $defaultDesignation): ?array {
            if (empty($data)) {
                return null;
            }

            if (is_array($data)) {
                return array_merge([
                    'designation' => $defaultDesignation,
                ], array_filter($data, fn ($v) => $v !== null && $v !== ''));
            }

            if (is_object($data)) {
                return [
                    'id' => data_get($data, 'id'),
                    'name' => data_get($data, 'display_name') ?: data_get($data, 'name') ?: trim((string) data_get($data, 'first_name', '').' '.(string) data_get($data, 'last_name', '')),
                    'first_name' => data_get($data, 'first_name'),
                    'last_name' => data_get($data, 'last_name'),
                    'email' => data_get($data, 'email'),
                    'phone' => data_get($data, 'phone'),
                    'profile_photo_url' => data_get($data, 'profile_photo_url'),
                    'company_name' => data_get($data, 'company_name'),
                    'designation' => data_get($data, 'designation') ?? $defaultDesignation,
                ];
            }

            $str = trim((string) $data);

            return $str !== '' ? ['name' => $str, 'designation' => $defaultDesignation] : null;
        };

        $leadershipTeam = $this->calendarGet('leadership_team')
            ?? $this->calendarGet('leadership.team')
            ?? ($this->resource->leadership_team ?? null);

        $chairData = data_get($leadershipTeam, 'chair') ?? $this->calendarGet('leadership.chair') ?? $this->calendarGet('chair');

        $resolveUserById = static function ($data) {
            if (is_string($data) && Str::isUuid($data)) {
                $user = User::find($data);
                if ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'profile_photo_url' => $user->profile_photo_url,
                        'email' => $user->email,
                        'phone' => $user->phone ?? null,
                        'company_name' => $user->company_name,
                    ];
                }
            }

            return $data;
        };

        $bgChairData = data_get($leadershipTeam, 'business_growth_committee_chair')
            ?? data_get($leadershipTeam, 'business_growth_chair')
            ?? $this->calendarGet('leadership.business_growth_committee_chair')
            ?? $this->calendarGet('leadership.business_growth_committee_chair_user_id')
            ?? $this->calendarGet('business_growth_committee_chair');
        $bgChairData = $resolveUserById($bgChairData);

        $mgChairData = data_get($leadershipTeam, 'membership_growth_committee_chair')
            ?? data_get($leadershipTeam, 'membership_growth_chair')
            ?? $this->calendarGet('leadership.membership_growth_committee_chair')
            ?? $this->calendarGet('leadership.membership_growth_committee_chair_user_id')
            ?? $this->calendarGet('membership_growth_committee_chair');
        $mgChairData = $resolveUserById($mgChairData);

        $eiChairData = data_get($leadershipTeam, 'events_impacts_committee_chair')
            ?? data_get($leadershipTeam, 'events_and_impacts_committee_chair')
            ?? data_get($leadershipTeam, 'events_impacts_chair')
            ?? $this->calendarGet('leadership.events_impacts_committee_chair')
            ?? $this->calendarGet('leadership.events_impacts_committee_chair_user_id')
            ?? $this->calendarGet('events_impacts_committee_chair');
        $eiChairData = $resolveUserById($eiChairData);

        $phChairs = data_get($leadershipTeam, 'power_house_chairs');
        $ph1Data = data_get($leadershipTeam, 'power_house_chair_1') ?? data_get($phChairs, 0) ?? $this->calendarGet('leadership.power_house_chair_1') ?? $this->calendarGet('leadership.power_house_chair_1_user_id') ?? $this->calendarGet('power_house_chair_1');
        $ph1Data = $resolveUserById($ph1Data);

        $ph2Data = data_get($leadershipTeam, 'power_house_chair_2') ?? data_get($phChairs, 1) ?? $this->calendarGet('leadership.power_house_chair_2') ?? $this->calendarGet('leadership.power_house_chair_2_user_id') ?? $this->calendarGet('power_house_chair_2');
        $ph2Data = $resolveUserById($ph2Data);

        $ph3Data = data_get($leadershipTeam, 'power_house_chair_3') ?? data_get($phChairs, 2) ?? $this->calendarGet('leadership.power_house_chair_3') ?? $this->calendarGet('leadership.power_house_chair_3_user_id') ?? $this->calendarGet('power_house_chair_3');
        $ph3Data = $resolveUserById($ph3Data);

        $members = $this->relationLoaded('members') ? $this->members : collect();

        if ($members->isNotEmpty()) {
            foreach ($members as $member) {
                $role = strtolower((string) ($member->role ?? ''));
                $metaDesig = strtolower((string) data_get($member->meta, 'designation', ''));
                $user = $member->relationLoaded('user') ? $member->user : null;

                $userPayload = $user ? [
                    'id' => $user->id,
                    'name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'profile_photo_url' => $user->profile_photo_url,
                    'email' => $user->email,
                    'phone' => $user->phone ?? null,
                    'company_name' => $user->company_name,
                    'designation' => data_get($member->meta, 'designation') ?? 'Chair',
                ] : null;

                if (! $chairData && in_array($role, ['chair', 'committee_leader', 'chair_leader'], true)) {
                    $chairData = $userPayload ?? ['designation' => 'Chair', 'role' => $role];
                }

                if (! $bgChairData && (str_contains($role, 'business_growth') || str_contains($metaDesig, 'business growth'))) {
                    $bgChairData = $userPayload ?? ['designation' => 'Business Growth Committee Chair', 'role' => $role];
                }

                if (! $mgChairData && (str_contains($role, 'membership_growth') || str_contains($metaDesig, 'membership growth'))) {
                    $mgChairData = $userPayload ?? ['designation' => 'Membership Growth Committee Chair', 'role' => $role];
                }

                if (! $eiChairData && (str_contains($role, 'events_impacts') || str_contains($metaDesig, 'events & impacts') || str_contains($metaDesig, 'events and impacts'))) {
                    $eiChairData = $userPayload ?? ['designation' => 'Events & Impacts Committee Chair', 'role' => $role];
                }

                if (! $ph1Data && (in_array($role, ['power_house_chair_1', 'powerhouse_1', 'power_house_1'], true) || str_contains($metaDesig, 'power house chair 1'))) {
                    $ph1Data = $userPayload ?? ['designation' => 'Power House Chair 1', 'role' => $role];
                }

                if (! $ph2Data && (in_array($role, ['power_house_chair_2', 'powerhouse_2', 'power_house_2'], true) || str_contains($metaDesig, 'power house chair 2'))) {
                    $ph2Data = $userPayload ?? ['designation' => 'Power House Chair 2', 'role' => $role];
                }

                if (! $ph3Data && (in_array($role, ['power_house_chair_3', 'powerhouse_3', 'power_house_3'], true) || str_contains($metaDesig, 'power house chair 3'))) {
                    $ph3Data = $userPayload ?? ['designation' => 'Power House Chair 3', 'role' => $role];
                }
            }
        }

        $ph1Formatted = $formatLeader($ph1Data, 'Power House Chair 1');
        $ph2Formatted = $formatLeader($ph2Data, 'Power House Chair 2');
        $ph3Formatted = $formatLeader($ph3Data, 'Power House Chair 3');

        $phList = array_values(array_filter([$ph1Formatted, $ph2Formatted, $ph3Formatted]));

        return [
            'chair' => $formatLeader($chairData, 'Chair'),
            'business_growth_committee_chair' => $formatLeader($bgChairData, 'Business Growth Committee Chair'),
            'membership_growth_committee_chair' => $formatLeader($mgChairData, 'Membership Growth Committee Chair'),
            'events_impacts_committee_chair' => $formatLeader($eiChairData, 'Events & Impacts Committee Chair'),
            'power_house_chairs' => $phList,
            'power_house_chair_1' => $ph1Formatted,
            'power_house_chair_2' => $ph2Formatted,
            'power_house_chair_3' => $ph3Formatted,
        ];
    }

    private function resolveRegionalLeaders(): array
    {
        $founder = $this->relationLoaded('circleFounder') && $this->circleFounder
            ? $this->circleFounder
            : ($this->relationLoaded('founder') && $this->founder
                ? $this->founder
                : ($this->circle_founder_user_id ?? $this->founder_user_id ?? null));

        $director = $this->relationLoaded('circleDirector') && $this->circleDirector
            ? $this->circleDirector
            : ($this->relationLoaded('director') && $this->director
                ? $this->director
                : ($this->circle_director_user_id ?? $this->director_user_id ?? null));

        $industryDirector = $this->relationLoaded('industryDirector') && $this->industryDirector
            ? $this->industryDirector
            : ($this->industry_director_user_id ?? null);

        $ded = $this->relationLoaded('ded') && $this->ded
            ? $this->ded
            : ($this->ded_user_id ?? null);

        $eed = $this->relationLoaded('eed') && $this->eed
            ? $this->eed
            : ($this->eed_user_id ?? null);

        $formatLeader = static function ($user, string $roleLabel): ?array {
            if (empty($user)) {
                return null;
            }

            if (is_string($user) && Str::isUuid($user)) {
                $user = User::find($user);
            }

            if (! $user) {
                return null;
            }

            return [
                'id' => data_get($user, 'id'),
                'user_id' => data_get($user, 'id'),
                'name' => data_get($user, 'display_name') ?: trim((string) data_get($user, 'first_name', '').' '.(string) data_get($user, 'last_name', '')),
                'first_name' => data_get($user, 'first_name'),
                'last_name' => data_get($user, 'last_name'),
                'email' => data_get($user, 'email'),
                'phone' => data_get($user, 'phone'),
                'profile_photo_url' => data_get($user, 'profile_photo_url'),
                'company_name' => data_get($user, 'company_name'),
                'role' => $roleLabel,
                'designation' => $roleLabel,
                'region' => data_get($user, 'city'),
            ];
        };

        $leaders = array_values(array_filter([
            $formatLeader($ded, 'DED'),
            $formatLeader($industryDirector, 'ID'),
            $formatLeader($director, 'Director'),
            $formatLeader($founder, 'Founder'),
            $formatLeader($eed, 'EED'),
        ]));

        if (! empty($leaders)) {
            return $leaders;
        }

        $rawRegional = $this->calendarGet('regional_leaders')
            ?? $this->calendarGet('leadership.regional_leaders')
            ?? ($this->resource->regional_leaders ?? null);

        if (is_array($rawRegional)) {
            return array_values(array_map(static function ($item) {
                if (is_array($item)) {
                    return $item;
                }

                return ['designation' => (string) $item];
            }, $rawRegional));
        }

        $members = $this->relationLoaded('members') ? $this->members : collect();

        if ($members->isNotEmpty()) {
            foreach ($members as $member) {
                $role = strtolower((string) ($member->role ?? ''));
                if (in_array($role, ['regional_leader', 'regional_director', 'regional_head'], true)) {
                    $user = $member->relationLoaded('user') ? $member->user : null;
                    $meta = is_array($member->meta) ? $member->meta : [];

                    $leaders[] = array_merge([
                        'id' => $user?->id ?? $member->id,
                        'name' => $user ? ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))) : null,
                        'first_name' => $user?->first_name,
                        'last_name' => $user?->last_name,
                        'email' => $user?->email,
                        'phone' => $user?->phone,
                        'profile_photo_url' => $user?->profile_photo_url,
                        'role' => data_get($meta, 'designation') ?? 'Regional Leader',
                        'designation' => data_get($meta, 'designation') ?? 'Regional Leader',
                        'region' => data_get($meta, 'region') ?? $user?->city ?? null,
                        'chapter' => data_get($meta, 'chapter') ?? null,
                        'training_info' => data_get($meta, 'training_info') ?? data_get($meta, 'training') ?? null,
                    ], $meta);
                }
            }
        }

        return $leaders;
    }
}
