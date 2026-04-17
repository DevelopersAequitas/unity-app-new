<?php

namespace App\Services\Impacts;

use App\Models\AdminUser;
use App\Models\Impact;
use App\Models\LifeImpactHistory;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ImpactService
{
    public function __construct(
        private readonly ImpactUserNotificationService $notificationService,
        private readonly ImpactEmailService $emailService,
    ) {
    }

    public function submitImpact(User $user, array $data): Impact
    {
        $impact = Impact::create([
            'user_id' => $user->id,
            'impacted_peer_id' => $data['impacted_peer_id'],
            'impact_date' => $data['date'] ?? now()->toDateString(),
            'action' => $data['action'],
            'story_to_share' => $data['story_to_share'],
            'life_impacted' => max(1, (int) ($data['life_impacted'] ?? 1)),
            'additional_remarks' => $data['additional_remarks'] ?? null,
            'requires_leadership_approval' => (bool) config('impact.requires_leadership_approval', true),
            'status' => 'pending',
        ]);

        Log::info('impact.submitted', [
            'impact_id' => (string) $impact->id,
            'user_id' => (string) $user->id,
            'impacted_peer_id' => (string) $impact->impacted_peer_id,
        ]);

        $impact->loadMissing(['user', 'impactedPeer']);

        $this->notificationService->sendSubmitted($impact);
        $this->emailService->sendSubmitted($impact);

        return $impact;
    }

    public function approveImpact(Impact|string $impactOrId, User|AdminUser|string $adminOrId, ?string $reviewRemarks = null): Impact
    {
        return DB::transaction(function () use ($impactOrId, $adminOrId, $reviewRemarks) {
            $impactId = $impactOrId instanceof Impact ? (string) $impactOrId->getKey() : (string) $impactOrId;
            $adminId = $this->resolveAdminId($adminOrId);

            $impact = Impact::query()->with('user')->lockForUpdate()->findOrFail($impactId);

            Log::info('impact.approve.started', [
                'impact_id' => (string) $impact->id,
                'old_status' => (string) $impact->status,
                'admin_id' => $adminId,
            ]);

            if ($impact->status !== 'pending') {
                if ($impact->status === 'approved') {
                    return $impact->fresh(['user', 'impactedPeer']);
                }

                throw new \RuntimeException('Only pending impacts can be approved.');
            }

            $impact->status = 'approved';
            $impact->approved_by = $adminId;
            $impact->approved_at = now();
            $impact->timeline_posted_at = now();
            $impact->rejected_by = null;
            $impact->rejected_at = null;
            $impact->review_remarks = $reviewRemarks;
            $impact->save();

            $this->storeApprovedImpactHistory($impact, $adminId, $reviewRemarks);

            Log::info('impact.approve.saved', [
                'impact_id' => (string) $impact->id,
                'status' => (string) $impact->status,
                'approved_by' => (string) $impact->approved_by,
                'approved_at' => optional($impact->approved_at)->toISOString(),
                'timeline_posted_at' => optional($impact->timeline_posted_at)->toISOString(),
            ]);

            $incrementBy = max(1, (int) ($impact->life_impacted ?? 1));
            $recalculatedTotal = $this->recalculateUserLifeImpactedCount((string) $impact->user_id);

            Log::info('impact.approved', [
                'impact_id' => (string) $impact->id,
                'user_id' => (string) $impact->user_id,
                'approved_by' => $adminId,
            ]);

            Log::info('impact.life_impacted_incremented', [
                'impact_id' => (string) $impact->id,
                'user_id' => (string) $impact->user_id,
                'incremented_by' => $incrementBy,
                'recalculated_total' => $recalculatedTotal,
            ]);

            $impact = $impact->fresh(['user', 'impactedPeer']);

            DB::afterCommit(function () use ($impact): void {
                try {
                    $this->notificationService->sendApproved($impact);
                    $this->emailService->sendApproved($impact);
                } catch (\Throwable $exception) {
                    Log::error('impact.approve.side_effect_failed', [
                        'impact_id' => (string) $impact->id,
                        'user_id' => (string) $impact->user_id,
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

            Log::info('impact.approve.completed', [
                'impact_id' => (string) $impact->id,
                'final_status' => (string) $impact->status,
            ]);

            return $impact;
        });
    }

    public function rejectImpact(Impact|string $impactOrId, User|AdminUser|string $adminOrId, ?string $reviewRemarks = null): Impact
    {
        return DB::transaction(function () use ($impactOrId, $adminOrId, $reviewRemarks) {
            $impactId = $impactOrId instanceof Impact ? (string) $impactOrId->getKey() : (string) $impactOrId;
            $adminId = $this->resolveAdminId($adminOrId);

            $impact = Impact::query()->lockForUpdate()->findOrFail($impactId);

            if ($impact->status === 'rejected') {
                return $impact;
            }

            if ($impact->status === 'approved') {
                throw new \RuntimeException('Approved impact cannot be rejected.');
            }

            $impact->status = 'rejected';
            $impact->rejected_by = $adminId;
            $impact->rejected_at = now();
            $impact->approved_by = null;
            $impact->approved_at = null;
            $impact->timeline_posted_at = null;
            $impact->review_remarks = $reviewRemarks;
            $impact->save();

            Log::info('impact.rejected', [
                'impact_id' => (string) $impact->id,
                'user_id' => (string) $impact->user_id,
                'rejected_by' => $adminId,
            ]);

            $this->notify((string) $impact->user_id, 'impact_rejected', [
                'impact_id' => (string) $impact->id,
                'title' => 'Impact rejected',
                'body' => 'Your impact was reviewed and rejected.',
                'review_remarks' => $reviewRemarks,
            ]);

            return $impact;
        });
    }

    private function resolveAdminId(User|AdminUser|string $adminOrId): string
    {
        if ($adminOrId instanceof User || $adminOrId instanceof AdminUser) {
            return (string) $adminOrId->getKey();
        }

        return (string) $adminOrId;
    }

    public function recalculateUserLifeImpactedCount(User|string $userOrId): int
    {
        $userId = $userOrId instanceof User ? (string) $userOrId->id : (string) $userOrId;

        $sum = (int) Impact::query()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->sum(DB::raw('COALESCE(life_impacted, 1)'));

        User::query()
            ->where('id', $userId)
            ->update(['life_impacted_count' => $sum]);

        return $sum;
    }

    private function notify(string $userId, string $type, array $payload): void
    {
        Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'payload' => $payload,
            'is_read' => false,
            'created_at' => now(),
            'read_at' => null,
        ]);
    }

    private function storeApprovedImpactHistory(Impact $impact, string $adminId, ?string $reviewRemarks = null): void
    {
        $alreadyExists = LifeImpactHistory::query()
            ->where('activity_id', (string) $impact->id)
            ->where('activity_type', 'impact')
            ->exists();

        if ($alreadyExists) {
            Log::info('impact.approve.history_exists', [
                'impact_id' => (string) $impact->id,
                'user_id' => (string) $impact->user_id,
                'admin_id' => $adminId,
            ]);

            return;
        }

        $history = LifeImpactHistory::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => (string) $impact->user_id,
            'triggered_by_user_id' => $adminId,
            'activity_type' => 'impact',
            'activity_id' => (string) $impact->id,
            'impact_value' => max(1, (int) ($impact->life_impacted ?? 1)),
            'title' => (string) $impact->action,
            'description' => $impact->additional_remarks ?: $reviewRemarks,
            'meta' => [
                'source' => 'impact_approval',
                'impact_id' => (string) $impact->id,
                'impact_date' => optional($impact->impact_date)?->toDateString(),
                'impacted_peer_id' => (string) ($impact->impacted_peer_id ?? ''),
                'story_to_share' => $impact->story_to_share,
                'additional_remarks' => $impact->additional_remarks,
                'review_remarks' => $reviewRemarks,
                'requires_leadership_approval' => (bool) $impact->requires_leadership_approval,
                'status' => (string) $impact->status,
                'approved_at' => optional($impact->approved_at)?->toISOString(),
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Log::info('impact.approve.history_created', [
            'impact_id' => (string) $impact->id,
            'user_id' => (string) $impact->user_id,
            'admin_id' => $adminId,
            'history_id' => (string) $history->id,
        ]);
    }
}
