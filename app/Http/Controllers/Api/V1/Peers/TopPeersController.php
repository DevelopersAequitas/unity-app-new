<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Peers;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Peers\TopPeersRequest;
use App\Services\Peers\TopPeersService;
use Illuminate\Http\JsonResponse;

class TopPeersController extends BaseApiController
{
    public function __construct(
        private readonly TopPeersService $topPeersService,
    ) {}

    /**
     * Get top peers by business deals.
     */
    public function businessDeals(TopPeersRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $authUser = $request->user();

        $data = $this->topPeersService->getTopBusinessDeals($filters, $authUser);

        return $this->success($data, 'Top peers for business deals fetched successfully.');
    }

    /**
     * Get top peers by P2P meetings.
     */
    public function p2pMeetings(TopPeersRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $authUser = $request->user();

        $data = $this->topPeersService->getTopP2pMeetings($filters, $authUser);

        return $this->success($data, 'Top peers for P2P meetings fetched successfully.');
    }

    /**
     * Get top peers by testimonials.
     */
    public function testimonials(TopPeersRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $authUser = $request->user();

        $data = $this->topPeersService->getTopTestimonials($filters, $authUser);

        return $this->success($data, 'Top peers for testimonials fetched successfully.');
    }

    /**
     * Get top peers by referrals.
     */
    public function referrals(TopPeersRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $authUser = $request->user();

        $data = $this->topPeersService->getTopReferrals($filters, $authUser);

        return $this->success($data, 'Top peers for referrals fetched successfully.');
    }
}
