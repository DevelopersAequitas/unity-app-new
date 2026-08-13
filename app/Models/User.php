<?php

namespace App\Models;

use App\Services\Admin\DistrictSyncService;
use App\Services\Creative\WearTheBadgeImageGenerator;
use App\Services\MilestoneBadgeService;
use App\Support\CoinMilestoneResolver;
use App\Support\ContributionMilestoneResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;
use Throwable;

class User extends Authenticatable
{
    public const STATUS_FREE_TRIAL = 'free_trial_peer';

    public const STATUS_FREE = 'free_peer';

    public const STATUS_GREEN_PEER = 'Only Unity Peer';

    public const STATUS_GREEN_PEER_LABEL = 'Global Peer';

    private const FREE_PEER_STATUS_CANDIDATES = [self::STATUS_FREE, 'Free Peer', 'Free_peer'];

    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'first_name',
        'last_name',
        'display_name',
        'email',
        'phone',
        'password',
        'password_hash',
        'company_name',
        'designation',
        'gender',
        'dob',
        'experience_years',
        'experience_summary',
        'city_id',
        'city',
        'status',
        'approval_status',
        'contacts_allowed',
        'skills',
        'interests',
        'social_links',
        'media',
        'profile_photo_id',
        'cover_photo_id',
        'business_logo_id',
        'state',
        'country',
        'timezone',
        'preferred_language',
        'business_category_id',
        'business_sub_category',
        'company_type',
        'year_of_establishment',
        'annual_revenue_range',
        'number_of_employees',
        'gst_number',
        'business_website',
        'superpower',
        'i_can_help_with',
        'i_am_looking_for',
        'business_keywords',
        'products_services_offered',
        'secondary_mobile',
        'linkedin_profile',
        'instagram_handle',
        'twitter_handle',
        'facebook_profile',
        'youtube_channel',
        'other_website',
        'contact_visibility',
        'profile_visibility',
        'business_address',
        'business_city',
        'business_state',
        'business_pincode',
        'business_country',
        'google_maps_latitude',
        'google_maps_longitude',
        'industries_of_interest',
        'collaboration_goals',
        'preferred_meeting_format',
        'willing_to_mentor',
        'open_to_cross_city_collaboration',
        'open_to_speaking_at_events',
        'membership_status',
        'membership_expiry',
        'coins_balance',
        'coins_remark',
        'membership_expiry_date_remark',
        'coin_medal_rank',
        'coin_milestone_title',
        'coin_milestone_meaning',
        'contribution_award_name',
        'contribution_award_recognition',
        'profile_photo_url',
        'welcome_creative_url',
        'profile_card_image_url',
        'short_bio',
        'long_bio_html',
        'industry_tags',
        'business_type',
        'turnover_range',
        'introduced_by',
        'members_introduced_count',
        'life_impacted_count',
        'influencer_stars',
        'target_regions',
        'target_business_categories',
        'main_business_category_id',
        'city_of_residence',
        'referred_by_user_id',
        'hobbies_interests',
        'leadership_roles',
        'is_sponsored_member',
        'public_profile_slug',
        'special_recognitions',
        'gdpr_deleted_at',
        'anonymized_at',
        'is_gdpr_exported',
        'last_login_at',
        'last_seen_at',
        'is_online',
        'profile_photo_file_id',
        'cover_photo_file_id',
        'profile_video_id',
        'zoho_customer_id',
        'zoho_subscription_id',
        'zoho_plan_code',
        'zoho_last_invoice_id',
        'membership_starts_at',
        'membership_ends_at',
        'membership_start_date',
        'membership_end_date',
        'membership_approved_at',
        'membership_approved_by',
        'last_payment_at',
        'active_circle_subscription_id',
        'circle_joined_at',
        'circle_expires_at',
        'active_circle_id',
        'active_circle_addon_code',
        'active_circle_addon_name',
        'welcome_membership_email_sent_at',
        'welcome_membership_email_status',
        'welcome_membership_email_error',
        'welcome_membership_email_plan_code',
        'website',
        'sustainability_contribution',
        'sustainability_areas',
        'greenpreneur_goals',
        'community_directory_listing',
        'anniversary_date',
        'android_fcm_token',
        'ios_fcm_token',
        'bookmarks',
        'global_peer_certificate_sent_at',
        'global_peer_certificate_file_id',
    ];

    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'industry_tags' => 'array',
        'target_regions' => 'array',
        'target_business_categories' => 'array',
        'hobbies_interests' => 'array',
        'leadership_roles' => 'array',
        'special_recognitions' => 'array',
        'membership_expiry' => 'datetime',
        'gdpr_deleted_at' => 'datetime',
        'anonymized_at' => 'datetime',
        'last_login_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'membership_starts_at' => 'datetime',
        'membership_ends_at' => 'datetime',
        'membership_start_date' => 'date',
        'membership_end_date' => 'date',
        'membership_approved_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'circle_joined_at' => 'datetime',
        'circle_expires_at' => 'datetime',
        'welcome_membership_email_sent_at' => 'datetime',
        'global_peer_certificate_sent_at' => 'datetime',
        'dob' => 'date',
        'anniversary_date' => 'date',
        'skills' => 'array',
        'interests' => 'array',
        'social_links' => 'array',
        'media' => 'array',
        'i_can_help_with' => 'array',
        'i_am_looking_for' => 'array',
        'business_keywords' => 'array',
        'industries_of_interest' => 'array',
        'collaboration_goals' => 'array',
        'year_of_establishment' => 'integer',
        'google_maps_latitude' => 'decimal:7',
        'google_maps_longitude' => 'decimal:7',
        'willing_to_mentor' => 'boolean',
        'open_to_cross_city_collaboration' => 'boolean',
        'open_to_speaking_at_events' => 'boolean',
        'coins_balance' => 'integer',
        'is_sponsored_member' => 'boolean',
        'life_impacted_count' => 'integer',
        'is_online' => 'boolean',
        'sustainability_areas' => 'array',
        'greenpreneur_goals' => 'array',
        'contacts_allowed' => 'boolean',
        'bookmarks' => 'array',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function getLifeImpactedCountAttribute($value): int
    {
        $count = (int) ($value ?? 0);
        if ($count > 0) {
            return $count;
        }

        if (! $this->exists || ! isset($this->id)) {
            return 0;
        }

        if (Schema::hasTable('life_impact_histories')) {
            $hasStatus = Schema::hasColumn('life_impact_histories', 'status');
            $hasCounted = Schema::hasColumn('life_impact_histories', 'counted_in_total');
            $hasImpactValue = Schema::hasColumn('life_impact_histories', 'impact_value');
            $hasLifeImpacted = Schema::hasColumn('life_impact_histories', 'life_impacted');

            $valueExpr = ($hasImpactValue && $hasLifeImpacted)
                ? 'COALESCE(NULLIF(impact_value, 0), NULLIF(life_impacted, 0), 0)'
                : ($hasImpactValue ? 'COALESCE(NULLIF(impact_value, 0), 0)' : ($hasLifeImpacted ? 'COALESCE(NULLIF(life_impacted, 0), 0)' : '0'));

            $query = DB::table('life_impact_histories')
                ->where('user_id', (string) $this->id);

            if ($hasCounted) {
                $query->where(function ($q): void {
                    $q->whereNull('counted_in_total')->orWhere('counted_in_total', true);
                });
            }

            if ($hasStatus) {
                $query->where(function ($q): void {
                    $q->whereNull('status')->orWhere('status', 'approved');
                });
            }

            $sum = (int) $query->sum(DB::raw($valueExpr));
            if ($sum > 0) {
                return $sum;
            }
        }

        if (Schema::hasTable('impacts')) {
            $hasImpactsStatus = Schema::hasColumn('impacts', 'status');
            $hasImpactsLife = Schema::hasColumn('impacts', 'life_impacted');
            $impactsLifeExpr = $hasImpactsLife ? 'COALESCE(NULLIF(life_impacted, 0), 1)' : '1';

            $query = DB::table('impacts')
                ->where('user_id', (string) $this->id);

            if ($hasImpactsStatus) {
                $query->where(function ($q): void {
                    $q->whereNull('status')->orWhere('status', 'approved');
                });
            }

            $sum = (int) $query->sum(DB::raw($impactsLifeExpr));
            if ($sum > 0) {
                return $sum;
            }
        }

        return 0;
    }

    public function contactPosts(): HasMany
    {
        return $this->hasMany(ContactPost::class, 'user_id');
    }

    public function approvedSentConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'requester_id')->where('is_approved', true);
    }

    public function approvedReceivedConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'addressee_id')->where('is_approved', true);
    }

    public function introducedMembers(): HasMany
    {
        return $this->hasMany(User::class, 'introduced_by');
    }

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->syncMembershipExpiryAttributes();
            $user->syncCoinMilestoneAttributes();
            $user->syncContributionMilestoneAttributes();
        });

        static::creating(function (self $user): void {
            if (empty($user->id)) {
                $user->id = Str::uuid()->toString();
            }

            if (empty($user->display_name)) {
                $user->display_name = trim($user->first_name.' '.($user->last_name ?? ''));
            }

            if (empty($user->peer_id)) {
                $hasPeerIdColumn = true;
                try {
                    $hasPeerIdColumn = Schema::hasColumn('users', 'peer_id');
                } catch (Throwable) {
                    $hasPeerIdColumn = false;
                }

                if ($hasPeerIdColumn) {
                    $usedSeq = false;
                    try {
                        if (DB::getDriverName() === 'pgsql') {
                            $seqCheck = DB::selectOne("SELECT to_regclass('peer_id_seq') AS seq_exists");
                            if (! empty($seqCheck?->seq_exists)) {
                                $seqResult = DB::selectOne("SELECT 'PG3182736' || nextval('peer_id_seq') AS peer_id");
                                if (! empty($seqResult?->peer_id)) {
                                    $user->peer_id = (string) $seqResult->peer_id;
                                    $usedSeq = true;
                                }
                            }
                        }
                    } catch (Throwable) {
                        $usedSeq = false;
                    }

                    if (! $usedSeq) {
                        $maxNum = 0;
                        try {
                            $maxUser = static::query()
                                ->where('peer_id', 'LIKE', 'PG3182736%')
                                ->get()
                                ->filter(fn ($u): bool => ! empty($u->peer_id) && preg_match('/^PG3182736([0-9]+)$/', (string) $u->peer_id))
                                ->sortByDesc(fn ($u): int => (int) substr((string) $u->peer_id, 9))
                                ->first();

                            if ($maxUser && ! empty($maxUser->peer_id)) {
                                $maxNum = (int) substr((string) $maxUser->peer_id, 9);
                            }
                        } catch (Throwable) {
                            $maxNum = 0;
                        }
                        $user->peer_id = 'PG3182736'.($maxNum + 1);
                    }
                }
            }
        });

        static::created(function (self $user): void {
            try {
                app(WearTheBadgeImageGenerator::class)->generateOrGetUrl($user);
            } catch (Throwable) {
                // Safeguard: User creation completes safely even if image generation encounters an error
            }
        });

        static::saved(function (self $user): void {
            if ($user->wasRecentlyCreated || $user->wasChanged(['city_id', 'city', 'business_city', 'state', 'business_state', 'district'])) {
                app(DistrictSyncService::class)->syncFromUser($user);
            }

            if ($user->wasRecentlyCreated || $user->wasChanged(['coins_balance', 'life_impacted_count', 'members_introduced_count'])) {
                app(MilestoneBadgeService::class)->calculateForUser($user);
            }
        });
    }

    public function syncCoinMilestoneAttributes(): bool
    {
        $resolved = CoinMilestoneResolver::resolve($this->coins_balance);

        $changes = [
            'coin_medal_rank' => $resolved['medal_rank'],
            'coin_milestone_title' => $resolved['title'],
            'coin_milestone_meaning' => $resolved['meaning'],
        ];

        $dirty = false;

        foreach ($changes as $attribute => $value) {
            if ($this->{$attribute} !== $value) {
                $this->{$attribute} = $value;
                $dirty = true;
            }
        }

        return $dirty;
    }

    public function syncContributionMilestoneAttributes(): bool
    {
        $resolved = ContributionMilestoneResolver::resolve($this->members_introduced_count);

        $changes = [
            'contribution_award_name' => $resolved['award_name'],
            'contribution_award_recognition' => $resolved['recognition'],
        ];

        $dirty = false;

        foreach ($changes as $attribute => $value) {
            if ($this->{$attribute} !== $value) {
                $this->{$attribute} = $value;
                $dirty = true;
            }
        }

        return $dirty;
    }

    public function syncMembershipExpiryAttributes(): bool
    {
        $targetExpiry = null;

        if ($this->isDirty('membership_ends_at')) {
            $targetExpiry = $this->membership_ends_at;
        } elseif ($this->isDirty('membership_expiry')) {
            $targetExpiry = $this->membership_expiry;
        } elseif (! $this->membershipDatesMatch()) {
            $targetExpiry = $this->membership_ends_at ?? $this->membership_expiry;
        } else {
            return false;
        }

        $this->membership_ends_at = $targetExpiry;
        $this->membership_expiry = $targetExpiry;

        return true;
    }

    /**
     * Resolve the welcome creative URL for this user.
     * If NULL in database, automatically generate the creative image, save it to storage and SQL, and return the URL.
     */
    public function resolveWelcomeCreativeUrl(bool $forceRegenerate = false): string
    {
        $existing = $this->getAttribute('welcome_creative_url') ?? $this->getAttribute('profile_card_image_url');
        if (! $forceRegenerate && filled($existing)) {
            return (string) $existing;
        }

        try {
            return app(WearTheBadgeImageGenerator::class)->generateOrGetUrl($this, $forceRegenerate);
        } catch (Throwable $e) {
            Log::warning("User {$this->id}: Could not automatically generate welcome creative URL on demand: {$e->getMessage()}");

            return (string) ($existing ?? '');
        }
    }

    public function membershipDatesMatch(): bool
    {
        return $this->normalizedMembershipDate($this->membership_ends_at) === $this->normalizedMembershipDate($this->membership_expiry);
    }

    private function normalizedMembershipDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s.uP');
        }

        return (string) $value;
    }

    public function links(): HasMany
    {
        return $this->hasMany(UserLink::class);
    }

    public function userLinks(): HasMany
    {
        return $this->hasMany(UserLink::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function cityRelation(): BelongsTo
    {
        return $this->belongsTo(City::class, 'city_id');
    }

    public function introducedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'introduced_by');
    }

    public function introducedPeers(): HasMany
    {
        return $this->hasMany(User::class, 'introduced_by');
    }

    public function referredByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function mainBusinessCategory(): BelongsTo
    {
        return $this->belongsTo(CircleCategory::class, 'main_business_category_id');
    }

    public function businessCategory(): BelongsTo
    {
        return $this->belongsTo(CircleCategory::class, 'business_category_id');
    }

    public function level4Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategoryLevel4::class, 'business_category_id');
    }

    public function foundedCircles(): HasMany
    {
        return $this->hasMany(Circle::class, 'circle_founder_user_id');
    }

    public function circleMembers(): HasMany
    {
        return $this->hasMany(CircleMember::class);
    }

    public function circleMemberships(): HasMany
    {
        return $this->hasMany(CircleMember::class, 'user_id');
    }

    public function joinedCircleCategories(): HasMany
    {
        return $this->hasMany(JoinedCircleCategory::class, 'user_id');
    }

    public function customCategoryRequests(): HasMany
    {
        return $this->hasMany(CustomCategoryRequest::class, 'user_id');
    }

    public function circleSubscriptions(): HasMany
    {
        return $this->hasMany(CircleSubscription::class, 'user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'user_id');
    }

    public function activeCircle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'active_circle_id');
    }

    public function activeCircleSubscription(): BelongsTo
    {
        return $this->belongsTo(CircleSubscription::class, 'active_circle_subscription_id');
    }

    public function circles(): BelongsToMany
    {
        return $this->belongsToMany(Circle::class, 'circle_members', 'user_id', 'circle_id')
            ->withPivot(['status', 'joined_at', 'deleted_at'])
            ->wherePivot('status', 'approved')
            ->wherePivotNull('deleted_at')
            ->orderByPivot('joined_at', 'desc');
    }

    public function adminDisplayName(): string
    {
        $rawDisplayName = trim((string) ($this->getRawOriginal('display_name') ?? $this->attributes['display_name'] ?? ''));
        if ($rawDisplayName !== '' && ! str_contains($rawDisplayName, '@')) {
            return $rawDisplayName;
        }

        $rawFullName = trim((string) ($this->getRawOriginal('full_name') ?? $this->attributes['full_name'] ?? ''));
        if ($rawFullName !== '' && ! str_contains($rawFullName, '@')) {
            return $rawFullName;
        }

        $firstNameLastName = trim(
            trim((string) ($this->first_name ?? '')).' '.trim((string) ($this->last_name ?? ''))
        );
        if ($firstNameLastName !== '' && ! str_contains($firstNameLastName, '@')) {
            return $firstNameLastName;
        }

        if ($rawDisplayName !== '') {
            return $rawDisplayName;
        }

        if ($rawFullName !== '') {
            return $rawFullName;
        }

        $email = trim((string) ($this->email ?? ''));

        return $email !== '' ? $email : '—';
    }

    public function getDisplayNameAttribute()
    {
        $firstName = trim($this->first_name ?? '');
        $lastName = trim($this->last_name ?? '');

        $fullName = trim($firstName.' '.$lastName);

        if ($fullName !== '') {
            return $fullName;
        }

        return $this->email;
    }

    public function adminCompanyLabel(): string
    {
        $company = trim((string) ($this->company_name ?? ''));

        if ($company !== '') {
            return $company;
        }

        $businessName = trim((string) ($this->business_name ?? ''));

        return $businessName !== '' ? $businessName : 'No Company';
    }

    public function adminCityLabel(): string
    {
        $city = trim((string) ($this->city ?? ''));

        return $city !== '' ? $city : 'No City';
    }

    public function adminCircleLabel(): string
    {
        if ($this->relationLoaded('circleMembers')) {
            $names = $this->circleMembers->map(fn ($cm) => trim((string) optional($cm->circle)->name))->filter()->unique();

            return $names->isNotEmpty() ? $names->implode(', ') : 'No Circle';
        }

        if ($this->relationLoaded('circles')) {
            $names = $this->circles->map(fn ($c) => trim((string) $c->name))->filter()->unique();

            return $names->isNotEmpty() ? $names->implode(', ') : 'No Circle';
        }

        try {
            $members = $this->circleMembers()
                ->where('status', 'approved')
                ->whereNull('deleted_at')
                ->with(['circle:id,name'])
                ->orderByDesc('joined_at')
                ->get();

            $names = $members->map(fn ($cm) => trim((string) optional($cm->circle)->name))->filter()->unique();

            return $names->isNotEmpty() ? $names->implode(', ') : 'No Circle';
        } catch (Throwable $e) {
            return 'No Circle';
        }
    }

    public function adminFounderOptionLabel(): string
    {
        return implode(PHP_EOL, [
            $this->adminDisplayName(),
            $this->adminCompanyLabel(),
            $this->adminCityLabel(),
            $this->adminCircleLabel(),
        ]);
    }

    public function adminName(): string
    {
        $displayName = trim((string) ($this->display_name ?? ''));

        if ($displayName !== '') {
            return $displayName;
        }

        $fullName = trim(
            trim((string) ($this->first_name ?? '')).' '.trim((string) ($this->last_name ?? ''))
        );

        if ($fullName !== '') {
            return $fullName;
        }

        return $this->adminDisplayName();
    }

    public function adminCompany(): string
    {
        return $this->adminCompanyLabel();
    }

    public function adminCity(): string
    {
        return $this->adminCityLabel();
    }

    public function adminCircleName(): string
    {
        return $this->adminCircleLabel();
    }

    public function adminDisplayParts(): array
    {
        return [
            $this->adminName(),
            $this->adminCompany(),
            $this->adminCity(),
            $this->adminCircleName(),
        ];
    }

    public function adminDisplayLabel(): string
    {
        return implode(PHP_EOL, $this->adminDisplayParts());
    }

    public function adminDisplayInlineLabel(): string
    {
        return implode(' — ', $this->adminDisplayParts());
    }

    public function adminNameCompanyCityLabel(): string
    {
        return implode(', ', [
            $this->adminName(),
            $this->adminCompanyLabel(),
            $this->adminCityLabel(),
        ]);
    }

    public function adminFounderDropdownLabel(): string
    {
        return $this->adminNameCompanyCityLabel();
    }

    public function isMembershipExpired(): bool
    {
        $membershipEndsAt = $this->membership_ends_at ?? $this->membership_expiry;

        return $membershipEndsAt !== null && $membershipEndsAt->isPast();
    }

    public function hasExpiredFreeTrial(): bool
    {
        return (string) $this->membership_status === self::STATUS_FREE_TRIAL
            && $this->membership_ends_at !== null
            && $this->membership_ends_at->lessThanOrEqualTo(now());
    }

    public function expireFreeTrialIfNeeded(): bool
    {
        if (! $this->hasExpiredFreeTrial()) {
            return false;
        }

        $this->forceFill([
            'membership_status' => self::STATUS_FREE,
        ])->save();

        return true;
    }

    public function getEffectiveMembershipStatusAttribute(): ?string
    {
        if ($this->hasExpiredFreeTrial()) {
            return self::STATUS_FREE;
        }

        if ($this->isMembershipExpired()) {
            return self::freePeerMembershipStatus();
        }

        return $this->membership_status;
    }

    public static function freePeerMembershipStatus(): string
    {
        $configuredStatuses = (array) config('membership.statuses', []);

        foreach (self::FREE_PEER_STATUS_CANDIDATES as $candidate) {
            if (in_array($candidate, $configuredStatuses, true)) {
                return $candidate;
            }
        }

        return self::STATUS_FREE;
    }

    public function geoLocation(): HasOne
    {
        return $this->hasOne(UserGeoLocation::class, 'user_id');
    }

    public function requestedConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'requester_id');
    }

    public function receivedConnections(): HasMany
    {
        return $this->hasMany(Connection::class, 'addressee_id');
    }

    public function followers(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'following_id');
    }

    public function following(): HasMany
    {
        return $this->hasMany(UserFollow::class, 'follower_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function savedPosts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_saves', 'user_id', 'post_id')->withTimestamps();
    }

    public function postComments(): HasMany
    {
        return $this->hasMany(PostComment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by_user_id');
    }

    public function eventRsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function relatedActivities(): HasMany
    {
        return $this->hasMany(Activity::class, 'related_user_id');
    }

    public function verifiedActivities(): HasMany
    {
        return $this->hasMany(Activity::class, 'verified_by_admin_id');
    }

    public function coinLedgers(): HasMany
    {
        return $this->hasMany(CoinLedger::class);
    }

    public function coinsLedger(): HasMany
    {
        return $this->hasMany(CoinsLedger::class, 'user_id');
    }

    public function walletTransactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    public function supportRequests(): HasMany
    {
        return $this->hasMany(SupportRequest::class);
    }

    public function chatsInitiated(): HasMany
    {
        return $this->hasMany(Chat::class, 'user1_id');
    }

    public function chatsReceived(): HasMany
    {
        return $this->hasMany(Chat::class, 'user2_id');
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function circleChatMessagesSent(): HasMany
    {
        return $this->hasMany(CircleChatMessage::class, 'sender_id');
    }

    public function circleChatMessageReads(): HasMany
    {
        return $this->hasMany(CircleChatMessageRead::class, 'user_id');
    }

    public function pushTokens(): HasMany
    {
        return $this->hasMany(UserPushToken::class, UserPushToken::getUserIdColumn());
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'admin_user_roles', 'user_id', 'role_id');
    }

    public function uploadedFiles(): HasMany
    {
        return $this->hasMany(FileModel::class, 'uploader_user_id');
    }

    public function adminAuditLogs(): HasMany
    {
        return $this->hasMany(AdminAuditLog::class, 'admin_user_id');
    }

    public function referralLinks(): HasMany
    {
        return $this->hasMany(ReferralLink::class, 'referrer_user_id');
    }

    public function referralDataAsReferrer(): HasMany
    {
        return $this->hasMany(ReferralData::class, 'referrer_user_id');
    }

    public function referralDataAsReferred(): HasMany
    {
        return $this->hasMany(ReferralData::class, 'referred_user_id');
    }

    public function visitorLeads(): HasMany
    {
        return $this->hasMany(VisitorLead::class, 'converted_user_id');
    }

    public function dataExports(): HasMany
    {
        return $this->hasMany(DataExport::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    public function profilePhotoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'profile_photo_file_id');
    }

    public function coverPhotoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_photo_file_id');
    }

    public function profileVideoFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'profile_video_id');
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        $profilePhotoId = $this->attributes['profile_photo_file_id']
            ?? $this->attributes['profile_photo_id']
            ?? null;

        if ($profilePhotoId) {
            return url('/api/v1/files/'.$profilePhotoId);
        }

        $storedProfilePhotoUrl = $this->attributes['profile_photo_url'] ?? null;

        if (blank($storedProfilePhotoUrl)) {
            return null;
        }

        if (filter_var($storedProfilePhotoUrl, FILTER_VALIDATE_URL)) {
            return $storedProfilePhotoUrl;
        }

        return Storage::disk('public')->url($storedProfilePhotoUrl);
    }

    public function getProfileVideoUrlAttribute(): ?string
    {
        if (! $this->profile_video_id) {
            return null;
        }

        return url('/api/v1/files/'.$this->profile_video_id);
    }

    public function isFreeMember(): bool
    {
        return in_array((string) $this->effective_membership_status, [self::STATUS_FREE, self::STATUS_FREE_TRIAL], true);
    }

    public function isPaidMember(): bool
    {
        return ! $this->isFreeMember();
    }

    public function publicProfileArray(): array
    {
        $name = (string) ($this->getAttribute('name')
            ?? $this->display_name
            ?? trim(($this->first_name ?? '').' '.($this->last_name ?? '')));

        $companyName = (string) ($this->getAttribute('company_name') ?? '');
        $city = (string) ($this->getAttribute('city') ?? '');
        $industry = (string) ($this->getAttribute('industry') ?? '');

        if ((blank($companyName) || blank($city) || blank($industry)) && method_exists($this, 'profile')) {
            try {
                $profile = $this->relationLoaded('profile')
                    ? $this->getRelation('profile')
                    : $this->profile()->first();

                $companyName = blank($companyName) ? (string) ($profile->company_name ?? '') : $companyName;
                $city = blank($city) ? (string) ($profile->city ?? '') : $city;
                $industry = blank($industry) ? (string) ($profile->industry ?? '') : $industry;
            } catch (Throwable $e) {
                // Relation is optional in this project scope.
            }
        }

        return [
            'id' => (string) $this->id,
            'name' => $name,
            'company_name' => $companyName,
            'email' => (string) ($this->email ?? ''),
            'city' => $city,
            'industry' => $industry,
        ];
    }

    /**
     * Calculate user profile completion percentage (0 to 100)
     * matching the 5-section Flutter specification.
     */
    public function calculateProfileCompletionPercentage(): int
    {
        $isCompleted = static function (mixed $value): bool {
            if ($value === null) {
                return false;
            }

            if (is_string($value)) {
                return trim($value) !== '';
            }

            if (is_array($value)) {
                return count($value) > 0;
            }

            if ($value instanceof \Countable) {
                return count($value) > 0;
            }

            return true;
        };

        // Section 1: Personal Details (14 fields)
        $personal = [
            $this->getAttribute('first_name') ?: $this->getAttribute('display_name'),
            $this->getAttribute('last_name'),
            $this->getAttribute('email'),
            $this->getAttribute('phone') ?: $this->getAttribute('secondary_mobile'),
            $this->getAttribute('gender'),
            $this->getAttribute('dob'),
            $this->getAttribute('anniversary_date'),
            $this->getAttribute('city') ?: $this->getAttribute('city_id') ?: $this->getAttribute('city_of_residence'),
            $this->getAttribute('state'),
            $this->getAttribute('country'),
            $this->getAttribute('profile_photo_file_id') ?: $this->getAttribute('profile_photo_id') ?: $this->getAttribute('profile_photo_url'),
            $this->getAttribute('cover_photo_file_id') ?: $this->getAttribute('cover_photo_id'),
            $this->getAttribute('profile_video_id') ?: $this->getAttribute('intro_video_id'),
            $this->getAttribute('short_bio') ?: $this->getAttribute('long_bio_html') ?: $this->getAttribute('experience_summary'),
        ];

        // Section 2: Business Details (19 fields)
        $business = [
            $this->getAttribute('company_name'),
            $this->getAttribute('designation'),
            $this->getAttribute('business_category_id') ?: $this->getAttribute('main_business_category_id'),
            $this->getAttribute('business_sub_category'),
            $this->getAttribute('company_type'),
            $this->getAttribute('business_type'),
            $this->getAttribute('year_of_establishment'),
            $this->getAttribute('annual_revenue_range') ?: $this->getAttribute('turnover_range'),
            $this->getAttribute('number_of_employees'),
            $this->getAttribute('gst_number'),
            $this->getAttribute('business_website') ?: $this->getAttribute('website'),
            $this->getAttribute('superpower'),
            $this->getAttribute('i_can_help_with'),
            $this->getAttribute('i_am_looking_for'),
            $this->getAttribute('business_keywords'),
            $this->getAttribute('products_services_offered'),
            $this->getAttribute('business_address'),
            $this->getAttribute('business_pincode'),
            $this->getAttribute('business_logo_id'),
        ];

        // Section 3: Interests & Skills (2 fields)
        $interestsSkills = [
            $this->getAttribute('skills'),
            $this->getAttribute('interests') ?: $this->getAttribute('hobbies_interests') ?: $this->getAttribute('industries_of_interest') ?: $this->getAttribute('collaboration_goals'),
        ];

        // Section 4: Social Links (6 fields)
        $socialLinks = [
            $this->getAttribute('linkedin_profile') ?: data_get($this->getAttribute('social_links'), 'linkedin'),
            $this->getAttribute('instagram_handle') ?: data_get($this->getAttribute('social_links'), 'instagram'),
            $this->getAttribute('twitter_handle') ?: data_get($this->getAttribute('social_links'), 'twitter'),
            $this->getAttribute('facebook_profile') ?: data_get($this->getAttribute('social_links'), 'facebook'),
            $this->getAttribute('youtube_channel') ?: data_get($this->getAttribute('social_links'), 'youtube'),
            $this->getAttribute('other_website') ?: data_get($this->getAttribute('social_links'), 'website'),
        ];

        // Section 5: Contact Visibility / Privacy (1 field)
        $privacy = [
            $this->getAttribute('contact_visibility'),
        ];

        $personalPct = (collect($personal)->filter($isCompleted)->count() / 14) * 100;
        $businessPct = (collect($business)->filter($isCompleted)->count() / 19) * 100;
        $interestsPct = (collect($interestsSkills)->filter($isCompleted)->count() / 2) * 100;
        $socialPct = (collect($socialLinks)->filter($isCompleted)->count() / 6) * 100;
        $privacyPct = (collect($privacy)->filter($isCompleted)->count() / 1) * 100;

        $totalPercentage = (int) round(($personalPct + $businessPct + $interestsPct + $socialPct + $privacyPct) / 5);

        return min(100, max(0, $totalPercentage));
    }
}
