<?php

declare(strict_types=1);

namespace App\Services\Membership;

use App\Models\Circle;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Support\Zoho\ZohoBillingClient;
use App\Support\Zoho\ZohoBillingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MembershipZohoInvoiceService
{
    public function __construct(
        private readonly ZohoBillingClient $zohoBillingClient,
        private readonly ZohoBillingService $zohoBillingService,
    ) {}

    /**
     * Find existing Zoho customer by email or create a new one.
     */
    public function findOrCreateZohoCustomer(User $user, ?string $gstNumber = null): ?string
    {
        $existingCustomerId = trim((string) ($user->zoho_customer_id ?? ''));
        $gstNumber = trim((string) ($gstNumber ?: $user->gst_number ?: ''));
        $email = trim((string) ($user->email ?? ''));

        $name = trim((string) ($user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))));
        if ($name === '') {
            $name = $user->company_name ?: ($email !== '' ? $email : 'Customer');
        }

        $phone = trim((string) ($user->phone ?? ''));
        $companyName = trim((string) ($user->company_name ?: ($gstNumber !== '' ? $name : '')));

        $syncPayload = [
            'display_name' => $name,
            'first_name' => (string) ($user->first_name ?: $name),
            'last_name' => (string) ($user->last_name ?? ''),
        ];
        if ($companyName !== '') {
            $syncPayload['company_name'] = $companyName;
        }
        if ($phone !== '') {
            $syncPayload['phone'] = $phone;
            $syncPayload['mobile'] = $phone;
        }
        if ($gstNumber !== '') {
            $syncPayload['gst_no'] = $gstNumber;
            $syncPayload['gst_treatment'] = 'business_gst';
        }

        if ($existingCustomerId !== '') {
            // Always keep customer display name and GST updated to match database
            try {
                $this->zohoBillingClient->request('PUT', '/customers/'.$existingCustomerId, $syncPayload);
                Log::info('Updated Zoho customer details', [
                    'customer_id' => $existingCustomerId,
                    'display_name' => $name,
                    'gst_no' => $gstNumber,
                ]);
            } catch (Throwable $e) {
                Log::warning('Could not update details on existing Zoho customer', [
                    'customer_id' => $existingCustomerId,
                    'error' => $e->getMessage(),
                ]);
            }

            return $existingCustomerId;
        }

        if ($email === '') {
            Log::warning('Cannot find or create Zoho customer: User email is empty', ['user_id' => $user->id]);

            return null;
        }

        try {
            // Search Zoho by email first
            $search = $this->zohoBillingClient->request('GET', '/customers', ['email' => $email], true);
            $customers = $search['customers'] ?? [];

            foreach ($customers as $customer) {
                if (strtolower(trim((string) ($customer['email'] ?? ''))) === strtolower($email)) {
                    $foundId = (string) ($customer['customer_id'] ?? '');
                    if ($foundId !== '') {
                        $user->forceFill(['zoho_customer_id' => $foundId])->save();
                        Log::info('Found existing Zoho customer by email', ['user_id' => $user->id, 'customer_id' => $foundId]);

                        try {
                            $this->zohoBillingClient->request('PUT', '/customers/'.$foundId, $syncPayload);
                            Log::info('Updated Zoho customer details for found customer', [
                                'customer_id' => $foundId,
                                'display_name' => $name,
                                'gst_no' => $gstNumber,
                            ]);
                        } catch (Throwable $e) {
                            Log::warning('Could not update details on found Zoho customer', [
                                'customer_id' => $foundId,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        return $foundId;
                    }
                }
            }

            // Customer does not exist in Zoho, create new customer
            $createPayload = array_merge($syncPayload, [
                'email' => $email,
                'billing_address' => [
                    'city' => (string) ($user->city ?? ''),
                    'state' => '',
                ],
            ]);

            if ($gstNumber !== '') {
                $createPayload['gst_no'] = $gstNumber;
                $createPayload['gst_treatment'] = 'business_gst';
            } else {
                $createPayload['gst_treatment'] = 'business_none';
            }

            $createResponse = $this->zohoBillingClient->request('POST', '/customers', $createPayload);
            $newCustomerId = (string) (data_get($createResponse, 'customer.customer_id') ?? data_get($createResponse, 'customer_id') ?? '');

            if ($newCustomerId !== '') {
                $user->forceFill(['zoho_customer_id' => $newCustomerId])->save();
                Log::info('Created new Zoho customer for user', ['user_id' => $user->id, 'customer_id' => $newCustomerId]);

                return $newCustomerId;
            }

            Log::error('Zoho customer creation returned empty customer ID', ['user_id' => $user->id, 'response' => $createResponse]);

            return null;
        } catch (Throwable $e) {
            Log::error('Error in findOrCreateZohoCustomer', [
                'user_id' => $user->id,
                'email' => $email,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create an invoice in Zoho Billing and mark it as PAID by recording payment.
     */
    public function createPaidInvoiceForMembership(User $user, MembershipPlan $plan, Payment $payment): ?array
    {
        $gstNumber = trim((string) ($payment->gst_number ?: $user->gst_number ?: ''));
        $customerId = $this->findOrCreateZohoCustomer($user, $gstNumber);
        if (! $customerId) {
            Log::warning('Zoho invoice creation skipped: Could not resolve Zoho customer ID', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
            ]);

            return [
                'synced' => false,
                'status' => 'pending',
                'invoice_id' => null,
                'invoice_number' => null,
                'invoice_url' => null,
                'invoice_pdf_url' => null,
                'error' => 'Failed to find or create customer in Zoho Billing.',
            ];
        }

        $existingInvoiceId = trim((string) ($payment->zoho_invoice_id ?? ''));
        if ($existingInvoiceId !== '') {
            Log::info('Zoho invoice already exists for payment, verifying and applying payment', [
                'payment_id' => $payment->id,
                'invoice_id' => $existingInvoiceId,
            ]);

            return $this->applyPaymentToExistingInvoice(
                $user,
                $payment,
                $customerId,
                $existingInvoiceId,
                $gstNumber,
                'Razorpay payment for Membership '.$plan->name,
                (float) ($payment->total_amount > 0 ? $payment->total_amount : $plan->price)
            );
        }

        $amount = (float) ($payment->total_amount > 0 ? $payment->total_amount : $plan->price);
        $referenceNumber = (string) ($payment->razorpay_payment_id ?: $payment->id);
        $description = 'Membership Plan: '.$plan->name.($plan->duration_months ? ' ('.$plan->duration_months.' months)' : '');

        $notes = 'Paid via Razorpay: '.$referenceNumber;
        if ($gstNumber !== '') {
            $notes .= ' | GSTIN: '.$gstNumber;
        }

        $invoicePayload = [
            'customer_id' => $customerId,
            'date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'currency_code' => 'INR',
            'reference_number' => $referenceNumber,
            'invoice_items' => [[
                'name' => 'Membership - '.$plan->name,
                'description' => $description,
                'rate' => $amount,
                'price' => $amount,
                'quantity' => 1,
            ]],
            'notes' => $notes,
            'terms' => 'Thank you for your subscription.',
        ];

        if ($gstNumber !== '') {
            $invoicePayload['gst_no'] = $gstNumber;
            $invoicePayload['gst_treatment'] = 'business_gst';
        } else {
            $invoicePayload['gst_treatment'] = 'business_none';
        }

        try {
            Log::info('Creating Zoho invoice for membership payment', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'payload' => $invoicePayload,
            ]);

            $response = $this->zohoBillingClient->request('POST', '/invoices', $invoicePayload);
            $invoice = is_array($response['invoice'] ?? null) ? $response['invoice'] : $response;
            $invoiceId = (string) data_get($invoice, 'invoice_id', '');

            if ($invoiceId === '') {
                Log::error('Zoho invoice creation returned empty invoice ID', [
                    'payment_id' => $payment->id,
                    'response' => $response,
                ]);

                return [
                    'synced' => false,
                    'status' => 'pending',
                    'invoice_id' => null,
                    'invoice_number' => null,
                    'invoice_url' => null,
                    'invoice_pdf_url' => null,
                    'error' => 'Zoho returned an empty invoice ID.',
                ];
            }

            $invoiceNumber = (string) (data_get($invoice, 'invoice_number') ?? data_get($invoice, 'number') ?? '');
            $invoiceUrl = data_get($invoice, 'invoice_url') ?? data_get($invoice, 'url');
            $invoicePdfUrl = data_get($invoice, 'invoice_pdf_url') ?? data_get($invoice, 'pdf_url');
            $invoiceStatus = strtolower((string) data_get($invoice, 'status', ''));

            Log::info('Zoho invoice created successfully', [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'initial_status' => $invoiceStatus,
            ]);

            // Convert to open if draft
            if ($invoiceStatus === 'draft') {
                try {
                    $this->zohoBillingClient->postZohoAction('/invoices/'.$invoiceId.'/converttoopen', []);
                    Log::info('Zoho invoice converted to open', ['invoice_id' => $invoiceId]);
                } catch (Throwable $e) {
                    Log::warning('Zoho convert to open notice', [
                        'invoice_id' => $invoiceId,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            // Determine exact balance to apply
            $balanceToPay = (float) (data_get($invoice, 'balance') ?? data_get($invoice, 'total') ?? $amount);
            if ($balanceToPay <= 0) {
                try {
                    $refreshed = $this->zohoBillingClient->request('GET', '/invoices/'.$invoiceId);
                    $balanceToPay = (float) data_get($refreshed, 'invoice.balance', $amount);
                } catch (Throwable $e) {
                    $balanceToPay = $amount;
                }
            }

            // Apply payment to make invoice status 'paid'
            $paymentPayload = [
                'customer_id' => $customerId,
                'payment_mode' => 'others',
                'amount' => $balanceToPay,
                'date' => now()->toDateString(),
                'reference_number' => $referenceNumber,
                'description' => 'Razorpay payment for Membership '.$plan->name.' | ref: '.$referenceNumber,
                'invoices' => [[
                    'invoice_id' => $invoiceId,
                    'amount_applied' => $balanceToPay,
                ]],
            ];

            try {
                $this->zohoBillingService->createPaymentForInvoice($paymentPayload);
                Log::info('Payment recorded in Zoho for invoice', [
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                ]);
            } catch (Throwable $e) {
                Log::error('Failed to apply payment to Zoho invoice', [
                    'invoice_id' => $invoiceId,
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Update Payment record
            $paymentUpdates = [
                'zoho_invoice_id' => $invoiceId,
                'provider' => 'razorpay',
            ];
            if (Schema::hasColumn('payments', 'zoho_payment_id')) {
                $paymentUpdates['zoho_payment_id'] = $referenceNumber;
            }
            $payment->update($paymentUpdates);

            // Update User record
            $userUpdates = [
                'zoho_customer_id' => $customerId,
                'zoho_last_invoice_id' => $invoiceId,
            ];
            if ($gstNumber !== '' && empty($user->gst_number)) {
                $userUpdates['gst_number'] = $gstNumber;
            }
            $user->forceFill($userUpdates)->save();

            return [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'invoice_url' => $invoiceUrl,
                'invoice_pdf_url' => $invoicePdfUrl,
                'status' => 'paid',
            ];
        } catch (Throwable $e) {
            Log::error('Failed to create paid Zoho invoice for membership', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'synced' => false,
                'status' => 'pending',
                'invoice_id' => null,
                'invoice_number' => null,
                'invoice_url' => null,
                'invoice_pdf_url' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create an invoice in Zoho Billing for a Circle Join fee and mark it as PAID.
     */
    public function createPaidInvoiceForCircle(User $user, Circle $circle, ?MembershipPlan $plan, Payment $payment): ?array
    {
        $gstNumber = trim((string) ($payment->gst_number ?: $user->gst_number ?: ''));
        $customerId = $this->findOrCreateZohoCustomer($user, $gstNumber);
        if (! $customerId) {
            Log::warning('Zoho invoice creation skipped for circle: Could not resolve Zoho customer ID', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'circle_id' => $circle->id,
            ]);

            return [
                'synced' => false,
                'status' => 'pending',
                'invoice_id' => null,
                'invoice_number' => null,
                'invoice_url' => null,
                'invoice_pdf_url' => null,
                'error' => 'Failed to find or create customer in Zoho Billing.',
            ];
        }

        $existingInvoiceId = trim((string) ($payment->zoho_invoice_id ?? ''));
        if ($existingInvoiceId !== '') {
            Log::info('Zoho invoice already exists for circle payment, verifying and applying payment', [
                'payment_id' => $payment->id,
                'invoice_id' => $existingInvoiceId,
            ]);

            return $this->applyPaymentToExistingInvoice(
                $user,
                $payment,
                $customerId,
                $existingInvoiceId,
                $gstNumber,
                'Razorpay payment for Circle Join '.$circle->name,
                (float) ($payment->total_amount > 0 ? $payment->total_amount : ($payment->amount > 0 ? $payment->amount : 15000.00))
            );
        }

        $amount = (float) ($payment->total_amount > 0 ? $payment->total_amount : ($payment->amount > 0 ? $payment->amount : ($plan?->price ?: ($circle->circle_price_amount ?: 15000.00))));
        $currency = strtoupper((string) ($payment->currency ?: ($circle->circle_price_currency ?: 'INR')));
        $referenceNumber = (string) ($payment->razorpay_payment_id ?: $payment->id);
        $planTitle = $plan ? ' | Plan: '.$plan->name : '';
        $description = 'Circle Join Fee: '.$circle->name.$planTitle;

        $notes = 'Paid via Razorpay: '.$referenceNumber;
        if ($gstNumber !== '') {
            $notes .= ' | GSTIN: '.$gstNumber;
        }

        $invoicePayload = [
            'customer_id' => $customerId,
            'date' => now()->toDateString(),
            'due_date' => now()->addDay()->toDateString(),
            'currency_code' => $currency,
            'reference_number' => $referenceNumber,
            'invoice_items' => [[
                'name' => 'Circle Join - '.$circle->name,
                'description' => $description,
                'rate' => $amount,
                'price' => $amount,
                'quantity' => 1,
            ]],
            'notes' => $notes,
            'terms' => 'Thank you for joining the circle.',
        ];

        if ($gstNumber !== '') {
            $invoicePayload['gst_no'] = $gstNumber;
            $invoicePayload['gst_treatment'] = 'business_gst';
        } else {
            $invoicePayload['gst_treatment'] = 'business_none';
        }

        try {
            Log::info('Creating Zoho invoice for circle join payment', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'circle_id' => $circle->id,
                'payload' => $invoicePayload,
            ]);

            $response = $this->zohoBillingClient->request('POST', '/invoices', $invoicePayload);
            $invoice = is_array($response['invoice'] ?? null) ? $response['invoice'] : $response;
            $invoiceId = (string) data_get($invoice, 'invoice_id', '');

            if ($invoiceId === '') {
                Log::error('Zoho invoice creation returned empty invoice ID for circle join', [
                    'payment_id' => $payment->id,
                    'response' => $response,
                ]);

                return [
                    'synced' => false,
                    'status' => 'pending',
                    'invoice_id' => null,
                    'invoice_number' => null,
                    'invoice_url' => null,
                    'invoice_pdf_url' => null,
                    'error' => 'Zoho returned an empty invoice ID.',
                ];
            }

            $invoiceNumber = (string) (data_get($invoice, 'invoice_number') ?? data_get($invoice, 'number') ?? '');
            $invoiceUrl = data_get($invoice, 'invoice_url') ?? data_get($invoice, 'url');
            $invoicePdfUrl = data_get($invoice, 'invoice_pdf_url') ?? data_get($invoice, 'pdf_url');
            $invoiceStatus = strtolower((string) data_get($invoice, 'status', ''));

            Log::info('Zoho invoice created successfully for circle join', [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'initial_status' => $invoiceStatus,
            ]);

            // Convert to open if draft
            if ($invoiceStatus === 'draft') {
                try {
                    $this->zohoBillingClient->postZohoAction('/invoices/'.$invoiceId.'/converttoopen', []);
                    Log::info('Zoho invoice converted to open for circle join', ['invoice_id' => $invoiceId]);
                } catch (Throwable $e) {
                    Log::warning('Zoho convert to open notice for circle join', [
                        'invoice_id' => $invoiceId,
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            // Determine exact balance to apply
            $balanceToPay = (float) (data_get($invoice, 'balance') ?? data_get($invoice, 'total') ?? $amount);
            if ($balanceToPay <= 0) {
                try {
                    $refreshed = $this->zohoBillingClient->request('GET', '/invoices/'.$invoiceId);
                    $balanceToPay = (float) data_get($refreshed, 'invoice.balance', $amount);
                } catch (Throwable $e) {
                    $balanceToPay = $amount;
                }
            }

            // Apply payment to make invoice status 'paid'
            $paymentPayload = [
                'customer_id' => $customerId,
                'payment_mode' => 'others',
                'amount' => $balanceToPay,
                'date' => now()->toDateString(),
                'reference_number' => $referenceNumber,
                'description' => 'Razorpay payment for Circle Join '.$circle->name.' | ref: '.$referenceNumber,
                'invoices' => [[
                    'invoice_id' => $invoiceId,
                    'amount_applied' => $balanceToPay,
                ]],
            ];

            try {
                $this->zohoBillingService->createPaymentForInvoice($paymentPayload);
                Log::info('Payment recorded in Zoho for circle invoice', [
                    'invoice_id' => $invoiceId,
                    'amount' => $amount,
                ]);
            } catch (Throwable $e) {
                Log::error('Failed to apply payment to Zoho circle invoice', [
                    'invoice_id' => $invoiceId,
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Update Payment record
            $paymentUpdates = [
                'zoho_invoice_id' => $invoiceId,
                'provider' => 'razorpay',
            ];
            if (Schema::hasColumn('payments', 'zoho_payment_id')) {
                $paymentUpdates['zoho_payment_id'] = $referenceNumber;
            }
            $payment->update($paymentUpdates);

            // Update User record
            $userUpdates = [
                'zoho_customer_id' => $customerId,
                'zoho_last_invoice_id' => $invoiceId,
            ];
            if ($gstNumber !== '' && empty($user->gst_number)) {
                $userUpdates['gst_number'] = $gstNumber;
            }
            $user->forceFill($userUpdates)->save();

            return [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'invoice_url' => $invoiceUrl,
                'invoice_pdf_url' => $invoicePdfUrl,
                'status' => 'paid',
            ];
        } catch (Throwable $e) {
            Log::error('Failed to create paid Zoho invoice for circle join', [
                'user_id' => $user->id,
                'payment_id' => $payment->id,
                'circle_id' => $circle->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'synced' => false,
                'status' => 'pending',
                'invoice_id' => null,
                'invoice_number' => null,
                'invoice_url' => null,
                'invoice_pdf_url' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function applyPaymentToExistingInvoice(
        User $user,
        Payment $payment,
        string $customerId,
        string $invoiceId,
        string $gstNumber,
        string $description,
        float $fallbackAmount
    ): array {
        try {
            $resp = $this->zohoBillingClient->request('GET', '/invoices/'.$invoiceId);
            $invoice = is_array($resp['invoice'] ?? null) ? $resp['invoice'] : $resp;
            $status = strtolower((string) data_get($invoice, 'status', ''));
            $balance = (float) (data_get($invoice, 'balance') ?? data_get($invoice, 'balance_due') ?? 0);

            if ($status === 'draft') {
                try {
                    $this->zohoBillingClient->postZohoAction('/invoices/'.$invoiceId.'/converttoopen', []);
                    Log::info('Existing Zoho invoice converted to open', ['invoice_id' => $invoiceId]);
                } catch (Throwable $e) {
                    Log::warning('Zoho convert to open notice for existing invoice', [
                        'invoice_id' => $invoiceId,
                        'message' => $e->getMessage(),
                    ]);
                }
                $resp = $this->zohoBillingClient->request('GET', '/invoices/'.$invoiceId);
                $invoice = is_array($resp['invoice'] ?? null) ? $resp['invoice'] : $resp;
                $status = strtolower((string) data_get($invoice, 'status', ''));
                $balance = (float) (data_get($invoice, 'balance') ?? data_get($invoice, 'balance_due') ?? 0);
            }

            $referenceNumber = (string) ($payment->razorpay_payment_id ?: $payment->id);
            if ($balance > 0 && ! in_array($status, ['paid', 'closed'], true)) {
                $paymentPayload = [
                    'customer_id' => $customerId,
                    'payment_mode' => 'others',
                    'amount' => $balance,
                    'date' => optional($payment->paid_at)->toDateString() ?: now()->toDateString(),
                    'reference_number' => $referenceNumber,
                    'description' => $description.' | ref: '.$referenceNumber,
                    'invoices' => [[
                        'invoice_id' => $invoiceId,
                        'amount_applied' => $balance,
                    ]],
                ];

                try {
                    $this->zohoBillingService->createPaymentForInvoice($paymentPayload);
                    Log::info('Payment recorded in Zoho for existing invoice', [
                        'invoice_id' => $invoiceId,
                        'amount' => $balance,
                    ]);
                } catch (Throwable $e) {
                    Log::error('Failed to apply payment to existing Zoho invoice', [
                        'invoice_id' => $invoiceId,
                        'customer_id' => $customerId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $resp = $this->zohoBillingClient->request('GET', '/invoices/'.$invoiceId);
                $invoice = is_array($resp['invoice'] ?? null) ? $resp['invoice'] : $resp;
                $status = strtolower((string) data_get($invoice, 'status', ''));
                $balance = (float) (data_get($invoice, 'balance') ?? data_get($invoice, 'balance_due') ?? 0);
            }

            $invoiceNumber = (string) (data_get($invoice, 'invoice_number') ?? data_get($invoice, 'number') ?? '');
            $invoiceUrl = data_get($invoice, 'invoice_url') ?? data_get($invoice, 'url');
            $invoicePdfUrl = data_get($invoice, 'invoice_pdf_url') ?? data_get($invoice, 'pdf_url');

            $paymentUpdates = [
                'zoho_invoice_id' => $invoiceId,
                'provider' => 'razorpay',
            ];
            if (Schema::hasColumn('payments', 'zoho_payment_id')) {
                $paymentUpdates['zoho_payment_id'] = $referenceNumber;
            }
            $payment->update($paymentUpdates);

            $userUpdates = [
                'zoho_customer_id' => $customerId,
                'zoho_last_invoice_id' => $invoiceId,
            ];
            if ($gstNumber !== '' && empty($user->gst_number)) {
                $userUpdates['gst_number'] = $gstNumber;
            }
            $user->forceFill($userUpdates)->save();

            $isPaid = in_array($status, ['paid', 'closed'], true) || $balance <= 0;

            return [
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'invoice_url' => $invoiceUrl,
                'invoice_pdf_url' => $invoicePdfUrl,
                'status' => $isPaid ? 'paid' : $status,
            ];
        } catch (Throwable $e) {
            Log::error('Failed to process existing Zoho invoice', [
                'invoice_id' => $invoiceId,
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'invoice_id' => $invoiceId,
                'status' => 'pending',
                'error' => $e->getMessage(),
            ];
        }
    }
}
