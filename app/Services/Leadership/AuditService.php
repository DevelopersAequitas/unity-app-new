<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log an administrative or critical system action.
     *
     * @param  array<string, mixed>|null  $beforeData
     * @param  array<string, mixed>|null  $afterData
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        string $entityType,
        ?string $entityId = null,
        ?string $campaignId = null,
        ?array $beforeData = null,
        ?array $afterData = null,
        ?string $remarks = null,
        array $metadata = [],
        ?Request $request = null
    ): LeadershipAuditLog {
        $rawActorId = Auth::id() ?? (Auth::guard('admin')->id() ?? Auth::guard('sanctum')->id());
        $actorId = ($rawActorId && \App\Models\User::where('id', $rawActorId)->exists()) ? (string) $rawActorId : null;

        $ipAddress = null;
        $userAgent = null;

        if ($request) {
            $ipAddress = $request->ip();
            $userAgent = $request->userAgent();
        } elseif (request()) {
            $ipAddress = request()->ip();
            $userAgent = request()->userAgent();
        }

        return LeadershipAuditLog::create([
            'campaign_id' => $campaignId,
            'actor_user_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'before_data' => $beforeData,
            'after_data' => $afterData,
            'remarks' => $remarks,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'metadata' => $metadata,
        ]);
    }
}
