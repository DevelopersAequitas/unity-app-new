<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\System\StoreAdminIpAllowlistRequest;
use App\Http\Requests\Admin\System\UpdateAdminIpAccessEnforcementRequest;
use App\Models\AdminIpAllowlist;
use App\Policies\AdminIpAccessPolicy;
use App\Services\Admin\Auth\AdminIpAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminIpAccessController extends Controller
{
    public function __construct(
        private readonly AdminIpAccessService $adminIpAccessService,
        private readonly AdminIpAccessPolicy $adminIpAccessPolicy
    ) {}

    /**
     * Public, unauthenticated endpoint returning the login policy for the requesting client IP.
     * Never exposes the allowlist or sensitive configuration.
     */
    public function loginPolicy(Request $request): JsonResponse
    {
        $policy = $this->adminIpAccessService->evaluateLoginPolicy($request);

        return response()
            ->json([
                'success' => true,
                'message' => 'Login policy retrieved successfully.',
                'data' => $policy,
            ], 200)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, private, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0')
            ->header('Vary', 'X-Forwarded-For, Forwarded');
    }

    /**
     * Retrieve the full IP Access configuration for the current environment.
     */
    public function show(Request $request): JsonResponse
    {
        if (! $this->adminIpAccessPolicy->view($request->user())) {
            return $this->forbiddenResponse();
        }

        $data = $this->adminIpAccessService->getConfiguration($request);

        return response()->json([
            'success' => true,
            'message' => 'IP Access configuration retrieved successfully.',
            'data' => $data,
        ], 200);
    }

    /**
     * List all configured IP addresses for the current environment.
     */
    public function listIps(Request $request): JsonResponse
    {
        if (! $this->adminIpAccessPolicy->view($request->user())) {
            return $this->forbiddenResponse();
        }

        $ips = $this->adminIpAccessService->listAllowedIps()
            ->map(fn (AdminIpAllowlist $item): array => $this->adminIpAccessService->formatAllowlistItem($item))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'Authorized IP addresses retrieved successfully.',
            'data' => [
                'environment' => $this->adminIpAccessService->resolveEnvironment(),
                'ip_access_enabled' => $this->adminIpAccessService->isEnforcementEnabled(),
                'allowlist' => $ips,
            ],
        ], 200);
    }

    /**
     * Add a new authorized IPv4 or IPv6 address.
     */
    public function storeIp(StoreAdminIpAllowlistRequest $request): JsonResponse
    {
        if (! $this->adminIpAccessPolicy->create($request->user())) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validated();
        $actor = $this->adminIpAccessPolicy->resolveActorMetadata($request->user());
        $requestIp = $this->adminIpAccessService->resolveClientIp($request);

        $result = $this->adminIpAccessService->addAllowedIp(
            rawIp: (string) $validated['ip_address'],
            label: isset($validated['label']) ? (string) $validated['label'] : null,
            actor: $actor,
            requestIp: $requestIp,
            userAgent: $request->userAgent()
        );

        $payload = [
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
        ];

        if (isset($result['error_code'])) {
            $payload['error_code'] = $result['error_code'];
        }

        if (isset($result['errors'])) {
            $payload['errors'] = $result['errors'];
        }

        return response()->json($payload, $result['status']);
    }

    /**
     * Remove an authorized IP address by ID.
     */
    public function destroyIp(Request $request, string $id): JsonResponse
    {
        if (! $this->adminIpAccessPolicy->delete($request->user())) {
            return $this->forbiddenResponse();
        }

        $confirmLastIpRemoval = $request->boolean('confirm_last_ip_removal')
            || $request->boolean('confirm')
            || $request->boolean('force');

        $actor = $this->adminIpAccessPolicy->resolveActorMetadata($request->user());
        $requestIp = $this->adminIpAccessService->resolveClientIp($request);

        $result = $this->adminIpAccessService->removeAllowedIp(
            id: $id,
            confirmLastIpRemoval: $confirmLastIpRemoval,
            actor: $actor,
            requestIp: $requestIp,
            userAgent: $request->userAgent()
        );

        $payload = [
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
        ];

        if (isset($result['error_code'])) {
            $payload['error_code'] = $result['error_code'];
        }

        if (isset($result['requires_confirmation'])) {
            $payload['requires_confirmation'] = $result['requires_confirmation'];
        }

        if (! empty($result['warning'])) {
            $payload['warning'] = $result['warning'];
        }

        return response()->json($payload, $result['status']);
    }

    /**
     * Enable or disable IP-based password login enforcement.
     */
    public function updateEnforcement(UpdateAdminIpAccessEnforcementRequest $request): JsonResponse
    {
        if (! $this->adminIpAccessPolicy->update($request->user())) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validated();
        $actor = $this->adminIpAccessPolicy->resolveActorMetadata($request->user());
        $requestIp = $this->adminIpAccessService->resolveClientIp($request);

        $result = $this->adminIpAccessService->updateEnforcement(
            enabled: (bool) $validated['ip_access_enabled'],
            confirmEmptyAllowlist: $request->confirmedEmptyAllowlist(),
            actor: $actor,
            requestIp: $requestIp,
            userAgent: $request->userAgent()
        );

        $payload = [
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
        ];

        if (isset($result['error_code'])) {
            $payload['error_code'] = $result['error_code'];
        }

        if (isset($result['requires_confirmation'])) {
            $payload['requires_confirmation'] = $result['requires_confirmation'];
        }

        if (! empty($result['warning'])) {
            $payload['warning'] = $result['warning'];
        }

        return response()->json($payload, $result['status']);
    }

    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'You do not have permission to manage IP Access configuration.',
            'error_code' => 'FORBIDDEN',
            'data' => null,
        ], 403);
    }
}

