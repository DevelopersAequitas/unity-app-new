<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Resources\CircleLeaderResource;
use App\Http\Resources\MyLeadershipCircleResource;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CircleLeadershipController extends BaseApiController
{
    public function myLeadershipCircles(Request $request): JsonResponse
    {
        $allowedRoles = CircleMember::LEADERSHIP_ROLE_OPTIONS;

        $members = CircleMember::query()
            ->with(['circle.coverFile', 'roleModel'])
            ->where('user_id', $request->user()->id)
            ->whereNull('left_at')
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhereIn(DB::raw('LOWER(circle_members.status::text)'), CircleMember::activeStatuses());
            })
            ->whereHas('circle')
            ->orderBy('joined_at')
            ->orderBy('created_at')
            ->get()
            ->map(function (CircleMember $member): CircleMember {
                $member->setAttribute('resolved_role_slug', $this->resolveRoleSlug($member));

                return $member;
            })
            ->filter(fn (CircleMember $member): bool => in_array($member->getAttribute('resolved_role_slug'), $allowedRoles, true))
            ->values();

        return $this->success([
            'total' => $members->count(),
            'items' => MyLeadershipCircleResource::collection($members),
        ], 'My leadership circles fetched successfully.');
    }

    /**
     * Get Circle Leaders (Chair, Committee Chairs, Powerhouse Chairs, etc.) for a circle.
     */
    public function circleLeaders(Request $request, Circle|string $circle): JsonResponse
    {
        $circleModel = $this->resolveCircle($circle);
        if (! $circleModel) {
            return $this->error('Circle not found.', 404);
        }

        $with = ['user.cityRelation', 'user.businessCategory', 'user.mainBusinessCategory', 'roleModel'];

        $members = CircleMember::query()
            ->where('circle_id', $circleModel->id)
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhereIn(DB::raw('LOWER(circle_members.status::text)'), CircleMember::activeStatuses());
            })
            ->with($with)
            ->get();

        $calendar = is_array($circleModel->calendar)
            ? $circleModel->calendar
            : (is_string($circleModel->calendar) ? json_decode($circleModel->calendar, true) : []);

        $leadershipTeam = data_get($calendar, 'leadership_team')
            ?? data_get($calendar, 'leadership.team')
            ?? ($circleModel->leadership_team ?? null);

        $slots = [
            [
                'role' => 'chair',
                'role_name' => 'Chair',
                'designation' => 'Chair',
                'aliases' => ['chair', 'circle_chair', 'committee_leader', 'chair_leader'],
                'calendar_path' => 'chair',
                'user_id' => $circleModel->chair_user_id ?? data_get($calendar, 'leadership.chair_user_id'),
            ],
            [
                'role' => 'chair_business_growth_committee',
                'role_name' => 'Business Growth Committee Chair',
                'designation' => 'Business Growth Committee Chair',
                'aliases' => ['chair_business_growth_committee', 'business_growth_committee_chair', 'business_growth_chair', 'business_growth'],
                'calendar_path' => 'business_growth_committee_chair',
                'user_id' => data_get($calendar, 'leadership.business_growth_committee_chair_user_id'),
            ],
            [
                'role' => 'chair_membership_growth_committee',
                'role_name' => 'Membership Growth Committee Chair',
                'designation' => 'Membership Growth Committee Chair',
                'aliases' => ['chair_membership_growth_committee', 'membership_growth_committee_chair', 'membership_growth_chair', 'membership_growth'],
                'calendar_path' => 'membership_growth_committee_chair',
                'user_id' => data_get($calendar, 'leadership.membership_growth_committee_chair_user_id'),
            ],
            [
                'role' => 'chair_events_impacts_committee',
                'role_name' => 'Events & Impacts Committee Chair',
                'designation' => 'Events & Impacts Committee Chair',
                'aliases' => ['chair_events_impacts_committee', 'events_impacts_committee_chair', 'events_impacts_chair', 'events_and_impacts_committee_chair', 'events_impacts'],
                'calendar_path' => 'events_impacts_committee_chair',
                'user_id' => data_get($calendar, 'leadership.events_impacts_committee_chair_user_id'),
            ],
            [
                'role' => 'power_house_chair_1',
                'role_name' => 'Power House Chair 1',
                'designation' => 'Power House Chair 1',
                'aliases' => ['power_house_chair_1', 'powerhouse_1', 'power_house_1'],
                'calendar_path' => 'power_house_chair_1',
                'user_id' => data_get($calendar, 'leadership.power_house_chair_1_user_id'),
            ],
            [
                'role' => 'power_house_chair_2',
                'role_name' => 'Power House Chair 2',
                'designation' => 'Power House Chair 2',
                'aliases' => ['power_house_chair_2', 'powerhouse_2', 'power_house_2'],
                'calendar_path' => 'power_house_chair_2',
                'user_id' => data_get($calendar, 'leadership.power_house_chair_2_user_id'),
            ],
            [
                'role' => 'power_house_chair_3',
                'role_name' => 'Power House Chair 3',
                'designation' => 'Power House Chair 3',
                'aliases' => ['power_house_chair_3', 'powerhouse_3', 'power_house_3'],
                'calendar_path' => 'power_house_chair_3',
                'user_id' => data_get($calendar, 'leadership.power_house_chair_3_user_id'),
            ],
            [
                'role' => 'vice_chair',
                'role_name' => 'Vice Chair',
                'designation' => 'Vice Chair',
                'aliases' => ['vice_chair', 'circle_vice_chair'],
                'calendar_path' => 'vice_chair',
                'user_id' => $circleModel->vice_chair_user_id ?? data_get($calendar, 'leadership.vice_chair_user_id'),
            ],
            [
                'role' => 'secretary',
                'role_name' => 'Secretary',
                'designation' => 'Secretary',
                'aliases' => ['secretary', 'circle_secretary'],
                'calendar_path' => 'secretary',
                'user_id' => $circleModel->secretary_user_id ?? data_get($calendar, 'leadership.secretary_user_id'),
            ],
        ];

        $matchedMemberIds = [];
        $leaders = [];

        foreach ($slots as $slot) {
            $matchedMember = null;
            foreach ($members as $m) {
                if (in_array($m->id, $matchedMemberIds, true)) {
                    continue;
                }

                $role = strtolower(trim((string) ($m->role ?? '')));
                $roleSlug = $m->roleModel ? strtolower(trim((string) ($m->roleModel->slug ?? $m->roleModel->name ?? ''))) : '';
                $metaDesig = strtolower(trim((string) data_get($m->meta, 'designation', '')));

                $matches = in_array($role, $slot['aliases'], true)
                    || in_array($roleSlug, $slot['aliases'], true)
                    || str_contains($metaDesig, strtolower(str_replace('_', ' ', $slot['role'])))
                    || ($slot['user_id'] && (string) $m->user_id === (string) $slot['user_id']);

                if ($matches) {
                    $matchedMember = $m;
                    $matchedMemberIds[] = $m->id;
                    break;
                }
            }

            if ($matchedMember) {
                $leaders[] = [
                    'id' => $matchedMember->id,
                    'circle_id' => $circleModel->id,
                    'user_id' => $matchedMember->user_id,
                    'role' => $slot['role'],
                    'role_name' => $slot['role_name'],
                    'designation' => data_get($matchedMember->meta, 'designation') ?? $slot['designation'],
                    'user' => $matchedMember->user,
                ];
            } else {
                $calendarData = data_get($leadershipTeam, $slot['calendar_path'])
                    ?? data_get($calendar, 'leadership.'.$slot['calendar_path'])
                    ?? data_get($calendar, $slot['calendar_path']);

                $userId = $slot['user_id'] ?: (is_string($calendarData) && Str::isUuid($calendarData) ? $calendarData : data_get($calendarData, 'id') ?: data_get($calendarData, 'user_id'));
                $user = null;

                if (! empty($userId) && is_string($userId) && Str::isUuid($userId)) {
                    $user = User::with(['cityRelation', 'businessCategory', 'mainBusinessCategory'])->find($userId);
                }

                if ($user || ! empty($calendarData)) {
                    $leaders[] = [
                        'id' => $user?->id ?? (is_array($calendarData) ? data_get($calendarData, 'id') : null),
                        'circle_id' => $circleModel->id,
                        'user_id' => $user?->id ?? (is_array($calendarData) ? data_get($calendarData, 'user_id', data_get($calendarData, 'id')) : null),
                        'role' => $slot['role'],
                        'role_name' => $slot['role_name'],
                        'designation' => (is_array($calendarData) ? data_get($calendarData, 'designation') : null) ?? $slot['designation'],
                        'name' => is_array($calendarData) ? data_get($calendarData, 'name') : (is_string($calendarData) && ! Str::isUuid($calendarData) ? $calendarData : null),
                        'user' => $user,
                    ];
                }
            }
        }

        // Add any additional committee leaders from CircleMember not yet matched
        foreach ($members as $m) {
            if (in_array($m->id, $matchedMemberIds, true)) {
                continue;
            }

            $role = strtolower(trim((string) ($m->role ?? '')));
            if (CircleMember::isCircleLeaderRole($role)) {
                $matchedMemberIds[] = $m->id;
                $roleTitle = ucwords(str_replace('_', ' ', $role));
                $leaders[] = [
                    'id' => $m->id,
                    'circle_id' => $circleModel->id,
                    'user_id' => $m->user_id,
                    'role' => $role,
                    'role_name' => $roleTitle,
                    'designation' => data_get($m->meta, 'designation') ?? $roleTitle,
                    'user' => $m->user,
                ];
            }
        }

        return $this->success([
            'total' => count($leaders),
            'items' => CircleLeaderResource::collection($leaders),
        ], 'Circle leaders fetched successfully.');
    }

    /**
     * Get Regional Leaders (DED, ID, Director, Founder, EED, etc.) for a circle.
     */
    public function regionalLeaders(Request $request, Circle|string $circle): JsonResponse
    {
        $circleModel = $this->resolveCircle($circle);
        if (! $circleModel) {
            return $this->error('Circle not found.', 404);
        }

        $circleModel->loadMissing([
            'ded.cityRelation',
            'industryDirector.cityRelation',
            'circleDirector.cityRelation',
            'director.cityRelation',
            'circleFounder.cityRelation',
            'founder.cityRelation',
            'eed.cityRelation',
        ]);

        $regionalSlots = [
            [
                'role' => 'ded',
                'role_name' => 'District Executive Director (DED)',
                'designation' => 'DED',
                'aliases' => ['ded', 'district_executive_director'],
                'user_id' => $circleModel->ded_user_id,
                'user' => $circleModel->ded,
            ],
            [
                'role' => 'industry_director',
                'role_name' => 'Industry Director',
                'designation' => 'ID',
                'aliases' => ['industry_director', 'id'],
                'user_id' => $circleModel->industry_director_user_id,
                'user' => $circleModel->industryDirector,
            ],
            [
                'role' => 'circle_director',
                'role_name' => 'Circle Director',
                'designation' => 'Director',
                'aliases' => ['circle_director', 'director', 'cd'],
                'user_id' => $circleModel->circle_director_user_id ?? $circleModel->director_user_id,
                'user' => $circleModel->circleDirector ?? $circleModel->director,
            ],
            [
                'role' => 'circle_founder',
                'role_name' => 'Circle Founder',
                'designation' => 'Founder',
                'aliases' => ['circle_founder', 'founder', 'cf'],
                'user_id' => $circleModel->circle_founder_user_id ?? $circleModel->founder_user_id,
                'user' => $circleModel->circleFounder ?? $circleModel->founder,
            ],
            [
                'role' => 'eed',
                'role_name' => 'Executive Executive Director (EED)',
                'designation' => 'EED',
                'aliases' => ['eed', 'executive_executive_director'],
                'user_id' => $circleModel->eed_user_id,
                'user' => $circleModel->eed,
            ],
        ];

        $matchedUserIds = [];
        $leaders = [];

        foreach ($regionalSlots as $slot) {
            $user = $slot['user'];
            $userId = $slot['user_id'];

            if (! $user && ! empty($userId) && is_string($userId) && Str::isUuid($userId)) {
                $user = User::with(['cityRelation', 'businessCategory', 'mainBusinessCategory'])->find($userId);
            }

            if ($user) {
                $matchedUserIds[] = (string) $user->id;
                $leaders[] = [
                    'id' => (string) $user->id,
                    'circle_id' => $circleModel->id,
                    'user_id' => (string) $user->id,
                    'role' => $slot['role'],
                    'role_name' => $slot['role_name'],
                    'designation' => $slot['designation'],
                    'region' => $user->cityRelation?->name ?? (is_string($user->city) ? $user->city : null),
                    'user' => $user,
                ];
            }
        }

        // Look for regional leader members in circle_members
        $regionalMembers = CircleMember::query()
            ->where('circle_id', $circleModel->id)
            ->whereNull('deleted_at')
            ->where(function ($query): void {
                $query->whereNull('status')
                    ->orWhereIn(DB::raw('LOWER(circle_members.status::text)'), CircleMember::activeStatuses());
            })
            ->where(function ($query): void {
                $query->whereIn(DB::raw('LOWER(circle_members.role::text)'), CircleMember::REGIONAL_ROLES)
                    ->orWhereHas('roleModel', function ($rq): void {
                        $rq->whereIn(DB::raw('LOWER(slug)'), CircleMember::REGIONAL_ROLES)
                           ->orWhereIn(DB::raw('LOWER(name)'), CircleMember::REGIONAL_ROLES);
                    });
            })
            ->with(['user.cityRelation', 'user.businessCategory', 'user.mainBusinessCategory', 'roleModel'])
            ->get();

        foreach ($regionalMembers as $m) {
            $userId = (string) $m->user_id;
            if (in_array($userId, $matchedUserIds, true)) {
                continue;
            }

            $matchedUserIds[] = $userId;
            $role = strtolower(trim((string) ($m->role ?? 'regional_leader')));
            $meta = is_array($m->meta) ? $m->meta : [];
            $roleTitle = data_get($meta, 'designation') ?: ucwords(str_replace('_', ' ', $role));

            $leaders[] = [
                'id' => $m->id,
                'circle_id' => $circleModel->id,
                'user_id' => $m->user_id,
                'role' => $role,
                'role_name' => $roleTitle,
                'designation' => data_get($meta, 'designation') ?? $roleTitle,
                'region' => data_get($meta, 'region') ?? $m->user?->cityRelation?->name ?? (is_string($m->user?->city) ? $m->user?->city : null),
                'chapter' => data_get($meta, 'chapter'),
                'training_info' => data_get($meta, 'training_info') ?? data_get($meta, 'training'),
                'user' => $m->user,
            ];
        }

        // Also check raw calendar['regional_leaders'] if available
        $calendar = is_array($circleModel->calendar)
            ? $circleModel->calendar
            : (is_string($circleModel->calendar) ? json_decode($circleModel->calendar, true) : []);

        $rawRegional = data_get($calendar, 'regional_leaders')
            ?? data_get($calendar, 'leadership.regional_leaders')
            ?? ($circleModel->regional_leaders ?? null);

        if (is_array($rawRegional)) {
            foreach ($rawRegional as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $itemId = (string) (data_get($item, 'user_id') ?: data_get($item, 'id'));
                if ($itemId !== '' && in_array($itemId, $matchedUserIds, true)) {
                    continue;
                }

                if ($itemId !== '') {
                    $matchedUserIds[] = $itemId;
                }

                $leaders[] = [
                    'id' => data_get($item, 'id') ?: ($itemId !== '' ? $itemId : null),
                    'circle_id' => $circleModel->id,
                    'user_id' => $itemId !== '' ? $itemId : null,
                    'role' => strtolower(trim((string) (data_get($item, 'role') ?: data_get($item, 'designation') ?: 'regional_leader'))),
                    'role_name' => data_get($item, 'role_name') ?: data_get($item, 'designation') ?: 'Regional Leader',
                    'designation' => data_get($item, 'designation') ?: 'Regional Leader',
                    'region' => data_get($item, 'region'),
                    'chapter' => data_get($item, 'chapter'),
                    'training_info' => data_get($item, 'training_info') ?: data_get($item, 'training'),
                    'name' => data_get($item, 'name'),
                    'display_name' => data_get($item, 'display_name') ?: data_get($item, 'name'),
                    'first_name' => data_get($item, 'first_name'),
                    'last_name' => data_get($item, 'last_name'),
                    'email' => data_get($item, 'email'),
                    'phone' => data_get($item, 'phone'),
                    'profile_photo_url' => data_get($item, 'profile_photo_url'),
                    'profile_photo_image' => data_get($item, 'profile_photo_image') ?: data_get($item, 'profile_photo_url'),
                    'company_name' => data_get($item, 'company_name'),
                    'is_online' => (bool) data_get($item, 'is_online', false),
                ];
            }
        }

        return $this->success([
            'total' => count($leaders),
            'items' => CircleLeaderResource::collection($leaders),
        ], 'Regional leaders fetched successfully.');
    }

    private function resolveCircle(Circle|string $circle): ?Circle
    {
        if ($circle instanceof Circle) {
            return $circle;
        }

        if (is_string($circle) && Str::isUuid($circle)) {
            return Circle::find($circle);
        }

        return null;
    }

    private function resolveRoleSlug(CircleMember $member): ?string
    {
        $role = $member->relationLoaded('roleModel') ? $member->roleModel : null;

        return $this->normalizeRoleSlug(
            $role?->slug
            ?? $role?->name
            ?? $member->role
        );
    }

    private function normalizeRoleSlug(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $slug = Str::of($value)
            ->lower()
            ->trim()
            ->replace(['-', ' '], '_')
            ->replaceMatches('/_+/', '_')
            ->trim('_')
            ->toString();

        if ($slug === 'circle_member') {
            return 'member';
        }

        if (str_starts_with($slug, 'circle_')) {
            $withoutPrefix = Str::after($slug, 'circle_');

            return in_array($withoutPrefix, CircleMember::LEADERSHIP_ROLE_OPTIONS, true)
                ? $withoutPrefix
                : $slug;
        }

        return $slug;
    }
}
