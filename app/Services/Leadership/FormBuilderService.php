<?php

declare(strict_types=1);

namespace App\Services\Leadership;

use App\Models\Leadership\LeadershipCampaign;
use App\Models\Leadership\LeadershipFormOption;
use App\Models\Leadership\LeadershipFormQuestion;
use App\Models\Leadership\LeadershipFormSection;
use App\Models\Leadership\LeadershipFormTemplate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class FormBuilderService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * List forms for a campaign.
     *
     * @return Collection<int, LeadershipFormTemplate>
     */
    public function getCampaignForms(string $campaignId): Collection
    {
        return LeadershipFormTemplate::query()
            ->withCount(['sections'])
            ->where('campaign_id', $campaignId)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Create form template.
     *
     * @param  array<string, mixed>  $data
     */
    public function createForm(string $campaignId, array $data, ?string $userId = null): LeadershipFormTemplate
    {
        $campaign = LeadershipCampaign::findOrFail($campaignId);

        $latestVersion = (int) LeadershipFormTemplate::query()
            ->where('campaign_id', $campaign->id)
            ->where('form_type', $data['form_type'])
            ->max('version');

        $data['campaign_id'] = $campaign->id;
        $data['version'] = $latestVersion + 1;
        $data['status'] = 'draft';
        $data['created_by'] = ($userId && \App\Models\User::where('id', $userId)->exists()) ? (string) $userId : null;

        /** @var LeadershipFormTemplate $template */
        $template = LeadershipFormTemplate::create($data);

        $this->auditService->log(
            action: 'form.created',
            entityType: 'LeadershipFormTemplate',
            entityId: $template->id,
            campaignId: $campaign->id,
            afterData: $template->toArray(),
            remarks: "Form {$template->name} created (v{$template->version})"
        );

        return $template;
    }

    /**
     * Get complete form structure with sections, questions, and options.
     */
    public function getFormStructure(string $formId, bool $activeOnly = false): LeadershipFormTemplate
    {
        $query = LeadershipFormTemplate::query()
            ->with(['sections' => function ($q) use ($activeOnly): void {
                if ($activeOnly) {
                    $q->where('is_active', true);
                }
                $q->orderBy('sort_order')
                    ->with(['questions' => function ($qq) use ($activeOnly): void {
                        if ($activeOnly) {
                            $qq->where('is_active', true);
                        }
                        $qq->orderBy('sort_order')
                            ->with(['options' => function ($qqq) use ($activeOnly): void {
                                if ($activeOnly) {
                                    $qqq->where('is_active', true);
                                }
                                $qqq->orderBy('sort_order');
                            }]);
                    }]);
            }]);

        /** @var LeadershipFormTemplate $form */
        $form = $query->findOrFail($formId);

        return $form;
    }

    /**
     * Update form metadata.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateFormMetadata(string $formId, array $data): LeadershipFormTemplate
    {
        /** @var LeadershipFormTemplate $form */
        $form = LeadershipFormTemplate::findOrFail($formId);
        $before = $form->toArray();

        $form->update([
            'name' => $data['name'] ?? $form->name,
            'configuration' => $data['configuration'] ?? $form->configuration,
        ]);

        $this->auditService->log(
            action: 'form.metadata.updated',
            entityType: 'LeadershipFormTemplate',
            entityId: $form->id,
            campaignId: $form->campaign_id,
            beforeData: $before,
            afterData: $form->fresh()->toArray()
        );

        return $form->fresh();
    }

    /**
     * Add section to form.
     *
     * @param  array<string, mixed>  $data
     */
    public function addSection(string $formId, array $data): LeadershipFormSection
    {
        $form = LeadershipFormTemplate::findOrFail($formId);

        $maxSort = (int) LeadershipFormSection::query()
            ->where('form_template_id', $form->id)
            ->max('sort_order');

        $data['form_template_id'] = $form->id;
        $data['sort_order'] = $data['sort_order'] ?? ($maxSort + 1);

        /** @var LeadershipFormSection $section */
        $section = LeadershipFormSection::create($data);

        $this->auditService->log(
            action: 'form.section.added',
            entityType: 'LeadershipFormSection',
            entityId: $section->id,
            campaignId: $form->campaign_id,
            afterData: $section->toArray(),
            remarks: "Section {$section->title} added"
        );

        return $section;
    }

    /**
     * Add question to section.
     *
     * @param  array<string, mixed>  $data
     */
    public function addQuestion(string $sectionId, array $data): LeadershipFormQuestion
    {
        $section = LeadershipFormSection::with('formTemplate')->findOrFail($sectionId);

        $maxSort = (int) LeadershipFormQuestion::query()
            ->where('section_id', $section->id)
            ->max('sort_order');

        $optionsData = $data['options'] ?? [];
        unset($data['options']);

        $data['section_id'] = $section->id;
        $data['sort_order'] = $data['sort_order'] ?? ($maxSort + 1);

        return DB::transaction(function () use ($section, $data, $optionsData): LeadershipFormQuestion {
            /** @var LeadershipFormQuestion $question */
            $question = LeadershipFormQuestion::create($data);

            if (! empty($optionsData) && is_array($optionsData)) {
                $sort = 1;
                foreach ($optionsData as $opt) {
                    LeadershipFormOption::create([
                        'question_id' => $question->id,
                        'option_label' => $opt['label'] ?? $opt['option_label'],
                        'option_value' => $opt['value'] ?? $opt['option_value'],
                        'sort_order' => $opt['sort_order'] ?? $sort++,
                        'is_active' => $opt['is_active'] ?? true,
                    ]);
                }
            }

            $this->auditService->log(
                action: 'form.question.added',
                entityType: 'LeadershipFormQuestion',
                entityId: $question->id,
                campaignId: $section->formTemplate->campaign_id,
                afterData: $question->toArray(),
                remarks: "Question {$question->question_key} added"
            );

            return $question->load('options');
        });
    }

    /**
     * Update question, options, and validation.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateQuestion(string $questionId, array $data): LeadershipFormQuestion
    {
        /** @var LeadershipFormQuestion $question */
        $question = LeadershipFormQuestion::with(['section.formTemplate', 'options'])->findOrFail($questionId);
        $before = $question->toArray();

        $optionsData = $data['options'] ?? null;
        unset($data['options']);

        return DB::transaction(function () use ($question, $before, $data, $optionsData): LeadershipFormQuestion {
            $question->update($data);

            if ($optionsData !== null && is_array($optionsData)) {
                // Refresh options
                $question->options()->delete();
                $sort = 1;
                foreach ($optionsData as $opt) {
                    LeadershipFormOption::create([
                        'question_id' => $question->id,
                        'option_label' => $opt['label'] ?? $opt['option_label'],
                        'option_value' => $opt['value'] ?? $opt['option_value'],
                        'sort_order' => $opt['sort_order'] ?? $sort++,
                        'is_active' => $opt['is_active'] ?? true,
                    ]);
                }
            }

            $this->auditService->log(
                action: 'form.question.updated',
                entityType: 'LeadershipFormQuestion',
                entityId: $question->id,
                campaignId: $question->section->formTemplate->campaign_id,
                beforeData: $before,
                afterData: $question->fresh(['options'])->toArray(),
                remarks: "Question {$question->question_key} updated"
            );

            return $question->fresh(['options']);
        });
    }

    /**
     * Publish a new form version.
     */
    public function publishForm(string $formId, string $remarks = '', ?string $userId = null): LeadershipFormTemplate
    {
        /** @var LeadershipFormTemplate $form */
        $form = LeadershipFormTemplate::findOrFail($formId);

        if ($form->status === 'published') {
            throw new RuntimeException('This form version is already published.');
        }

        $before = $form->toArray();

        return DB::transaction(function () use ($form, $before, $remarks): LeadershipFormTemplate {
            // Archive previous published form for this campaign and type
            LeadershipFormTemplate::query()
                ->where('campaign_id', $form->campaign_id)
                ->where('form_type', $form->form_type)
                ->where('status', 'published')
                ->where('id', '!=', $form->id)
                ->update(['status' => 'archived']);

            $form->update([
                'status' => 'published',
                'published_at' => Carbon::now(),
            ]);

            $this->auditService->log(
                action: 'form.published',
                entityType: 'LeadershipFormTemplate',
                entityId: $form->id,
                campaignId: $form->campaign_id,
                beforeData: $before,
                afterData: $form->fresh()->toArray(),
                remarks: $remarks ?: "Form {$form->name} (v{$form->version}) published"
            );

            return $form->fresh();
        });
    }

    /**
     * Get published form for public display.
     */
    public function getPublishedForm(string $campaignId, string $formType = 'nomination'): ?LeadershipFormTemplate
    {
        /** @var LeadershipFormTemplate|null $form */
        $form = LeadershipFormTemplate::query()
            ->with(['sections' => function ($q): void {
                $q->where('is_active', true)
                    ->orderBy('sort_order')
                    ->with(['questions' => function ($qq): void {
                        $qq->where('is_active', true)
                            ->orderBy('sort_order')
                            ->with(['options' => function ($qqq): void {
                                $qqq->where('is_active', true)->orderBy('sort_order');
                            }]);
                    }]);
            }])
            ->where('campaign_id', $campaignId)
            ->where('form_type', $formType)
            ->where('status', 'published')
            ->orderBy('version', 'desc')
            ->first();

        return $form;
    }
}
