<?php

namespace App\Services\Events;

use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class EventRazorpayPaymentFinalizer
{
    public function __construct(
        private readonly EventQrService $qr,
        private readonly EventZohoInvoiceSyncService $zohoInvoices,
    ) {}

    public function markPaid(EventRegistration $registration, array $paymentData = []): EventRegistration
    {
        $wasPaid = ($registration->payment_status ?? null) === 'paid';
        $registration = DB::transaction(function () use ($registration, $paymentData): EventRegistration {
            $locked = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);

            if (($locked->payment_status ?? null) !== 'paid') {
                $locked->forceFill($this->filterRegistrationColumns([
                    'payment_status' => 'paid',
                    'status' => 'registered',
                    'payment_completed_at' => now(),
                    'razorpay_payment_id' => $paymentData['razorpay_payment_id'] ?? $locked->razorpay_payment_id,
                    'razorpay_signature' => $paymentData['razorpay_signature'] ?? $locked->razorpay_signature,
                    'razorpay_payment_status' => $paymentData['razorpay_payment_status'] ?? 'captured',
                    'razorpay_paid_at' => now(),
                ]))->save();
            }

            if (empty($locked->qr_code_path) && empty($locked->qr_code_url)) {
                $this->qr->generateAndStore($locked);
                Log::info('qr.generated', ['registration_id' => $locked->id]);
            }

            return $locked->fresh(['event.circle', 'occurrence', 'user']);
        });

        $synced = $this->zohoInvoices->sync($registration);
        if ($synced->zoho_invoice_id || $synced->zoho_invoice_number) {
            Log::info('zoho.invoice.synced', ['registration_id' => $synced->id, 'invoice_number' => $synced->zoho_invoice_number]);
        } else {
            Log::warning('zoho.invoice.failed', ['registration_id' => $synced->id, 'sync_error' => $synced->zoho_invoice_sync_error]);
        }

        Log::info('event.payment.finalized', [
            'registration_id' => $synced->id,
            'was_already_paid' => $wasPaid,
            'payment_status' => $synced->payment_status,
            'status' => $synced->status,
        ]);

        return $synced;
    }

    private function filterRegistrationColumns(array $data): array
    {
        return array_filter($data, fn ($value, $key) => Schema::hasColumn('event_registrations', $key), ARRAY_FILTER_USE_BOTH);
    }
}
