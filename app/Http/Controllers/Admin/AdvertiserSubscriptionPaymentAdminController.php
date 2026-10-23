<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdvertiserPayment;
use App\Models\AdvertiserSubscription;
use App\Services\Advertiser\AdvertiserPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdvertiserSubscriptionPaymentAdminController extends Controller
{
    public function __construct(
        protected AdvertiserPaymentService $paymentService
    ) {}

    public function subscriptions(Request $request): View
    {
        $query = AdvertiserSubscription::with(['application', 'brandPartner', 'package', 'user'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->whereHas('application', function ($sub) use ($q) {
                $sub->where('company_name', 'ILIKE', "%{$q}%")
                    ->orWhere('application_number', 'ILIKE', "%{$q}%");
            });
        }

        $subscriptions = $query->paginate(20)->appends($request->query());

        $counts = [
            'total' => AdvertiserSubscription::count(),
            'active' => AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_ACTIVE)->count(),
            'pending_payment' => AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_PENDING_PAYMENT)->count(),
            'expired' => AdvertiserSubscription::where('status', AdvertiserSubscription::STATUS_EXPIRED)->count(),
        ];

        return view('admin.advertiser.subscriptions.index', compact('subscriptions', 'counts'));
    }

    public function subscriptionShow(AdvertiserSubscription $subscription): View
    {
        $subscription->loadMissing([
            'application.assets',
            'brandPartner',
            'package',
            'payments',
            'user',
        ]);

        return view('admin.advertiser.subscriptions.show', compact('subscription'));
    }

    public function payments(Request $request): View
    {
        $query = AdvertiserPayment::with(['subscription.application'])->orderByDesc('created_at');

        if ($request->filled('status')) {
            $status = (string) $request->query('status');
            if ($status === 'pending') {
                $query->whereIn('status', [AdvertiserPayment::STATUS_CREATED, AdvertiserPayment::STATUS_PENDING]);
            } else {
                $query->where('status', $status);
            }
        }

        if ($request->filled('q')) {
            $q = trim((string) $request->query('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('provider_order_id', 'ILIKE', "%{$q}%")
                    ->orWhere('provider_payment_id', 'ILIKE', "%{$q}%")
                    ->orWhereHas('subscription.application', function ($appQ) use ($q) {
                        $appQ->where('company_name', 'ILIKE', "%{$q}%")
                            ->orWhere('application_number', 'ILIKE', "%{$q}%");
                    });
            });
        }

        $payments = $query->paginate(20)->appends($request->query());

        $counts = [
            'total' => AdvertiserPayment::count(),
            'captured' => AdvertiserPayment::where('status', AdvertiserPayment::STATUS_CAPTURED)->count(),
            'pending' => AdvertiserPayment::whereIn('status', [AdvertiserPayment::STATUS_CREATED, AdvertiserPayment::STATUS_PENDING])->count(),
            'failed' => AdvertiserPayment::where('status', AdvertiserPayment::STATUS_FAILED)->count(),
            'total_captured_amount' => (float) AdvertiserPayment::where('status', AdvertiserPayment::STATUS_CAPTURED)->sum('amount'),
        ];

        return view('admin.advertiser.payments.index', compact('payments', 'counts'));
    }

    public function paymentShow(AdvertiserPayment $payment): View
    {
        $payment->loadMissing(['subscription.application', 'subscription.brandPartner']);

        return view('admin.advertiser.payments.show', compact('payment'));
    }

    public function reconcile(Request $request, AdvertiserPayment $payment): RedirectResponse
    {
        $reconciled = $this->paymentService->reconcilePayment($payment);

        return redirect()->back()->with('success', "Payment reconciliation completed. Status: {$reconciled->status}");
    }
}
