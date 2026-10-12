<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipNotificationLog;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use RuntimeException;

class NotificationService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List notification delivery logs (L1).
     *
     * @param  array<string, mixed>  $filters
     */
    public function listLogs(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = LeadershipNotificationLog::query()
            ->with(['campaign', 'nomination', 'recipientUser']);

        if (! empty($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', $filters['channel']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_date'])) {
            $query->where('created_at', '>=', $filters['from_date']);
        }

        if (! empty($filters['to_date'])) {
            $query->where('created_at', '<=', $filters['to_date']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * View notification details (L2).
     */
    public function getLogDetails(string $id): LeadershipNotificationLog
    {
        /** @var LeadershipNotificationLog $log */
        $log = LeadershipNotificationLog::with(['campaign', 'nomination', 'recipientUser'])->findOrFail($id);

        return $log;
    }

    /**
     * Retry failed notification (L3).
     */
    public function retryNotification(string $id): LeadershipNotificationLog
    {
        /** @var LeadershipNotificationLog $log */
        $log = LeadershipNotificationLog::findOrFail($id);

        if ($log->status !== 'failed') {
            throw new RuntimeException('Only failed notifications can be retried.');
        }

        $log->increment('attempt_count');
        $log->update([
            'status' => 'queued',
            'sent_at' => Carbon::now(),
            'error_message' => null,
        ]);

        $this->auditService->log(
            action: 'notification.retried',
            entityType: 'LeadershipNotificationLog',
            entityId: $log->id,
            campaignId: $log->campaign_id,
            remarks: "Notification {$log->id} re-queued for delivery"
        );

        return $log->fresh();
    }
}
