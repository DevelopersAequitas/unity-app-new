<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\CircleSubscription;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class UserSubscriptionController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Fetch base membership payments
        $payments = Payment::query()
            ->with('plan')
            ->where('user_id', $user->id)
            ->whereIn('status', ['paid', 'success'])
            ->get();

        // 2. Fetch circle subscriptions
        $circleSubscriptions = CircleSubscription::query()
            ->with('circle')
            ->where('user_id', $user->id)
            ->whereIn('status', ['paid', 'success', 'completed', 'active'])
            ->get();

        $items = collect();

        // 3. Map base membership payments
        foreach ($payments as $payment) {
            $date = $payment->paid_at ?? $payment->created_at;
            $items->push([
                'date' => $date ? $date->toDateTimeString() : null,
                'date_formatted' => $date ? $date->format('d M Y, h:i A') : null,
                'description' => ($payment->plan?->name ? ($payment->plan->name . ' Membership') : 'Unity Peer Membership'),
                'payment_invoice' => $payment->razorpay_payment_id ?? $payment->id,
                'total' => $payment->total_amount !== null ? (float) $payment->total_amount : 0.00,
            ]);
        }

        // 4. Map circle subscriptions
        foreach ($circleSubscriptions as $sub) {
            $date = $sub->paid_at ?? $sub->started_at ?? $sub->created_at;
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
                'date' => $date ? $date->toDateTimeString() : null,
                'date_formatted' => $date ? $date->format('d M Y, h:i A') : null,
                'description' => $description,
                'payment_invoice' => $sub->zoho_invoice_id ?? $sub->zoho_payment_id ?? $sub->id,
                'total' => $sub->amount !== null ? (float) $sub->amount : 0.00,
            ]);
        }

        // 5. Sort by date descending
        $sortedItems = $items->sortByDesc('date')->values()->all();

        return $this->success($sortedItems, 'User subscriptions fetched successfully.');
    }
}
