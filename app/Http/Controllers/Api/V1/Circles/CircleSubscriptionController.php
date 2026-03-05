<?php

namespace App\Http\Controllers\Api\V1\Circles;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\Circle;
use App\Models\CircleMember;
use App\Models\CircleMemberSubscription;
use App\Services\Zoho\ZohoCircleAddonService;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CircleSubscriptionController extends BaseApiController
{
    public function __construct(private readonly ZohoBillingService $zohoBillingService)
    {
    }

    public function options(Circle $circle)
    {
        $options = $circle->subscriptionPrices()
            ->where('price', '>', 0)
            ->whereNotNull('zoho_addon_id')
            ->orderBy('duration_months')
            ->get()
            ->map(fn ($row) => [
                'duration_months' => $row->duration_months,
                'label' => ZohoCircleAddonService::durationLabel((int) $row->duration_months),
                'price' => number_format((float) $row->price, 2, '.', ''),
                'zoho_addon_id' => $row->zoho_addon_id,
            ])
            ->values();

        return $this->success([
            'circle_id' => $circle->id,
            'currency' => 'INR',
            'options' => $options,
        ]);
    }

    public function joinWithSubscription(Request $request, Circle $circle)
    {
        $validated = $request->validate([
            'duration_months' => ['required', 'integer', 'in:1,3,6,12'],
        ]);

        $user = $request->user();

        if ($circle->founder_user_id === $user->id) {
            return $this->error('You are the founder of this circle', 422);
        }

        $existingMembership = CircleMember::query()
            ->where('circle_id', $circle->id)
            ->where('user_id', $user->id)
            ->whereIn('status', ['approved', 'pending'])
            ->first();

        if ($existingMembership) {
            return $this->error('You have already requested to join or are already a member', 422);
        }

        $price = $circle->subscriptionPrices()
            ->where('duration_months', $validated['duration_months'])
            ->first();

        if (! $price || ! $price->zoho_addon_code) {
            return $this->error('Subscription option not available for selected duration', 422);
        }

        $result = DB::transaction(function () use ($user, $circle, $price, $validated) {
            $requestModel = CircleMemberSubscription::query()->create([
                'circle_id' => $circle->id,
                'user_id' => $user->id,
                'duration_months' => $validated['duration_months'],
                'price' => $price->price,
                'currency' => $price->currency ?: 'INR',
                'status' => 'pending',
            ]);

            $checkout = $this->zohoBillingService->createHostedPageForCircleSubscription($user, (string) $price->zoho_addon_code);

            $requestModel->forceFill([
                'zoho_hostedpage_id' => $checkout['hostedpage_id'],
                'payload' => $checkout['response'] ?? null,
            ])->save();

            return [$requestModel, $checkout];
        });

        [$requestModel, $checkout] = $result;

        return $this->success([
            'subscription_request_id' => $requestModel->id,
            'hostedpage_id' => $checkout['hostedpage_id'],
            'checkout_url' => $checkout['checkout_url'],
        ]);
    }
}
