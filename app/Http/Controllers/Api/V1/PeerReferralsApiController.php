<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Api\V1\StorePeerReferralRequest;
use App\Models\PeerReferral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeerReferralsApiController extends BaseApiController
{
    public function store(StorePeerReferralRequest $request): JsonResponse
    {
        $referrer = $request->user();

        $peerReferral = PeerReferral::create([
            'referrer_user_id' => $referrer->id,
            'referred_name' => $request->validated('referred_name'),
            'referred_phone' => $request->validated('referred_phone'),
            'referred_email' => $request->validated('referred_email'),
            'referred_company_name' => $request->validated('referred_company_name'),
            'referred_designation' => $request->validated('referred_designation'),
            'main_circle_id' => $request->validated('main_circle_id'),
            'circle_id' => $request->validated('circle_id'),
            'open_category_id' => $request->validated('open_category_id'),
            'message' => $request->validated('message'),
            'status' => 'pending',
        ]);

        return $this->success([
            'id' => $peerReferral->id,
            'referred_name' => $peerReferral->referred_name,
            'referred_phone' => $peerReferral->referred_phone,
            'referred_email' => $peerReferral->referred_email,
            'referred_company_name' => $peerReferral->referred_company_name,
            'referred_designation' => $peerReferral->referred_designation,
            'main_circle_id' => $peerReferral->main_circle_id,
            'circle_id' => $peerReferral->circle_id,
            'open_category_id' => $peerReferral->open_category_id,
            'message' => $peerReferral->message,
            'status' => $peerReferral->status,
            'created_at' => $peerReferral->created_at?->toIso8601String(),
        ], 'Peer referral submitted successfully.', 201);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = (int) $request->query('per_page', 15);

        $referrals = PeerReferral::query()
            ->with(['mainCircle', 'circle', 'category'])
            ->where('referrer_user_id', $user->id)
            ->latest('created_at')
            ->paginate($perPage);

        $items = collect($referrals->items())->map(fn (PeerReferral $ref) => [
            'id' => $ref->id,
            'referred_name' => $ref->referred_name,
            'referred_phone' => $ref->referred_phone,
            'referred_email' => $ref->referred_email,
            'referred_company_name' => $ref->referred_company_name,
            'referred_designation' => $ref->referred_designation,
            'main_circle_id' => $ref->main_circle_id,
            'main_circle_name' => $ref->mainCircle?->name,
            'circle_id' => $ref->circle_id,
            'circle_name' => $ref->circle?->name,
            'open_category_id' => $ref->open_category_id,
            'open_category_name' => $ref->category?->name,
            'message' => $ref->message,
            'status' => $ref->status,
            'created_at' => $ref->created_at?->toIso8601String(),
        ]);

        return $this->success([
            'items' => $items,
            'pagination' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'per_page' => $referrals->perPage(),
                'total' => $referrals->total(),
            ],
        ], 'Referrals retrieved successfully.');
    }
}
