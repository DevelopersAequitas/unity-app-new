<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ask;

use App\Http\Controllers\Controller;
use App\Http\Resources\Ask\AskFlowResource;
use App\Http\Resources\Ask\AskOptionGroupResource;
use App\Http\Resources\Ask\AskTypeResource;
use App\Models\Ask\AskFlow;
use App\Models\Ask\AskOptionGroup;
use App\Models\Ask\AskType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AskConfigController extends Controller
{
    /**
     * API 1 — Get Ask Flows
     * GET /api/asks/flows
     */
    public function getFlows(Request $request): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive');

        $flows = AskFlow::query()
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => AskFlowResource::collection($flows),
        ]);
    }

    /**
     * API 2 — Get Ask Types for a Flow
     * GET /api/asks/flows/{flow}/types
     */
    public function getTypes(Request $request, string $flow): JsonResponse
    {
        $includeInactive = $request->boolean('include_inactive');

        $askFlow = AskFlow::query()
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
            ->where(function (Builder $q) use ($flow): void {
                $q->where('code', $flow);
                if (Str::isUuid($flow)) {
                    $q->orWhere('id', $flow);
                }
            })
            ->firstOrFail();

        $types = AskType::query()
            ->where('flow_id', $askFlow->id)
            ->whereNull('parent_id')
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
            ->with(['children' => fn ($q) => $q->when(! $includeInactive, fn ($sq) => $sq->where('is_active', true))->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => AskTypeResource::collection($types),
        ]);
    }

    /**
     * API 3 — Get Dynamic Ask Form Configuration
     * GET /api/asks/form-config?flow=...&type=...
     */
    public function getFormConfig(Request $request): JsonResponse
    {
        $flowParam = (string) ($request->query('flow') ?? '');
        $typeParam = (string) ($request->query('type') ?? '');
        $includeInactive = $request->boolean('include_inactive');

        $flow = AskFlow::query()
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
            ->when($flowParam !== '', function (Builder $q) use ($flowParam): void {
                $q->where('code', $flowParam);
                if (Str::isUuid($flowParam)) {
                    $q->orWhere('id', $flowParam);
                }
            })
            ->first();

        $type = null;
        if ($flow && $typeParam !== '') {
            $type = AskType::query()
                ->where('flow_id', $flow->id)
                ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
                ->where(function (Builder $q) use ($typeParam): void {
                    $q->where('code', $typeParam);
                    if (Str::isUuid($typeParam)) {
                        $q->orWhere('id', $typeParam);
                    }
                })
                ->first();
        }

        // Fetch option groups with options relevant to flow / type
        $groups = AskOptionGroup::query()
            ->when(! $includeInactive, fn ($q) => $q->where('is_active', true))
            ->with(['options' => function ($q) use ($flow, $type, $includeInactive): void {
                $q->when(! $includeInactive, fn ($oq) => $oq->where('is_active', true))
                    ->where(function (Builder $oq) use ($flow, $type): void {
                        $oq->where(function (Builder $sq): void {
                            $sq->whereNull('flow_id')->whereNull('ask_type_id');
                        });
                        if ($flow) {
                            $oq->orWhere('flow_id', $flow->id);
                        }
                        if ($type) {
                            $oq->orWhere('ask_type_id', $type->id);
                        }
                    })
                    ->orderBy('sort_order');
            }])
            ->orderBy('sort_order')
            ->get();

        // Organize configuration into sections
        $flowCode = $flow?->code;
        $sections = match ($flowCode) {
            'collaboration', 'collaborate' => [
                'details' => ['goal', 'collaboration_bring', 'collaboration_need'],
                'filters' => ['industry', 'geography', 'business_stage', 'timeline', 'expected_outcome'],
            ],
            'referral' => [
                'details' => ['who_to_meet', 'ideal_profile', 'referral_reason', 'what_i_offer'],
                'filters' => ['referral_industry', 'referral_geography'],
            ],
            'help' => [
                'details' => ['mentorship_subtype', 'help_topic', 'what_needed', 'done_definition', 'help_duration', 'help_timing'],
                'filters' => ['industry', 'geography'],
            ],
            default => [
                'details' => $groups->pluck('code')->toArray(),
                'filters' => ['industry', 'geography', 'business_stage', 'timeline'],
            ],
        };

        // If a flow was requested, filter groups to only include groups relevant to that flow
        if ($flow) {
            $relevantGroupCodes = array_unique(array_merge($sections['details'] ?? [], $sections['filters'] ?? []));
            $groups = $groups->filter(fn ($g) => in_array($g->code, $relevantGroupCodes, true))->values();
        }

        // Allow explicit group filtering via query parameter: ?groups=industry,geography,business_stage
        $groupsParam = (string) ($request->query('groups') ?? $request->query('group') ?? '');
        if ($groupsParam !== '') {
            $selectedGroupCodes = array_map('trim', explode(',', $groupsParam));
            $groups = $groups->filter(fn ($g) => in_array($g->code, $selectedGroupCodes, true))->values();
        }

        return response()->json([
            'success' => true,
            'flow' => $flow ? new AskFlowResource($flow) : null,
            'type' => $type ? new AskTypeResource($type) : null,
            'sections' => $sections,
            'groups' => AskOptionGroupResource::collection($groups),
        ]);
    }
}
