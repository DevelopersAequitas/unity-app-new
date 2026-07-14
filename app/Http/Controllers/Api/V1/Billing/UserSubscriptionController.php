<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CircleSubscription;
use App\Models\Payment;
use App\Models\UserMembership;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserSubscriptionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $items = collect();

        // 1. Fetch base membership subscriptions/payments
        if (Schema::hasTable('user_memberships')) {
            $memberships = UserMembership::query()
                ->with(['plan', 'payment'])
                ->where('user_id', $user->id)
                ->get();

            foreach ($memberships as $membership) {
                $startsAt = $membership->starts_at;
                $endsAt = $membership->ends_at;

                $items->push([
                    'id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'membership_plan_id' => $membership->membership_plan_id,
                    'starts_at' => $startsAt ? $startsAt->format('Y-m-d\TH:i:s') : null,
                    'ends_at' => $endsAt ? $endsAt->format('Y-m-d\TH:i:s') : null,
                    'status' => $membership->status,
                    'payment_id' => $membership->payment_id,
                    'membership_plan' => $membership->plan ? [
                        'id' => $membership->plan->id,
                        'name' => $membership->plan->name,
                    ] : null,
                    'payment' => $membership->payment ? [
                        'id' => $membership->payment->id,
                        'status' => $membership->payment->status,
                    ] : null,
                    'circle_payment' => 'no',
                    'is_circle_payment' => false,
                ]);
            }
        } else {
            // Fallback to payments table if user_memberships doesn't exist
            $payments = Payment::query()
                ->with('plan')
                ->where('user_id', $user->id)
                ->get();

            foreach ($payments as $payment) {
                $startsAt = $payment->paid_at ?? $payment->created_at;
                $endsAt = $payment->paid_at ? $payment->paid_at->copy()->addYear() : null;

                $items->push([
                    'id' => $payment->id,
                    'user_id' => $payment->user_id,
                    'membership_plan_id' => $payment->membership_plan_id,
                    'starts_at' => $startsAt ? $startsAt->format('Y-m-d\TH:i:s') : null,
                    'ends_at' => $endsAt ? $endsAt->format('Y-m-d\TH:i:s') : null,
                    'status' => in_array($payment->status, ['paid', 'success']) ? 'active' : 'inactive',
                    'payment_id' => $payment->razorpay_payment_id ?? $payment->id,
                    'membership_plan' => $payment->plan ? [
                        'id' => $payment->plan->id,
                        'name' => $payment->plan->name,
                    ] : null,
                    'payment' => [
                        'id' => $payment->id,
                        'status' => $payment->status,
                    ],
                    'circle_payment' => 'no',
                    'is_circle_payment' => false,
                ]);
            }
        }

        // 2. Fetch circle subscriptions
        $circleSubscriptions = CircleSubscription::query()
            ->with('circle')
            ->where('user_id', $user->id)
            ->get();

        foreach ($circleSubscriptions as $sub) {
            $startsAt = $sub->paid_at ?? $sub->started_at ?? $sub->created_at;
            $endsAt = $sub->expires_at;

            $circleName = $sub->circle?->name;
            $addonName = $sub->zoho_addon_name;
            $description = 'Circle Subscription';
            if ($circleName && $addonName) {
                $description = "{$circleName} ({$addonName})";
            } elseif ($circleName) {
                $description = "Subscription for {$circleName}";
            } elseif ($addonName) {
                $description = "Circle Subscription - {$addonName}";
            }

            $items->push([
                'id' => $sub->id,
                'user_id' => $sub->user_id,
                'membership_plan_id' => $sub->zoho_addon_id,
                'starts_at' => $startsAt ? $startsAt->format('Y-m-d\TH:i:s') : null,
                'ends_at' => $endsAt ? $endsAt->format('Y-m-d\TH:i:s') : null,
                'status' => $sub->status,
                'payment_id' => $sub->zoho_payment_id ?? $sub->zoho_invoice_id ?? $sub->id,
                'membership_plan' => [
                    'id' => $sub->zoho_addon_id ?? $sub->circle_id,
                    'name' => $description,
                ],
                'payment' => [
                    'id' => $sub->zoho_payment_id ?? $sub->zoho_invoice_id ?? $sub->id,
                    'status' => in_array(strtolower((string) $sub->status), ['paid', 'success', 'completed', 'active']) ? 'paid' : $sub->status,
                ],
                'circle_payment' => 'yes',
                'is_circle_payment' => true,
            ]);
        }

        // 3. Sort by starts_at descending
        $sortedItems = $items->sortByDesc('starts_at')->values()->all();

        return $this->success($sortedItems, 'User subscriptions fetched successfully.');
    }
}
