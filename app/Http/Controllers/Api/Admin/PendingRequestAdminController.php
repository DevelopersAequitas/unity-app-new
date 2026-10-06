<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Admin\PendingRequestApproveRequest;
use App\Http\Requests\Admin\PendingRequestListRequest;
use App\Http\Requests\Admin\PendingRequestRejectRequest;
use App\Models\AdminUser;
use App\Models\User;
use App\Services\Admin\AdminScopeService;
use App\Services\Admin\PendingRequestModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PendingRequestAdminController extends BaseApiController
{
    public function __construct(
        private readonly PendingRequestModerationService $service,
        private readonly AdminScopeService $scopeService,
    ) {}

    /**
     * Display a consolidated listing of pending platform requests across Circle Join Requests,
     * Membership Upgrades, and Proofs with multi-criteria filtering, search, and server-side pagination.
     */
    public function index(PendingRequestListRequest $request): JsonResponse
    {
        $this->ensureCanModerate($request->user());

        $result = $this->service->listPendingRequests($request->validated(), $request->user());

        return $this->success($result);
    }

    /**
     * Return aggregated count metrics for pending platform requests.
     */
    public function count(Request $request): JsonResponse
    {
        $this->ensureCanModerate($request->user());

        $counts = $this->service->getCounts($request->user());

        return $this->success($counts, 'Pending request counts loaded.');
    }

    /**
     * Approve a pending moderation request by ID.
     */
    public function approve(PendingRequestApproveRequest $request, string $id): JsonResponse
    {
        $this->ensureCanModerate($request->user());

        try {
            $result = $this->service->approve($id, $request->validated(), $request->user(), $request);

            return $this->success($result, 'Pending request approved successfully.');
        } catch (NotFoundHttpException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to approve request: '.$e->getMessage(), 500);
        }
    }

    /**
     * Reject a pending moderation request by ID.
     */
    public function reject(PendingRequestRejectRequest $request, string $id): JsonResponse
    {
        $this->ensureCanModerate($request->user());

        try {
            $result = $this->service->reject($id, $request->validated(), $request->user(), $request);

            return $this->success($result, 'Pending request rejected successfully.');
        } catch (NotFoundHttpException $e) {
            return $this->error($e->getMessage(), 404);
        } catch (ValidationException $e) {
            return $this->error($e->getMessage(), 422, $e->errors());
        } catch (\Throwable $e) {
            return $this->error('Failed to reject request: '.$e->getMessage(), 500);
        }
    }

    private function ensureCanModerate(User|AdminUser|null $user): void
    {
        abort_unless($user !== null, 401, 'Unauthenticated.');

        if ($user instanceof AdminUser) {
            return;
        }

        if ($this->scopeService->isGlobal($user)) {
            return;
        }

        $roles = $this->scopeService->roleKeys($user);
        $allowed = ['global_admin', 'super_admin', 'admin', 'director', 'circle_leader', 'industry_director', 'moderator'];
        if (array_intersect($roles, $allowed) !== []) {
            return;
        }

        if ($user->roles()->exists()) {
            return;
        }

        abort(403, 'Unauthorized. Moderator or administrator privileges required.');
    }
}
