<?php

declare(strict_types=1);

namespace App\Modules\DynamicAccess\Controllers;

use App\Http\Controllers\Api\BaseApiController;
use App\Modules\DynamicAccess\Services\DynamicAccessConfigService;
use App\Shared\Authorization\AccessConstants;
use App\Shared\Authorization\EffectiveAccessResolverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessBootstrapController extends BaseApiController
{
    public function __construct(
        private readonly EffectiveAccessResolverService $accessResolver,
        private readonly DynamicAccessConfigService $configService,
    ) {}

    /**
     * API 1: Access System Bootstrap.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $actor = $request->user() ?? auth('admin')->user();
        $effective = $this->accessResolver->resolveEffectiveAccess($actor);

        return $this->success([
            'current_user_access' => $effective,
            'roles_summary' => $this->configService->listRoles(['status' => 'active']),
            'supported_scope_types' => AccessConstants::SCOPE_TYPES,
            'supported_resource_keys' => AccessConstants::RESOURCE_KEYS,
            'supported_action_keys' => AccessConstants::ACTION_KEYS,
            'supported_mask_strategies' => AccessConstants::MASK_STRATEGIES,
        ], 'Access system bootstrap loaded successfully.');
    }

    /**
     * API 21: Get Current User Navigation Menu & Screen Permissions.
     */
    public function navigation(Request $request): JsonResponse
    {
        $actor = $request->user() ?? auth('admin')->user();
        $targetApp = $request->query('target_app') ? (string) $request->query('target_app') : null;
        $effective = $this->accessResolver->resolveEffectiveAccess($actor);

        $screens = $this->accessResolver->resolveVisibleScreens(
            $effective['effective_role_keys'],
            (bool) $effective['is_global'],
            $targetApp
        );

        return $this->success([
            'primary_role_key' => $effective['primary_role_key'],
            'effective_role_keys' => $effective['effective_role_keys'],
            'is_global' => $effective['is_global'],
            'navigation' => $screens,
            'permissions' => $effective['permissions'],
        ], 'Navigation menu resolved successfully.');
    }
}

