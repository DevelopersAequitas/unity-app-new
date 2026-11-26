<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Mail\Leadership\LeadershipNominationSubmittedMail;
use App\Models\City;
use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipCampaignScope;
use App\Models\Leadership\LeadershipFormQuestion;
use App\Models\Leadership\LeadershipNomination;
use App\Models\Leadership\LeadershipNominationAnswer;
use App\Models\Leadership\LeadershipNominationDocument;
use App\Models\Leadership\LeadershipNominationHistory;
use App\Models\Leadership\LeadershipNotificationLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class NominationService
{
    public function __construct(
        protected FormBuilderService $formBuilderService,
        protected AuditService $auditService
    ) {}

    /**
     * Resolve and validate scope_id to prevent PostgreSQL foreign key constraint violations.
     */
    public function resolveScopeId(string $campaignId, mixed $rawScopeId): ?string
    {
        if (empty($rawScopeId) || ! is_string($rawScopeId) || ! Str::isUuid($rawScopeId)) {
            return null;
        }

        if (! Schema::hasTable('web_leadership_campaign_scopes')) {
            return null;
        }

        // 1. Direct match on web_leadership_campaign_scopes.id
        $scope = LeadershipCampaignScope::where('id', $rawScopeId)
            ->where('campaign_id', $campaignId)
            ->first();

        if ($scope) {
            return (string) $scope->id;
        }

        // 2. Check if rawScopeId is a city_id already mapped to a scope in this campaign
        $scopeByCity = LeadershipCampaignScope::where('city_id', $rawScopeId)
            ->where('campaign_id', $campaignId)
            ->first();

        if ($scopeByCity) {
            return (string) $scopeByCity->id;
        }

        // 3. Check if rawScopeId matches a valid City; if so, create the campaign scope
        if (Schema::hasTable('cities')) {
            $city = City::find($rawScopeId);
            if ($city) {
                $scopeName = ! empty($city->district) ? "{$city->district} District" : $city->name;
                $newScope = LeadershipCampaignScope::create([
                    'campaign_id' => $campaignId,
                    'city_id' => (string) $city->id,
                    'name' => $scopeName,
                    'scope_name' => $scopeName,
                    'scope_type' => 'district',
                    'city' => $city->name,
                    'district' => $city->district ?: $city->name,
                    'state' => $city->state,
                    'seats_available' => 1,
                    'status' => 'active',
                ]);

                return (string) $newScope->id;
            }
        }

        // 4. Any non-matching ID is discarded gracefully to avoid FK errors
        return null;
    }

    /**
     * Retrieve Unity profile for verified contact (C2).
     *
     * @return array{profile_found: bool, can_continue_as_new_nominee?: bool, profile: array<string, mixed>|null}
     */
    public function getVerifiedProfile(string $campaignId, string $verificationToken): array
    {
        $tokenData = Cache::get('nomination_token:'.$verificationToken);
        if (! $tokenData || $tokenData['campaign_id'] !== $campaignId) {
            throw new RuntimeException('Invalid or expired verification token.');
        }

        $contact = $tokenData['contact'];
        $user = $tokenData['contact_type'] === 'email'
            ? User::where('email', $contact)->first()
            : User::where('phone', $contact)->first();

        if (! $user) {
            return [
                'profile_found' => false,
                'can_continue_as_new_nominee' => true,
                'profile' => null,
            ];
        }

        $photoUrl = $user->profile_photo_url;

        $sinceDate = $user->membership_starts_at ?? $user->membership_start_date ?? $user->created_at;
        $sinceYear = $sinceDate ? ($sinceDate instanceof \Carbon\Carbon ? $sinceDate->format('Y') : date('Y', strtotime((string) $sinceDate))) : date('Y');
        $memberSince = "Since {$sinceYear}";
        $lifeImpactScore = (int) ($user->life_impacted_count ?? 0);

        return [
            'profile_found' => true,
            'profile' => [
                'user_id' => (string) $user->id,
                'full_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->display_name ?? 'Member'),
                'email' => $user->email,
                'mobile' => $user->phone,
                'phone' => $user->phone,
                'company' => $user->company_name,
                'company_name' => $user->company_name,
                'designation' => $user->designation,
                'city' => $user->city,
                'profile_photo_url' => $photoUrl,
                'photo_url' => $photoUrl,
                'avatar_url' => $photoUrl,
                'image_url' => $photoUrl,
                'life_impact_score' => $lifeImpactScore,
                'lives_impacted' => $lifeImpactScore,
                'member_since' => $memberSince,
                'membership_starts_at' => $sinceDate ? ($sinceDate instanceof \Carbon\Carbon ? $sinceDate->toIso8601String() : (string) $sinceDate) : null,
                'role' => 'Peers Global Member',
                'current_role' => 'Peers Global Member',
                'current_circle' => $user->circle?->name ?? ($user->adminCircleName() ?: ''),
            ],
        ];
    }

    /**
     * Save nomination draft (C3).
     *
     * @param  array<string, mixed>  $data
     * @return array{nomination_id: string, application_number: string, status: string}
     */
    public function saveDraft(array $data): array
    {
        $tokenData = Cache::get('nomination_token:'.($data['verification_token'] ?? ''));
        if (! $tokenData || $tokenData['campaign_id'] !== $data['campaign_id']) {
            throw new RuntimeException('Invalid or expired verification token.');
        }

        $campaign = LeadershipCampaign::findOrFail($data['campaign_id']);
        if ($campaign->status !== 'active') {
            throw new RuntimeException('Campaign is not open for nominations.');
        }

        $contact = $tokenData['contact'];
        $user = $tokenData['contact_type'] === 'email'
            ? User::where('email', $contact)->first()
            : User::where('phone', $contact)->first();

        // Check if an existing nomination draft exists for this verified contact and campaign
        /** @var LeadershipNomination|null $existingDraft */
        $existingDraft = LeadershipNomination::query()
            ->where('campaign_id', $campaign->id)
            ->where(function ($q) use ($contact): void {
                $q->where('email', $contact)->orWhere('mobile', $contact);
            })
            ->whereIn('status', ['draft', 'changes_requested'])
            ->first();

        $profileData = $data['profile'] ?? [];
        $profileData['form_type'] = $data['form_type'] ?? 'main_form_1';
        $answers = $data['answers'] ?? [];
        $resolvedScopeId = $this->resolveScopeId($campaign->id, $data['scope_id'] ?? null);

        return DB::transaction(function () use ($existingDraft, $campaign, $user, $contact, $tokenData, $profileData, $answers, $resolvedScopeId): array {
            if ($existingDraft) {
                $existingDraft->update([
                    'scope_id' => $resolvedScopeId ?? $existingDraft->scope_id,
                    'full_name' => $profileData['full_name'] ?? $existingDraft->full_name,
                    'profile_snapshot' => array_merge((array) ($existingDraft->profile_snapshot ?? []), $profileData),
                    'edited_profile' => array_merge((array) ($existingDraft->edited_profile ?? []), $profileData),
                    'profile_changes' => $this->computeProfileDiff($existingDraft->original_profile, $profileData),
                ]);

                $nomination = $existingDraft;
            } else {
                $appNumber = $this->generateApplicationNumber($campaign->campaign_year);

                $origProfile = [];
                if (! $user) {
                    $cId = $data['candidate_id'] ?? null;
                    $e = $profileData['email'] ?? ($tokenData['contact_type'] === 'email' ? $contact : null);
                    $m = $profileData['mobile'] ?? ($tokenData['contact_type'] === 'mobile' ? $contact : null);
                    if (! empty($cId)) {
                        $user = User::find($cId);
                    }
                    if (! $user && ! empty($e)) {
                        $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($e))])->first();
                    }
                    if (! $user && ! empty($m)) {
                        $cleanedMobile = preg_replace('/\D/', '', $m);
                        if (strlen($cleanedMobile) >= 10) {
                            $last10 = substr($cleanedMobile, -10);
                            $user = User::whereRaw("RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10) = ?", [$last10])->first();
                        }
                    }
                }

                if ($user) {
                    $photo = $user->profile_photo_url;
                    $origProfile = [
                        'full_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->display_name,
                        'email' => $user->email,
                        'mobile' => $user->phone,
                        'company_name' => $user->company_name,
                        'designation' => $user->designation,
                        'photo_url' => $photo,
                        'profile_photo_url' => $photo,
                        'avatar_url' => $photo,
                    ];
                    if ($photo && empty($profileData['photo_url']) && empty($profileData['profile_photo_url'])) {
                        $profileData['photo_url'] = $photo;
                        $profileData['profile_photo_url'] = $photo;
                        $profileData['avatar_url'] = $photo;
                    }
                }

                $nomination = LeadershipNomination::create([
                    'campaign_id' => $campaign->id,
                    'scope_id' => $resolvedScopeId,
                    'user_id' => $user?->id,
                    'application_number' => $appNumber,
                    'full_name' => $profileData['full_name'] ?? ($origProfile['full_name'] ?? 'Candidate'),
                    'email' => $tokenData['contact_type'] === 'email' ? $contact : ($profileData['email'] ?? null),
                    'mobile' => $tokenData['contact_type'] === 'mobile' ? $contact : ($profileData['mobile'] ?? null),
                    'profile_snapshot' => $profileData,
                    'original_profile' => $origProfile,
                    'edited_profile' => $profileData,
                    'profile_changes' => $this->computeProfileDiff($origProfile, $profileData),
                    'status' => 'draft',
                ]);

                LeadershipNominationHistory::create([
                    'nomination_id' => $nomination->id,
                    'previous_status' => null,
                    'new_status' => 'draft',
                    'action' => 'draft_created',
                    'remarks' => 'Nomination draft created by candidate',
                ]);
            }

            // Save answers
            if (! empty($answers)) {
                foreach ($answers as $key => $val) {
                    LeadershipNominationAnswer::updateOrCreate(
                        ['nomination_id' => $nomination->id, 'question_key' => $key],
                        ['answer' => is_array($val) ? $val : ['value' => $val]]
                    );
                }
            }

            return [
                'nomination_id' => $nomination->id,
                'application_number' => $nomination->application_number,
                'status' => $nomination->status,
            ];
        });
    }

    /**
     * Get nomination draft (C4).
     */
    public function getDraft(string $nominationId, string $verificationToken): LeadershipNomination
    {
        $nomination = LeadershipNomination::with(['answers', 'documents', 'campaign', 'scope'])->findOrFail($nominationId);
        $this->verifyCandidateAccess($nomination, $verificationToken);

        return $nomination;
    }

    /**
     * Update nomination draft (C5).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateDraft(string $nominationId, array $data, string $verificationToken): LeadershipNomination
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::findOrFail($nominationId);
        $this->verifyCandidateAccess($nomination, $verificationToken);

        if (! in_array($nomination->status, ['draft', 'changes_requested'], true)) {
            throw new RuntimeException('Only draft or changes-requested nominations can be modified.');
        }

        $profileData = $data['profile'] ?? [];
        $answers = $data['answers'] ?? [];
        $resolvedScopeId = isset($data['scope_id']) ? $this->resolveScopeId($nomination->campaign_id, $data['scope_id']) : $nomination->scope_id;

        return DB::transaction(function () use ($nomination, $profileData, $answers, $resolvedScopeId): LeadershipNomination {
            $updatedProfile = array_merge($nomination->edited_profile, $profileData);
            $nomination->update([
                'scope_id' => $resolvedScopeId,
                'full_name' => $profileData['full_name'] ?? $nomination->full_name,
                'edited_profile' => $updatedProfile,
                'profile_changes' => $this->computeProfileDiff($nomination->original_profile, $updatedProfile),
            ]);

            if (! empty($answers)) {
                foreach ($answers as $key => $val) {
                    LeadershipNominationAnswer::updateOrCreate(
                        ['nomination_id' => $nomination->id, 'question_key' => $key],
                        ['answer' => is_array($val) ? $val : ['value' => $val]]
                    );
                }
            }

            return $nomination->fresh(['answers', 'documents', 'scope']);
        });
    }

    /**
     * Upload nomination document (C6).
     */
    public function uploadDocument(string $nominationId, string $documentType, UploadedFile $file, string $verificationToken): LeadershipNominationDocument
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::findOrFail($nominationId);
        $this->verifyCandidateAccess($nomination, $verificationToken);

        if (! in_array($nomination->status, ['draft', 'changes_requested'], true)) {
            throw new RuntimeException('Documents can only be uploaded while the application is in draft or correction state.');
        }

        $disk = config('filesystems.default') === 's3' ? 's3' : 'public';
        $path = $file->store("leadership/nominations/{$nomination->id}", $disk);

        /** @var LeadershipNominationDocument $document */
        $document = LeadershipNominationDocument::create([
            'nomination_id' => $nomination->id,
            'document_type' => $documentType,
            'original_filename' => $file->getClientOriginalName(),
            'storage_disk' => $disk,
            'storage_key' => $path,
            'mime_type' => $file->getClientMimeType(),
            'file_size_bytes' => $file->getSize(),
            'verification_status' => 'pending',
            'uploaded_by' => $nomination->user_id,
        ]);

        return $document;
    }

    /**
     * Delete nomination document (C7).
     */
    public function deleteDocument(string $nominationId, string $documentId, string $verificationToken): bool
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::findOrFail($nominationId);
        $this->verifyCandidateAccess($nomination, $verificationToken);

        if (! in_array($nomination->status, ['draft', 'changes_requested'], true)) {
            throw new RuntimeException('Documents can only be deleted while the application is in draft or revision state.');
        }

        /** @var LeadershipNominationDocument $doc */
        $doc = LeadershipNominationDocument::where('nomination_id', $nomination->id)->findOrFail($documentId);

        if (Storage::disk($doc->storage_disk)->exists($doc->storage_key)) {
            Storage::disk($doc->storage_disk)->delete($doc->storage_key);
        }

        return (bool) $doc->delete();
    }

    /**
     * Submit nomination (C8).
     *
     * @param  array<string, bool>  $declarations
     * @return array{nomination_id: string, application_number: string, status: string, submitted_at: string}
     */
    public function submitNomination(string $nominationId, array $declarations, string $verificationToken): array
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::findOrFail($nominationId);
        $this->verifyCandidateAccess($nomination, $verificationToken);

        if ($nomination->status === 'submitted') {
            return [
                'nomination_id' => $nomination->id,
                'application_number' => $nomination->application_number,
                'status' => $nomination->status,
                'submitted_at' => $nomination->submitted_at?->toIso8601String() ?? Carbon::now()->toIso8601String(),
            ];
        }

        if (! in_array($nomination->status, ['draft', 'changes_requested'], true)) {
            throw new RuntimeException("Application in status '{$nomination->status}' cannot be submitted.");
        }

        $now = Carbon::now();
        $isResubmission = $nomination->status === 'changes_requested';
        $newStatus = $isResubmission ? 'resubmitted' : 'submitted';

        return DB::transaction(function () use ($nomination, $newStatus, $now, $declarations, $isResubmission): array {
            $nomination->update([
                'status' => $newStatus,
                'submitted_at' => $now,
                'profile_snapshot' => array_merge($nomination->edited_profile, ['declarations' => $declarations]),
            ]);

            LeadershipNominationHistory::create([
                'nomination_id' => $nomination->id,
                'previous_status' => $isResubmission ? 'changes_requested' : 'draft',
                'new_status' => $newStatus,
                'action' => $isResubmission ? 'resubmitted' : 'submitted',
                'remarks' => 'Application submitted with verified declarations',
                'metadata' => ['declarations' => $declarations],
            ]);

            $this->auditService->log(
                action: 'nomination.submitted',
                entityType: 'LeadershipNomination',
                entityId: $nomination->id,
                campaignId: $nomination->campaign_id,
                afterData: $nomination->fresh()->toArray(),
                remarks: "Nomination {$nomination->application_number} submitted"
            );

            return [
                'nomination_id' => $nomination->id,
                'application_number' => $nomination->application_number,
                'status' => $nomination->status,
                'submitted_at' => $now->toIso8601String(),
            ];
        });

        // Dispatch confirmation email with branded template & audit log
        $this->sendSubmissionConfirmationEmail($nomination->fresh(['campaign.role', 'scope', 'user']));

        return $result;
    }

    protected function generateApplicationNumber(int $year): string
    {
        $prefix = "PG-{$year}-";
        $count = LeadershipNomination::where('application_number', 'like', "{$prefix}%")->count() + 1;

        return $prefix.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $edited
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function computeProfileDiff(array $original, array $edited): array
    {
        $diff = [];
        foreach ($edited as $key => $val) {
            $origVal = $original[$key] ?? null;
            if ($origVal !== $val) {
                $diff[$key] = [
                    'from' => $origVal,
                    'to' => $val,
                ];
            }
        }

        return $diff;
    }

    protected function verifyCandidateAccess(LeadershipNomination $nomination, string $verificationToken): void
    {
        $tokenData = Cache::get('nomination_token:'.$verificationToken);
        if (! $tokenData || $tokenData['campaign_id'] !== $nomination->campaign_id) {
            throw new RuntimeException('Unauthorized or expired access token.');
        }

        $contact = $tokenData['contact'];
        if (strtolower((string) $nomination->email) !== $contact && (string) $nomination->mobile !== $contact) {
            throw new RuntimeException('You are not authorized to view or edit this nomination.');
        }
    }

    /**
     * Direct single-step nomination submission (C9).
     *
     * @param  array<string, mixed>  $data
     * @return array{id: string, nomination_id: string, application_number: string, status: string, submitted_at: string}
     */
    public function directNominate(string $campaignId, array $data): array
    {
        $campaign = LeadershipCampaign::findOrFail($campaignId);

        $candidateId = $data['candidate_id'] ?? null;
        $scopeId = $this->resolveScopeId($campaign->id, $data['scope_id'] ?? null);

        $rawProfile = is_array($data['profile'] ?? null) ? $data['profile'] : [];
        $answers = is_array($data['answers'] ?? null) ? $data['answers'] : [];
        $declarations = is_array($data['declarations'] ?? null) ? $data['declarations'] : [];

        $fullName = $rawProfile['full_name'] ?? $data['full_name'] ?? 'Candidate';
        $email = strtolower(trim((string) ($rawProfile['email'] ?? $data['email'] ?? '')));
        $mobile = $rawProfile['mobile'] ?? $data['mobile'] ?? null;
        $appliedRole = trim((string) ($rawProfile['applied_role'] ?? $data['applied_role'] ?? $data['role'] ?? ($answers['applied_role'] ?? '')));
        $roleName = $data['role_name'] ?? $rawProfile['role_name'] ?? $appliedRole;

        // 1. Backend Safeguard: Idempotency / Debounce check (60-second window for identical campaign, email, and applied role)
        if (! empty($email)) {
            $recentDuplicate = LeadershipNomination::where('campaign_id', $campaign->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->where('created_at', '>=', Carbon::now()->subSeconds(60))
                ->when($appliedRole !== '', function ($q) use ($appliedRole) {
                    $q->where(function ($sub) use ($appliedRole) {
                        $sub->where('profile_snapshot->applied_role', $appliedRole)
                            ->orWhere('profile_snapshot->role', $appliedRole)
                            ->orWhere('profile_snapshot->role_name', $appliedRole);
                    });
                })
                ->latest()
                ->first();

            if ($recentDuplicate) {
                Log::info("[Nomination Idempotency] Duplicate submission blocked within 60s for {$email} ({$appliedRole}). Returning existing application {$recentDuplicate->application_number}.");

                return [
                    'id' => $recentDuplicate->id,
                    'nomination_id' => $recentDuplicate->id,
                    'application_number' => $recentDuplicate->application_number,
                    'status' => $recentDuplicate->status,
                    'submitted_at' => $recentDuplicate->submitted_at?->toIso8601String() ?? $recentDuplicate->created_at->toIso8601String(),
                    'is_idempotent_duplicate' => true,
                ];
            }
        }

        // 2. Comprehensive Profile Snapshot Mapping (Matching updated Web Portal Schema)
        $profileChanges = is_array($rawProfile['profile_changes'] ?? null)
            ? $rawProfile['profile_changes']
            : (is_array($data['profile_changes'] ?? null) ? $data['profile_changes'] : []);

        // Resolve or persist base64 data URI photo to public disk
        $photoCandidate = $rawProfile['photo_url'] ?? $data['photo_url'] ?? ($rawProfile['profile_photo_url'] ?? null) ?? ($data['profile_photo'] ?? null);
        if (is_string($photoCandidate) && str_starts_with($photoCandidate, 'data:image/')) {
            try {
                $ext = 'jpg';
                if (str_contains($photoCandidate, 'data:image/png')) {
                    $ext = 'png';
                }
                if (str_contains($photoCandidate, 'data:image/webp')) {
                    $ext = 'webp';
                }
                $base64Part = substr($photoCandidate, strpos($photoCandidate, ';base64,') + 8);
                $decoded = base64_decode($base64Part, true);
                if ($decoded !== false && ! empty($decoded)) {
                    $fileName = 'nominee-'.Str::random(16).'.'.$ext;
                    $savePath = 'nominations/'.$fileName;
                    Storage::disk('public')->put($savePath, $decoded);
                    $photoCandidate = asset('storage/'.$savePath);
                }
            } catch (\Throwable) {
            }
        }

        $profile = array_merge(
            $answers,
            $rawProfile,
            [
                'full_name' => $fullName,
                'email' => $email,
                'mobile' => $mobile,
                'photo_url' => $photoCandidate,
                'profile_photo_url' => $photoCandidate,
                'avatar_url' => $photoCandidate,
                'linkedin' => $rawProfile['linkedin'] ?? $data['linkedin'] ?? null,
                'website' => $rawProfile['website'] ?? $data['website'] ?? null,
                'company_name' => $rawProfile['company_name'] ?? $data['company_name'] ?? $data['company'] ?? ($answers['company_name'] ?? $answers['company'] ?? null),
                'designation' => $rawProfile['designation'] ?? $data['designation'] ?? ($answers['designation'] ?? null),
                'industry' => $rawProfile['industry'] ?? $data['industry'] ?? ($answers['industry'] ?? null),
                'since' => $rawProfile['since'] ?? $data['since'] ?? ($answers['since'] ?? null),
                'city' => $rawProfile['city'] ?? $data['city'] ?? $data['city_name'] ?? ($answers['city_name'] ?? $answers['city'] ?? null),
                'city_name' => $data['city_name'] ?? $rawProfile['city_name'] ?? $data['city'] ?? null,
                'belonging_type' => $data['belonging_type'] ?? $rawProfile['belonging_type'] ?? null,
                'belonging_name' => $data['belonging_name'] ?? $rawProfile['belonging_name'] ?? null,
                'applied_role' => $appliedRole,
                'role_name' => $roleName,
                // Leadership & Vision (NEW Portal Schema)
                'leadership_statement' => $rawProfile['leadership_statement'] ?? $data['leadership_statement'] ?? null,
                'why_role' => $rawProfile['why_role'] ?? $data['why_role'] ?? null,
                'vision_12_months' => $rawProfile['vision_12_months'] ?? $data['vision_12_months'] ?? null,
                'hours_per_week' => $rawProfile['hours_per_week'] ?? $data['hours_per_week'] ?? ($rawProfile['time_commitment'] ?? $data['time_commitment'] ?? null),
                'time_commitment' => $rawProfile['time_commitment'] ?? $rawProfile['hours_per_week'] ?? $data['hours_per_week'] ?? $data['time_commitment'] ?? null,
                'other_network_involvement' => filter_var($rawProfile['other_network_involvement'] ?? $data['other_network_involvement'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'other_network_details' => $rawProfile['other_network_details'] ?? $data['other_network_details'] ?? null,
                'video_url' => $rawProfile['video_url'] ?? $data['video_url'] ?? ($answers['video_url'] ?? null),
                'goals' => $rawProfile['goals'] ?? $data['goals'] ?? ($answers['goals'] ?? []),
                'leadership_roles' => $rawProfile['leadership_roles'] ?? $data['leadership_roles'] ?? ($answers['leadership_roles'] ?? []),
                'declarations' => $declarations,
                'profile_changes' => $profileChanges,
                'form_type' => $data['form_type'] ?? 'main_form_1',
            ]
        );

        if (is_array($data['documents'] ?? null)) {
            foreach ($data['documents'] as $key => $doc) {
                if (is_array($doc)) {
                    $docType = strtolower((string) ($doc['document_type'] ?? $doc['type'] ?? ''));
                    $fileUrl = $doc['file_url'] ?? $doc['url'] ?? $doc['link'] ?? $doc['file'] ?? '';
                } elseif (is_string($doc)) {
                    $docType = strtolower((string) $key);
                    $fileUrl = $doc;
                } else {
                    continue;
                }
                if (str_contains($docType, 'id')) {
                    $profile['id_file'] = $fileUrl;
                } elseif (str_contains($docType, 'letter') || str_contains($docType, 'recommendation')) {
                    $profile['letter_file'] = $fileUrl;
                } elseif (str_contains($docType, 'deck') || str_contains($docType, 'company') || str_contains($docType, 'profile')) {
                    $profile['deck_file'] = $fileUrl;
                } elseif (str_contains($docType, 'manifesto')) {
                    $profile['manifesto_file'] = $fileUrl;
                }
            }
        }

        $flatDocMap = [
            'id_file' => ['id_file', 'id_proof', 'identity_proof', 'aadhaar_file'],
            'letter_file' => ['letter_file', 'recommendation_letter', 'recommendation_file'],
            'deck_file' => ['deck_file', 'company_deck', 'company_profile', 'presentation_deck'],
            'manifesto_file' => ['manifesto_file', 'manifesto', 'candidate_manifesto'],
        ];
        foreach ($flatDocMap as $targetKey => $sourceKeys) {
            if (empty($profile[$targetKey])) {
                foreach ($sourceKeys as $sk) {
                    if (! empty($data[$sk])) {
                        $profile[$targetKey] = $data[$sk];
                        break;
                    }
                    if (! empty($rawProfile[$sk])) {
                        $profile[$targetKey] = $rawProfile[$sk];
                        break;
                    }
                    if (! empty($answers[$sk])) {
                        $profile[$targetKey] = is_array($answers[$sk]) ? ($answers[$sk]['value'] ?? null) : $answers[$sk];
                        break;
                    }
                }
            }
        }

        $user = null;
        if (! empty($candidateId)) {
            $user = User::find($candidateId);
        }
        if (! $user && ! empty($email)) {
            $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($email))])->first();
        }
        if (! $user && ! empty($mobile)) {
            $cleanedMobile = preg_replace('/\D/', '', $mobile);
            if (strlen($cleanedMobile) >= 10) {
                $last10 = substr($cleanedMobile, -10);
                $user = User::whereRaw("RIGHT(REGEXP_REPLACE(phone, '[^0-9]', ''), 10) = ?", [$last10])->first();
            }
        }

        if ($user) {
            $userName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->display_name;
            if ($userName && $fullName === 'Candidate') {
                $fullName = $userName;
            }
            $email = $email ?: $user->email;
            $mobile = $mobile ?: $user->phone;
            $photo = $user->profile_photo_url;
            if ($photo && empty($profile['photo_url']) && empty($profile['profile_photo_url'])) {
                $profile['photo_url'] = $photo;
                $profile['profile_photo_url'] = $photo;
                $profile['avatar_url'] = $photo;
                $profile['image_url'] = $photo;
            }
        }

        $appNumber = $this->generateApplicationNumber($campaign->campaign_year);
        $now = Carbon::now();

        return DB::transaction(function () use ($campaign, $scopeId, $user, $appNumber, $fullName, $email, $mobile, $profile, $profileChanges, $data, $now): array {
            $nomData = [
                'campaign_id' => $campaign->id,
                'scope_id' => $scopeId,
                'user_id' => $user?->id,
                'application_number' => $appNumber,
                'full_name' => $fullName,
                'email' => $email,
                'mobile' => $mobile,
                'profile_snapshot' => $profile,
                'original_profile' => $user ? [
                    'full_name' => $fullName,
                    'email' => $email,
                    'mobile' => $mobile,
                    'photo_url' => $user->profile_photo_url,
                    'profile_photo_url' => $user->profile_photo_url,
                    'avatar_url' => $user->profile_photo_url,
                    'image_url' => $user->profile_photo_url,
                ] : [],
                'edited_profile' => $profile,
                'profile_changes' => $profileChanges,
                'status' => 'submitted',
                'submitted_at' => $now,
            ];
            $nomTable = (new LeadershipNomination)->getTable();
            if (Schema::hasColumn($nomTable, 'form_type')) {
                $nomData['form_type'] = $data['form_type'] ?? 'main_form_1';
            }
            /** @var LeadershipNomination $nomination */
            $nomination = LeadershipNomination::create($nomData);

            // Save answers
            $answers = $data['answers'] ?? [];
            if (is_array($answers) && Schema::hasTable('web_leadership_nomination_answers')) {
                $hasQuestionId = Schema::hasColumn('web_leadership_nomination_answers', 'question_id');
                $hasQuestionKey = Schema::hasColumn('web_leadership_nomination_answers', 'question_key');
                $hasQuestionSnapshot = Schema::hasColumn('web_leadership_nomination_answers', 'question_snapshot');

                foreach ($answers as $key => $val) {
                    $question = null;
                    if ($hasQuestionId && Schema::hasTable('web_leadership_form_questions')) {
                        $question = LeadershipFormQuestion::query()
                            ->where('question_key', (string) $key)
                            ->first();
                    }

                    $answerPayload = [
                        'nomination_id' => $nomination->id,
                        'answer' => is_array($val) ? $val : ['value' => $val],
                    ];

                    if ($hasQuestionId) {
                        $answerPayload['question_id'] = $question?->id;
                    }
                    if ($hasQuestionKey) {
                        $answerPayload['question_key'] = (string) $key;
                    }
                    if ($hasQuestionSnapshot) {
                        $answerPayload['question_snapshot'] = $question ? [
                            'label' => $question->label,
                            'field_type' => $question->field_type,
                        ] : null;
                    }

                    LeadershipNominationAnswer::create($answerPayload);
                }
            }

            // Save documents
            $documents = $data['documents'] ?? ($data['profile']['documents'] ?? []);
            if (is_array($documents)) {
                foreach ($documents as $doc) {
                    if (is_array($doc)) {
                        $fileUrl = $doc['file_url'] ?? $doc['url'] ?? $doc['link'] ?? $doc['file'] ?? '';
                        $fileName = $doc['original_name'] ?? $doc['original_filename'] ?? $doc['name'] ?? $doc['filename'] ?? (basename(parse_url((string) $fileUrl, PHP_URL_PATH) ?: 'document.pdf'));
                        $docType = $doc['document_type'] ?? $doc['type'] ?? 'supporting_doc';

                        LeadershipNominationDocument::create([
                            'nomination_id' => $nomination->id,
                            'document_type' => $docType,
                            'original_filename' => $fileName,
                            'storage_disk' => 'local',
                            'storage_key' => $fileUrl,
                            'mime_type' => 'application/pdf',
                            'file_size_bytes' => 1024,
                            'verification_status' => 'pending',
                            'uploaded_by' => $user?->id,
                        ]);
                    }
                }
            }

            // Also persist any individual files mapped into profile if not already stored in documents
            $profileDocMap = [
                'ID Proof (Aadhaar / Passport)' => $profile['id_file'] ?? null,
                'Recommendation Letter' => $profile['letter_file'] ?? null,
                'Company Profile / Deck' => $profile['deck_file'] ?? null,
                'Candidate Manifesto' => $profile['manifesto_file'] ?? null,
            ];
            $savedUrls = LeadershipNominationDocument::where('nomination_id', $nomination->id)->pluck('storage_key')->filter()->toArray();
            foreach ($profileDocMap as $label => $fileUrl) {
                if ($fileUrl && ! in_array($fileUrl, $savedUrls, true)) {
                    LeadershipNominationDocument::create([
                        'nomination_id' => $nomination->id,
                        'document_type' => $label,
                        'original_filename' => basename(parse_url((string) $fileUrl, PHP_URL_PATH) ?: 'document.pdf'),
                        'storage_disk' => 'local',
                        'storage_key' => $fileUrl,
                        'mime_type' => 'application/pdf',
                        'file_size_bytes' => 1024,
                        'verification_status' => 'pending',
                        'uploaded_by' => $user?->id,
                    ]);
                    $savedUrls[] = $fileUrl;
                }
            }

            LeadershipNominationHistory::create([
                'nomination_id' => $nomination->id,
                'previous_status' => 'draft',
                'new_status' => 'submitted',
                'action' => 'submitted',
                'remarks' => 'Nomination application submitted via website portal',
                'changed_by' => $user?->id,
            ]);

            $this->auditService->log(
                action: 'nomination.submitted',
                entityType: 'LeadershipNomination',
                entityId: $nomination->id,
                campaignId: $nomination->campaign_id,
                remarks: "Nomination {$nomination->application_number} submitted via website portal"
            );

            // Dispatch confirmation email with branded template & audit log
            $this->sendSubmissionConfirmationEmail($nomination->fresh(['campaign.role', 'scope', 'user']));

            return [
                'id' => $nomination->id,
                'nomination_id' => $nomination->id,
                'application_number' => $nomination->application_number,
                'status' => 'submitted',
                'submitted_at' => $now->toIso8601String(),
            ];
        });
    }

    /**
     * Auto-assign newly submitted candidate nomination to all appointed standing jurors.
     */
    protected function autoAssignStandingJurors(LeadershipNomination $nomination): void
    {
        try {
            $standingJurorIds = \App\Models\Leadership\LeadershipJuryAssignment::query()
                ->whereNotNull('juror_user_id')
                ->distinct()
                ->pluck('juror_user_id')
                ->toArray();

            if (! empty($standingJurorIds)) {
                app(\App\Services\Leadership\JuryService::class)->assignJurors(
                    $nomination->id,
                    $standingJurorIds,
                    null,
                    'Auto-assigned to appointed standing jury panel upon nomination receipt'
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Failed to auto-assign standing jurors to nomination {$nomination->id}: ".$e->getMessage());
        }
    }

    /**
     * Dispatch submission confirmation email to the candidate and log notification.
     */
    protected function sendSubmissionConfirmationEmail(?LeadershipNomination $nomination): void
    {
        if (! $nomination) {
            return;
        }

        $recipientEmail = $nomination->email;
        if (empty($recipientEmail) && is_array($nomination->profile_snapshot) && ! empty($nomination->profile_snapshot['email'])) {
            $recipientEmail = (string) $nomination->profile_snapshot['email'];
        }
        if (empty($recipientEmail) && is_array($nomination->edited_profile) && ! empty($nomination->edited_profile['email'])) {
            $recipientEmail = (string) $nomination->edited_profile['email'];
        }
        if (empty($recipientEmail) && $nomination->user) {
            $recipientEmail = (string) $nomination->user->email;
        }

        if (empty($recipientEmail)) {
            Log::warning("Leadership nomination submission email skipped: no email found for nomination {$nomination->id}");

            return;
        }

        try {
            Mail::to($recipientEmail)->send(new LeadershipNominationSubmittedMail($nomination));

            LeadershipNotificationLog::create([
                'campaign_id' => $nomination->campaign_id,
                'nomination_id' => $nomination->id,
                'recipient_user_id' => $nomination->user_id,
                'recipient' => $recipientEmail,
                'channel' => 'email',
                'template_key' => 'nomination_submitted',
                'status' => 'sent',
                'attempt_count' => 1,
                'sent_at' => Carbon::now(),
                'payload_metadata' => [
                    'application_number' => $nomination->application_number,
                    'role' => $nomination->applied_role_name,
                    'jurisdiction' => $nomination->scope_name,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("Failed to dispatch leadership nomination submission email for {$nomination->id}: ".$e->getMessage(), [
                'exception' => $e,
            ]);

            try {
                LeadershipNotificationLog::create([
                    'campaign_id' => $nomination->campaign_id,
                    'nomination_id' => $nomination->id,
                    'recipient_user_id' => $nomination->user_id,
                    'recipient' => $recipientEmail,
                    'channel' => 'email',
                    'template_key' => 'nomination_submitted',
                    'status' => 'failed',
                    'attempt_count' => 1,
                    'error_message' => $e->getMessage(),
                    'payload_metadata' => [
                        'application_number' => $nomination->application_number,
                    ],
                ]);
            } catch (\Throwable) {
                // Ignore secondary log failures
            }
        }
    }

    /**
     * Stream a nomination document inline to the browser.
     */
    public function streamDocument(string $nominationId, string $documentId): \Symfony\Component\HttpFoundation\Response
    {
        /** @var LeadershipNominationDocument $document */
        $document = LeadershipNominationDocument::where('nomination_id', $nominationId)->findOrFail($documentId);

        $key = (string) $document->storage_key;

        if (str_starts_with($key, 'http://') || str_starts_with($key, 'https://')) {
            return redirect()->away($key);
        }

        $filename = $document->original_filename ?: basename($key);
        $encodedFilename = addslashes($filename);

        // Check configured storage disks
        $disks = array_filter(array_unique([
            $document->storage_disk,
            'public',
            'local',
            's3',
        ]));

        foreach ($disks as $disk) {
            if ($disk && Storage::disk($disk)->exists($key)) {
                $mime = $document->mime_type ?: Storage::disk($disk)->mimeType($key) ?: 'application/octet-stream';

                return Storage::disk($disk)->response($key, $filename, [
                    'Content-Type' => $mime,
                    'Content-Disposition' => 'inline; filename="'.$encodedFilename.'"',
                ]);
            }
        }

        // Direct filesystem path fallbacks for standard storage layouts
        $directPaths = [
            storage_path('app/public/'.$key),
            storage_path('app/'.$key),
            storage_path('app/private/'.$key),
            public_path('storage/'.$key),
        ];

        foreach ($directPaths as $path) {
            if (file_exists($path) && is_file($path)) {
                return response()->file($path, [
                    'Content-Disposition' => 'inline; filename="'.$encodedFilename.'"',
                ]);
            }
        }

        abort(404, 'The requested document file could not be found on storage.');
    }
}
