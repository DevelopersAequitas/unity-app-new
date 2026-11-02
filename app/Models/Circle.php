<?php

namespace App\Models;

use App\Services\Admin\DistrictSyncService;
use App\Services\Finance\LeaderTenureService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Circle extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_OPTIONS = ['pending', 'active', 'archived'];

    public const TYPE_OPTIONS = ['public', 'private'];

    public const MEETING_MODE_OPTIONS = ['online', 'offline', 'hybrid'];

    public const MEETING_FREQUENCY_OPTIONS = ['monthly', 'quarterly'];

    public const STAGE_OPTIONS = [
        'Conceptualized Circle',
        'Foundation Circle',
        'Pre-Launch Circle',
        'Launched Circle',
        'Growth Circle',
        'High-Impact Circle',
    ];

    public const RANK_OPTIONS = [
        'Bronze',
        'Silver',
        'Gold',
        'Platinum',
        'Titanium',
        'Diamond',
        'Royal',
        'Global Elite',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'purpose',
        'announcement',
        'circle_founder_user_id',
        'circle_director_user_id',
        'industry_director_user_id',
        'ded_user_id',
        'eed_user_id',
        'chair_user_id',
        'vice_chair_user_id',
        'secretary_user_id',
        'template_id',
        'status',
        'calendar',
        'city_id',
        'city',
        'industry_tags',
        'meeting_mode',
        'meeting_frequency',
        'meeting_repeat',
        'launch_date',
        'cover_file_id',
        'circle_image_file_id',
        'referral_score',
        'visitor_count',
        'type',
        'country',
        'circle_stage',
        'zoho_addon_code',
        'zoho_addon_id',
        'zoho_addon_name',
        'circle_price_amount',
        'circle_price_currency',
        'circle_duration_months',
        'circle_gst_percent',
        'is_package_active',
        'payment_gateway',
        'payment_plan_id',
    ];

    protected $casts = [
        'calendar' => 'array',
        'industry_tags' => 'array',
        'meeting_repeat' => 'array',
        'launch_date' => 'date',
        'circle_price_amount' => 'decimal:2',
        'circle_gst_percent' => 'decimal:2',
        'circle_duration_months' => 'integer',
        'is_package_active' => 'boolean',
    ];

    public function isRazorpay(): bool
    {
        return strtolower((string) ($this->payment_gateway ?? '')) === 'razorpay';
    }

    public function isZoho(): bool
    {
        return ! $this->isRazorpay();
    }

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'payment_plan_id', 'id');
    }

    protected $appends = ['cover_image_url', 'circle_image_url', 'city_display'];

    public function getCircleRanking(): array
    {
        $totalMembers = 0;

        if ($this->getAttribute('members_count') !== null) {
            $totalMembers = (int) $this->getAttribute('members_count');
        } elseif ($this->relationLoaded('members')) {
            $totalMembers = $this->members
                ->filter(function ($m): bool {
                    $status = strtolower((string) ($m->status ?? 'approved'));
                    $isApproved = in_array($status, ['approved', 'active'], true) || empty($m->status);

                    return $isApproved && ! CircleMember::isRegionalRole($m->role);
                })
                ->count();
        } else {
            $totalMembers = $this->members()
                ->where(function ($q): void {
                    $q->whereIn('status', CircleMember::activeStatuses())
                        ->orWhereNull('status');
                })
                ->whereNotIn(DB::raw('LOWER(circle_members.role::text)'), CircleMember::REGIONAL_ROLES)
                ->count();
        }

        return self::buildCircleRankingData($totalMembers);
    }

    public static function buildCircleRankingData(int $totalMembers): array
    {
        $totalMembers = max(0, $totalMembers);

        return [
            'total_members' => $totalMembers,
            'rank' => self::resolveCircleRank($totalMembers),
            'title' => self::resolveCircleTitle($totalMembers),
        ];
    }

    public static function rankRange(string $rank): ?array
    {
        return match ($rank) {
            'Bronze' => ['min' => 0, 'max' => 19],
            'Silver' => ['min' => 20, 'max' => 29],
            'Gold' => ['min' => 30, 'max' => 39],
            'Platinum' => ['min' => 40, 'max' => 49],
            'Titanium' => ['min' => 50, 'max' => 59],
            'Diamond' => ['min' => 60, 'max' => 74],
            'Royal' => ['min' => 75, 'max' => 99],
            'Global Elite' => ['min' => 100, 'max' => null],
            default => null,
        };
    }

    private static function resolveCircleRank(int $totalMembers): string
    {
        if ($totalMembers >= 100) {
            return 'Global Elite';
        }

        return match (true) {
            $totalMembers >= 75 => 'Royal',
            $totalMembers >= 60 => 'Diamond',
            $totalMembers >= 50 => 'Titanium',
            $totalMembers >= 40 => 'Platinum',
            $totalMembers >= 30 => 'Gold',
            $totalMembers >= 20 => 'Silver',
            default => 'Bronze',
        };
    }

    private static function resolveCircleTitle(int $totalMembers): string
    {
        if ($totalMembers >= 100) {
            return 'Flagship Circle';
        }

        return match (true) {
            $totalMembers >= 75 => 'Legacy Circle',
            $totalMembers >= 60 => 'Iconic Circle',
            $totalMembers >= 50 => 'Growth Powerhouse',
            $totalMembers >= 40 => 'Influencer Circle',
            $totalMembers >= 30 => 'Collaborative Circle',
            $totalMembers >= 20 => 'Trusted Circle',
            default => 'Rising Circle',
        };
    }

    protected static function booted()
    {
        static::creating(function (Circle $circle) {
            if (empty($circle->id)) {
                $circle->id = Str::uuid()->toString();
            }

            if (empty($circle->slug)) {
                $base = Str::slug($circle->name ?: 'circle');
                if ($base === '') {
                    $base = 'circle';
                }

                $circle->slug = static::generateUniqueSlug($base);
            }

            if (empty($circle->status)) {
                $circle->status = 'pending';
            }
        });

        static::saved(function (Circle $circle): void {
            if ($circle->wasRecentlyCreated || $circle->wasChanged(['city_id', 'city', 'city_display', 'state', 'district'])) {
                app(DistrictSyncService::class)->syncFromCircle($circle);
            }

            if ($circle->wasRecentlyCreated || $circle->wasChanged([
                'circle_founder_user_id',
                'founder_user_id',
                'circle_director_user_id',
                'director_user_id',
                'industry_director_user_id',
                'ded_user_id',
                'eed_user_id',
                'chair_user_id',
                'vice_chair_user_id',
                'secretary_user_id',
                'calendar',
            ])) {
                app(LeaderTenureService::class)->syncFromCircle(
                    $circle,
                    auth('admin')->id() ? (string) auth('admin')->id() : null,
                    'circle_model_saved'
                );
            }

            if (Schema::hasTable('tbl_permission_cache')) {
                DB::table('tbl_permission_cache')->truncate();
            }
            Cache::flush();
        });

        static::deleted(function (Circle $circle): void {
            if (Schema::hasTable('tbl_permission_cache')) {
                DB::table('tbl_permission_cache')->truncate();
            }
            Cache::flush();
        });
    }

    public function calendarGet(string $path, $default = null)
    {
        $calendar = is_array($this->calendar) ? $this->calendar : [];

        return data_get($calendar, $path, $default);
    }

    public function calendarSet(string $path, $value): void
    {
        $calendar = is_array($this->calendar) ? $this->calendar : [];
        data_set($calendar, $path, $value);
        $this->calendar = $calendar;
    }

    public function getMeetingModeAttribute(): ?string
    {
        $value = $this->calendarGet('settings.meeting_mode');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function getMeetingFrequencyAttribute(): ?string
    {
        $value = $this->calendarGet('settings.meeting_frequency');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function getMeetingLinkAttribute(): ?string
    {
        $value = $this->calendarGet('settings.meeting_link');

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $this->zoho_join_url ?? null;
    }

    public function getMeetingPasscodeAttribute(): ?string
    {
        $value = $this->calendarGet('settings.meeting_passcode');

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return $this->zoho_meeting_password ?? null;
    }

    public function getMeetingVenueAttribute(): ?string
    {
        $value = $this->calendarGet('settings.meeting_venue')
            ?? $this->calendarGet('settings.meeting_address');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function getMeetingLandmarkAttribute(): ?string
    {
        $value = $this->calendarGet('settings.meeting_landmark');

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function getLaunchDateAttribute($value): ?string
    {
        if ($value) {
            return is_string($value) ? $value : (string) $value;
        }

        $calendarDate = $this->calendarGet('settings.launch_date');

        return is_string($calendarDate) && trim($calendarDate) !== '' ? trim($calendarDate) : null;
    }

    public function getCircleDirectorUserIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('leadership.circle_director_user_id')
            ?? $this->calendarGet('leadership.director_user_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getDirectorUserIdAttribute($value): ?string
    {
        return $this->getCircleDirectorUserIdAttribute($value);
    }

    public function getCircleFounderUserIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('leadership.circle_founder_user_id')
            ?? $this->calendarGet('leadership.founder_user_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getFounderUserIdAttribute($value): ?string
    {
        return $this->getCircleFounderUserIdAttribute($value);
    }

    public function getIndustryDirectorUserIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('leadership.industry_director_user_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getDedUserIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('leadership.ded_user_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getEedUserIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('leadership.eed_user_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getCoverFileIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('cover.file_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getCircleImageFileIdAttribute($value): ?string
    {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        $calendarValue = $this->calendarGet('circle_image.file_id')
            ?? $this->calendarGet('circle_image_file_id');

        return is_string($calendarValue) && trim($calendarValue) !== '' ? trim($calendarValue) : null;
    }

    public function getMeetingScheduleAttribute(): array
    {
        $value = $this->calendarGet('meeting_schedule', []);

        return is_array($value) ? $value : [];
    }

    public static function normalizeCadencePayload(array $rawRepeat, ?array $calendar = null): array
    {
        $rawWeek = (string) ($rawRepeat['week_of_month'] ?? $rawRepeat['monthly_rule'] ?? $rawRepeat['week_number'] ?? data_get($calendar, 'monthly_rule') ?? 'week_1');
        $weekNum = match (strtolower(trim($rawWeek))) {
            'week_1', 'first', '1', '1st' => 1,
            'week_2', 'second', '2', '2nd' => 2,
            'week_3', 'third', '3', '3rd' => 3,
            'week_4', 'week_4_provisional', 'fourth', '4', '4th' => 4,
            'last', 'fifth', '5', '5th' => 5,
            default => 1,
        };

        $ordinalText = match ($weekNum) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            default => 'Last',
        };

        $monthlyRule = match ($weekNum) {
            1 => 'first',
            2 => 'second',
            3 => 'third',
            4 => 'fourth',
            default => 'last',
        };

        $weekday = strtolower(trim((string) ($rawRepeat['weekday'] ?? $rawRepeat['day_of_week'] ?? $rawRepeat['default_meet_day'] ?? data_get($calendar, 'default_meet_day') ?? 'wednesday')));
        $time = trim((string) ($rawRepeat['start_time'] ?? $rawRepeat['time'] ?? $rawRepeat['default_meet_time'] ?? data_get($calendar, 'default_meet_time') ?? '18:30'));
        $endTime = trim((string) ($rawRepeat['end_time'] ?? $rawRepeat['default_meet_end_time'] ?? data_get($calendar, 'end_time') ?? '21:00'));
        $frequency = strtolower(trim((string) ($rawRepeat['frequency'] ?? $rawRepeat['unit'] ?? data_get($calendar, 'frequency') ?? 'monthly')));
        if ($frequency === 'month') {
            $frequency = 'monthly';
        }
        if ($frequency === 'week') {
            $frequency = 'weekly';
        }

        return [
            'repeat_every' => (int) ($rawRepeat['repeat_every'] ?? 1),
            'interval' => (int) ($rawRepeat['interval'] ?? 1),
            'unit' => $frequency === 'weekly' ? 'week' : 'month',
            'frequency' => $frequency,
            'week_of_month' => "week_{$weekNum}",
            'week_number' => $weekNum,
            'week' => $weekNum,
            'ordinal' => $weekNum,
            'ordinal_text' => $ordinalText,
            'monthly_rule' => $monthlyRule,
            'weekday' => $weekday,
            'day_of_week' => $weekday,
            'default_meet_day' => $weekday,
            'start_time' => $time,
            'time' => $time,
            'default_meet_time' => $time,
            'end_time' => $endTime,
            'default_meet_end_time' => $endTime,
        ];
    }

    public function calculateUpcomingMeeting(?Carbon $now = null): ?array
    {
        $repeat = $this->meeting_repeat;
        if (! is_array($repeat) || empty($repeat)) {
            $repeat = $this->calendarGet('settings.meeting_repeat');
        }
        if (! is_array($repeat) || empty($repeat)) {
            $firstSchedule = $this->calendarGet('meeting_schedule.0') ?? $this->calendarGet('meetings.0');
            if (is_array($firstSchedule)) {
                $repeat = $firstSchedule;
            }
        }
        if (! is_array($repeat) || empty($repeat)) {
            return null;
        }

        $normalized = self::normalizeCadencePayload($repeat, is_array($this->calendar) ? $this->calendar : null);
        $now = $now ? $now->copy() : Carbon::now(config('app.timezone', 'Asia/Kolkata'));

        $frequency = $normalized['frequency'];
        $weekday = $normalized['weekday'];
        $time = $normalized['start_time'];
        $weekNum = $normalized['week_number'];
        $ordinalText = $normalized['ordinal_text'];
        $monthlyRule = $normalized['monthly_rule'];

        if ($frequency === 'weekly') {
            $candidate = $now->copy();
            while (strtolower($candidate->format('l')) !== $weekday) {
                $candidate->addDay();
            }
            [$h, $m] = explode(':', $time) + [0, 0];
            $candidate->setTime((int) $h, (int) $m, 0);
            if ($candidate->lt($now)) {
                $candidate->addWeek();
            }
        } else {
            // Monthly
            $candidate = self::findNthWeekdayOfMonth($now->year, $now->month, $weekNum, $weekday, $time);
            if ($candidate->lt($now)) {
                $nextMonth = $now->copy()->startOfMonth()->addMonth();
                $candidate = self::findNthWeekdayOfMonth($nextMonth->year, $nextMonth->month, $weekNum, $weekday, $time);
            }
        }

        $formattedTime = Carbon::createFromFormat('H:i', substr($time, 0, 5))->format('h:i A');
        $shortDay = $candidate->format('D');
        $datePart = $candidate->format('j M');
        $display = "{$ordinalText} {$shortDay}, {$datePart} • {$formattedTime}";

        return [
            'date' => $candidate->toDateString(),
            'time' => $time,
            'end_time' => $normalized['end_time'],
            'day' => $shortDay,
            'day_name' => $candidate->format('l'),
            'ordinal' => $ordinalText,
            'week_number' => $weekNum,
            'week_of_month' => "week_{$weekNum}",
            'monthly_rule' => $monthlyRule,
            'formatted_date' => $candidate->format('j M Y'),
            'formatted_time' => $formattedTime,
            'display' => $display,
            'starts_at' => $candidate->toIso8601String(),
        ];
    }

    private static function findNthWeekdayOfMonth(int $year, int $month, int $nth, string $weekday, string $time): Carbon
    {
        $dt = Carbon::create($year, $month, 1, 0, 0, 0);
        [$hour, $minute] = explode(':', $time) + [0, 0];

        while (strtolower($dt->format('l')) !== strtolower($weekday)) {
            $dt->addDay();
        }

        if ($nth > 1) {
            $dt->addWeeks($nth - 1);
        }

        $dt->setTime((int) $hour, (int) $minute, 0);

        return $dt;
    }

    public function getUpcomingMeetingAttribute(): ?array
    {
        return $this->calculateUpcomingMeeting();
    }

    public function getUpcomingMeetingDisplayAttribute(): ?string
    {
        $meeting = $this->calculateUpcomingMeeting();

        return $meeting['display'] ?? null;
    }

    public function getCityDisplayAttribute(): ?string
    {
        if ($this->relationLoaded('cityRef') && $this->cityRef) {
            return $this->cityRef->name ?? null;
        }

        $raw = $this->getAttribute('city');

        if ($raw === null || $raw === '') {
            if (! empty($this->getAttribute('city_id'))) {
                $city = $this->cityRef()->first();

                return $city?->name;
            }

            return null;
        }

        if (is_array($raw)) {
            return $raw['name'] ?? $raw['district'] ?? null;
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);

            if ($trimmed !== '' && ($trimmed[0] === '{' || $trimmed[0] === '[')) {
                $decoded = json_decode($trimmed, true);

                if (is_array($decoded)) {
                    return $decoded['name'] ?? $decoded['district'] ?? null;
                }
            }

            return $raw;
        }

        return null;
    }

    public static function normalizeCityPayload(string $cityName, ?array $existing = null): array
    {
        $existing = is_array($existing) ? $existing : [];
        $existing['name'] = $cityName;

        return $existing;
    }

    public function circleFounder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'circle_founder_user_id');
    }

    public function circleFounderUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'circle_founder_user_id');
    }

    public function circleDirector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'circle_director_user_id');
    }

    public function founder(): BelongsTo
    {
        return $this->circleFounder();
    }

    public function founderUser(): BelongsTo
    {
        return $this->circleFounderUser();
    }

    public function director(): BelongsTo
    {
        return $this->circleDirector();
    }

    public function industryDirector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'industry_director_user_id');
    }

    public function ded(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ded_user_id');
    }

    public function eed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'eed_user_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CircleTemplate::class, 'template_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function cityRef(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class, 'district_id');
    }

    public function chairUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'chair_user_id');
    }

    public function viceChairUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vice_chair_user_id');
    }

    public function secretaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'secretary_user_id');
    }

    public function circleCategory(): BelongsTo
    {
        return $this->belongsTo(CircleCategory::class, 'template_id');
    }

    public function chatMessages(): HasMany
    {
        return $this->hasMany(CircleChatMessage::class, 'circle_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CircleMember::class, 'circle_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(CircleMember::class);
    }

    public function circleSubscriptions(): HasMany
    {
        return $this->hasMany(CircleSubscription::class, 'circle_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'circle_members', 'circle_id', 'user_id')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function selectedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_circles', 'circle_id', 'event_id')
            ->withTimestamps();
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(CircleCategory::class, 'circle_category_mappings', 'circle_id', 'category_id')
            ->withPivot(['id', 'circle_id', 'category_id', 'created_at', 'updated_at'])
            ->withTimestamps();
    }

    public function coverFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_file_id');
    }

    public function circleImageFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'circle_image_file_id');
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        if (! $this->cover_file_id) {
            return null;
        }

        if ($this->relationLoaded('coverFile') && $this->coverFile && isset($this->coverFile->url)) {
            return $this->coverFile->url;
        }

        return url('/api/v1/files/'.$this->cover_file_id);
    }

    public function getCircleImageUrlAttribute(): ?string
    {
        if ($this->circle_image_file_id) {
            if ($this->relationLoaded('circleImageFile') && $this->circleImageFile && isset($this->circleImageFile->url)) {
                return $this->circleImageFile->url;
            }

            return url('/api/v1/files/'.$this->circle_image_file_id);
        }

        $calendarUrl = $this->calendarGet('circle_image.url')
            ?? $this->calendarGet('circle_image_url')
            ?? $this->calendarGet('image_url');

        if (is_string($calendarUrl) && trim($calendarUrl) !== '') {
            return trim($calendarUrl);
        }

        if ($this->cover_file_id) {
            return $this->cover_image_url;
        }

        return null;
    }

    public static function generateUniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name);

        if ($base === '') {
            $base = 'circle';
        }

        $slug = $base;
        $i = 1;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public static function syncLeadershipFromMembers(Circle|string $circle): void
    {
        if (! Schema::hasTable('circles')) {
            return;
        }

        $circleModel = is_string($circle) ? static::find($circle) : $circle;
        if (! $circleModel) {
            return;
        }

        $members = CircleMember::query()
            ->where('circle_id', $circleModel->id)
            ->whereNull('deleted_at')
            ->whereIn(DB::raw('status::text'), ['approved', 'active'])
            ->with(['roleRef'])
            ->get();

        $roleToColumns = [
            'ded' => ['ded_user_id'],
            'industry_director' => ['industry_director_user_id'],
            'id' => ['industry_director_user_id'],
            'circle_director' => ['circle_director_user_id', 'director_user_id'],
            'director' => ['circle_director_user_id', 'director_user_id'],
            'cd' => ['circle_director_user_id', 'director_user_id'],
            'circle_founder' => ['circle_founder_user_id', 'founder_user_id'],
            'founder' => ['circle_founder_user_id', 'founder_user_id'],
            'cf' => ['circle_founder_user_id', 'founder_user_id'],
            'eed' => ['eed_user_id'],
            'chair' => ['chair_user_id'],
            'vice_chair' => ['vice_chair_user_id'],
            'secretary' => ['secretary_user_id'],
        ];

        $allLeadershipCols = [
            'ded_user_id',
            'industry_director_user_id',
            'circle_director_user_id',
            'director_user_id',
            'circle_founder_user_id',
            'founder_user_id',
            'eed_user_id',
            'chair_user_id',
            'vice_chair_user_id',
            'secretary_user_id',
        ];

        $calendar = is_array($circleModel->calendar) ? $circleModel->calendar : (is_string($circleModel->calendar) ? json_decode($circleModel->calendar, true) : []);

        // Start by PRESERVING existing leadership values from table and calendar rather than resetting to null
        $newValues = [];
        foreach ($allLeadershipCols as $col) {
            $existingVal = $circleModel->getAttribute($col) ?? data_get($calendar, 'leadership.'.$col) ?? null;
            $newValues[$col] = $existingVal;
        }

        foreach ($members as $member) {
            $userId = $member->user_id;
            if (! $userId) {
                continue;
            }

            $roleKeys = [];
            if (! empty($member->role)) {
                $roleKeys[] = strtolower(trim((string) $member->role));
            }
            if ($member->roleRef && ! empty($member->roleRef->key)) {
                $roleKeys[] = strtolower(trim((string) $member->roleRef->key));
            }
            if ($member->roleRef && ! empty($member->roleRef->name)) {
                $roleKeys[] = strtolower(trim((string) $member->roleRef->name));
            }
            $roleKeys = array_unique($roleKeys);

            foreach ($roleKeys as $rk) {
                $normalizedRk = str_replace(' ', '_', $rk);

                $targetCols = $roleToColumns[$rk] ?? $roleToColumns[$normalizedRk] ?? null;
                if ($targetCols) {
                    foreach ($targetCols as $col) {
                        if (array_key_exists($col, $newValues)) {
                            $newValues[$col] = $userId;
                        }
                    }
                }
            }
        }

        // Validate all user IDs against users table to prevent foreign key constraint violations
        if (Schema::hasTable('users')) {
            $candidateUserIds = array_filter(array_values($newValues));

            if (isset($calendar['leadership']) && is_array($calendar['leadership'])) {
                foreach ($calendar['leadership'] as $lKey => $lVal) {
                    if (is_string($lVal) && $lVal !== '') {
                        $candidateUserIds[] = $lVal;
                    }
                }
            }

            $candidateUserIds = array_values(array_unique($candidateUserIds));

            if (! empty($candidateUserIds)) {
                $validUserIds = DB::table('users')
                    ->whereIn('id', $candidateUserIds)
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->all();

                $validUserIdSet = array_flip($validUserIds);

                foreach ($newValues as $col => $val) {
                    if ($val !== null && ! isset($validUserIdSet[(string) $val])) {
                        $newValues[$col] = null;
                    }
                }

                if (isset($calendar['leadership']) && is_array($calendar['leadership'])) {
                    foreach ($calendar['leadership'] as $lKey => $lVal) {
                        if ($lVal !== null && ! isset($validUserIdSet[(string) $lVal])) {
                            $calendar['leadership'][$lKey] = null;
                        }
                    }
                }
            }
        }

        $updates = [];

        foreach ($newValues as $col => $val) {
            if (Schema::hasColumn('circles', $col)) {
                $updates[$col] = $val;
            }
            data_set($calendar, 'leadership.'.$col, $val);
        }
        $updates['calendar'] = json_encode($calendar);
        $updates['updated_at'] = now();

        DB::table('circles')->where('id', $circleModel->id)->update($updates);
        $circleModel->refresh();

        app(LeaderTenureService::class)->syncFromCircle(
            $circleModel,
            auth('admin')->id() ? (string) auth('admin')->id() : null,
            'circle_member_sync'
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'circle_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CircleSubscription::class, 'circle_id');
    }
}
