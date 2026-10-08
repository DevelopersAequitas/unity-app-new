<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Leadership;

use App\Services\Leadership\FormBuilderService;
use App\Services\Leadership\NominationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NominationController extends LeadershipBaseController
{
    public function __construct(
        protected NominationService $nominationService,
        protected FormBuilderService $formBuilderService
    ) {}

    /**
     * C1. Get published nomination form.
     */
    public function form(Request $request, string $campaignId): JsonResponse
    {
        $form = $this->formBuilderService->getPublishedForm($campaignId, 'nomination');

        if (! $form) {
            return $this->error('No published nomination form found for this campaign.', 404);
        }

        return $this->success($form, 'Nomination form fetched successfully.');
    }

    /**
     * C2. Retrieve verified Unity profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'required|uuid',
            'verification_token' => 'required|string',
        ]);

        try {
            $data = $this->nominationService->getVerifiedProfile(
                $validated['campaign_id'],
                $validated['verification_token']
            );

            return $this->success($data, 'Profile checked successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * C3. Save nomination draft.
     */
    public function saveDraft(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'required|uuid',
            'scope_id' => 'nullable|uuid',
            'verification_token' => 'required|string',
            'profile' => 'nullable|array',
            'answers' => 'nullable|array',
        ]);

        try {
            $result = $this->nominationService->saveDraft($validated);

            return $this->success($result, 'Draft saved successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * C4. Get nomination draft.
     */
    public function getDraft(Request $request, string $nominationId): JsonResponse
    {
        $token = (string) ($request->query('verification_token') ?: $request->header('X-Verification-Token'));
        if (! $token) {
            return $this->error('Verification token is required.', 401);
        }

        try {
            $nomination = $this->nominationService->getDraft($nominationId, $token);

            return $this->success($nomination, 'Draft fetched successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 403);
        }
    }

    /**
     * C5. Update nomination draft.
     */
    public function updateDraft(Request $request, string $nominationId): JsonResponse
    {
        $token = (string) ($request->input('verification_token') ?: $request->header('X-Verification-Token'));
        if (! $token) {
            return $this->error('Verification token is required.', 401);
        }

        try {
            $nomination = $this->nominationService->updateDraft($nominationId, $request->all(), $token);

            return $this->success($nomination, 'Draft updated successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * C6. Upload nomination document.
     */
    public function uploadDocument(Request $request, string $nominationId): JsonResponse
    {
        $request->validate([
            'document_type' => 'required|string|max:100',
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
            'verification_token' => 'required|string',
        ]);

        try {
            $doc = $this->nominationService->uploadDocument(
                $nominationId,
                $request->input('document_type'),
                $request->file('file'),
                $request->input('verification_token')
            );

            return $this->success([
                'document_id' => $doc->id,
                'document_type' => $doc->document_type,
                'verification_status' => $doc->verification_status,
            ], 'Document uploaded successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * C7. Delete nomination document.
     */
    public function deleteDocument(Request $request, string $nominationId, string $documentId): JsonResponse
    {
        $token = (string) ($request->input('verification_token') ?: $request->header('X-Verification-Token'));
        if (! $token) {
            return $this->error('Verification token is required.', 401);
        }

        try {
            $this->nominationService->deleteDocument($nominationId, $documentId, $token);

            return $this->success(null, 'Document deleted successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }

    /**
     * C8. Submit nomination.
     */
    public function submit(Request $request, string $nominationId): JsonResponse
    {
        $validated = $request->validate([
            'verification_token' => 'required|string',
            'declarations' => 'required|array',
        ]);

        try {
            $result = $this->nominationService->submitNomination(
                $nominationId,
                $validated['declarations'],
                $validated['verification_token']
            );

            return $this->success($result, 'Nomination submitted successfully.');
        } catch (\Throwable $e) {
            return $this->error($e->getMessage(), 400);
        }
    }
}
