<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class CircleMember extends Model
{
    protected $table = 'circle_members';

    use HasFactory;
    use SoftDeletes;

    public const LEADERSHIP_ROLE_OPTIONS = [
        'circle_founder',
        'circle_director',
        'industry_director',
        'ded',
        'eed',
        'chair',
        'vice_chair',
        'secretary',
        'committee_leader',
    ];

    public const REGIONAL_ROLES = [
        'ded',
        'industry_director',
        'id',
        'circle_director',
        'director',
        'cd',
        'circle_founder',
        'founder',
        'cf',
        'eed',
        'regional_leader',
        'regional_director',
        'regional_head',
        'global_admin',
        'country_director',
        'superadmin',
        'super_admin',
    ];

    public const CIRCLE_LEADERSHIP_ROLES = [
        'chair',
        'vice_chair',
        'secretary',
        'chair_business_growth_committee',
        'business_growth_committee_chair',
        'business_growth_chair',
        'chair_membership_growth_committee',
        'membership_growth_committee_chair',
        'membership_growth_chair',
        'chair_events_impacts_committee',
        'events_impacts_committee_chair',
        'events_impacts_chair',
        'power_house_chair_1',
        'power_house_chair_2',
        'power_house_chair_3',
        'powerhouse_1',
        'powerhouse_2',
        'powerhouse_3',
        'power_house_1',
        'power_house_2',
        'power_house_3',
        'committee_leader',
        'chair_leader',
    ];

    public const ROLE_OPTIONS = [
        'member',
        'circle_founder',
        'circle_director',
        'industry_director',
        'ded',
        'eed',
        'chair',
        'vice_chair',
        'secretary',
        'committee_leader',
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'circle_id',
        'user_id',
        'level_1_category_id',
        'level_2_category_id',
        'level_3_category_id',
        'level_4_category_id',
        'role',
        'role_id',
        'status',
        'substitute_count',
        'joined_at',
        'left_at',
        'joined_via',
        'payment_id',
        'paid_at',
        'joined_via_payment',
        'billing_term',
        'paid_starts_at',
        'paid_ends_at',
        'expires_at',
        'zoho_subscription_id',
        'zoho_addon_code',
        'addon_name',
        'circle_subscription_id',
        'subscription_status',
        'payment_status',
        'meta',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
        'deleted_at' => 'datetime',
        'paid_at' => 'datetime',
        'paid_starts_at' => 'datetime',
        'paid_ends_at' => 'datetime',
        'expires_at' => 'datetime',
        'joined_via_payment' => 'boolean',
        'level_1_category_id' => 'integer',
        'level_2_category_id' => 'integer',
        'level_3_category_id' => 'integer',
        'level_4_category_id' => 'integer',
        'meta' => 'array',
    ];

    public static function isRegionalRole(?string $role): bool
    {
        if ($role === null || trim($role) === '') {
            return false;
        }

        $normalized = Str::of($role)->lower()->trim()->replace(['-', ' '], '_')->toString();

        return in_array($normalized, self::REGIONAL_ROLES, true);
    }

    public static function isCircleLeaderRole(?string $role): bool
    {
        if ($role === null || trim($role) === '') {
            return false;
        }

        $normalized = Str::of($role)->lower()->trim()->replace(['-', ' '], '_')->toString();

        return in_array($normalized, self::CIRCLE_LEADERSHIP_ROLES, true);
    }

    public static function roleOptions(): array
    {
        return self::ROLE_OPTIONS;
    }

    public static function activeStatuses(): array
    {
        return ['approved'];
    }

    protected static function booted(): void
    {
        static::creating(function (CircleMember $member): void {
            if (empty($member->id)) {
                $member->id = (string) Str::uuid();
            }
        });

        static::saving(function (CircleMember $member): void {
            if (! $member->role && ! $member->role_id) {
                return;
            }

            if (! Schema::hasTable('roles')) {
                return;
            }

            if ($member->role_id && (! $member->role || $member->isDirty('role_id'))) {
                $roleModel = Role::find($member->role_id);
                if ($roleModel && $roleModel->key) {
                    $member->role = $roleModel->key;
                }
            }

            if ($member->role) {
                if (Str::isUuid($member->role) && $roleById = Role::find($member->role)) {
                    $member->role_id = $roleById->id;
                    $member->role = $roleById->key;
                } elseif (! $member->role_id || $member->isDirty('role')) {
                    try {
                        $member->role_id = Role::mustIdByKey($member->role);
                    } catch (RuntimeException $exception) {
                        Log::error('Circle member role key missing in roles table.', [
                            'circle_member_id' => $member->id,
                            'circle_id' => $member->circle_id,
                            'user_id' => $member->user_id,
                            'role' => $member->role,
                        ]);

                        $member->role_id = Role::idByKey($member->role);
                    }
                }
            }
        });

        static::saved(function (CircleMember $member): void {
            if (! empty($member->circle_id)) {
                Circle::syncLeadershipFromMembers($member->circle_id);
            }

            $admin = auth('admin')->user();
            if ($admin) {
                Cache::forget('admin-access:allowed-users:'.$admin->id);
                Cache::forget('admin-access:allowed-circles:'.$admin->id);
                Cache::forget('admin-access:primary-role:'.$admin->id);
                Cache::forget('admin-access:assigned-circles:'.$admin->id);
                Cache::forget('admin-access:user:'.$admin->id);
                Cache::forget('admin-access:roles:'.$admin->id);
            }
        });

        static::deleted(function (CircleMember $member): void {
            if (! empty($member->circle_id)) {
                Circle::syncLeadershipFromMembers($member->circle_id);
            }

            $admin = auth('admin')->user();
            if ($admin) {
                Cache::forget('admin-access:allowed-users:'.$admin->id);
                Cache::forget('admin-access:allowed-circles:'.$admin->id);
                Cache::forget('admin-access:primary-role:'.$admin->id);
                Cache::forget('admin-access:assigned-circles:'.$admin->id);
                Cache::forget('admin-access:user:'.$admin->id);
                Cache::forget('admin-access:roles:'.$admin->id);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }

    public function roleRef(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function roleModel(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function categorySelection(): HasOne
    {
        return $this->hasOne(CircleMemberCategorySelection::class, 'circle_member_id');
    }

    public function level1Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategory::class, 'level_1_category_id');
    }

    public function level2Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategoryLevel2::class, 'level_2_category_id');
    }

    public function level3Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategoryLevel3::class, 'level_3_category_id');
    }

    public function level4Category(): BelongsTo
    {
        return $this->belongsTo(CircleCategoryLevel4::class, 'level_4_category_id');
    }

    public function joinedCircleCategory(): HasOne
    {
        return $this->hasOne(JoinedCircleCategory::class, 'circle_member_id');
    }
}
