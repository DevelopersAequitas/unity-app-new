<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\AdminPeerIndexRequest;
use App\Models\Circle;
use App\Models\CircleCategory;
use App\Models\CircleCategoryLevel4;
use App\Models\CircleJoinRequest;
use App\Models\CircleMember;
use App\Models\Impact;
use App\Models\JoinedCircleCategory;
use App\Models\Payment;
use App\Models\Role;
use App\Models\User;
use App\Services\Admin\AdminAuditService;
use App\Services\Admin\AdminPeerService;
use App\Services\Admin\AdminScopeService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CircleManagementController extends BaseApiController
{
    public function __construct(
        private readonly AdminScopeService $scope,
        private readonly AdminAuditService $audit,
        private readonly AdminPeerService $peerService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = Circle::query()->with('cityRef')->withCount('members');
        if ($request->filled('state_name')) {
            $state = $request->input('state_name');
            $q->where(function ($query) use ($state): void {
                $hasCircleState = Schema::hasColumn('circles', 'state');
                $hasCircleStateName = Schema::hasColumn('circles', 'state_name');
                $hasCityState = Schema::hasColumn('cities', 'state');
                $hasCityStateName = Schema::hasColumn('cities', 'state_name');
                if ($hasCircleState) {
                    $query->where('state', $state);
                }
                if ($hasCircleStateName) {
                    ($hasCircleState ? $query->orWhere('state_name', $state) : $query->where('state_name', $state));
                }
                if ($hasCityState || $hasCityStateName) {
                    $method = ($hasCircleState || $hasCircleStateName) ? 'orWhereHas' : 'whereHas';
                    $query->{$method}('cityRef', function ($cityQuery) use ($state, $hasCityState, $hasCityStateName): void {
                        if ($hasCityState) {
                            $cityQuery->where('state', $state);
                        }
                        if ($hasCityStateName) {
                            ($hasCityState ? $cityQuery->orWhere('state_name', $state) : $cityQuery->where('state_name', $state));
                        }
                    });
                }
            });
        }
        if ($request->filled('search') || $request->filled('q')) {
            $term = trim((string) ($request->input('search') ?: $request->input('q')));
            $q->where(function ($sub) use ($term): void {
                $sub->where('name', 'ilike', "%{$term}%")
                    ->orWhere('description', 'ilike', "%{$term}%")
                    ->orWhere('purpose', 'ilike', "%{$term}%")
                    ->orWhereHas('cityRef', function ($cityQuery) use ($term): void {
                        $cityQuery->where('name', 'ilike', "%{$term}%");
                        if (Schema::hasColumn('cities', 'state')) {
                            $cityQuery->orWhere('state', 'ilike', "%{$term}%");
                        }
                        if (Schema::hasColumn('cities', 'state_name')) {
                            $cityQuery->orWhere('state_name', 'ilike', "%{$term}%");
                        }
                    });
            });
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $q->where('status', strtolower((string) $request->input('status')));
        }

        $this->scope->applyCircleScope($q, $request->user());
        if ($request->filled('state_name')) {
            return $this->success($q->orderBy('name')->get()->map(fn (Circle $circle) => [
                'id' => $circle->id,
                'name' => $circle->name,
                'state_name' => $circle->state_name ?? $circle->state ?? $circle->cityRef?->state_name ?? $circle->cityRef?->state ?? null,
            ])->values(), 'Circles fetched successfully.');
        }

        $perPage = min(100, max(1, (int) $request->input('per_page', 50)));
        return $this->success($q->orderBy('name')->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        // Normalize city_id and file UUIDs before validation
        $rawCityId = $request->input('city_id');
        $resolvedCityId = null;
        if (!empty($rawCityId) && Str::isUuid((string) $rawCityId)) {
            $resolvedCityId = (string) $rawCityId;
        } elseif ($request->filled('city')) {
            $cityName = trim((string) $request->input('city'));
            $resolvedCityId = DB::table('cities')->where('name', 'ilike', $cityName)->value('id');
        }

        $bgChairId = $request->input('business_growth_committee_chair_user_id') ?? $request->input('chair_user_id');
        $mgChairId = $request->input('membership_growth_committee_chair_user_id') ?? $request->input('vice_chair_user_id');
        $eiChairId = $request->input('events_impacts_committee_chair_user_id') ?? $request->input('secretary_user_id');

        $mergeData = [
            'city_id' => $resolvedCityId,
            'cover_file_id' => ($request->filled('cover_file_id') && Str::isUuid((string) $request->input('cover_file_id'))) ? $request->input('cover_file_id') : null,
            'circle_image_file_id' => ($request->filled('circle_image_file_id') && Str::isUuid((string) $request->input('circle_image_file_id'))) ? $request->input('circle_image_file_id') : null,
            'chair_user_id' => (!empty($bgChairId) && Str::isUuid((string) $bgChairId)) ? (string) $bgChairId : null,
            'vice_chair_user_id' => (!empty($mgChairId) && Str::isUuid((string) $mgChairId)) ? (string) $mgChairId : null,
            'secretary_user_id' => (!empty($eiChairId) && Str::isUuid((string) $eiChairId)) ? (string) $eiChairId : null,
            'business_growth_committee_chair_user_id' => (!empty($bgChairId) && Str::isUuid((string) $bgChairId)) ? (string) $bgChairId : null,
            'membership_growth_committee_chair_user_id' => (!empty($mgChairId) && Str::isUuid((string) $mgChairId)) ? (string) $mgChairId : null,
            'events_impacts_committee_chair_user_id' => (!empty($eiChairId) && Str::isUuid((string) $eiChairId)) ? (string) $eiChairId : null,
        ];
        $request->merge($mergeData);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'announcement' => ['nullable', 'string'],
            'city_id' => ['nullable', 'uuid', 'exists:cities,id'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'circle_stage' => ['nullable', 'string'],
            'industry_tags' => ['nullable'],
            'circle_founder_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'circle_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'industry_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'ded_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'eed_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'vice_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'secretary_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'business_growth_committee_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'membership_growth_committee_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'events_impacts_committee_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'cover_file_id' => ['nullable', 'uuid'],
            'circle_image_file_id' => ['nullable', 'uuid'],
            'payment_gateway' => ['nullable', 'string'],
            'payment_plan_id' => ['nullable', 'string'],
            'circle_package' => ['nullable', 'string'],
            'circle_price_amount' => ['nullable', 'numeric'],
            'circle_gst_percent' => ['nullable', 'numeric'],
            'is_package_active' => ['nullable', 'boolean'],
            'meeting_mode' => ['nullable', 'string'],
            'meeting_frequency' => ['nullable', 'string'],
            'launch_date' => ['nullable', 'date'],
            'meeting_repeat' => ['nullable'],
            'meeting_venue' => ['nullable', 'string'],
            'meeting_landmark' => ['nullable', 'string'],
            'meeting_link' => ['nullable', 'string'],
            'meeting_passcode' => ['nullable', 'string'],
            'calendar' => ['nullable', 'array'],
            'categories' => ['nullable', 'array'],
        ]);

        $baseSlug = Str::slug((string) ($validated['name'] ?? 'circle'));
        if ($baseSlug === '') $baseSlug = 'circle';
        $slug = $baseSlug;
        $counter = 1;
        while (Circle::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        $circle = new Circle();
        $circle->slug = $slug;

        $fillableColumns = [
            'name', 'description', 'purpose', 'announcement', 'city_id', 'city', 'country',
            'type', 'status', 'circle_stage', 'circle_founder_user_id', 'circle_director_user_id',
            'industry_director_user_id', 'ded_user_id', 'eed_user_id',
            'chair_user_id', 'vice_chair_user_id', 'secretary_user_id',
            'cover_file_id',
            'circle_image_file_id', 'payment_gateway', 'payment_plan_id', 'circle_package',
            'circle_price_amount', 'circle_gst_percent', 'is_package_active', 'meeting_mode',
            'meeting_frequency', 'meeting_repeat', 'launch_date',
        ];

        foreach ($fillableColumns as $col) {
            if (array_key_exists($col, $validated) && Schema::hasColumn('circles', $col)) {
                $circle->{$col} = $validated[$col];
            }
        }

        if (isset($validated['meeting_repeat'])) {
            $repVal = $validated['meeting_repeat'];
            if (is_string($repVal)) {
                $repVal = json_decode($repVal, true);
            }
            if (is_array($repVal)) {
                $circle->meeting_repeat = $repVal;
            }
        }

        if (isset($validated['industry_tags'])) {
            $tags = is_array($validated['industry_tags'])
                ? $validated['industry_tags']
                : array_filter(array_map('trim', explode(',', (string) $validated['industry_tags'])));
            $circle->industry_tags = array_values($tags);
        }

        $calendar = isset($validated['calendar']) && is_array($validated['calendar']) ? $validated['calendar'] : [];
        $rawRepeat = $circle->meeting_repeat
            ?? data_get($validated, 'calendar.settings.meeting_repeat')
            ?? data_get($validated, 'calendar.meeting_schedule.0');
        if (is_array($rawRepeat) && !empty($rawRepeat)) {
            $normalizedRepeat = Circle::normalizeCadencePayload($rawRepeat, $calendar);
            $circle->meeting_repeat = $normalizedRepeat;
            data_set($calendar, 'settings.meeting_repeat', $normalizedRepeat);
            data_set($calendar, 'meeting_schedule', [$normalizedRepeat]);
            data_set($calendar, 'meetings', [$normalizedRepeat]);
            data_set($calendar, 'monthly_rule', $normalizedRepeat['monthly_rule']);
            data_set($calendar, 'week_of_month', $normalizedRepeat['week_of_month']);
            data_set($calendar, 'week_number', $normalizedRepeat['week_number']);
            data_set($calendar, 'ordinal', $normalizedRepeat['ordinal']);
            data_set($calendar, 'default_meet_day', $normalizedRepeat['default_meet_day']);
            data_set($calendar, 'day_of_week', $normalizedRepeat['day_of_week']);
            data_set($calendar, 'default_meet_time', $normalizedRepeat['default_meet_time']);
            data_set($calendar, 'start_time', $normalizedRepeat['start_time']);
            data_set($calendar, 'end_time', $normalizedRepeat['end_time']);
            data_set($calendar, 'frequency', $normalizedRepeat['frequency']);
        }
        if ($request->filled('meeting_venue')) {
            data_set($calendar, 'settings.meeting_venue', $request->input('meeting_venue'));
            data_set($calendar, 'settings.meeting_address', $request->input('meeting_venue'));
        }
        if ($request->filled('meeting_landmark')) {
            data_set($calendar, 'settings.meeting_landmark', $request->input('meeting_landmark'));
        }
        if ($request->filled('meeting_link')) {
            data_set($calendar, 'settings.meeting_link', $request->input('meeting_link'));
        }
        if ($request->filled('meeting_passcode')) {
            data_set($calendar, 'settings.meeting_passcode', $request->input('meeting_passcode'));
        }
        if ($request->filled('meeting_mode')) {
            data_set($calendar, 'settings.meeting_mode', $request->input('meeting_mode'));
        }
        if ($request->filled('meeting_frequency')) {
            data_set($calendar, 'settings.meeting_frequency', $request->input('meeting_frequency'));
        }
        if ($request->filled('launch_date')) {
            data_set($calendar, 'settings.launch_date', $request->input('launch_date'));
        }
        data_set($calendar, 'leadership.circle_founder_user_id', $validated['circle_founder_user_id'] ?? null);
        data_set($calendar, 'leadership.circle_director_user_id', $validated['circle_director_user_id'] ?? null);
        data_set($calendar, 'leadership.industry_director_user_id', $validated['industry_director_user_id'] ?? null);
        data_set($calendar, 'leadership.ded_user_id', $validated['ded_user_id'] ?? null);
        data_set($calendar, 'leadership.eed_user_id', $validated['eed_user_id'] ?? null);
        data_set($calendar, 'leadership.business_growth_committee_chair_user_id', $validated['chair_user_id'] ?? null);
        data_set($calendar, 'leadership.membership_growth_committee_chair_user_id', $validated['vice_chair_user_id'] ?? null);
        data_set($calendar, 'leadership.events_impacts_committee_chair_user_id', $validated['secretary_user_id'] ?? null);
        $circle->calendar = $calendar;

        $circle->save();

        if (isset($validated['categories']) && is_array($validated['categories']) && method_exists($circle, 'categories')) {
            $categoryIds = collect($validated['categories'])->filter()->map(fn($id) => (int) $id)->values()->all();
            $circle->categories()->sync($categoryIds);
        }

        $circle->load(['cityRef', 'circleFounder', 'circleDirector', 'industryDirector', 'ded', 'eed', 'categories']);

        return $this->success($circle, 'Circle created successfully.', 201);
    }

    public function show(string $id): JsonResponse
    {
        $relations = ['members', 'cityRef', 'circleFounder', 'circleDirector', 'industryDirector', 'ded', 'eed', 'chairUser', 'viceChairUser', 'secretaryUser'];
        if (Schema::hasTable('circle_category_mappings') || Schema::hasTable('circle_categories')) {
            $relations[] = 'categories';
        }

        return $this->success(Circle::query()->with($relations)->findOrFail($id));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $circle = Circle::query()->findOrFail($id);

        if ($request->has('city_id')) {
            $rawCityId = $request->input('city_id');
            $resolvedCityId = null;
            if (!empty($rawCityId) && Str::isUuid((string) $rawCityId)) {
                $resolvedCityId = (string) $rawCityId;
            } elseif ($request->filled('city')) {
                $cityName = trim((string) $request->input('city'));
                $resolvedCityId = DB::table('cities')->where('name', 'ilike', $cityName)->value('id');
            }
            $request->merge(['city_id' => $resolvedCityId]);
        }
        if ($request->has('cover_file_id') && !empty($request->input('cover_file_id')) && !Str::isUuid((string) $request->input('cover_file_id'))) {
            $request->merge(['cover_file_id' => null]);
        }
        if ($request->has('circle_image_file_id') && !empty($request->input('circle_image_file_id')) && !Str::isUuid((string) $request->input('circle_image_file_id'))) {
            $request->merge(['circle_image_file_id' => null]);
        }

        $bgChairId = $request->input('business_growth_committee_chair_user_id') ?? $request->input('chair_user_id');
        $mgChairId = $request->input('membership_growth_committee_chair_user_id') ?? $request->input('vice_chair_user_id');
        $eiChairId = $request->input('events_impacts_committee_chair_user_id') ?? $request->input('secretary_user_id');

        $mergeData = [];
        if ($request->has('business_growth_committee_chair_user_id') || $request->has('chair_user_id')) {
            $mergeData['chair_user_id'] = (!empty($bgChairId) && Str::isUuid((string) $bgChairId)) ? (string) $bgChairId : null;
            $mergeData['business_growth_committee_chair_user_id'] = $mergeData['chair_user_id'];
        }
        if ($request->has('membership_growth_committee_chair_user_id') || $request->has('vice_chair_user_id')) {
            $mergeData['vice_chair_user_id'] = (!empty($mgChairId) && Str::isUuid((string) $mgChairId)) ? (string) $mgChairId : null;
            $mergeData['membership_growth_committee_chair_user_id'] = $mergeData['vice_chair_user_id'];
        }
        if ($request->has('events_impacts_committee_chair_user_id') || $request->has('secretary_user_id')) {
            $mergeData['secretary_user_id'] = (!empty($eiChairId) && Str::isUuid((string) $eiChairId)) ? (string) $eiChairId : null;
            $mergeData['events_impacts_committee_chair_user_id'] = $mergeData['secretary_user_id'];
        }
        if (!empty($mergeData)) {
            $request->merge($mergeData);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'purpose' => ['nullable', 'string'],
            'announcement' => ['nullable', 'string'],
            'city_id' => ['nullable', 'uuid', 'exists:cities,id'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string'],
            'status' => ['nullable', 'string'],
            'circle_stage' => ['nullable', 'string'],
            'industry_tags' => ['nullable'],
            'circle_founder_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'circle_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'industry_director_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'ded_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'eed_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'vice_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'secretary_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'business_growth_committee_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'membership_growth_committee_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'events_impacts_committee_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'cover_file_id' => ['nullable', 'uuid'],
            'circle_image_file_id' => ['nullable', 'uuid'],
            'payment_gateway' => ['nullable', 'string'],
            'payment_plan_id' => ['nullable', 'string'],
            'circle_package' => ['nullable', 'string'],
            'circle_price_amount' => ['nullable', 'numeric'],
            'circle_gst_percent' => ['nullable', 'numeric'],
            'is_package_active' => ['nullable', 'boolean'],
            'meeting_mode' => ['nullable', 'string'],
            'meeting_frequency' => ['nullable', 'string'],
            'launch_date' => ['nullable', 'date'],
            'meeting_repeat' => ['nullable'],
            'meeting_venue' => ['nullable', 'string'],
            'meeting_landmark' => ['nullable', 'string'],
            'meeting_link' => ['nullable', 'string'],
            'meeting_passcode' => ['nullable', 'string'],
            'calendar' => ['nullable', 'array'],
            'categories' => ['nullable', 'array'],
        ]);

        $fillableColumns = [
            'name', 'description', 'purpose', 'announcement', 'city_id', 'city', 'country',
            'type', 'status', 'circle_stage', 'circle_founder_user_id', 'circle_director_user_id',
            'industry_director_user_id', 'ded_user_id', 'eed_user_id',
            'chair_user_id', 'vice_chair_user_id', 'secretary_user_id',
            'cover_file_id',
            'circle_image_file_id', 'payment_gateway', 'payment_plan_id', 'circle_package',
            'circle_price_amount', 'circle_gst_percent', 'is_package_active', 'meeting_mode',
            'meeting_frequency', 'meeting_repeat', 'launch_date',
        ];

        foreach ($fillableColumns as $col) {
            if (array_key_exists($col, $validated) && Schema::hasColumn('circles', $col)) {
                $circle->{$col} = $validated[$col];
            }
        }

        if (array_key_exists('meeting_repeat', $validated)) {
            $repVal = $validated['meeting_repeat'];
            if (is_string($repVal)) {
                $repVal = json_decode($repVal, true);
            }
            if (is_array($repVal)) {
                $circle->meeting_repeat = $repVal;
            } elseif ($repVal === null) {
                $circle->meeting_repeat = null;
            }
        }

        if (array_key_exists('industry_tags', $validated)) {
            $tags = is_array($validated['industry_tags'])
                ? $validated['industry_tags']
                : array_filter(array_map('trim', explode(',', (string) $validated['industry_tags'])));
            $circle->industry_tags = array_values($tags);
        }

        $currentCalendar = is_array($circle->calendar) ? $circle->calendar : [];
        if (isset($validated['calendar']) && is_array($validated['calendar'])) {
            $currentCalendar = array_replace_recursive($currentCalendar, $validated['calendar']);
        }
        $rawRepeat = $circle->meeting_repeat
            ?? data_get($validated, 'calendar.settings.meeting_repeat')
            ?? data_get($validated, 'calendar.meeting_schedule.0')
            ?? data_get($currentCalendar, 'settings.meeting_repeat');
        if (is_array($rawRepeat) && !empty($rawRepeat)) {
            $normalizedRepeat = Circle::normalizeCadencePayload($rawRepeat, $currentCalendar);
            $circle->meeting_repeat = $normalizedRepeat;
            data_set($currentCalendar, 'settings.meeting_repeat', $normalizedRepeat);
            data_set($currentCalendar, 'meeting_schedule', [$normalizedRepeat]);
            data_set($currentCalendar, 'meetings', [$normalizedRepeat]);
            data_set($currentCalendar, 'monthly_rule', $normalizedRepeat['monthly_rule']);
            data_set($currentCalendar, 'week_of_month', $normalizedRepeat['week_of_month']);
            data_set($currentCalendar, 'week_number', $normalizedRepeat['week_number']);
            data_set($currentCalendar, 'ordinal', $normalizedRepeat['ordinal']);
            data_set($currentCalendar, 'default_meet_day', $normalizedRepeat['default_meet_day']);
            data_set($currentCalendar, 'day_of_week', $normalizedRepeat['day_of_week']);
            data_set($currentCalendar, 'default_meet_time', $normalizedRepeat['default_meet_time']);
            data_set($currentCalendar, 'start_time', $normalizedRepeat['start_time']);
            data_set($currentCalendar, 'end_time', $normalizedRepeat['end_time']);
            data_set($currentCalendar, 'frequency', $normalizedRepeat['frequency']);
        }
        if ($request->has('meeting_venue')) {
            data_set($currentCalendar, 'settings.meeting_venue', $request->input('meeting_venue'));
            data_set($currentCalendar, 'settings.meeting_address', $request->input('meeting_venue'));
        }
        if ($request->has('meeting_landmark')) {
            data_set($currentCalendar, 'settings.meeting_landmark', $request->input('meeting_landmark'));
        }
        if ($request->has('meeting_link')) {
            data_set($currentCalendar, 'settings.meeting_link', $request->input('meeting_link'));
        }
        if ($request->has('meeting_passcode')) {
            data_set($currentCalendar, 'settings.meeting_passcode', $request->input('meeting_passcode'));
        }
        if ($request->has('meeting_mode')) {
            data_set($currentCalendar, 'settings.meeting_mode', $request->input('meeting_mode'));
        }
        if ($request->has('meeting_frequency')) {
            data_set($currentCalendar, 'settings.meeting_frequency', $request->input('meeting_frequency'));
        }
        if ($request->has('launch_date')) {
            data_set($currentCalendar, 'settings.launch_date', $request->input('launch_date'));
        }
        if (array_key_exists('circle_founder_user_id', $validated)) {
            data_set($currentCalendar, 'leadership.circle_founder_user_id', $validated['circle_founder_user_id']);
        }
        if (array_key_exists('circle_director_user_id', $validated)) {
            data_set($currentCalendar, 'leadership.circle_director_user_id', $validated['circle_director_user_id']);
        }
        if (array_key_exists('industry_director_user_id', $validated)) {
            data_set($currentCalendar, 'leadership.industry_director_user_id', $validated['industry_director_user_id']);
        }
        if (array_key_exists('ded_user_id', $validated)) {
            data_set($currentCalendar, 'leadership.ded_user_id', $validated['ded_user_id']);
        }
        if (array_key_exists('eed_user_id', $validated)) {
            data_set($currentCalendar, 'leadership.eed_user_id', $validated['eed_user_id']);
        }
        if (array_key_exists('chair_user_id', $validated)) {
            data_set($currentCalendar, 'leadership.business_growth_committee_chair_user_id', $validated['chair_user_id']);
        }
        if (array_key_exists('vice_chair_user_id', $validated)) {
            data_set($currentCalendar, 'leadership.membership_growth_committee_chair_user_id', $validated['vice_chair_user_id']);
        }
        if (array_key_exists('secretary_user_id', $validated)) {
            data_set($currentCalendar, 'leadership.events_impacts_committee_chair_user_id', $validated['secretary_user_id']);
        }
        $circle->calendar = $currentCalendar;

        if (isset($validated['name']) && $validated['name'] !== $circle->getOriginal('name')) {
            $circle->slug = Circle::generateUniqueSlug($validated['name'], $circle->id);
        }

        $circle->save();

        if (isset($validated['categories']) && is_array($validated['categories']) && method_exists($circle, 'categories')) {
            $categoryIds = collect($validated['categories'])->filter()->map(fn($id) => (int) $id)->values()->all();
            $circle->categories()->sync($categoryIds);
        }

        $circle->load(['cityRef', 'circleFounder', 'circleDirector', 'industryDirector', 'ded', 'eed', 'categories']);

        return $this->success($circle, 'Circle updated successfully.');
    }

    public function patchStatus(Request $request, string $id): JsonResponse
    {
        $request->validate(['status' => ['required', 'string']]);

        return $this->update($request, $id);
    }

    public function assignFounder(Request $request, string $id): JsonResponse
    {
        $request->merge(['circle_founder_user_id' => $request->validate(['user_id' => 'required|uuid|exists:users,id'])['user_id']]);

        return $this->update($request, $id);
    }

    public function assignDirector(Request $request, string $id): JsonResponse
    {
        $request->merge(['circle_director_user_id' => $request->validate(['user_id' => 'required|uuid|exists:users,id'])['user_id']]);

        return $this->update($request, $id);
    }

    public function assignLeadershipTeam(Request $request, string $id): JsonResponse
    {
        $v = $request->validate([
            'chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'vice_chair_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'secretary_user_id' => ['nullable', 'uuid', 'exists:users,id'],
            'powerhouse_user_id' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        foreach (['chair_user_id' => 'chair', 'vice_chair_user_id' => 'vice_chair', 'secretary_user_id' => 'secretary', 'powerhouse_user_id' => 'committee_leader'] as $key => $role) {
            if (! empty($v[$key])) {
                CircleMember::query()->updateOrCreate(['circle_id' => $id, 'user_id' => $v[$key]], ['role' => $role, 'status' => 'approved', 'joined_at' => now(), 'deleted_at' => null]);
            }
        }

        return $this->success(['assigned' => true]);
    }

    public function joinRequests(string $id): JsonResponse
    {
        return $this->success(CircleJoinRequest::query()->where('circle_id', $id)->paginate(20));
    }

    public function peers(AdminPeerIndexRequest $request, string $id): JsonResponse
    {
        Circle::query()->findOrFail($id);
        $filters = $request->validated();
        $perPage = (int) $request->input('per_page', 20);

        $paginator = $this->peerService->listCirclePeers($id, $filters, $request->user(), $perPage);

        return $this->success($this->peerService->transformPaginated($paginator));
    }

    public function members(Request $request, string $id): JsonResponse
    {
        $circle = Circle::query()->findOrFail($id);

        if ($request->has('canonical')) {
            $paginator = $this->peerService->listCirclePeers($id, $request->all(), $request->user(), (int) $request->input('per_page', 20));

            return $this->success($this->peerService->transformPaginated($paginator));
        }

        $showDed = $request->boolean('show_ded') || in_array(strtolower(trim((string) $request->input('show_ded', ''))), ['1', 'true', 'on', 'yes'], true);
        $showId = $request->boolean('show_id') || $request->boolean('show_ids') || in_array(strtolower(trim((string) ($request->input('show_id') ?? $request->input('show_ids') ?? ''))), ['1', 'true', 'on', 'yes'], true);

        $peerNameFilter = trim((string) $request->input('peer_name', ''));
        $peerEmailFilter = trim((string) $request->input('peer_email', ''));

        $query = CircleMember::query()
            ->where('circle_id', $id)
            ->whereNull('deleted_at')
            ->with([
                'user.cityRelation',
                'roleRef',
                'joinedCircleCategory.level1Category',
                'joinedCircleCategory.level4Category',
            ])
            ->when(! $showDed || ! $showId, function ($q) use ($showDed, $showId): void {
                $excludedRoles = [];
                if (! $showDed) {
                    $excludedRoles[] = 'ded';
                }
                if (! $showId) {
                    $excludedRoles[] = 'industry_director';
                    $excludedRoles[] = 'id';
                }
                $q->whereNotIn(DB::raw('LOWER(circle_members.role::text)'), $excludedRoles);
            })
            ->when($peerNameFilter !== '', function ($q) use ($peerNameFilter): void {
                $like = '%'.$peerNameFilter.'%';
                $q->whereHas('user', function ($uq) use ($like): void {
                    $uq->where('display_name', 'ILIKE', $like)
                        ->orWhere('first_name', 'ILIKE', $like)
                        ->orWhere('last_name', 'ILIKE', $like)
                        ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) ILIKE ?", [$like]);
                });
            })
            ->when($peerEmailFilter !== '', function ($q) use ($peerEmailFilter): void {
                $like = '%'.$peerEmailFilter.'%';
                $q->whereHas('user', fn ($uq) => $uq->where('email', 'ILIKE', $like));
            })
            ->orderByDesc('joined_at')
            ->orderByDesc('created_at');

        $perPage = (int) $request->input('per_page', 50);
        $paginated = $query->paginate($perPage);

        $memberRows = collect($paginated->items())->map(function (CircleMember $membership): array {
            $user = $membership->user;
            $name = $user ? trim((string) (($user->first_name ?? '').' '.($user->last_name ?? ''))) : '';
            if ($name === '') {
                $name = (string) ($user?->display_name ?: $user?->name ?: 'Peer');
            }
            $company = (string) ($user?->company_name ?: $user?->business_name ?: 'No Company');
            $city = (string) ($user?->cityRelation?->name ?: $user?->city_name ?: $user?->city ?: 'No City');

            $roleSlug = strtolower(trim((string) ($membership->role ?? '')));
            $isDed = $roleSlug === 'ded';
            $isId = in_array($roleSlug, ['industry_director', 'id'], true);

            $joinedCat = $membership->joinedCircleCategory;
            $subCategory = $joinedCat?->level4Category;
            $mainCategory = $joinedCat?->level1Category;

            return [
                'id' => (string) $membership->id,
                'circle_id' => (string) $membership->circle_id,
                'user_id' => (string) $membership->user_id,
                'name' => $name,
                'email' => (string) ($user?->email ?? ''),
                'company_name' => $company,
                'city' => $city,
                'role' => (string) ($membership->role ?? 'member'),
                'role_id' => $membership->role_id ? (string) $membership->role_id : null,
                'role_name' => $membership->roleRef?->name ?? ucwords(str_replace('_', ' ', (string) $membership->role)),
                'is_ded' => $isDed,
                'is_id' => $isId,
                'status' => (string) ($membership->status ?? 'approved'),
                'joined_at' => $membership->joined_at ? Carbon::parse($membership->joined_at)->format('Y-m-d') : ($membership->created_at ? Carbon::parse($membership->created_at)->format('Y-m-d') : ''),
                'expires_at' => $membership->expires_at ? Carbon::parse($membership->expires_at)->format('Y-m-d') : null,
                'show_on_member_list' => (bool) ($membership->show_on_member_list ?? false),
                'level1_category' => $mainCategory ? ['id' => $mainCategory->id, 'name' => $mainCategory->name] : null,
                'level4_category' => $subCategory ? ['id' => $subCategory->id, 'name' => $subCategory->name] : null,
                'sub_category_name' => $subCategory?->name,
                'profile_photo_url' => $user?->profile_photo_url,
            ];
        });

        // Circle Categories
        $circleMainCategories = $circle->categories ?? collect();
        if ($circleMainCategories->isEmpty() && Schema::hasTable('circle_category_mappings')) {
            $mappedCategoryIds = DB::table('circle_category_mappings')
                ->where('circle_id', $circle->id)
                ->pluck('category_id')
                ->all();
            if (! empty($mappedCategoryIds)) {
                $circleMainCategories = CircleCategory::query()->whereIn('id', $mappedCategoryIds)->orderBy('sort_order')->get();
            }
        }
        if ($circleMainCategories->isEmpty()) {
            $circleMainCategories = CircleCategory::query()->orderBy('sort_order')->get();
        }

        $mainCategoryIds = $circleMainCategories->pluck('id')->all();
        $circleSubCategories = CircleCategoryLevel4::query()
            ->whereIn('circle_category_id', $mainCategoryIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'circle_category_id', 'level2_id', 'level3_id']);

        $categoryPeerCounts = DB::table('joined_circle_categories')
            ->where('circle_id', $circle->id)
            ->whereNotNull('level4_category_id')
            ->select('level4_category_id', DB::raw('count(*) as count'))
            ->groupBy('level4_category_id')
            ->pluck('count', 'level4_category_id')
            ->toArray();

        $roles = Role::query()->orderBy('name')->get(['id', 'name', 'key'])->map(fn ($r) => [
            'id' => (string) $r->id,
            'name' => (string) $r->name,
            'key' => (string) ($r->key ?? $r->id),
        ]);

        return response()->json([
            'success' => true,
            'data' => $memberRows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
            'circle_main_categories' => $circleMainCategories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'circle_sub_categories' => $circleSubCategories->map(fn ($sc) => [
                'id' => $sc->id,
                'name' => $sc->name,
                'circle_category_id' => $sc->circle_category_id,
            ]),
            'category_peer_counts' => $categoryPeerCounts,
            'roles' => $roles,
            'show_ded' => $showDed,
            'show_id' => $showId,
        ]);
    }

    public function peerOptions(Request $request, string $id): JsonResponse
    {
        Circle::query()->findOrFail($id);
        $q = trim((string) $request->input('q', ''));

        $query = User::query()
            ->whereNull('deleted_at')
            ->where('status', 'active');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('first_name', 'ILIKE', $like)
                    ->orWhere('last_name', 'ILIKE', $like)
                    ->orWhere('display_name', 'ILIKE', $like)
                    ->orWhere('email', 'ILIKE', $like)
                    ->orWhere('company_name', 'ILIKE', $like)
                    ->orWhereRaw("CONCAT_WS(' ', first_name, last_name) ILIKE ?", [$like]);
            });
        }

        $users = $query->limit(50)->get(['id', 'first_name', 'last_name', 'display_name', 'email', 'company_name', 'city']);

        $options = $users->map(function ($u): array {
            $name = trim(($u->first_name ?? '').' '.($u->last_name ?? ''));
            if ($name === '') {
                $name = $u->display_name ?: $u->email;
            }
            $subtitle = array_filter([$u->company_name, $u->city, $u->email]);

            return [
                'id' => (string) $u->id,
                'name' => $name,
                'email' => (string) $u->email,
                'company' => (string) ($u->company_name ?? ''),
                'city' => (string) ($u->city ?? ''),
                'text' => $name.(empty($subtitle) ? '' : ' ('.implode(' • ', $subtitle).')'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    public function addMember(Request $request, string $id): JsonResponse
    {
        $circle = Circle::query()->findOrFail($id);
        $v = $request->validate([
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'role' => ['required', 'string'],
            'joined_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'level1_category_id' => ['nullable'],
            'level4_category_id' => ['nullable'],
            'show_on_member_list' => ['nullable', 'boolean'],
        ]);

        $exists = DB::table('circle_members')
            ->where('circle_id', $id)
            ->where('user_id', $v['user_id'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'This peer is already a member of this circle.',
            ], 422);
        }

        $joinedAt = ! empty($v['joined_at']) ? Carbon::parse($v['joined_at']) : now();
        $expiresAt = ! empty($v['expires_at']) ? Carbon::parse($v['expires_at']) : null;
        $showOnMemberList = $request->boolean('show_on_member_list', false);

        $payload = [
            'circle_id' => $id,
            'user_id' => $v['user_id'],
            'role' => $v['role'],
            'status' => 'approved',
            'joined_at' => $joinedAt,
            'expires_at' => $expiresAt,
            'show_on_member_list' => $showOnMemberList,
        ];

        if (Str::isUuid($v['role'])) {
            $payload['role_id'] = $v['role'];
        }

        $member = DB::transaction(function () use ($circle, $v, $payload, $joinedAt, $expiresAt) {
            $existingMember = CircleMember::withTrashed()
                ->where('circle_id', $circle->id)
                ->where('user_id', $v['user_id'])
                ->first();

            if ($existingMember) {
                if ($existingMember->trashed()) {
                    $existingMember->restore();
                }
                $payload['left_at'] = null;
                $existingMember->forceFill($payload)->save();
                $m = $existingMember;
            } else {
                $payload['id'] = (string) Str::uuid();
                $m = CircleMember::query()->create($payload);
            }

            Circle::syncLeadershipFromMembers($circle);

            $level4Id = (int) ($v['level4_category_id'] ?? 0);
            if ($level4Id > 0 && Schema::hasTable('joined_circle_categories')) {
                $level4 = CircleCategoryLevel4::find($level4Id);
                $level1Id = $level4?->circle_category_id ?? ($v['level1_category_id'] ?? null);

                JoinedCircleCategory::query()->updateOrCreate(
                    ['circle_member_id' => $m->id],
                    [
                        'user_id' => $v['user_id'],
                        'circle_id' => $circle->id,
                        'level1_category_id' => $level1Id,
                        'level2_category_id' => $level4?->level2_id ?? null,
                        'level3_category_id' => $level4?->level3_id ?? null,
                        'level4_category_id' => $level4Id,
                    ]
                );
            } elseif (! empty($v['level1_category_id']) && Schema::hasTable('joined_circle_categories')) {
                JoinedCircleCategory::query()->updateOrCreate(
                    ['circle_member_id' => $m->id],
                    [
                        'user_id' => $v['user_id'],
                        'circle_id' => $circle->id,
                        'level1_category_id' => (int) $v['level1_category_id'],
                        'level2_category_id' => null,
                        'level3_category_id' => null,
                        'level4_category_id' => null,
                    ]
                );
            }

            $user = User::query()->find($v['user_id']);
            if ($user && empty($user->active_circle_id)) {
                $user->active_circle_id = $circle->id;
                $user->circle_joined_at = $joinedAt;
                $user->circle_expires_at = $expiresAt;
                $user->save();
            }

            return $m;
        });

        return response()->json([
            'success' => true,
            'message' => 'Member added to the circle.',
            'data' => $member,
        ]);
    }

    public function updateMember(Request $request, string $id, string $memberId): JsonResponse
    {
        $circle = Circle::query()->findOrFail($id);
        $membership = CircleMember::query()
            ->where('circle_id', $id)
            ->where(function ($q) use ($memberId): void {
                $q->where('id', $memberId)->orWhere('user_id', $memberId);
            })
            ->firstOrFail();

        $v = $request->validate([
            'role' => ['nullable', 'string'],
            'show_on_member_list' => ['nullable', 'boolean'],
        ]);

        if (isset($v['role'])) {
            $newRole = (string) $v['role'];
            if (Str::isUuid($newRole)) {
                $membership->role_id = $newRole;
                $membership->role = $newRole;
            } else {
                $membership->role = $newRole;
            }
        }

        if ($request->has('show_on_member_list')) {
            $membership->show_on_member_list = $request->boolean('show_on_member_list');
        }

        $membership->save();
        Circle::syncLeadershipFromMembers($circle);

        return response()->json([
            'success' => true,
            'message' => 'Member updated successfully.',
            'data' => $membership,
        ]);
    }

    public function toggleMemberList(Request $request, string $id, string $memberId): JsonResponse
    {
        $membership = CircleMember::query()
            ->where('circle_id', $id)
            ->where(function ($q) use ($memberId): void {
                $q->where('id', $memberId)->orWhere('user_id', $memberId);
            })
            ->firstOrFail();

        $newValue = $request->has('show_on_member_list')
            ? $request->boolean('show_on_member_list')
            : ! (bool) ($membership->show_on_member_list ?? false);

        $membership->show_on_member_list = $newValue;
        $membership->save();

        return response()->json([
            'success' => true,
            'show_on_member_list' => (bool) $membership->show_on_member_list,
            'message' => 'Member list visibility updated successfully.',
        ]);
    }

    public function updateMemberCategory(Request $request, string $id, string $memberId): JsonResponse
    {
        $membership = CircleMember::query()
            ->where('circle_id', $id)
            ->where(function ($q) use ($memberId): void {
                $q->where('id', $memberId)->orWhere('user_id', $memberId);
            })
            ->firstOrFail();

        $v = $request->validate([
            'level4_category_id' => ['required', 'integer', 'min:1'],
        ]);

        $level4 = CircleCategoryLevel4::find((int) $v['level4_category_id']);
        if (! $level4) {
            return response()->json(['success' => false, 'message' => 'Selected category not found.'], 404);
        }

        JoinedCircleCategory::query()->updateOrCreate(
            ['circle_member_id' => $membership->id],
            [
                'user_id' => $membership->user_id,
                'circle_id' => $id,
                'level1_category_id' => $level4->circle_category_id ?? null,
                'level2_category_id' => $level4->level2_id ?? null,
                'level3_category_id' => $level4->level3_id ?? null,
                'level4_category_id' => (int) $v['level4_category_id'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Category assigned successfully.',
        ]);
    }

    public function removeMember(string $id, string $memberId): JsonResponse
    {
        $circle = Circle::query()->findOrFail($id);
        $membership = CircleMember::query()
            ->where('circle_id', $id)
            ->where(function ($q) use ($memberId): void {
                $q->where('id', $memberId)->orWhere('user_id', $memberId);
            })
            ->firstOrFail();

        DB::transaction(function () use ($circle, $membership) {
            $circleChanged = false;

            // If the member being removed holds founder or leadership roles on the circle, unassign them
            if ($circle->circle_founder_user_id === $membership->user_id || in_array($membership->role, ['circle_founder', 'founder'], true)) {
                $circle->circle_founder_user_id = null;
                $calendar = is_array($circle->calendar) ? $circle->calendar : [];
                data_set($calendar, 'leadership.circle_founder_user_id', null);
                data_set($calendar, 'leadership.circle_founder_name', null);
                $circle->calendar = $calendar;
                $circleChanged = true;
            }

            if ($circle->circle_director_user_id === $membership->user_id || in_array($membership->role, ['circle_director', 'director'], true)) {
                $circle->circle_director_user_id = null;
                $calendar = is_array($circle->calendar) ? $circle->calendar : [];
                data_set($calendar, 'leadership.circle_director_user_id', null);
                data_set($calendar, 'leadership.circle_director_name', null);
                $circle->calendar = $calendar;
                $circleChanged = true;
            }

            if ($circle->industry_director_user_id === $membership->user_id) {
                $circle->industry_director_user_id = null;
                $calendar = is_array($circle->calendar) ? $circle->calendar : [];
                data_set($calendar, 'leadership.industry_director_user_id', null);
                data_set($calendar, 'leadership.industry_director_name', null);
                $circle->calendar = $calendar;
                $circleChanged = true;
            }

            if ($circle->ded_user_id === $membership->user_id) {
                $circle->ded_user_id = null;
                $circleChanged = true;
            }

            if ($circle->eed_user_id === $membership->user_id) {
                $circle->eed_user_id = null;
                $circleChanged = true;
            }

            if ($circleChanged) {
                $circle->save();
            }

            DB::table('users')
                ->where('id', $membership->user_id)
                ->where('active_circle_id', $circle->id)
                ->update([
                    'active_circle_id' => null,
                    'active_circle_addon_code' => null,
                    'active_circle_addon_name' => null,
                    'circle_joined_at' => null,
                    'circle_expires_at' => null,
                    'active_circle_subscription_id' => null,
                ]);

            $membership->forceFill(['left_at' => now()])->save();

            if (Schema::hasTable('joined_circle_categories')) {
                JoinedCircleCategory::query()->where('circle_member_id', $membership->id)->delete();
            }

            $membership->delete();
            Circle::syncLeadershipFromMembers($circle);
        });

        return response()->json([
            'success' => true,
            'message' => 'Member removed from the circle.',
        ]);
    }

    public function health(string $id): JsonResponse
    {
        $memberCount = CircleMember::query()->where('circle_id', $id)->where('status', 'approved')->whereNull('deleted_at')->count();
        $impactTotal = Impact::query()->where('circle_id', $id)->where('status', 'approved')->sum(DB::raw('COALESCE(impact_score,impact_value,1)'));
        $revenue = Payment::query()->where('circle_id', $id)->where('status', 'paid')->sum('amount');

        return $this->success([
            'current_members' => $memberCount,
            'target_members' => null,
            'avg_attendance' => null,
            'visitor_count' => (int) Circle::query()->where('id', $id)->value('visitor_count'),
            'conversions' => null,
            'renewals' => null,
            'total_impact' => (int) $impactTotal,
            'revenue' => (float) $revenue,
            'latest_health_log' => null,
        ]);
    }

    public function performance(string $id): JsonResponse
    {
        return $this->success([
            'founder' => Circle::query()->where('id', $id)->value('circle_founder_user_id'),
            'director' => Circle::query()->where('id', $id)->value('circle_director_user_id'),
            'leadership' => CircleMember::query()->where('circle_id', $id)->whereIn(DB::raw('role::text'), ['chair', 'vice_chair', 'secretary', 'committee_leader'])->whereNull('deleted_at')->get(),
        ]);
    }

    public function patchPackage(Request $request, string $id): JsonResponse
    {
        $circle = Circle::query()->findOrFail($id);
        $circle->fill($request->only(['zoho_addon_code', 'zoho_addon_id', 'zoho_addon_name', 'circle_price_amount', 'circle_price_currency', 'circle_duration_months']))->save();

        return $this->success($circle);
    }
}
