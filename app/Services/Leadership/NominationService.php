<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipNomination;
use App\Models\Leadership\LeadershipNominationAnswer;
use App\Models\Leadership\LeadershipNominationDocument;
use App\Models\Leadership\LeadershipNominationHistory;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        return [
            'profile_found' => true,
            'profile' => [
                'full_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: ($user->display_name ?? 'Member'),
                'email' => $user->email,
                'mobile' => $user->phone,
                'company_name' => $user->company_name,
                'designation' => $user->designation,
                'profile_photo_url' => $user->profile_photo_url,
                'member_since' => $user->created_at?->format('Y-m-d'),
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
        $answers = $data['answers'] ?? [];

        return DB::transaction(function () use ($existingDraft, $campaign, $user, $data, $contact, $tokenData, $profileData, $answers): array {
            if ($existingDraft) {
                $existingDraft->update([
                    'scope_id' => $data['scope_id'] ?? $existingDraft->scope_id,
                    'full_name' => $profileData['full_name'] ?? $existingDraft->full_name,
                    'edited_profile' => array_merge($existingDraft->edited_profile, $profileData),
                    'profile_changes' => $this->computeProfileDiff($existingDraft->original_profile, $profileData),
                ]);

                $nomination = $existingDraft;
            } else {
                $appNumber = $this->generateApplicationNumber($campaign->campaign_year);

                $origProfile = [];
                if ($user) {
                    $origProfile = [
                        'full_name' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->display_name,
                        'email' => $user->email,
                        'mobile' => $user->phone,
                        'company_name' => $user->company_name,
                        'designation' => $user->designation,
                    ];
                }

                $nomination = LeadershipNomination::create([
                    'campaign_id' => $campaign->id,
                    'scope_id' => $data['scope_id'] ?? null,
                    'user_id' => $user?->id,
                    'application_number' => $appNumber,
                    'full_name' => $profileData['full_name'] ?? ($origProfile['full_name'] ?? 'Candidate'),
                    'email' => $tokenData['contact_type'] === 'email' ? $contact : ($profileData['email'] ?? null),
                    'mobile' => $tokenData['contact_type'] === 'mobile' ? $contact : ($profileData['mobile'] ?? null),
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

        return DB::transaction(function () use ($nomination, $profileData, $answers, $data): LeadershipNomination {
            $updatedProfile = array_merge($nomination->edited_profile, $profileData);
            $nomination->update([
                'scope_id' => $data['scope_id'] ?? $nomination->scope_id,
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

        $path = $file->store("leadership/nominations/{$nomination->id}", 'local');

        /** @var LeadershipNominationDocument $document */
        $document = LeadershipNominationDocument::create([
            'nomination_id' => $nomination->id,
            'document_type' => $documentType,
            'original_filename' => $file->getClientOriginalName(),
            'storage_disk' => 'local',
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
        $scopeId = $data['scope_id'] ?? null;
        if ($scopeId && ! Str::isUuid($scopeId)) {
            $scopeId = null;
        }

        $profile = $data['profile'] ?? [];
        $fullName = $profile['full_name'] ?? $data['full_name'] ?? 'Candidate';
        $email = $profile['email'] ?? $data['email'] ?? null;
        $mobile = $profile['mobile'] ?? $data['mobile'] ?? null;

        $user = null;
        if ($candidateId && Str::isUuid($candidateId)) {
            $user = User::find($candidateId);
        }
        if (! $user && $email) {
            $user = User::where('email', strtolower(trim((string) $email)))->first();
        }
        if (! $user && $mobile) {
            $user = User::where('phone', trim((string) $mobile))->first();
        }

        if ($user) {
            $userName = trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->display_name;
            if ($userName && $fullName === 'Candidate') {
                $fullName = $userName;
            }
            $email = $email ?: $user->email;
            $mobile = $mobile ?: $user->phone;
        }

        $appNumber = $this->generateApplicationNumber($campaign->campaign_year);
        $now = Carbon::now();

        return DB::transaction(function () use ($campaign, $scopeId, $user, $appNumber, $fullName, $email, $mobile, $profile, $data, $now): array {
            /** @var LeadershipNomination $nomination */
            $nomination = LeadershipNomination::create([
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
                ] : [],
                'edited_profile' => $profile,
                'profile_changes' => [],
                'status' => 'submitted',
                'submitted_at' => $now,
            ]);

            // Save answers
            $answers = $data['answers'] ?? [];
            if (is_array($answers)) {
                foreach ($answers as $key => $val) {
                    LeadershipNominationAnswer::create([
                        'nomination_id' => $nomination->id,
                        'question_key' => (string) $key,
                        'answer' => is_array($val) ? $val : ['value' => $val],
                    ]);
                }
            }

            // Save documents
            $documents = $data['documents'] ?? [];
            if (is_array($documents)) {
                foreach ($documents as $doc) {
                    if (is_array($doc)) {
                        LeadershipNominationDocument::create([
                            'nomination_id' => $nomination->id,
                            'document_type' => $doc['document_type'] ?? 'supporting_doc',
                            'original_filename' => $doc['original_name'] ?? 'document.pdf',
                            'storage_disk' => 'local',
                            'storage_key' => $doc['file_url'] ?? '',
                            'mime_type' => 'application/pdf',
                            'file_size_bytes' => 1024,
                            'verification_status' => 'pending',
                            'uploaded_by' => $user?->id,
                        ]);
                    }
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

            return [
                'id' => $nomination->id,
                'nomination_id' => $nomination->id,
                'application_number' => $nomination->application_number,
                'status' => 'submitted',
                'submitted_at' => $now->toIso8601String(),
            ];
        });
    }
}
