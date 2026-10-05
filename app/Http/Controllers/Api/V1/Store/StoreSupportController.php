<?php

namespace App\Http\Controllers\Api\V1\Store;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Store\StoreSupportMessageRequest;
use App\Http\Requests\Store\StoreSupportTicketRequest;
use App\Services\Store\StoreSupportService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StoreSupportController extends BaseApiController
{
    protected StoreSupportService $supportService;

    public function __construct(StoreSupportService $supportService)
    {
        $this->supportService = $supportService;
    }

    public function store(StoreSupportTicketRequest $request): JsonResponse
    {
        $user = $request->user();
        $ticket = $this->supportService->createTicket($user, $request->validated());

        return $this->success($ticket, 'Support ticket created successfully', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->input('per_page', 20);

        $tickets = $this->supportService->getUserTickets($user, $perPage);

        return $this->success($tickets, 'Support tickets retrieved');
    }

    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $ticket = $this->supportService->getTicketDetails($user, $id);

            return $this->success($ticket, 'Support ticket details retrieved');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 404);
        }
    }

    public function addMessage(StoreSupportMessageRequest $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $msg = $this->supportService->addMessage(
                $user,
                $id,
                $request->input('message'),
                $request->input('attachment_url')
            );

            return $this->success($msg, 'Message sent successfully', 201);
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }

    public function close(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $ticket = $this->supportService->closeTicket($user, $id);

            return $this->success($ticket, 'Ticket closed successfully');
        } catch (Exception $e) {
            return $this->error($e->getMessage(), $e->getCode() ?: 400);
        }
    }
}
