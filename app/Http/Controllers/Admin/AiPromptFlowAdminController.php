<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiPromptFlow;
use App\Services\Ai\AiPromptFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AiPromptFlowAdminController extends Controller
{
    public function __construct(
        protected AiPromptFlowService $aiPromptFlowService
    ) {}

    /**
     * Display a listing of all AI Prompt Flows.
     */
    public function index(): View
    {
        $flows = collect();

        if (Schema::hasTable('ai_prompt_flows')) {
            $flows = AiPromptFlow::query()->orderBy('name')->get();
        }

        // If no records in database, provide the default business category flow
        if ($flows->isEmpty()) {
            $flows = collect([
                (object) [
                    'id' => 'default-category-flow',
                    'slug' => 'business_category_suggestion',
                    'name' => 'Business Category Suggestion',
                    'model' => 'gemini-1.5-flash',
                    'description' => 'Maps user company name, designation, and description to one of the 18 main business categories.',
                    'temperature' => 0.20,
                    'is_active' => true,
                    'is_virtual' => true,
                ],
            ]);
        }

        return view('admin.ai-flows.index', compact('flows'));
    }

    /**
     * Show form for editing an AI Prompt Flow.
     */
    public function edit(string $id): View
    {
        $flow = null;

        if (Schema::hasTable('ai_prompt_flows')) {
            $flow = AiPromptFlow::query()->where('id', $id)->orWhere('slug', $id)->first();
        }

        if (! $flow) {
            $flow = new AiPromptFlow([
                'id' => (string) Str::uuid(),
                'slug' => 'business_category_suggestion',
                'name' => 'Business Category Suggestion',
                'description' => 'Classifies users into 1 of the 18 main business categories and subcategories using Google Gemini AI.',
                'model' => 'gemini-1.5-flash',
                'system_instruction' => 'You are an intelligent business classification AI for Peers Global Unity network. Your task is to accurately map a business or professional to the most relevant main business category and subcategory from the provided allowed category list. Always return valid JSON matching the specified schema.',
                'prompt_template' => <<<'PROMPT'
Analyze the following professional and company details:
- Company Name: {{company_name}}
- Designation / Title: {{designation}}
- Business Description / Bio: {{description}}
- Industry / Keywords: {{industry}}

Allowed Main Business Categories:
{{categories_list}}

Instructions:
1. First, select the single best matching Main Category from the Allowed Categories list above.
2. If the user fits an existing subcategory or specific domain, suggest a concise subcategory name.
3. Provide a confidence score between 0.0 and 1.0.
4. Provide a brief 1-sentence reasoning.

Respond strictly in the following JSON structure:
{
  "main_category_id": <number or null>,
  "main_category_name": "<exact name from the allowed list>",
  "sub_category_id": <number or null>,
  "sub_category_name": "<suggested subcategory name>",
  "confidence": <number between 0.0 and 1.0>,
  "reasoning": "<short explanation>"
}
PROMPT,
                'temperature' => 0.20,
                'max_tokens' => 1024,
                'is_active' => true,
            ]);
        }

        return view('admin.ai-flows.edit', compact('flow'));
    }

    /**
     * Update an AI Prompt Flow.
     */
    public function update(Request $request, string $id): RedirectResponse
    {
        if (! Schema::hasTable('ai_prompt_flows')) {
            return redirect()->route('admin.ai-flows.index')
                ->with('error', "Database table 'ai_prompt_flows' is missing. Please run the provided SQL query.");
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'model' => ['required', 'string', 'max:100'],
            'system_instruction' => ['nullable', 'string'],
            'prompt_template' => ['required', 'string'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        AiPromptFlow::query()->updateOrCreate(
            ['slug' => $validated['slug']],
            $validated
        );

        return redirect()->route('admin.ai-flows.index')
            ->with('success', "AI prompt flow '{$validated['name']}' saved successfully.");
    }

    /**
     * Test a prompt live with Gemini.
     */
    public function testPrompt(Request $request): JsonResponse
    {
        $request->validate([
            'company_name' => ['nullable', 'string'],
            'designation' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        try {
            $result = $this->aiPromptFlowService->suggestCategory([
                'company_name' => $request->input('company_name', 'SolarTech Clean Energy Pvt Ltd'),
                'designation' => $request->input('designation', 'Managing Director'),
                'description' => $request->input('description', 'Manufacturing solar inverters and battery storage modules for residential and commercial projects.'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Test executed successfully.',
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
}
