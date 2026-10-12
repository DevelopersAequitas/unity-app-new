<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipNomination;
use App\Models\Leadership\LeadershipNominationDocument;
use App\Models\Leadership\LeadershipNominationHistory;
use App\Services\Notifications\WhatsappNotificationService;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminNominationService
{
    public function __construct(
        protected AuditService $auditService,
        protected WhatsappNotificationService $whatsappService
    ) {}

    /**
     * List and filter nominations for admin (F1).
     *
     * @param  array<string, mixed>  $filters
     */
    public function listNominations(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = LeadershipNomination::query()
            ->with(['campaign.role', 'scope', 'user'])
            ->withCount([
                'documents as documents_pending' => fn ($q) => $q->where('verification_status', 'pending'),
            ]);

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (! empty($filters['scope_id'])) {
            $query->where('scope_id', $filters['scope_id']);
        }

        // Role filtering: supports role_id, role key, or role name (e.g. DED)
        if (! empty($filters['role_id']) && \Illuminate\Support\Str::isUuid((string) $filters['role_id'])) {
            $query->whereHas('campaign', fn ($q) => $q->where('role_id', $filters['role_id']));
        }

        if (! empty($filters['role_key'])) {
            $query->whereHas('campaign.role', fn ($q) => $q->where('key', $filters['role_key']));
        }

        if (! empty($filters['role'])) {
            $roleTerm = trim((string) $filters['role']);
            $isUuid = \Illuminate\Support\Str::isUuid($roleTerm);
            $query->where(function ($q) use ($roleTerm, $isUuid): void {
                $q->whereHas('campaign', function ($cq) use ($roleTerm, $isUuid): void {
                    if ($isUuid) {
                        $cq->where('role_id', $roleTerm)
                            ->orWhereHas('role', fn ($rq) => $rq->where('id', $roleTerm));
                    } else {
                        $cq->whereHas('role', function ($rq) use ($roleTerm): void {
                            $rq->where('key', $roleTerm)
                                ->orWhere('name', 'ilike', '%'.$roleTerm.'%');
                        });
                    }
                });
            });
        }

        // Form Type filtering: Main form 1 vs Sub form 2
        if (! empty($filters['form_type'])) {
            $formType = trim((string) $filters['form_type']);
            if (in_array($formType, ['sub_form_2', 'sub', '2'], true)) {
                $query->where(function ($q): void {
                    $hasCol = \Illuminate\Support\Facades\Schema::hasColumn('leadership_nominations', 'form_type');
                    if ($hasCol) {
                        $q->where('form_type', 'sub_form_2')
                            ->orWhere('profile_snapshot->form_type', 'sub_form_2');
                    } else {
                        $q->where('profile_snapshot->form_type', 'sub_form_2');
                    }
                });
            } else {
                // Default to main_form_1: all website nominations
                $query->where(function ($q): void {
                    $hasCol = \Illuminate\Support\Facades\Schema::hasColumn('leadership_nominations', 'form_type');
                    if ($hasCol) {
                        $q->where('form_type', 'main_form_1')
                            ->orWhereNull('form_type')
                            ->orWhere('profile_snapshot->form_type', 'main_form_1')
                            ->orWhereNull('profile_snapshot->form_type');
                    } else {
                        $q->where('profile_snapshot->form_type', 'main_form_1')
                            ->orWhereNull('profile_snapshot->form_type');
                    }
                });
            }
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $query->where('submitted_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('submitted_at', '<=', $filters['to_date']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.trim((string) $filters['search']).'%';
            $query->where(fn ($q) => $q->where('full_name', 'ilike', $term)
                ->orWhere('application_number', 'ilike', $term)
                ->orWhere('email', 'ilike', $term)
                ->orWhere('mobile', 'ilike', $term));
        }

        $sortBy = $filters['sort_by'] ?? 'submitted_at';
        $sortOrder = strtolower((string) ($filters['sort_order'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $allowedSorts = ['full_name', 'submitted_at', 'status', 'created_at', 'application_number'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('submitted_at', 'desc');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get complete nomination details (F2).
     */
    public function getNominationDetails(string $nominationId): LeadershipNomination
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::query()
            ->with([
                'campaign.role',
                'scope',
                'user',
                'reviewer',
                'answers.question',
                'documents',
                'history.actor',
                'finalDecision',
            ])
            ->withCount(['votes'])
            ->findOrFail($nominationId);

        return $nomination;
    }

    /**
     * List documents with short-lived authorized URLs (F3).
     *
     * @return Collection<int, LeadershipNominationDocument>
     */
    public function getDocuments(string $nominationId): Collection
    {
        /** @var Collection<int, LeadershipNominationDocument> $docs */
        $docs = LeadershipNominationDocument::query()
            ->where('nomination_id', $nominationId)
            ->get();

        foreach ($docs as $doc) {
            $doc->download_url = Storage::disk($doc->storage_disk)->temporaryUrl(
                $doc->storage_key,
                now()->addMinutes(15)
            );
        }

        return $docs;
    }

    /**
     * Verify or reject a nomination document (F4).
     */
    public function verifyDocument(
        string $nominationId,
        string $documentId,
        string $decision,
        string $remarks,
        ?string $userId = null
    ): LeadershipNominationDocument {
        /** @var LeadershipNominationDocument $document */
        $document = LeadershipNominationDocument::where('nomination_id', $nominationId)->findOrFail($documentId);
        $before = $document->toArray();
        $validUser = $this->resolveValidUserId($userId);

        $document->update([
            'verification_status' => $decision,
            'verification_remarks' => $remarks,
            'verified_by' => $validUser,
            'verified_at' => Carbon::now(),
        ]);

        $this->auditService->log(
            action: 'document.verified',
            entityType: 'LeadershipNominationDocument',
            entityId: $document->id,
            campaignId: $document->nomination->campaign_id,
            beforeData: $before,
            afterData: $document->fresh()->toArray(),
            remarks: "Document {$document->document_type} marked as {$decision}: {$remarks}"
        );

        return $document->fresh();
    }

    /**
     * Request changes on a nomination (F5).
     *
     * @param  array<string>  $requiredChanges
     */
    public function requestChanges(
        string $nominationId,
        string $remarks,
        array $requiredChanges = [],
        ?string $userId = null
    ): LeadershipNomination {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::findOrFail($nominationId);
        $validUser = $this->resolveValidUserId($userId);

        return DB::transaction(function () use ($nomination, $remarks, $requiredChanges, $validUser): LeadershipNomination {
            $oldStatus = $nomination->status;
            $nomination->update([
                'status' => 'changes_requested',
                'review_remarks' => $remarks,
                'reviewed_by' => $validUser,
                'reviewed_at' => Carbon::now(),
            ]);

            LeadershipNominationHistory::create([
                'nomination_id' => $nomination->id,
                'previous_status' => $oldStatus,
                'new_status' => 'changes_requested',
                'action' => 'changes_requested',
                'remarks' => $remarks,
                'changed_by' => $validUser,
                'metadata' => ['required_changes' => $requiredChanges],
            ]);

            $this->auditService->log(
                action: 'nomination.changes_requested',
                entityType: 'LeadershipNomination',
                entityId: $nomination->id,
                campaignId: $nomination->campaign_id,
                remarks: $remarks
            );

            return $nomination->fresh();
        });
    }

    /**
     * Approve nomination (F6).
     */
    public function approveNomination(string $nominationId, string $remarks = '', ?string $userId = null): LeadershipNomination
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::findOrFail($nominationId);
        $validUser = $this->resolveValidUserId($userId);

        $approved = DB::transaction(function () use ($nomination, $remarks, $validUser): LeadershipNomination {
            $oldStatus = $nomination->status;
            $nomination->update([
                'status' => 'approved',
                'review_remarks' => $remarks,
                'reviewed_by' => $validUser,
                'reviewed_at' => Carbon::now(),
            ]);

            LeadershipNominationHistory::create([
                'nomination_id' => $nomination->id,
                'previous_status' => $oldStatus,
                'new_status' => 'approved',
                'action' => 'approved',
                'remarks' => $remarks ?: 'Nomination approved by reviewer',
                'changed_by' => $validUser,
            ]);

            $this->auditService->log(
                action: 'nomination.approved',
                entityType: 'LeadershipNomination',
                entityId: $nomination->id,
                campaignId: $nomination->campaign_id,
                remarks: $remarks ?: 'Approved'
            );

            return $nomination->fresh(['campaign.role', 'scope']);
        });

        // Trigger WhatsApp & Email notifications to candidate
        $this->sendNominationApprovedNotification($approved);

        return $approved;
    }

    /**
     * Reject nomination (F7).
     */
    public function rejectNomination(
        string $nominationId,
        string $reason,
        string $remarks = '',
        ?string $userId = null
    ): LeadershipNomination {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::findOrFail($nominationId);
        $validUser = $this->resolveValidUserId($userId);

        $rejected = DB::transaction(function () use ($nomination, $reason, $remarks, $validUser): LeadershipNomination {
            $oldStatus = $nomination->status;
            $nomination->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
                'review_remarks' => $remarks,
                'reviewed_by' => $validUser,
                'reviewed_at' => Carbon::now(),
            ]);

            LeadershipNominationHistory::create([
                'nomination_id' => $nomination->id,
                'previous_status' => $oldStatus,
                'new_status' => 'rejected',
                'action' => 'rejected',
                'remarks' => "Reason: {$reason}. Remarks: {$remarks}",
                'changed_by' => $validUser,
            ]);

            $this->auditService->log(
                action: 'nomination.rejected',
                entityType: 'LeadershipNomination',
                entityId: $nomination->id,
                campaignId: $nomination->campaign_id,
                remarks: "Rejected: {$reason}"
            );

            return $nomination->fresh(['campaign.role', 'scope']);
        });

        // Trigger WhatsApp & Email notifications to candidate
        $this->sendNominationRejectedNotification($rejected, $reason);

        return $rejected;
    }

    /**
     * Shortlist candidate (F8).
     */
    public function shortlistCandidate(
        string $nominationId,
        string $remarks = '',
        bool $enablePublicVoting = true,
        bool $enableJuryEvaluation = true,
        ?string $userId = null
    ): LeadershipNomination {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::findOrFail($nominationId);
        $validUser = $this->resolveValidUserId($userId);

        return DB::transaction(function () use ($nomination, $remarks, $enablePublicVoting, $enableJuryEvaluation, $validUser): LeadershipNomination {
            $oldStatus = $nomination->status;
            $now = Carbon::now();

            $nomination->update([
                'status' => 'shortlisted',
                'shortlisted_at' => $now,
                'review_remarks' => $remarks,
                'reviewed_by' => $validUser,
                'reviewed_at' => $now,
            ]);

            LeadershipNominationHistory::create([
                'nomination_id' => $nomination->id,
                'previous_status' => $oldStatus,
                'new_status' => 'shortlisted',
                'action' => 'shortlisted',
                'remarks' => $remarks ?: 'Candidate shortlisted for voting and jury evaluation',
                'changed_by' => $validUser,
                'metadata' => [
                    'enable_public_voting' => $enablePublicVoting,
                    'enable_jury_evaluation' => $enableJuryEvaluation,
                ],
            ]);

            $this->auditService->log(
                action: 'nomination.shortlisted',
                entityType: 'LeadershipNomination',
                entityId: $nomination->id,
                campaignId: $nomination->campaign_id,
                remarks: "Shortlisted: {$remarks}"
            );

            return $nomination->fresh();
        });
    }

    protected function resolveValidUserId(?string $userId): ?string
    {
        return ($userId && \App\Models\User::where('id', $userId)->exists()) ? (string) $userId : null;
    }

    /**
     * Get full nomination history (F9).
     *
     * @return Collection<int, LeadershipNominationHistory>
     */
    public function getHistory(string $nominationId): Collection
    {
        return LeadershipNominationHistory::query()
            ->with('actor')
            ->where('nomination_id', $nominationId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Send approval notifications (WhatsApp & Email) with official voting link.
     */
    protected function sendNominationApprovedNotification(LeadershipNomination $nomination): void
    {
        $nomination->loadMissing(['campaign.role', 'scope']);
        $phone = $nomination->mobile;
        $candidateName = $nomination->full_name ?: 'Candidate';
        $campaignName = $nomination->campaign?->name ?? 'Leadership Campaign';
        $roleName = $nomination->campaign?->role?->name ?? 'Leadership Role';
        $appNumber = $nomination->application_number;
        $votingLink = "https://peersglobal.com/leadership/campaigns/{$nomination->campaign_id}/vote?candidate={$nomination->id}";

        // 1. Dispatch WhatsApp message
        if ($phone) {
            try {
                $payload = [
                    'name' => $candidateName,
                    'candidate_name' => $candidateName,
                    'campaign_name' => $campaignName,
                    'role_name' => $roleName,
                    'application_number' => $appNumber,
                    'status' => 'Approved',
                    'voting_link' => $votingLink,
                    'message' => "Congratulations {$candidateName}! Your nomination application ({$appNumber}) for {$roleName} in {$campaignName} has been officially APPROVED. Share your official voting link with peers to cast votes: {$votingLink}",
                ];

                $this->whatsappService->send(
                    templateKey: 'nomination_approved',
                    phone: $phone,
                    payload: $payload,
                    userId: $nomination->user_id
                );
            } catch (\Throwable $e) {
                Log::warning('WhatsApp nomination approval failed: '.$e->getMessage());
            }
        }

        // 2. Dispatch Email with Shareable Voting Link
        if ($nomination->email) {
            try {
                Mail::send([], [], function ($message) use ($nomination, $candidateName, $campaignName, $roleName, $appNumber, $votingLink) {
                    $html = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                        <div style='text-align: center; margin-bottom: 24px;'>
                            <h2 style='color: #0f172a; margin-bottom: 4px;'>Nomination Application Approved</h2>
                            <p style='color: #64748b; font-size: 14px;'>Peers Global Leadership Selection 2026</p>
                        </div>
                        <p style='color: #334155; font-size: 15px;'>Dear <strong>{$candidateName}</strong>,</p>
                        <p style='color: #334155; font-size: 15px; line-height: 1.6;'>
                            We are pleased to inform you that your candidate nomination request for <strong>{$roleName}</strong> in <strong>{$campaignName}</strong> has been officially <strong>APPROVED</strong> by the Scrutiny and Election Governance Committee.
                        </p>
                        <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 16px; margin: 20px 0;'>
                            <p style='margin: 4px 0; color: #475569; font-size: 14px;'><strong>Application Number:</strong> {$appNumber}</p>
                            <p style='margin: 4px 0; color: #475569; font-size: 14px;'><strong>Role:</strong> {$roleName}</p>
                            <p style='margin: 4px 0; color: #475569; font-size: 14px;'><strong>Status:</strong> <span style='color: #16a34a; font-weight: bold;'>Approved</span></p>
                        </div>
                        
                        <div style='background-color: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 18px; margin: 20px 0; text-align: center;'>
                            <h3 style='margin: 0 0 8px 0; color: #1e40af; font-size: 16px;'>Your Official Shareable Voting Link</h3>
                            <p style='color: #475569; font-size: 13px; margin-bottom: 12px;'>Share this link with your network and fellow peers to receive votes:</p>
                            <a href='{$votingLink}' target='_blank' style='display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 10px 22px; border-radius: 6px; font-weight: bold; font-size: 14px;'>Cast / View Voting Booth</a>
                            <p style='margin-top: 10px; font-size: 12px; color: #64748b; word-break: break-all;'>{$votingLink}</p>
                        </div>

                        <p style='color: #334155; font-size: 15px; line-height: 1.6;'>
                            Your profile has been advanced to the voter roster and jury assessment phase. You will receive further updates regarding voter interaction and ballot schedules.
                        </p>
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;' />
                        <p style='color: #94a3b8; font-size: 12px; text-align: center;'>
                            Peers Global Unity Platform &bull; Election Governance Committee
                        </p>
                    </div>";

                    $message->to($nomination->email)
                        ->subject("Your Nomination Request is Approved - Peers Global ({$appNumber})")
                        ->html($html);
                });
            } catch (\Throwable $e) {
                Log::warning('Email nomination approval failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Manually trigger sending approval email and WhatsApp notification to candidate.
     *
     * @return array{nomination_id: string, application_number: string, candidate_name: string, email: string|null, mobile: string|null, voting_link: string, sent_at: string}
     */
    public function sendManualApprovalEmail(string $nominationId): array
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::with(['campaign.role', 'scope'])->findOrFail($nominationId);

        $this->sendNominationApprovedNotification($nomination);

        $votingLink = "https://peersglobal.com/leadership/campaigns/{$nomination->campaign_id}/vote?candidate={$nomination->id}";

        $this->auditService->log(
            action: 'nomination.manual_email_sent',
            entityType: 'LeadershipNomination',
            entityId: $nomination->id,
            campaignId: $nomination->campaign_id,
            remarks: "Approval email and notifications manually dispatched to {$nomination->email}"
        );

        return [
            'nomination_id' => $nomination->id,
            'application_number' => $nomination->application_number,
            'candidate_name' => $nomination->full_name,
            'email' => $nomination->email,
            'mobile' => $nomination->mobile,
            'voting_link' => $votingLink,
            'sent_at' => Carbon::now()->toIso8601String(),
        ];
    }

    protected function sendNominationRejectedNotification(LeadershipNomination $nomination, string $reason): void
    {
        $nomination->loadMissing(['campaign.role', 'scope']);
        $phone = $nomination->mobile;
        $candidateName = $nomination->full_name ?: 'Candidate';
        $campaignName = $nomination->campaign?->name ?? 'Leadership Campaign';
        $roleName = $nomination->campaign?->role?->name ?? 'Leadership Role';
        $appNumber = $nomination->application_number;

        // 1. WhatsApp
        if ($phone) {
            try {
                $payload = [
                    'name' => $candidateName,
                    'candidate_name' => $candidateName,
                    'campaign_name' => $campaignName,
                    'role_name' => $roleName,
                    'application_number' => $appNumber,
                    'status' => 'Rejected',
                    'reason' => $reason,
                    'message' => "Dear {$candidateName}, your nomination application ({$appNumber}) for {$roleName} has not been approved. Reason: {$reason}",
                ];

                $this->whatsappService->send(
                    templateKey: 'nomination_rejected',
                    phone: $phone,
                    payload: $payload,
                    userId: $nomination->user_id
                );
            } catch (\Throwable $e) {
                Log::warning('WhatsApp nomination rejection failed: '.$e->getMessage());
            }
        }

        // 2. Email
        if ($nomination->email) {
            try {
                Mail::send([], [], function ($message) use ($nomination, $candidateName, $campaignName, $roleName, $appNumber, $reason) {
                    $html = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; border: 1px solid #e2e8f0; border-radius: 8px;'>
                        <div style='text-align: center; margin-bottom: 24px;'>
                            <h2 style='color: #0f172a; margin-bottom: 4px;'>Nomination Application Update</h2>
                            <p style='color: #64748b; font-size: 14px;'>Peers Global Leadership Selection 2026</p>
                        </div>
                        <p style='color: #334155; font-size: 15px;'>Dear <strong>{$candidateName}</strong>,</p>
                        <p style='color: #334155; font-size: 15px; line-height: 1.6;'>
                            Thank you for your interest and nomination application for <strong>{$roleName}</strong> in <strong>{$campaignName}</strong>. Following review by the Scrutiny Committee, your application has not been approved at this stage.
                        </p>
                        <div style='background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; padding: 16px; margin: 20px 0;'>
                            <p style='margin: 4px 0; color: #991b1b; font-size: 14px;'><strong>Reason:</strong> {$reason}</p>
                        </div>
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;' />
                        <p style='color: #94a3b8; font-size: 12px; text-align: center;'>
                            Peers Global Unity Platform &bull; Election Governance Committee
                        </p>
                    </div>";

                    $message->to($nomination->email)
                        ->subject("Update on Your Nomination Request - Peers Global ({$appNumber})")
                        ->html($html);
                });
            } catch (\Throwable $e) {
                Log::warning('Email nomination rejection failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Send Form 2 (Jury Evaluation Data) link to nominee and advance status to shortlisted.
     *
     * @return array<string, mixed>
     */
    public function proceedToJury(string $nominationId, ?string $adminRemarks = null): array
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::with(['campaign.role', 'scope', 'user'])->findOrFail($nominationId);

        $fromStatus = $nomination->status;
        if ($nomination->status !== 'shortlisted') {
            $nomination->status = 'shortlisted';
            $nomination->shortlisted_at = Carbon::now();
        }

        $form2Link = url("/leadership/form-2/{$nomination->id}");

        $snapshot = (array) ($nomination->profile_snapshot ?? []);
        $snapshot['form_2_link'] = $form2Link;
        $snapshot['form_2_link_sent_at'] = Carbon::now()->toIso8601String();
        $snapshot['form_2_status'] = $snapshot['form_2_status'] ?? 'pending_submission';
        if ($adminRemarks) {
            $snapshot['jury_proceed_remarks'] = $adminRemarks;
        }

        $nomination->profile_snapshot = $snapshot;
        $nomination->save();

        LeadershipNominationHistory::create([
            'id' => (string) Str::uuid(),
            'nomination_id' => $nomination->id,
            'actor_id' => $this->resolveValidUserId(Auth::id()),
            'action' => 'proceed_to_jury',
            'from_status' => $fromStatus,
            'to_status' => 'shortlisted',
            'remarks' => $adminRemarks ?? 'Candidate proceeded to Jury Evaluation phase and Form 2 link dispatched.',
            'metadata' => [
                'form_2_link' => $form2Link,
                'email' => $nomination->email,
            ],
        ]);

        $this->sendJuryForm2Notification($nomination, $form2Link);

        $this->auditService->log(
            action: 'nomination.proceed_to_jury',
            entityType: 'LeadershipNomination',
            entityId: $nomination->id,
            campaignId: $nomination->campaign_id,
            remarks: "Candidate proceeded to Jury; Form 2 link sent to {$nomination->email}"
        );

        return [
            'nomination_id' => $nomination->id,
            'application_number' => $nomination->application_number,
            'candidate_name' => $nomination->candidate_name,
            'email' => $nomination->email,
            'mobile' => $nomination->mobile,
            'form_2_link' => $form2Link,
            'status' => $nomination->status,
            'sent_at' => Carbon::now()->toIso8601String(),
        ];
    }

    /**
     * Batch dispatch proceed to jury links.
     *
     * @param  array<int, string>  $nominationIds
     * @return array<string, mixed>
     */
    public function batchProceedToJury(array $nominationIds, ?string $adminRemarks = null): array
    {
        $successful = [];
        $failed = [];

        foreach ($nominationIds as $id) {
            try {
                $successful[] = $this->proceedToJury((string) $id, $adminRemarks);
            } catch (\Throwable $e) {
                $failed[] = [
                    'id' => $id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'total' => count($nominationIds),
            'processed_count' => count($successful),
            'failed_count' => count($failed),
            'successful' => $successful,
            'failed' => $failed,
        ];
    }

    /**
     * Send Jury Form 2 notification via WhatsApp and Email.
     */
    protected function sendJuryForm2Notification(LeadershipNomination $nomination, string $form2Link): void
    {
        $nomination->loadMissing(['campaign.role', 'scope']);
        $phone = $nomination->mobile;
        $candidateName = $nomination->candidate_name ?: 'Candidate';
        $campaignName = $nomination->campaign?->name ?? 'Leadership Selection 2026';
        $roleName = $nomination->campaign?->role?->name ?? 'Leadership Role';
        $appNumber = $nomination->application_number;

        // 1. WhatsApp notification
        if ($phone) {
            try {
                $payload = [
                    'name' => $candidateName,
                    'candidate_name' => $candidateName,
                    'campaign_name' => $campaignName,
                    'role_name' => $roleName,
                    'application_number' => $appNumber,
                    'form_2_link' => $form2Link,
                    'message' => "Congratulations {$candidateName}! You have been shortlisted for the Jury Evaluation phase for {$roleName} in {$campaignName}. Please complete your detailed Form 2 (Jury Evaluation Data Form): {$form2Link}",
                ];

                $this->whatsappService->send(
                    templateKey: 'nomination_proceed_jury',
                    phone: $phone,
                    payload: $payload,
                    userId: $nomination->user_id
                );
            } catch (\Throwable $e) {
                Log::warning('WhatsApp Form 2 notification failed: '.$e->getMessage());
            }
        }

        // 2. Email notification
        if ($nomination->email) {
            try {
                Mail::send([], [], function ($message) use ($nomination, $candidateName, $campaignName, $roleName, $appNumber, $form2Link): void {
                    $html = "
                    <div style='font-family: Arial, sans-serif; max-width: 620px; margin: 0 auto; padding: 28px; border: 1px solid #e2e8f0; border-radius: 12px; background-color: #ffffff;'>
                        <div style='text-align: center; margin-bottom: 24px;'>
                            <h2 style='color: #0f172a; margin-bottom: 4px; font-size: 22px;'>Shortlisted for Jury Evaluation</h2>
                            <p style='color: #64748b; font-size: 14px; margin-top: 0;'>Peers Global Leadership Selection 2026</p>
                        </div>
                        <p style='color: #334155; font-size: 15px;'>Dear <strong>{$candidateName}</strong>,</p>
                        <p style='color: #334155; font-size: 15px; line-height: 1.6;'>
                            Congratulations! Your candidacy for <strong>{$roleName}</strong> in <strong>{$campaignName}</strong> has been <strong>SHORTLISTED</strong> by the Scrutiny and Governance Committee to proceed directly to the <strong>Jury Evaluation & Interview Phase</strong>.
                        </p>
                        <div style='background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin: 20px 0;'>
                            <p style='margin: 4px 0; color: #475569; font-size: 14px;'><strong>Application Number:</strong> {$appNumber}</p>
                            <p style='margin: 4px 0; color: #475569; font-size: 14px;'><strong>Position:</strong> {$roleName}</p>
                            <p style='margin: 4px 0; color: #475569; font-size: 14px;'><strong>Status:</strong> <span style='color: #2563eb; font-weight: bold;'>Shortlisted for Jury</span></p>
                        </div>
                        
                        <div style='background: linear-gradient(135deg, #eff6ff 0%, #f0fdf4 100%); border: 1px solid #bfdbfe; border-radius: 10px; padding: 22px; margin: 24px 0; text-align: center;'>
                            <h3 style='margin: 0 0 8px 0; color: #1e3a8a; font-size: 17px;'>Action Required: Complete Form 2 (Jury Evaluation Data)</h3>
                            <p style='color: #475569; font-size: 13.5px; margin-bottom: 16px; line-height: 1.5;'>
                                The Jury Panel requires detailed operational and bench data (business credibility, track record, leadership bench, and 12-month vision) to score your candidacy.
                            </p>
                            <a href='{$form2Link}' target='_blank' style='display: inline-block; background-color: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: bold; font-size: 15px; box-shadow: 0 2px 4px rgba(37,99,235,0.2);'>Open Form 2: Jury Evaluation Form &rarr;</a>
                            <p style='margin-top: 14px; font-size: 12px; color: #64748b;'>
                                Or open directly in browser: <br/><a href='{$form2Link}' style='color: #2563eb; word-break: break-all;'>{$form2Link}</a>
                            </p>
                        </div>

                        <div style='background-color: #fffbeb; border: 1px solid #fef3c7; border-radius: 8px; padding: 14px; margin: 20px 0;'>
                            <p style='margin: 0; color: #92400e; font-size: 13px; line-height: 1.5;'>
                                <strong>Important Security Note:</strong> When you open the form, you will be prompted to enter your registered email address (<code>{$nomination->email}</code>) to verify your identity and prevent application mismatch.
                            </p>
                        </div>

                        <p style='color: #334155; font-size: 14.5px; line-height: 1.6;'>
                            Please complete Form 2 at your earliest convenience so your file can be scheduled for Jury Board review.
                        </p>
                        <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 24px 0;' />
                        <p style='color: #94a3b8; font-size: 12px; text-align: center; margin: 0;'>
                            Peers Global Unity Platform &bull; Scrutiny & Election Governance Committee
                        </p>
                    </div>";

                    $message->to($nomination->email)
                        ->subject("Shortlisted: Please Complete Form 2 (Jury Evaluation Data) - Peers Global ({$appNumber})")
                        ->html($html);
                });
            } catch (\Throwable $e) {
                Log::warning('Email Form 2 dispatch failed: '.$e->getMessage());
            }
        }
    }

    /**
     * Submit Form 2 (Jury Evaluation Data Form) filled by nominee.
     *
     * @param  array<string, mixed>  $payload
     */
    public function submitForm2(string $nominationId, array $payload): LeadershipNomination
    {
        /** @var LeadershipNomination $nomination */
        $nomination = LeadershipNomination::with(['campaign.role', 'scope', 'user'])->findOrFail($nominationId);

        // Prevent mismatch: verify email address
        $submittedEmail = trim((string) ($payload['email'] ?? ''));
        $nomineeEmail = trim((string) ($nomination->email ?? ''));

        if (strtolower($submittedEmail) !== strtolower($nomineeEmail)) {
            throw new \InvalidArgumentException("Email mismatch: The entered email ({$submittedEmail}) does not match the nominee application email ({$nomineeEmail}).");
        }

        return DB::transaction(function () use ($nomination, $payload): LeadershipNomination {
            $f2AppNumber = $nomination->application_number.'-F2';

            // Find existing Sub Form 2 nomination or create new
            $subNomination = LeadershipNomination::where('application_number', $f2AppNumber)
                ->orWhere(function ($q) use ($nomination): void {
                    $q->where('campaign_id', $nomination->campaign_id)
                        ->where('profile_snapshot->parent_nomination_id', $nomination->id);
                })
                ->first();

            $f2Data = [
                'form_type' => 'sub_form_2',
                'parent_nomination_id' => $nomination->id,
                'parent_application_number' => $nomination->application_number,
                'candidate_name' => $nomination->candidate_name,
                'applied_role_name' => $nomination->campaign?->role?->name ?? 'Leadership Role',
                'campaign_name' => $nomination->campaign?->name,
                'email' => $nomination->email,
                'mobile' => $nomination->mobile,
                'submitted_at' => Carbon::now()->toIso8601String(),
                'form_2' => $payload,
            ];

            if ($subNomination) {
                $subNomination->profile_snapshot = $f2Data;
                $subNomination->status = 'submitted';
                $subNomination->submitted_at = Carbon::now();
                if (\Illuminate\Support\Facades\Schema::hasColumn('leadership_nominations', 'form_type')) {
                    $subNomination->form_type = 'sub_form_2';
                }
                $subNomination->save();
            } else {
                $subNomination = new LeadershipNomination;
                $subNomination->id = (string) Str::uuid();
                $subNomination->campaign_id = $nomination->campaign_id;
                $subNomination->scope_id = $nomination->scope_id;
                $subNomination->user_id = $nomination->user_id;
                $subNomination->application_number = $f2AppNumber;
                $subNomination->full_name = $nomination->full_name;
                $subNomination->email = $nomination->email;
                $subNomination->mobile = $nomination->mobile;
                $subNomination->status = 'submitted';
                $subNomination->submitted_at = Carbon::now();
                $subNomination->profile_snapshot = $f2Data;
                if (\Illuminate\Support\Facades\Schema::hasColumn('leadership_nominations', 'form_type')) {
                    $subNomination->form_type = 'sub_form_2';
                }
                $subNomination->save();
            }

            // Update parent nomination status and snapshot
            $parentSnapshot = (array) ($nomination->profile_snapshot ?? []);
            $parentSnapshot['form_2_status'] = 'submitted';
            $parentSnapshot['form_2_submitted_at'] = Carbon::now()->toIso8601String();
            $parentSnapshot['form_2_sub_nomination_id'] = $subNomination->id;
            $parentSnapshot['form_2'] = $payload;

            $nomination->profile_snapshot = $parentSnapshot;
            $nomination->save();

            LeadershipNominationHistory::create([
                'id' => (string) Str::uuid(),
                'nomination_id' => $nomination->id,
                'actor_id' => $this->resolveValidUserId($nomination->user_id),
                'action' => 'form_2_submitted',
                'from_status' => $nomination->status,
                'to_status' => $nomination->status,
                'remarks' => 'Nominee successfully submitted Form 2: Jury Evaluation Data Form.',
                'metadata' => [
                    'sub_nomination_id' => $subNomination->id,
                    'f2_application_number' => $f2AppNumber,
                ],
            ]);

            $this->auditService->log(
                action: 'nomination.form_2_submitted',
                entityType: 'LeadershipNomination',
                entityId: $subNomination->id,
                campaignId: $nomination->campaign_id,
                remarks: "Form 2 submitted by {$nomination->email} (App: {$f2AppNumber})"
            );

            return $subNomination;
        });
    }
}
