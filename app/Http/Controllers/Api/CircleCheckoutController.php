<?php

namespace App\Http\Controllers\Api;

use App\Enums\CircleBillingTerm;
use App\Http\Requests\Api\V1\CircleCheckoutRequest;
use App\Models\Circle;
use App\Models\UserCircleSubscription;
use App\Services\Zoho\CircleCheckoutService;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CircleCheckoutController extends BaseApiController
{
    public function __construct(private readonly CircleCheckoutService $checkoutService)
    {
    }

    public function store(CircleCheckoutRequest $request, Circle $circle)
    {
        $user = $request->user();
        $term = CircleBillingTerm::tryFromString((string) $request->input('billing_term'));

        if (! $term) {
            return $this->error('Invalid billing term.', 422);
        }

        $alreadyActive = Schema::hasTable('user_circle_subscriptions')
            ? UserCircleSubscription::query()
                ->where('user_id', $user->id)
                ->where('circle_id', $circle->id)
                ->where('billing_term', $term->value)
                ->where('status', 'active')
                ->exists()
            : false;

        if ($alreadyActive) {
            return $this->error('Active circle subscription already exists for selected term.', 422);
        }

        try {
            $result = $this->checkoutService->createCheckout($user, $circle, $term);
        } catch (Throwable $throwable) {
            return $this->error($throwable->getMessage(), 422);
        }

        return $this->success($result, 'Circle checkout created successfully.');
    }
}
