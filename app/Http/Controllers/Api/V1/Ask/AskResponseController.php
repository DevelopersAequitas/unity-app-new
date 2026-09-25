<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Ask;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ask\SubmitAskResponseRequest;
use App\Http\Requests\Ask\UpdateResponseContactRequest;
use App\Http\Requests\Ask\UpdateResponseStatusRequest;
use App\Http\Resources\Ask\AskResource;
use App\Http\Resources\Ask\AskResponseContactResource;
use App\Http\Resources\Ask\AskResponseResource;
use App\Http\Resources\Ask\AskResponseStatusHistoryResource;
use App\Models\Ask\Ask;
use App\Models\Ask\AskResponse;
use App\Models\User;
use App\Services\Ask\AskResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AskResponseController extends Controller
{
    public function __construct(
        protected AskResponseService $responseService
    ) {}

    /**
     * API 18 — Get Ask For Response (Responder View)
     * GET /api/asks/{ask}/respond
     */
    public function respondView(Request $request, Ask $ask): JsonResponse
    {
        $askForResponse = $this->responseService->getAskForResponse($ask);

        // Check if current user has already responded
        /** @var User $user */
        $user = $request->user();
        $existingResponse = AskResponse::query()
            ->where('ask_id', $ask->id)
            ->where('responder_user_id', $user->id)
            ->with(['contact'])
            ->first();

        return response()->json([
            'success' => true,
            'ask' => new AskResource($askForResponse),
            'available_response_types' => [
                [
                    'code' => AskResponse::TYPE_CAN_HELP_DIRECTLY,
                    'label' => 'I can help directly',
                    'description' => 'Offer direct assistance with this request.',
                ],
                [
                    'code' => AskResponse::TYPE_CAN_INTRODUCE_PEER,
                    'label' => 'I can introduce a peer',
                    'description' => 'Introduce another peer from your network who can help.',
                ],
                [
                    'code' => AskResponse::TYPE_KNOW_SOMEONE,
                    'label' => 'I know someone',
                    'description' => 'Share contact details of someone external who can help.',
                ],
                [
                    'code' => AskResponse::TYPE_NOT_RELEVANT,
                    'label' => 'Not relevant',
                    'description' => 'Dismiss this request if it is not relevant.',
                ],
            ],
            'existing_response' => $existingResponse ? new AskResponseResource($existingResponse) : null,
        ]);
    }

    /**
     * API 19 — Submit Ask Response
     * POST /api/asks/{ask}/responses
     */
    public function store(SubmitAskResponseRequest $request, Ask $ask): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $response = $this->responseService->submitResponse($ask, $user, $request->validated());

        $message = match ($response->response_type) {
            AskResponse::TYPE_KNOW_SOMEONE => 'Referral submitted successfully.',
            AskResponse::TYPE_CAN_INTRODUCE_PEER => 'Peer introduced successfully.',
            default => 'Response submitted successfully.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => new AskResponseResource($response),
        ], 201);
    }

    /**
     * API 20 — Get Ask Responses (Ask Owner View)
     * GET /api/asks/{ask}/responses
     */
    public function index(Request $request, Ask $ask): JsonResponse
    {
        $this->authorizeOwner($request->user(), $ask);

        $responses = $this->responseService->getResponses($ask, $request->all());

        return response()->json([
            'success' => true,
            'data' => AskResponseResource::collection($responses->items()),
            'meta' => [
                'current_page' => $responses->currentPage(),
                'per_page' => $responses->perPage(),
                'total' => $responses->total(),
                'last_page' => $responses->lastPage(),
            ],
        ]);
    }

    /**
     * API 21 — Response Details
     * GET /api/asks/{ask}/responses/{response}
     */
    public function show(Request $request, Ask $ask, AskResponse $response): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $ask, $response);

        $detailed = $this->responseService->getResponseDetails($response);

        return response()->json([
            'success' => true,
            'data' => new AskResponseResource($detailed),
        ]);
    }

    /**
     * API 22 — Update Response Status
     * PATCH /api/asks/{ask}/responses/{response}
     */
    public function update(UpdateResponseStatusRequest $request, Ask $ask, AskResponse $response): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $ask, $response);

        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();
        $updated = $this->responseService->updateResponseStatus(
            $response,
            $user,
            (string) $validated['status'],
            isset($validated['note']) ? (string) $validated['note'] : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Response status updated successfully.',
            'data' => new AskResponseResource($updated),
        ]);
    }

    /**
     * API 23 — Get Response Status History
     * GET /api/asks/{ask}/responses/{response}/history
     */
    public function history(Request $request, Ask $ask, AskResponse $response): JsonResponse
    {
        $this->authorizeParticipant($request->user(), $ask, $response);

        $history = $this->responseService->getResponseStatusHistory($response);

        return response()->json([
            'success' => true,
            'data' => AskResponseStatusHistoryResource::collection($history),
        ]);
    }

    /**
     * API 25 — Update Response Contact
     * PATCH /api/asks/{ask}/responses/{response}/contact
     */
    public function updateContact(UpdateResponseContactRequest $request, Ask $ask, AskResponse $response): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Only the responder who submitted the contact or the ask owner may edit
        if ((string) $response->responder_user_id !== (string) $user->id && (string) $ask->user_id !== (string) $user->id) {
            abort(403, 'Unauthorized to update contact information.');
        }

        $contact = $this->responseService->updateResponseContact($response, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Contact information updated successfully.',
            'data' => new AskResponseContactResource($contact),
        ]);
    }

    protected function authorizeOwner(?User $user, Ask $ask): void
    {
        if (! $user || (string) $ask->user_id !== (string) $user->id) {
            abort(403, 'Unauthorized action on this Ask.');
        }
    }

    protected function authorizeParticipant(?User $user, Ask $ask, AskResponse $response): void
    {
        if (! $user) {
            abort(401);
        }

        $isOwner = (string) $ask->user_id === (string) $user->id;
        $isResponder = (string) $response->responder_user_id === (string) $user->id;

        if (! $isOwner && ! $isResponder) {
            abort(403, 'Unauthorized to access this response.');
        }
    }
}
