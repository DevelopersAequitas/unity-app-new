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
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
            'currency' => ['nullable', 'string', 'max:10'],
        ]);

        $user = $request->user();
        $currency = strtoupper((string) ($validated['currency'] ?? 'INR'));

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
            ->where('currency', $currency)
            ->first();

        if (! $price || ! $price->zoho_addon_code) {
            return $this->error('Subscription option not available for selected duration', 422);
        }

        [$checkout, $paymentId] = DB::transaction(function () use ($user, $circle, $price, $validated, $currency) {
            CircleMemberSubscription::query()->create([
                'circle_id' => $circle->id,
                'user_id' => $user->id,
                'duration_months' => $validated['duration_months'],
                'price' => $price->price,
                'currency' => $currency,
                'status' => 'pending',
            ]);

            $checkout = $this->zohoBillingService->createHostedPageForCircleSubscription($user, (string) $price->zoho_addon_code);

            $payload = [
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'circle_id' => $circle->id,
                'provider' => 'zoho',
                'status' => 'pending',
                'duration_months' => (int) $validated['duration_months'],
                'price' => $price->price,
                'currency' => $currency,
                'zoho_hostedpage_id' => (string) $checkout['hostedpage_id'],
                'zoho_hostedpage_url' => (string) $checkout['checkout_url'],
                'payload' => $checkout['response'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (! Schema::hasTable('circle_join_payments')) {
                throw new \RuntimeException('circle_join_payments table not found');
            }

            DB::table('circle_join_payments')->insert($payload);

            return [$checkout, $payload['id']];
        });

        return response()->json([
            'success' => true,
            'message' => 'Checkout created',
            'data' => [
                'hostedpage_id' => $checkout['hostedpage_id'],
                'checkout_url' => $checkout['checkout_url'],
                'payment_id' => $paymentId,
            ],
        ]);
    }
}
