<?php

namespace App\Services\Events;

use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EventRazorpayPaymentFinalizer
{
    public function __construct(
        private readonly EventRegistrationQrService $registrationQr,
        private readonly EventZohoInvoiceSyncService $zohoInvoices,
    ) {}

    public function markPaid(EventRegistration $registration, array $paymentData = []): EventRegistration
    {
        $registration = DB::transaction(function () use ($registration, $paymentData): EventRegistration {
            $locked = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);

            if (($locked->payment_status ?? null) !== 'paid') {
                $invoiceNumber = $locked->invoice_number ?? $locked->zoho_invoice_number;
                if (empty($invoiceNumber)) {
                    $invoiceNumber = 'INV-EVT-'.strtoupper(substr(str_replace('-', '', (string) $locked->id), 0, 8));
                }

                $locked->forceFill($this->filterRegistrationColumns([
                    'payment_status' => 'paid',
                    'status' => 'registered',
                    'payment_completed_at' => now(),
                    'payment_gateway' => 'razorpay',
                    'razorpay_payment_id' => $paymentData['razorpay_payment_id'] ?? $locked->razorpay_payment_id,
                    'razorpay_signature' => $paymentData['razorpay_signature'] ?? $locked->razorpay_signature,
                    'razorpay_payment_status' => $paymentData['razorpay_payment_status'] ?? 'captured',
                    'razorpay_paid_at' => $locked->razorpay_paid_at ?? now(),
                    'invoice_number' => $invoiceNumber,
                    'zoho_invoice_number' => $locked->zoho_invoice_number ?? $invoiceNumber,
                ]))->save();
                Log::info('payment_success_registration_updated', ['event_registration_id' => (string) $locked->id]);
            }

            $locked = $this->registrationQr->ensureQrGenerated($locked);

            if ($locked->user) {
                $userUpdates = [];
                if (in_array((string) $locked->user->membership_status, ['visitor', 'free_peer', ''], true)) {
                    $userUpdates['membership_status'] = 'free_trial_peer';
                }
                if (($locked->user->status ?? 'active') !== 'active') {
                    $userUpdates['status'] = 'active';
                }
                if (! empty($userUpdates)) {
                    $locked->user->forceFill($userUpdates)->save();
                    Log::info('visitor_promoted_to_free_trial_peer_on_event_payment', [
                        'user_id' => (string) $locked->user->id,
                        'event_registration_id' => (string) $locked->id,
                        'updates' => $userUpdates,
                    ]);
                }
            }

            return $locked->fresh(['event.circle', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub']);
        });

        if (empty($registration->zoho_invoice_id)) {
            $registration = $this->zohoInvoices->sync($registration);
        }

        return $registration->fresh(['event.circle', 'occurrence', 'user', 'invitedByUser', 'businessCategoryMain', 'businessCategorySub']) ?? $registration;
    }

    private function filterRegistrationColumns(array $data): array
    {
        return array_filter($data, fn ($value, $key) => Schema::hasColumn('event_registrations', $key), ARRAY_FILTER_USE_BOTH);
    }
}
