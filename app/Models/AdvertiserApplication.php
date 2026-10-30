<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdvertiserApplication extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_EMAIL_VERIFICATION = 'pending_email_verification';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_CHANGES_REQUESTED = 'changes_requested';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    public const ALLOWED_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING_EMAIL_VERIFICATION,
        self::STATUS_SUBMITTED,
        self::STATUS_UNDER_REVIEW,
        self::STATUS_CHANGES_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_ACTIVE,
        self::STATUS_REJECTED,
        self::STATUS_WITHDRAWN,
    ];

    protected $table = 'advertiser_applications';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'application_number',
        'user_id',
        'package_id',
        'brand_partner_id',
        'source',
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'applicant_email_verified',
        'company_name',
        'company_legal_name',
        'company_type',
        'industry',
        'company_website',
        'company_email',
        'company_phone',
        'whatsapp_number',
        'address_line',
        'city',
        'state',
        'country',
        'postal_code',
        'contact_person_name',
        'contact_person_designation',
        'brand_description',
        'target_audience',
        'target_locations',
        'advertising_objective',
        'requested_start_date',
        'additional_requirements',
        'requested_duration_months',
        'requested_package_name',
        'requested_package_price',
        'requested_currency',
        'status',
        'admin_notes',
        'applicant_response',
        'rejection_reason',
        'reviewed_by',
        'reviewed_at',
        'approved_at',
        'rejected_at',
        'idempotency_key',
        'consent_to_terms',
        'consent_to_privacy',
        'marketing_consent',
        'consented_at',
        'metadata',
        'submitted_at',
    ];

    protected $casts = [
        'applicant_email_verified' => 'boolean',
        'target_locations' => 'array',
        'metadata' => 'array',
        'consent_to_terms' => 'boolean',
        'consent_to_privacy' => 'boolean',
        'marketing_consent' => 'boolean',
        'consented_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'requested_start_date' => 'date',
        'requested_duration_months' => 'integer',
        'requested_package_price' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(AdvertiserPackage::class, 'package_id');
    }

    public function brandPartner(): BelongsTo
    {
        return $this->belongsTo(BrandPartner::class, 'brand_partner_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AdvertiserApplicationAsset::class, 'application_id')->orderBy('display_order');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(AdvertiserApplicationStatusHistory::class, 'application_id')->orderByDesc('created_at');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(AdvertiserSubscription::class, 'application_id')->orderByDesc('created_at');
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(AdvertiserSubscription::class, 'application_id')->latestOfMany('created_at');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(AdvertiserCampaign::class, 'application_id')->orderByDesc('created_at');
    }

    public function emailVerifications(): HasMany
    {
        return $this->hasMany(AdvertiserEmailVerification::class, 'application_id')->orderByDesc('created_at');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Check valid transitions based on current state.
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $allowedTransitions = [
            self::STATUS_DRAFT => [
                self::STATUS_PENDING_EMAIL_VERIFICATION,
                self::STATUS_SUBMITTED,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_PENDING_EMAIL_VERIFICATION => [
                self::STATUS_SUBMITTED,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_SUBMITTED => [
                self::STATUS_UNDER_REVIEW,
                self::STATUS_CHANGES_REQUESTED,
                self::STATUS_APPROVED,
                self::STATUS_REJECTED,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_UNDER_REVIEW => [
                self::STATUS_CHANGES_REQUESTED,
                self::STATUS_APPROVED,
                self::STATUS_REJECTED,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_CHANGES_REQUESTED => [
                self::STATUS_SUBMITTED,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_APPROVED => [
                self::STATUS_ACTIVE,
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_ACTIVE => [
                self::STATUS_WITHDRAWN,
            ],
            self::STATUS_REJECTED => [],
            self::STATUS_WITHDRAWN => [],
        ];

        return in_array($newStatus, $allowedTransitions[$this->status] ?? [], true);
    }
}
