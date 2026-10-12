<?php

declare(strict_types=1);

namespace App\Leader\Controllers;

use App\Http\Controllers\Controller;
use App\Leader\Services\LeaderAskFlowService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderAskFlowController extends Controller
{
    public function __construct(
        private readonly LeaderAskFlowService $askFlowService,
    ) {}

    /**
     * Get the 3 primary Ask Flows (Collaboration, Referral, Get Help) with sub-types & metrics.
     * GET /api/v1/leader/asks/flows
     */
    public function flows(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->askFlowService->getFlows($user, $request->all());

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Ask discovery flows retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get complete master data, sub-types, sections, and options for a specific flow.
     * GET /api/v1/leader/asks/flows/{flow}
     */
    public function flowDetails(Request $request, string $flow): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $result = $this->askFlowService->getFlowDetails($user, $flow, $request->all());

        if (! ($result['found'] ?? true)) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $result['message'] ?? 'Flow not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => "Flow '{$flow}' details and categories retrieved successfully.",
            'data' => $result,
        ]);
    }

    /**
     * Get dynamic form layout, sections, and options for a specific flow and category.
     * GET /api/v1/leader/asks/form-config?flow=...&type=...
     */
    public function formConfig(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $flow = (string) ($request->query('flow') ?? 'collaboration');
        $type = $request->query('type') ? (string) $request->query('type') : null;
        $includeInactive = $request->boolean('include_inactive');

        $result = $this->askFlowService->getFormConfig($user, $flow, $type, $includeInactive);

        if (! ($result['found'] ?? true)) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $result['message'] ?? 'Form configuration not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Dynamic form configuration retrieved successfully.',
            'data' => $result,
        ]);
    }

    /**
     * Get complete Master Data across ALL 3 Flows for mobile client caching.
     * GET /api/v1/leader/asks/all-data
     */
    public function allData(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->askFlowService->getAllData($user);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Complete Ask Discovery master data retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get KPI Summary & Flow Breakdown for the Leader App Dashboard.
     * GET /api/v1/leader/asks/summary
     */
    public function summary(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $this->askFlowService->getSummary($user);

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Leader Asks summary and flow breakdown retrieved successfully.',
            'data' => $data,
        ]);
    }
}
