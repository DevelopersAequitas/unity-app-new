<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Http\Controllers\Controller;
use App\Models\Web\WebPartnershipProposal;
use App\Models\Web\WebPublicInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PublicInquiryController extends Controller
{
    /**
     * Submit a partnership proposal (from /partner#start-conversation)
     */
    public function submitPartnership(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'organisation' => 'required|string|max:255',
            'role' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'relationshipType' => 'nullable|string|max:100',
            'relationship_type' => 'nullable|string|max:100',
            'whyPeersGlobal' => 'nullable|string',
            'why_peers_global' => 'nullable|string',
            'whatToBuild' => 'nullable|string',
            'what_to_build' => 'nullable|string',
            'whoBenefits' => 'nullable|string',
            'who_benefits' => 'nullable|string',
            'whatToContribute' => 'nullable|string',
            'what_to_contribute' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $proposal = WebPartnershipProposal::create([
                'name' => (string) $request->input('name'),
                'organisation' => (string) $request->input('organisation'),
                'role' => $request->input('role'),
                'email' => strtolower(trim((string) $request->input('email'))),
                'phone' => $request->input('phone'),
                'relationship_type' => $request->input('relationship_type') ?? $request->input('relationshipType') ?? 'Strategic Partnership',
                'why_peers_global' => $request->input('why_peers_global') ?? $request->input('whyPeersGlobal'),
                'what_to_build' => $request->input('what_to_build') ?? $request->input('whatToBuild'),
                'who_benefits' => $request->input('who_benefits') ?? $request->input('whoBenefits'),
                'what_to_contribute' => $request->input('what_to_contribute') ?? $request->input('whatToContribute'),
                'status' => 'new',
                'metadata' => [
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Partnership proposal submitted successfully',
                'proposal_number' => $proposal->proposal_number,
                'data' => [
                    'id' => $proposal->id,
                    'proposal_number' => $proposal->proposal_number,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Partnership submission error: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record partnership proposal. Please try again.',
            ], 500);
        }
    }

    /**
     * Submit general inquiries, city requests, speaker apps, or investor inquiries
     */
    public function submitInquiry(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:50',
            'source_type' => 'nullable|string|in:general,city_request,speaker,investor,other',
            'source' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'organisation' => 'nullable|string|max:255',
            'subject' => 'nullable|string|max:255',
            'message' => 'nullable|string',
            'form_payload' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $sourceType = $request->input('source_type') ?? $request->input('source') ?? 'general';
            if (! in_array($sourceType, ['general', 'city_request', 'speaker', 'investor', 'other'], true)) {
                $sourceType = 'general';
            }

            $payload = $request->input('form_payload', []);
            if (! is_array($payload)) {
                $payload = [];
            }
            // Merge all other non-standard input fields into form_payload for flexibility
            $standardFields = ['name', 'email', 'phone', 'source_type', 'source', 'city', 'organisation', 'subject', 'message', 'form_payload'];
            $extraFields = $request->except($standardFields);
            $finalPayload = array_merge($extraFields, $payload);

            $inquiry = WebPublicInquiry::create([
                'source_type' => $sourceType,
                'name' => (string) $request->input('name'),
                'email' => strtolower(trim((string) $request->input('email'))),
                'phone' => $request->input('phone'),
                'city' => $request->input('city') ?? ($finalPayload['city'] ?? null),
                'organisation' => $request->input('organisation') ?? ($finalPayload['organisation'] ?? $finalPayload['company'] ?? null),
                'subject' => $request->input('subject') ?? ($finalPayload['subject'] ?? null),
                'message' => $request->input('message') ?? ($finalPayload['message'] ?? null),
                'form_payload' => $finalPayload,
                'status' => 'new',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Inquiry submitted successfully',
                'inquiry_number' => $inquiry->inquiry_number,
                'data' => [
                    'id' => $inquiry->id,
                    'inquiry_number' => $inquiry->inquiry_number,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Inquiry submission error: '.$e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to record inquiry. Please try again.',
            ], 500);
        }
    }
}
