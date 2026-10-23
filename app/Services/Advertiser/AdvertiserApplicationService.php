<?php

declare(strict_types=1);

namespace App\Services\Advertiser;

use App\Models\AdvertiserApplication;
use App\Models\AdvertiserApplicationAsset;
use App\Models\AdvertiserApplicationStatusHistory;
use App\Models\AdvertiserEmailVerification;
use App\Models\AdvertiserPackage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdvertiserApplicationService
{
    public function __construct(
        protected AdvertiserNotificationService $notificationService
    ) {}

    /**
     * Generate sequential application number. e.g. ADV-2026-00001
     */
    public function generateApplicationNumber(): string
    {
        $year = date('Y');
        $prefix = "ADV-{$year}-";

        $count = AdvertiserApplication::where('application_number', 'LIKE', "{$prefix}%")->count() + 1;
        $candidate = $prefix . str_pad((string) $count, 5, '0', STR_PAD_LEFT);

        while (AdvertiserApplication::where('application_number', $candidate)->exists()) {
            $count++;
            $candidate = $prefix . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }

    /**
     * Submit an advertiser application.
     */
    public function submitApplication(array $data, ?User $authenticatedUser = null): AdvertiserApplication
    {
        // 1. Check idempotency if provided
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if (!empty($idempotencyKey)) {
            $existing = AdvertiserApplication::where('idempotency_key', $idempotencyKey)->first();
            if ($existing) {
                return $existing->loadMissing(['package', 'assets', 'statusHistory']);
            }
        }

        // 2. Validate package existence and active state
        $packageId = $data['package_id'] ?? null;
        $package = AdvertiserPackage::find($packageId);
        if (!$package || !$package->is_active) {
            throw ValidationException::withMessages([
                'package_id' => ['The selected advertising package is currently not available.'],
            ]);
        }

        // 3. Normalize email and applicant details
        $applicant = $data['applicant'] ?? [];
        $company = $data['company'] ?? [];
        $campaign = $data['campaign'] ?? [];
        $consents = $data['consents'] ?? [];
        $source = in_array($data['source'] ?? '', ['app', 'website', 'admin'], true) ? $data['source'] : 'website';

        $applicantEmail = strtolower(trim((string) ($applicant['email'] ?? '')));
        $applicantPhone = trim((string) ($applicant['mobile_number'] ?? ($applicant['phone'] ?? '')));
        $applicantName = trim((string) ($applicant['full_name'] ?? ($applicant['name'] ?? '')));

        if ($authenticatedUser) {
            $userId = $authenticatedUser->id;
            $emailVerified = true;
            $initialStatus = AdvertiserApplication::STATUS_SUBMITTED;
        } else {
            $userId = null;
            $emailVerified = false;
            $initialStatus = AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION;
        }

        return DB::transaction(function () use (
            $package,
            $applicant,
            $company,
            $campaign,
            $consents,
            $data,
            $source,
            $applicantName,
            $applicantEmail,
            $applicantPhone,
            $userId,
            $emailVerified,
            $initialStatus,
            $idempotencyKey
        ): AdvertiserApplication {
            $applicationNumber = $this->generateApplicationNumber();

            $application = AdvertiserApplication::create([
                'application_number' => $applicationNumber,
                'user_id' => $userId,
                'package_id' => $package->id,
                'source' => $source,
                'applicant_name' => $applicantName,
                'applicant_email' => $applicantEmail,
                'applicant_phone' => $applicantPhone,
                'applicant_email_verified' => $emailVerified,
                'company_name' => trim((string) ($company['company_name'] ?? '')),
                'company_legal_name' => $company['legal_business_name'] ?? ($company['company_legal_name'] ?? null),
                'company_type' => $company['business_type'] ?? ($company['company_type'] ?? null),
                'industry' => $company['industry'] ?? ($company['category_id'] ?? 'General'),
                'company_website' => $company['website'] ?? null,
                'company_email' => !empty($company['official_email']) ? strtolower(trim($company['official_email'])) : null,
                'company_phone' => $company['phone'] ?? null,
                'whatsapp_number' => $company['whatsapp'] ?? ($company['whatsapp_number'] ?? null),
                'address_line' => $company['address_line_1'] ?? ($company['address_line'] ?? null),
                'city' => $company['city'] ?? null,
                'state' => $company['state'] ?? null,
                'country' => $company['country'] ?? 'India',
                'postal_code' => $company['postal_code'] ?? null,
                'contact_person_name' => $company['contact_person_name'] ?? $applicantName,
                'contact_person_designation' => $company['contact_person_designation'] ?? null,
                'brand_description' => $company['description'] ?? ($company['brand_description'] ?? 'Brand Partner'),
                'target_audience' => $campaign['target_audience'] ?? null,
                'target_locations' => $campaign['target_locations'] ?? [],
                'advertising_objective' => $campaign['advertising_objective'] ?? null,
                'requested_start_date' => $campaign['preferred_start_date'] ?? null,
                'additional_requirements' => $campaign['additional_requirements'] ?? null,
                'requested_duration_months' => $package->duration_months,
                'requested_package_name' => $package->name,
                'requested_package_price' => $package->price,
                'requested_currency' => $package->currency ?? 'INR',
                'status' => $initialStatus,
                'idempotency_key' => $idempotencyKey,
                'consent_to_terms' => (bool) ($consents['terms_accepted'] ?? false),
                'consent_to_privacy' => (bool) ($consents['privacy_notice_accepted'] ?? false),
                'marketing_consent' => (bool) ($consents['marketing_consent'] ?? false),
                'consented_at' => now(),
                'submitted_at' => ($initialStatus === AdvertiserApplication::STATUS_SUBMITTED) ? now() : null,
            ]);

            // Save initial assets if provided
            if (!empty($data['assets']) && is_array($data['assets'])) {
                foreach ($data['assets'] as $index => $assetData) {
                    if (empty($assetData['storage_key'])) {
                        continue;
                    }

                    AdvertiserApplicationAsset::create([
                        'application_id' => $application->id,
                        'asset_type' => $assetData['asset_type'] ?? AdvertiserApplicationAsset::TYPE_CAMPAIGN_CREATIVE,
                        'original_filename' => $assetData['original_file_name'] ?? ($assetData['file_name'] ?? 'asset_' . $index),
                        'storage_disk' => $assetData['storage_disk'] ?? 'public',
                        'storage_key' => $assetData['storage_key'],
                        'mime_type' => $assetData['mime_type'] ?? 'image/jpeg',
                        'file_size_bytes' => (int) ($assetData['file_size'] ?? ($assetData['file_size_bytes'] ?? 1024)),
                        'display_order' => $index,
                        'review_status' => AdvertiserApplicationAsset::STATUS_PENDING,
                    ]);
                }
            }

            // Create initial status history
            AdvertiserApplicationStatusHistory::create([
                'application_id' => $application->id,
                'old_status' => null,
                'new_status' => $initialStatus,
                'changed_by' => $userId,
                'actor_type' => $userId ? 'applicant' : 'system',
                'notes' => 'Application submitted through ' . $source,
                'metadata' => [
                    'package_id' => $package->id,
                    'package_price' => $package->price,
                ],
                'created_at' => now(),
            ]);

            // If guest user, trigger email verification challenge
            if ($initialStatus === AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION) {
                $this->createEmailVerificationChallenge($application);
            } else {
                $this->notificationService->sendApplicationSubmitted($application);
            }

            return $application->fresh(['package', 'assets', 'statusHistory']);
        });
    }

    /**
     * Create and dispatch email verification OTP challenge.
     */
    public function createEmailVerificationChallenge(AdvertiserApplication $application): AdvertiserEmailVerification
    {
        // Rate-limit resends: check if a challenge was created in the last 45 seconds (bypass in local/testing)
        if (!app()->environment('local', 'testing')) {
            $recent = AdvertiserEmailVerification::where('application_id', $application->id)
                ->where('created_at', '>=', now()->subSeconds(45))
                ->first();

            if ($recent) {
                throw ValidationException::withMessages([
                    'email' => ['Please wait at least 45 seconds before requesting another verification code.'],
                ]);
            }
        }

        // Invalidate prior unconsumed challenges
        AdvertiserEmailVerification::where('application_id', $application->id)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        // In local/testing/dev/staging environments, support standard test OTP 123456
        $plainOtp = (!app()->environment('production') || app()->environment('local', 'testing', 'dev', 'staging')) ? '123456' : (string) random_int(100000, 999999);
        $otpHash = hash('sha256', $plainOtp);

        Log::info("Advertiser verification OTP generated: {$plainOtp} for application: {$application->application_number} ({$application->applicant_email})");

        $verification = AdvertiserEmailVerification::create([
            'application_id' => $application->id,
            'email' => strtolower(trim($application->applicant_email)),
            'otp_hash' => $otpHash,
            'expires_at' => now()->addMinutes(30),
            'attempts' => 0,
            'max_attempts' => 5,
            'created_at' => now(),
        ]);

        $this->notificationService->sendEmailVerification($application, $plainOtp);

        return $verification;
    }

    /**
     * Confirm applicant email verification OTP.
     */
    public function confirmEmailVerification(AdvertiserApplication $application, string $code): bool
    {
        $code = trim($code);

        // If email is already verified, return true immediately
        if ($application->applicant_email_verified) {
            return true;
        }

        $isTestOtp = ($code === '123456') && (!app()->environment('production') || app()->environment('local', 'testing', 'dev', 'staging'));
        $hashedInput = hash('sha256', $code);

        $verification = AdvertiserEmailVerification::where('application_id', $application->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('created_at')
            ->first();

        // In local/testing, if verification expired/consumed but 123456 was used, fallback to latest verification or allow
        if (!$verification && $isTestOtp) {
            $verification = AdvertiserEmailVerification::where('application_id', $application->id)
                ->latest('created_at')
                ->first();
        }

        if (!$verification && !$isTestOtp) {
            throw ValidationException::withMessages([
                'verification_code' => ['The verification code has expired or is invalid. Please request a new code.'],
            ]);
        }

        if ($verification && $verification->attempts >= $verification->max_attempts && !$isTestOtp) {
            $verification->update(['consumed_at' => now()]);
            throw ValidationException::withMessages([
                'verification_code' => ['Maximum verification attempts exceeded. Please request a new code.'],
            ]);
        }

        if ($verification) {
            $verification->increment('attempts');
        }

        if ($verification && !hash_equals($verification->otp_hash, $hashedInput) && !$isTestOtp) {
            $remaining = max(0, $verification->max_attempts - $verification->attempts);
            throw ValidationException::withMessages([
                'verification_code' => ["Invalid verification code. {$remaining} attempts remaining."],
            ]);
        }

        // Verification successful
        DB::transaction(function () use ($application, $verification) {
            if ($verification) {
                $verification->update([
                    'verified_at' => now(),
                    'consumed_at' => now(),
                ]);
            }

            $application->applicant_email_verified = true;
            if ($application->status === AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION) {
                $application->status = AdvertiserApplication::STATUS_SUBMITTED;
                $application->submitted_at = now();

                AdvertiserApplicationStatusHistory::create([
                    'application_id' => $application->id,
                    'old_status' => AdvertiserApplication::STATUS_PENDING_EMAIL_VERIFICATION,
                    'new_status' => AdvertiserApplication::STATUS_SUBMITTED,
                    'actor_type' => 'applicant',
                    'notes' => 'Email verified successfully.',
                    'created_at' => now(),
                ]);

                $this->notificationService->sendApplicationSubmitted($application);
            }
            $application->save();
        });

        return true;
    }

    /**
     * Transition application to a new status safely with history recording.
     */
    public function transitionStatus(
        AdvertiserApplication $application,
        string $newStatus,
        string $actorType = 'admin',
        ?string $actorId = null,
        ?string $notes = null,
        array $metadata = []
    ): AdvertiserApplication {
        if (!$application->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition application from '{$application->status}' to '{$newStatus}'."],
            ]);
        }

        return DB::transaction(function () use ($application, $newStatus, $actorType, $actorId, $notes, $metadata) {
            $oldStatus = $application->status;
            $application->status = $newStatus;

            if ($actorType === 'admin') {
                $application->reviewed_by = $actorId;
                $application->reviewed_at = now();
            }

            if ($newStatus === AdvertiserApplication::STATUS_APPROVED) {
                $application->approved_at = now();
            } elseif ($newStatus === AdvertiserApplication::STATUS_REJECTED) {
                $application->rejected_at = now();
                if (!empty($metadata['rejection_reason'])) {
                    $application->rejection_reason = $metadata['rejection_reason'];
                }
            }

            if ($notes !== null) {
                if ($actorType === 'admin') {
                    $application->admin_notes = $notes;
                } else {
                    $application->applicant_response = $notes;
                }
            }

            $application->save();

            AdvertiserApplicationStatusHistory::create([
                'application_id' => $application->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'changed_by' => $actorId,
                'actor_type' => $actorType,
                'notes' => $notes,
                'metadata' => $metadata,
                'created_at' => now(),
            ]);

            // Dispatch relevant notifications
            if ($newStatus === AdvertiserApplication::STATUS_CHANGES_REQUESTED) {
                $this->notificationService->sendCorrectionsRequested($application, $notes ?? 'Please review requested changes.');
            } elseif ($newStatus === AdvertiserApplication::STATUS_APPROVED) {
                $this->notificationService->sendApplicationApproved($application, $metadata['applicant_message'] ?? null);
            } elseif ($newStatus === AdvertiserApplication::STATUS_REJECTED) {
                $this->notificationService->sendApplicationRejected(
                    $application,
                    $metadata['rejection_reason'] ?? 'Requirements not met.',
                    $metadata['applicant_message'] ?? null
                );
            }

            return $application->fresh(['package', 'assets', 'statusHistory']);
        });
    }

    /**
     * Update application details or submit requested corrections.
     */
    public function updateApplicationCorrections(
        AdvertiserApplication $application,
        array $data,
        bool $resubmit = false
    ): AdvertiserApplication {
        // Only allow edits if in draft or changes_requested or submitted state
        if (!in_array($application->status, [AdvertiserApplication::STATUS_DRAFT, AdvertiserApplication::STATUS_CHANGES_REQUESTED, AdvertiserApplication::STATUS_SUBMITTED], true)) {
            throw ValidationException::withMessages([
                'application' => ['Application cannot be edited in its current status.'],
            ]);
        }

        return DB::transaction(function () use ($application, $data, $resubmit) {
            $company = $data['company'] ?? [];
            $campaign = $data['campaign'] ?? [];

            if (!empty($company['company_name'])) {
                $application->company_name = trim($company['company_name']);
            }
            if (isset($company['website'])) {
                $application->company_website = $company['website'];
            }
            if (isset($company['description'])) {
                $application->brand_description = $company['description'];
            }
            if (isset($company['phone'])) {
                $application->company_phone = $company['phone'];
            }
            if (isset($company['whatsapp'])) {
                $application->whatsapp_number = $company['whatsapp'];
            }
            if (isset($campaign['advertising_objective'])) {
                $application->advertising_objective = $campaign['advertising_objective'];
            }
            if (isset($campaign['additional_requirements'])) {
                $application->additional_requirements = $campaign['additional_requirements'];
            }

            if (!empty($data['applicant_response'])) {
                $application->applicant_response = $data['applicant_response'];
            }

            $application->save();

            if ($resubmit && $application->status === AdvertiserApplication::STATUS_CHANGES_REQUESTED) {
                $this->transitionStatus(
                    application: $application,
                    newStatus: AdvertiserApplication::STATUS_SUBMITTED,
                    actorType: 'applicant',
                    actorId: $application->user_id,
                    notes: 'Applicant resubmitted updated information and creatives.'
                );
            }

            return $application->fresh(['package', 'assets', 'statusHistory']);
        });
    }

    /**
     * Withdraw an eligible application.
     */
    public function withdrawApplication(AdvertiserApplication $application, ?string $reason = null): AdvertiserApplication
    {
        return $this->transitionStatus(
            application: $application,
            newStatus: AdvertiserApplication::STATUS_WITHDRAWN,
            actorType: 'applicant',
            actorId: $application->user_id,
            notes: $reason ?: 'Application withdrawn by applicant.'
        );
    }

    /**
     * Securely link an application to an authenticated Peer user.
     */
    public function linkApplicationToUser(AdvertiserApplication $application, User $user, ?string $claimToken = null): AdvertiserApplication
    {
        if ($application->user_id !== null && $application->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'claim' => ['This application is already linked to another account.'],
            ]);
        }

        // Email ownership verification: either user email matches applicant email OR applicant was already email verified
        $userEmail = strtolower(trim((string) $user->email));
        $appEmail = strtolower(trim($application->applicant_email));

        if ($userEmail !== $appEmail && !$application->applicant_email_verified) {
            throw ValidationException::withMessages([
                'claim' => ['The application email must be verified before linking.'],
            ]);
        }

        $application->user_id = $user->id;
        $application->applicant_email_verified = true;
        $application->save();

        AdvertiserApplicationStatusHistory::create([
            'application_id' => $application->id,
            'old_status' => $application->status,
            'new_status' => $application->status,
            'changed_by' => $user->id,
            'actor_type' => 'applicant',
            'notes' => 'Application linked to Peer user ' . $user->display_name,
            'created_at' => now(),
        ]);

        return $application->fresh();
    }
}
