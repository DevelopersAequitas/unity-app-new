<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Billing\InvoiceDetailResource;
use App\Http\Resources\Billing\InvoiceListItemResource;
use App\Models\Payment;
use App\Models\User;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class InvoiceController extends BaseApiController
{
    public function __construct(private readonly ZohoBillingService $zohoBillingService) {}

    /**
     * List user invoices, returning both Membership and Circle Package invoices.
     *
     * GET /api/v1/billing/invoices
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthorized.', 401);
        }

        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 20);

        $combinedInvoices = new Collection;
        $matchedZohoInvoiceIds = [];

        // 1. Fetch local completed payments (both membership and circle_package)
        try {
            $payments = Payment::query()
                ->where('user_id', $user->id)
                ->whereIn('status', [Payment::STATUS_SUCCESS, 'paid', 'captured'])
                ->with(['plan', 'circle'])
                ->orderByDesc('paid_at')
                ->orderByDesc('created_at')
                ->get();

            foreach ($payments as $payment) {
                $isCircle = ($payment->payment_type === Payment::TYPE_CIRCLE_PACKAGE)
                    || ($payment->circle_id && ! $payment->membership_plan_id);

                $packageName = $isCircle
                    ? ($payment->circle?->zoho_addon_name ?: ($payment->circle?->name ? $payment->circle->name.' Package' : 'Circle Package'))
                    : ($payment->plan?->name ?: 'Membership Subscription');

                $baseAmount = (float) ($payment->base_amount ?? $payment->amount);
                $gstPercent = (float) ($payment->gst_percent ?? 18.00);
                $gstAmount = (float) ($payment->gst_amount ?? round($baseAmount * ($gstPercent / 100), 2));
                $totalAmount = (float) ($payment->total_amount ?? round($baseAmount + $gstAmount, 2));

                $invoiceNumber = $payment->zoho_invoice_id ?: ('INV-'.substr(str_replace('-', '', (string) $payment->id), 0, 8));

                if (! empty($payment->zoho_invoice_id)) {
                    $matchedZohoInvoiceIds[] = (string) $payment->zoho_invoice_id;
                }

                $combinedInvoices->push([
                    'invoice_id' => (string) ($payment->zoho_invoice_id ?: $payment->id),
                    'invoice_number' => $invoiceNumber,
                    'date' => $payment->paid_at?->toDateString() ?: $payment->created_at?->toDateString(),
                    'due_date' => $payment->paid_at?->toDateString() ?: $payment->created_at?->toDateString(),
                    'status' => 'paid',
                    'payment_status' => 'paid',
                    'currency_code' => $payment->currency ?: 'INR',
                    'total' => $totalAmount,
                    'balance' => 0.00,
                    'customer_name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                    'subscription_id' => $payment->zoho_subscription_id,
                    'invoice_url' => null,
                    'pdf_url' => url('/api/v1/billing/invoices/'.($payment->zoho_invoice_id ?: $payment->id).'/pdf'),
                    'created_time' => $payment->created_at?->toIso8601String(),

                    // Unified payment fields
                    'payment_reference' => (string) ($payment->razorpay_payment_id ?: $payment->id),
                    'payment_id' => (string) ($payment->razorpay_payment_id ?: $payment->id),
                    'order_id' => (string) ($payment->razorpay_order_id ?? ''),
                    'payment_type' => $isCircle ? 'circle_package' : 'membership',
                    'package_name' => $packageName,
                    'circle_name' => $payment->circle?->name,
                    'circle_id' => $payment->circle_id ? (string) $payment->circle_id : null,
                    'base_amount' => $baseAmount,
                    'gst_percent' => $gstPercent,
                    'gst_amount' => $gstAmount,
                    'total_amount' => $totalAmount,
                    'payment_date' => $payment->paid_at?->toIso8601String(),
                    'user_id' => (string) $user->id,
                ]);
            }
        } catch (Throwable $e) {
            Log::warning('Error fetching local payments for invoice listing', ['error' => $e->getMessage()]);
        }

        // 2. Fetch Zoho invoices if mapped
        if ($this->zohoBillingService->hasUserZohoMapping($user)) {
            try {
                $result = $this->zohoBillingService->listInvoicesForUser($user, 1, 100);
                $zohoInvoices = is_array($result['invoices'] ?? null) ? $result['invoices'] : [];

                foreach ($zohoInvoices as $zi) {
                    $zId = (string) ($zi['invoice_id'] ?? '');
                    if ($zId !== '' && in_array($zId, $matchedZohoInvoiceIds, true)) {
                        continue;
                    }

                    $total = (float) ($zi['total'] ?? 0);
                    $combinedInvoices->push([
                        'invoice_id' => $zId,
                        'invoice_number' => $zi['invoice_number'] ?? $zId,
                        'date' => $zi['date'] ?? null,
                        'due_date' => $zi['due_date'] ?? null,
                        'status' => $zi['status'] ?? 'paid',
                        'payment_status' => $zi['status'] ?? 'paid',
                        'currency_code' => $zi['currency_code'] ?? 'INR',
                        'total' => $total,
                        'balance' => (float) ($zi['balance'] ?? 0),
                        'customer_name' => $zi['customer_name'] ?? ($user->display_name ?: 'Customer'),
                        'subscription_id' => $zi['subscription_id'] ?? null,
                        'invoice_url' => $zi['invoice_url'] ?? null,
                        'pdf_url' => $zi['pdf_url'] ?? url('/api/v1/billing/invoices/'.$zId.'/pdf'),
                        'created_time' => $zi['created_time'] ?? null,
                        'payment_reference' => $zi['invoice_number'] ?? $zId,
                        'payment_id' => null,
                        'order_id' => null,
                        'payment_type' => 'membership',
                        'package_name' => 'Membership Subscription',
                        'circle_name' => null,
                        'circle_id' => null,
                        'base_amount' => round($total / 1.18, 2),
                        'gst_percent' => 18.00,
                        'gst_amount' => round($total - ($total / 1.18), 2),
                        'total_amount' => $total,
                        'payment_date' => $zi['date'] ?? null,
                        'user_id' => (string) $user->id,
                    ]);
                }
            } catch (Throwable $throwable) {
                Log::info('Zoho invoice listing notice', ['message' => $throwable->getMessage()]);
            }
        }

        $totalCount = $combinedInvoices->count();
        $pagedItems = $combinedInvoices->slice(($page - 1) * $perPage, $perPage)->values();

        return $this->success([
            'items' => InvoiceListItemResource::collection($pagedItems),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'has_more_page' => ($page * $perPage) < $totalCount,
                'total' => $totalCount,
            ],
        ]);
    }

    /**
     * Show detailed invoice by ID.
     *
     * GET /api/v1/billing/invoices/{invoiceId}
     */
    public function show(Request $request, string $invoiceId)
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthorized.', 401);
        }

        // 1. Check local payment record first
        $payment = Payment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Payment::STATUS_SUCCESS, 'paid', 'captured'])
            ->where(function ($q) use ($invoiceId): void {
                if (Str::isUuid($invoiceId)) {
                    $q->where('id', $invoiceId)
                        ->orWhere('zoho_invoice_id', $invoiceId)
                        ->orWhere('razorpay_order_id', $invoiceId)
                        ->orWhere('razorpay_payment_id', $invoiceId);
                } else {
                    $q->where('zoho_invoice_id', $invoiceId)
                        ->orWhere('razorpay_order_id', $invoiceId)
                        ->orWhere('razorpay_payment_id', $invoiceId);
                }
            })
            ->with(['plan', 'circle'])
            ->first();

        if ($payment) {
            $isCircle = ($payment->payment_type === Payment::TYPE_CIRCLE_PACKAGE)
                || ($payment->circle_id && ! $payment->membership_plan_id);

            $packageName = $isCircle
                ? ($payment->circle?->zoho_addon_name ?: ($payment->circle?->name ? $payment->circle->name.' Package' : 'Circle Package'))
                : ($payment->plan?->name ?: 'Membership Subscription');

            $baseAmount = (float) ($payment->base_amount ?? $payment->amount);
            $gstPercent = (float) ($payment->gst_percent ?? 18.00);
            $gstAmount = (float) ($payment->gst_amount ?? round($baseAmount * ($gstPercent / 100), 2));
            $totalAmount = (float) ($payment->total_amount ?? round($baseAmount + $gstAmount, 2));
            $invoiceNumber = $payment->zoho_invoice_id ?: ('INV-'.substr(str_replace('-', '', (string) $payment->id), 0, 8));

            $invoiceData = [
                'invoice_id' => (string) ($payment->zoho_invoice_id ?: $payment->id),
                'invoice_number' => $invoiceNumber,
                'date' => $payment->paid_at?->toDateString() ?: $payment->created_at?->toDateString(),
                'due_date' => $payment->paid_at?->toDateString() ?: $payment->created_at?->toDateString(),
                'status' => 'paid',
                'payment_status' => 'paid',
                'currency_code' => $payment->currency ?: 'INR',
                'customer_name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'customer_id' => $user->zoho_customer_id ?: (string) $user->id,
                'subscription_id' => $payment->zoho_subscription_id,
                'payment_type' => $isCircle ? Payment::TYPE_CIRCLE_PACKAGE : Payment::TYPE_MEMBERSHIP,
                'payment_id' => (string) ($payment->razorpay_payment_id ?: $payment->id),
                'order_id' => (string) ($payment->razorpay_order_id ?? ''),
                'base_amount' => $baseAmount,
                'gst_percent' => $gstPercent,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'circle_id' => $payment->circle_id ? (string) $payment->circle_id : null,
                'circle_name' => $payment->circle?->name,
                'package_name' => $packageName,
                'billing_address' => [
                    'attention' => $user->display_name,
                    'address' => $user->address,
                    'city' => $user->city,
                    'country' => 'India',
                ],
                'line_items' => [[
                    'line_item_id' => (string) $payment->id,
                    'name' => $packageName,
                    'description' => $isCircle ? ('Circle package fee for '.($payment->circle?->name ?: 'Circle')) : ('Subscription for '.$packageName),
                    'quantity' => 1,
                    'rate' => $baseAmount,
                    'item_total' => $baseAmount,
                    'tax_name' => 'GST',
                    'tax_amount' => $gstAmount,
                ]],
                'subtotal' => $baseAmount,
                'sub_total' => $baseAmount,
                'tax' => $gstAmount,
                'tax_total' => $gstAmount,
                'total' => $totalAmount,
                'balance' => 0.00,
                'notes' => 'Paid via Razorpay: '.($payment->razorpay_payment_id ?: $payment->id),
                'terms' => 'Thank you for your payment.',
                'invoice_url' => null,
                'pdf_url' => url('/api/v1/billing/invoices/'.($payment->zoho_invoice_id ?: $payment->id).'/pdf'),
                'created_time' => $payment->created_at?->toIso8601String(),
                'last_payment_date' => $payment->paid_at?->toDateString(),
            ];

            return $this->success(new InvoiceDetailResource($invoiceData));
        }

        // 2. Fall back to Zoho billing if mapped
        if (! $this->zohoBillingService->hasUserZohoMapping($user)) {
            return $this->error('Invoice not found.', 404);
        }

        try {
            $invoice = $this->zohoBillingService->getInvoiceForUser($user, $invoiceId);

            if (! is_array($invoice)) {
                return $this->error('Invoice not found.', 404);
            }

            return $this->success(new InvoiceDetailResource($invoice));
        } catch (RuntimeException $runtimeException) {
            if ((int) $runtimeException->getCode() === 404) {
                return $this->error('Invoice not found.', 404);
            }

            return $this->error('Failed to fetch invoice detail.', (int) $runtimeException->getCode() >= 400 ? (int) $runtimeException->getCode() : 500);
        } catch (Throwable $throwable) {
            return $this->error('Failed to fetch invoice detail.', 500);
        }
    }

    /**
     * Download or stream invoice PDF.
     *
     * GET /api/v1/billing/invoices/{invoiceId}/pdf
     */
    public function pdf(Request $request, string $invoiceId)
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return $this->error('Unauthorized.', 401);
        }

        if ($this->zohoBillingService->hasUserZohoMapping($user)) {
            try {
                $pdf = $this->zohoBillingService->getInvoicePdfForUser($user, $invoiceId);

                if (is_array($pdf) && (string) ($pdf['content'] ?? '') !== '') {
                    $invoiceNumber = (string) ($pdf['invoice_number'] ?? $invoiceId);
                    $safeInvoiceNumber = Str::of($invoiceNumber)->replaceMatches('/[^A-Za-z0-9\\-_]/', '-')->toString();
                    $filename = 'invoice-'.trim($safeInvoiceNumber, '-').'.pdf';

                    return response()->stream(
                        fn () => print ($pdf['content']),
                        200,
                        [
                            'Content-Type' => 'application/pdf',
                            'Content-Disposition' => 'inline; filename="'.$filename.'"',
                            'Content-Length' => (string) strlen((string) $pdf['content']),
                        ]
                    );
                }
            } catch (Throwable $e) {
                Log::info('Zoho invoice PDF fetch skipped', ['error' => $e->getMessage()]);
            }
        }

        // Return JSON fallback invoice receipt if binary PDF is not available in Zoho
        $payment = Payment::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [Payment::STATUS_SUCCESS, 'paid', 'captured'])
            ->where(function ($q) use ($invoiceId): void {
                if (Str::isUuid($invoiceId)) {
                    $q->where('id', $invoiceId)
                        ->orWhere('zoho_invoice_id', $invoiceId)
                        ->orWhere('razorpay_order_id', $invoiceId)
                        ->orWhere('razorpay_payment_id', $invoiceId);
                } else {
                    $q->where('zoho_invoice_id', $invoiceId)
                        ->orWhere('razorpay_order_id', $invoiceId)
                        ->orWhere('razorpay_payment_id', $invoiceId);
                }
            })
            ->first();

        if ($payment) {
            return response()->json([
                'success' => true,
                'message' => 'Invoice receipt data.',
                'data' => [
                    'invoice_number' => $payment->zoho_invoice_id ?: ('INV-'.substr($payment->id, 0, 8)),
                    'payment_id' => $payment->razorpay_payment_id ?: $payment->id,
                    'order_id' => $payment->razorpay_order_id,
                    'amount' => (float) $payment->total_amount,
                    'currency' => $payment->currency ?: 'INR',
                    'paid_at' => $payment->paid_at?->toIso8601String(),
                ],
            ]);
        }

        return $this->error('Invoice PDF not found.', 404);
    }
}
