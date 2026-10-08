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
use Illuminate\Support\Facades\Storage;

class AdminNominationService
{
    public function __construct(
        protected AuditService $auditService
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

        return DB::transaction(function () use ($nomination, $remarks, $validUser): LeadershipNomination {
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

            return $nomination->fresh();
        });
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

        return DB::transaction(function () use ($nomination, $reason, $remarks, $validUser): LeadershipNomination {
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

            return $nomination->fresh();
        });
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
}
