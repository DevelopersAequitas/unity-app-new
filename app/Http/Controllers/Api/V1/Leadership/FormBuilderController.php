<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\FormBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FormBuilderController extends LeadershipBaseController
{
    public function __construct(
        protected FormBuilderService $formBuilderService
    ) {}

    /**
     * E1. List campaign forms.
     */
    public function index(string $campaignId): JsonResponse
    {
        $forms = $this->formBuilderService->getCampaignForms($campaignId);

        return $this->success($forms, 'Campaign forms fetched successfully.');
    }

    /**
     * E2. Create form template.
     */
    public function store(Request $request, string $campaignId): JsonResponse
    {
        $validated = $request->validate([
            'form_type' => 'required|in:nomination,jury_evaluation',
            'name' => 'required|string|max:200',
            'configuration' => 'nullable|array',
        ]);

        try {
            $form = $this->formBuilderService->createForm($campaignId, $validated, Auth::id());

            return $this->success($form, 'Form template created successfully.', 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * E3. Retrieve complete form structure.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $form = $this->formBuilderService->getFormStructure($id);

            return $this->success($form, 'Form structure fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 404);
        }
    }

    /**
     * E4. Update form metadata.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $form = $this->formBuilderService->updateFormMetadata($id, $request->all());

            return $this->success($form, 'Form metadata updated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * E5. Add form section.
     */
    public function addSection(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:200',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_required' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'visibility_rules' => 'nullable|array',
        ]);

        try {
            $section = $this->formBuilderService->addSection($id, $validated);

            return $this->success($section, 'Form section added successfully.', 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * E6. Add question.
     */
    public function addQuestion(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'question_key' => 'required|string|max:150',
            'label' => 'required|string|max:500',
            'description' => 'nullable|string',
            'field_type' => 'required|string|in:text,textarea,email,phone,number,date,dropdown,multiselect,radio,checkbox,url,file,image,video_url,declaration,repeatable_group',
            'placeholder' => 'nullable|string|max:300',
            'is_required' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
            'validation_rules' => 'nullable|array',
            'field_configuration' => 'nullable|array',
            'visibility_rules' => 'nullable|array',
            'options' => 'nullable|array',
        ]);

        try {
            $question = $this->formBuilderService->addQuestion($id, $validated);

            return $this->success($question, 'Question added successfully.', 201);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * E7. Update question, options, and validation.
     */
    public function updateQuestion(Request $request, string $id): JsonResponse
    {
        try {
            $question = $this->formBuilderService->updateQuestion($id, $request->all());

            return $this->success($question, 'Question updated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * E8. Publish a new form version.
     */
    public function publish(Request $request, string $id): JsonResponse
    {
        $remarks = (string) $request->input('remarks', '');

        try {
            $form = $this->formBuilderService->publishForm($id, $remarks, Auth::id());

            return $this->success([
                'form_template_id' => $form->id,
                'version' => $form->version,
                'status' => $form->status,
                'published_at' => $form->published_at?->toIso8601String(),
            ], 'Form published successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * List all form templates across campaigns.
     */
    public function allTemplates(Request $request): JsonResponse
    {
        $query = \App\Models\Leadership\LeadershipFormTemplate::query()
            ->with(['campaign.role', 'sections.questions.options']);

        if ($request->has('campaign_id') && ! empty($request->query('campaign_id'))) {
            $query->where('campaign_id', $request->query('campaign_id'));
        }

        if ($request->has('form_type') && ! empty($request->query('form_type'))) {
            $query->where('form_type', $request->query('form_type'));
        }

        return $this->success($query->orderBy('created_at', 'desc')->get(), 'Templates fetched successfully.');
    }

    /**
     * Create template globally.
     */
    public function storeGlobal(Request $request): JsonResponse
    {
        $campaignId = $request->input('campaign_id') ?? \App\Models\Leadership\LeadershipCampaign::value('id');
        if (! $campaignId) {
            return $this->error('Please create at least one campaign before creating templates.', 400);
        }

        return $this->store($request, (string) $campaignId);
    }

    /**
     * Add section with template_id in payload.
     */
    public function addSectionGeneric(Request $request): JsonResponse
    {
        $templateId = (string) ($request->input('template_id') ?? $request->input('form_template_id'));
        if (! $templateId) {
            return $this->error('template_id is required.', 422);
        }

        return $this->addSection($request, $templateId);
    }

    /**
     * Update section directly.
     */
    public function updateSectionGeneric(Request $request, string $id): JsonResponse
    {
        $section = \App\Models\Leadership\LeadershipFormSection::findOrFail($id);
        $section->update($request->all());

        return $this->success($section, 'Section updated successfully.');
    }

    /**
     * Delete section directly.
     */
    public function deleteSectionGeneric(string $id): JsonResponse
    {
        $section = \App\Models\Leadership\LeadershipFormSection::findOrFail($id);
        $section->delete();

        return $this->success(null, 'Section deleted successfully.');
    }

    /**
     * Add question with section_id in payload.
     */
    public function addQuestionGeneric(Request $request): JsonResponse
    {
        $sectionId = (string) $request->input('section_id');
        if (! $sectionId) {
            return $this->error('section_id is required.', 422);
        }

        return $this->addQuestion($request, $sectionId);
    }

    /**
     * Delete question directly.
     */
    public function deleteQuestionGeneric(string $id): JsonResponse
    {
        $question = \App\Models\Leadership\LeadershipFormQuestion::findOrFail($id);
        $question->delete();

        return $this->success(null, 'Question deleted successfully.');
    }
}
