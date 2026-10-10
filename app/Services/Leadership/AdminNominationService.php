<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipNomination;
use App\Models\Leadership\LeadershipNominationDocument;
use App\Models\Leadership\LeadershipNominationHistory;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Notifications\WhatsappNotificationService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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
            ->with(['campaign.role', 'scope'])
            ->withCount([
                'documents as documents_pending' => fn ($q) => $q->where('verification_status', 'pending'),
            ]);

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (! empty($filters['scope_id'])) {
            $query->where('scope_id', $filters['scope_id']);
        }

        if (! empty($filters['role_id'])) {
            $query->whereHas('campaign', fn ($q) => $q->where('role_id', $filters['role_id']));
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
    protected function sendNominationApprovedNotification(LeadershipNomination $nomination): void
    {
        $nomination->loadMissing(['campaign.role', 'scope']);
        $phone = $nomination->mobile;
        $candidateName = $nomination->full_name ?: 'Candidate';
        $campaignName = $nomination->campaign?->name ?? 'Leadership Campaign';
        $roleName = $nomination->campaign?->role?->name ?? 'Leadership Role';
        $appNumber = $nomination->application_number;

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
                    'message' => "Congratulations {$candidateName}! Your nomination application ({$appNumber}) for {$roleName} in {$campaignName} has been officially APPROVED by the Election Governance Committee.",
                ];

                $this->whatsappService->send(
                    templateKey: 'nomination_approved',
                    phone: $phone,
                    payload: $payload,
                    userId: $nomination->user_id
                );
            } catch (\Throwable $e) {
                Log::warning('WhatsApp nomination approval failed: ' . $e->getMessage());
            }
        }

        // 2. Dispatch Email
        if ($nomination->email) {
            try {
                Mail::send([], [], function ($message) use ($nomination, $candidateName, $campaignName, $roleName, $appNumber) {
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
                Log::warning('Email nomination approval failed: ' . $e->getMessage());
            }
        }
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
                Log::warning('WhatsApp nomination rejection failed: ' . $e->getMessage());
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
                Log::warning('Email nomination rejection failed: ' . $e->getMessage());
            }
        }
    }
}
