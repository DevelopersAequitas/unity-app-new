<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\AiCategorySuggestionRequest;
use App\Models\AiPromptFlow;
use App\Services\Ai\AiPromptFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AiController extends Controller
{
    public function __construct(
        protected AiPromptFlowService $aiPromptFlowService
    ) {}

    /**
     * Suggest the best matching main business category & subcategory.
     */
    public function suggestCategory(AiCategorySuggestionRequest $request): JsonResponse
    {
        try {
            $result = $this->aiPromptFlowService->suggestCategory($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Category suggestion generated successfully.',
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Execute any generic AI prompt flow dynamically.
     */
    public function executeFlow(string $slug, Request $request): JsonResponse
    {
        try {
            $variables = $request->all();
            $result = $this->aiPromptFlowService->execute($slug, $variables);

            return response()->json([
                'success' => true,
                'message' => "AI flow '{$slug}' executed successfully.",
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * List AI prompt flows (for Admin configuration).
     */
    public function flows(): JsonResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return response()->json([
                'success' => true,
                'message' => 'AI prompt flows loaded (default memory list).',
                'data' => [
                    [
                        'slug' => 'business_category_suggestion',
                        'name' => 'Business Category Suggestion',
                        'model' => 'gemini-2.5-flash',
                        'is_active' => true,
                    ],
                ],
            ]);
        }

        $flows = AiPromptFlow::query()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'AI prompt flows retrieved successfully.',
            'data' => $flows,
        ]);
    }

    /**
     * Update or create an AI prompt flow (for Admin panel).
     */
    public function updateFlow(string $slug, Request $request): JsonResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return response()->json([
                'success' => false,
                'message' => "The 'ai_prompt_flows' database table has not been created yet.",
                'data' => null,
            ], 500);
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'model' => ['nullable', 'string', 'max:100'],
            'system_instruction' => ['nullable', 'string'],
            'prompt_template' => ['required', 'string'],
            'temperature' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $flow = AiPromptFlow::query()->updateOrCreate(
            ['slug' => $slug],
            array_merge($validated, [
                'name' => $validated['name'] ?? ucwords(str_replace('_', ' ', $slug)),
            ])
        );

        return response()->json([
            'success' => true,
            'message' => "AI prompt flow '{$slug}' updated successfully.",
            'data' => $flow,
        ]);
    }
}
