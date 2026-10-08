<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Models\Leadership\LeadershipAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends LeadershipBaseController
{
    /**
     * L4. Search audit history.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LeadershipAuditLog::query()->with('actor');

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->query('campaign_id'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->query('action'));
        }

        if ($request->filled('entity_type')) {
            $query->where('entity_type', $request->query('entity_type'));
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', $request->query('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', $request->query('to_date'));
        }

        $perPage = (int) $request->query('per_page', 20);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return $this->paginate($logs, 'Audit logs fetched successfully.');
    }

    /**
     * L5. View campaign-specific audit history.
     */
    public function campaignLogs(Request $request, string $id): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $logs = LeadershipAuditLog::query()
            ->with('actor')
            ->where('campaign_id', $id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->paginate($logs, 'Campaign audit history fetched successfully.');
    }
}
