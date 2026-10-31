<?php

declare(strict_types=1);

namespace App\Modules\Leadership\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Shared\OrganizationalScope\OrganizationalScopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class LeadershipEventsController extends BaseApiController
{
    public function __construct(
        private readonly OrganizationalScopeService $scopeService,
    ) {}

    /**
     * API 49: Scoped Events & Attendance Summary.
     * GET /api/v1/leadership/events
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $circleId = $request->query('circle_id') ? (string) $request->query('circle_id') : null;

        if ($circleId && Str::isUuid($circleId) && ! $this->scopeService->canAccessCircle($user, $circleId)) {
            return $this->error('You do not have organizational scope access to view events for this circle.', 403);
        }

        if (! Schema::hasTable('events')) {
            return $this->success(['total' => 0, 'items' => []], 'Scoped events fetched successfully.');
        }

        $scope = $this->scopeService->resolveForActor($user, 'events');
        $query = DB::table('events');

        if (Schema::hasColumn('events', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if ($circleId && Str::isUuid($circleId) && Schema::hasColumn('events', 'circle_id')) {
            $query->where('circle_id', $circleId);
        } elseif (! $scope['is_global'] && Schema::hasColumn('events', 'circle_id')) {
            $query->where(function ($q) use ($scope): void {
                if (! empty($scope['circle_ids'])) {
                    $q->whereIn('circle_id', $scope['circle_ids']);
                }
                $q->orWhereNull('circle_id');
            });
        }

        $events = $query->orderByDesc('created_at')->limit(50)->get()->map(function ($e): array {
            $rsvpCount = Schema::hasTable('event_rsvps')
                ? DB::table('event_rsvps')->where('event_id', $e->id)->count()
                : 0;

            return [
                'id' => (string) $e->id,
                'title' => (string) ($e->title ?? 'Circle Event'),
                'description' => $e->description ?? null,
                'circle_id' => $e->circle_id ?? null,
                'starts_at' => $e->starts_at ?? null,
                'ends_at' => $e->ends_at ?? null,
                'location' => $e->location ?? $e->venue ?? null,
                'rsvps_count' => $rsvpCount,
                'created_at' => $e->created_at ?? null,
            ];
        })->all();

        return $this->success([
            'total' => count($events),
            'items' => $events,
        ], 'Scoped events fetched successfully.');
    }
}

