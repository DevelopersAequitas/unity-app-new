<?php

namespace App\Services\Events;

use App\Models\EventRegistration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Razorpay\Api\Api;
use Throwable;

class EventRazorpayPaymentService
{
    public function createOrder(EventRegistration $registration): EventRegistration
    {
        $registration->loadMissing(['event', 'occurrence', 'user']);

        if (! (bool) ($registration->payment_required ?? false)) {
            return $registration;
        }

        if (! empty($registration->razorpay_order_id)) {
            return $registration->fresh(['event.circle', 'occurrence', 'user']);
        }

        $amount = round((float) ($registration->amount ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Paid event amount must be greater than zero.']);
        }

        $currency = strtoupper((string) ($registration->currency ?: config('razorpay.currency', 'INR')));

        try {
            $api = $this->getApi();
            $order = $api->order->create([
                'amount' => (int) round($amount * 100),
                'currency' => $currency,
                'receipt' => 'EVT-'.$registration->id,
                'notes' => [
                    'type' => 'event_registration',
                    'registration_id' => (string) $registration->id,
                    'event_id' => (string) $registration->event_id,
                    'occurrence_id' => (string) $registration->occurrence_id,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::error('Razorpay event order creation failed', [
                'event_registration_id' => (string) $registration->id,
                'error' => $exception->getMessage(),
            ]);

            throw ValidationException::withMessages(['payment' => 'Unable to create Razorpay order.']);
        }

        return DB::transaction(function () use ($registration, $order): EventRegistration {
            $locked = EventRegistration::query()->lockForUpdate()->findOrFail($registration->id);
            $locked->forceFill($this->filterRegistrationColumns([
                'payment_gateway' => 'razorpay',
                'razorpay_order_id' => (string) ($order['id'] ?? ''),
                'razorpay_payment_status' => 'created',
                'payment_status' => 'pending',
                'status' => 'pending_payment',
                'payment_required' => true,
            ]))->save();
            Log::info('payment_order_created', [
                'event_registration_id' => (string) $locked->id,
                'razorpay_order_id' => (string) ($order['id'] ?? ''),
                'payment_gateway' => 'razorpay',
            ]);

            return $locked->fresh(['event.circle', 'occurrence', 'user']);
        });
    }

    public function checkoutPayload(EventRegistration $registration): array
    {
        $registration->loadMissing(['event', 'occurrence', 'user']);
        $amount = round((float) ($registration->amount ?? 0), 2);
        $currency = strtoupper((string) ($registration->currency ?: config('razorpay.currency', 'INR')));
        $attendee = $this->attendee($registration);
        $keyId = (string) config('razorpay.key_id');

        return [
            'key' => $keyId,
            'key_id' => $keyId,
            'order_id' => $registration->razorpay_order_id,
            'amount' => (int) round($amount * 100),
            'currency' => $currency,
            'name' => 'Peers Global Unity',
            'description' => 'Event Registration - '.(string) ($registration->event?->title ?? 'Event'),
            'prefill' => [
                'name' => $attendee['name'],
                'email' => $attendee['email'],
                'contact' => $attendee['phone'],
            ],
            'notes' => [
                'type' => 'event_registration',
                'registration_id' => (string) $registration->id,
                'event_id' => (string) $registration->event_id,
                'occurrence_id' => (string) $registration->occurrence_id,
            ],
        ];
    }

    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        $secret = (string) config('razorpay.key_secret');
        if ($secret === '' || $orderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $orderId.'|'.$paymentId, $secret);

        return hash_equals($expected, $signature);
    }

    public function fetchPayment(string $paymentId): ?array
    {
        try {
            $keyId = (string) config('razorpay.key_id');
            $keySecret = (string) config('razorpay.key_secret');
            if ($keyId === '' || $keySecret === '') {
                return null;
            }

            $api = $this->getApi();
            $payment = $api->payment->fetch($paymentId);

            return $payment ? $payment->toArray() : null;
        } catch (Throwable $exception) {
            Log::warning('Razorpay payment fetch failed', [
                'payment_id' => $paymentId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function validatePaymentDetails(string $orderId, string $paymentId, float $expectedAmount, string $expectedCurrency): array
    {
        $payment = $this->fetchPayment($paymentId);
        if ($payment === null) {
            // If the external API call could not be completed, we log and proceed if signature verification passed
            return [
                'verified' => true,
                'api_checked' => false,
                'payment' => null,
            ];
        }

        $paymentOrderId = (string) ($payment['order_id'] ?? '');
        if ($paymentOrderId !== '' && $paymentOrderId !== $orderId) {
            return [
                'verified' => false,
                'api_checked' => true,
                'error' => 'Payment does not belong to this order.',
            ];
        }

        $expectedPaise = (int) round($expectedAmount * 100);
        $actualPaise = (int) ($payment['amount'] ?? 0);
        if ($actualPaise > 0 && $expectedPaise > 0 && $actualPaise !== $expectedPaise) {
            return [
                'verified' => false,
                'api_checked' => true,
                'error' => 'Payment amount mismatch. Expected '.$expectedPaise.' paise but found '.$actualPaise.' paise.',
            ];
        }

        $actualCurrency = strtoupper((string) ($payment['currency'] ?? ''));
        if ($actualCurrency !== '' && $actualCurrency !== strtoupper($expectedCurrency)) {
            return [
                'verified' => false,
                'api_checked' => true,
                'error' => 'Payment currency mismatch.',
            ];
        }

        $status = strtolower((string) ($payment['status'] ?? ''));
        if (! in_array($status, ['captured', 'authorized'], true)) {
            return [
                'verified' => false,
                'api_checked' => true,
                'error' => 'Payment status is invalid: '.$status,
            ];
        }

        return [
            'verified' => true,
            'api_checked' => true,
            'payment' => $payment,
        ];
    }

    private function attendee(EventRegistration $registration): array
    {
        if ($registration->user) {
            $user = $registration->user;

            return [
                'name' => $user->display_name ?: trim(($user->first_name ?? '').' '.($user->last_name ?? '')),
                'email' => (string) $user->email,
                'phone' => (string) ($user->phone ?? $user->mobile ?? ''),
            ];
        }

        return [
            'name' => (string) $registration->visitor_name,
            'email' => (string) $registration->visitor_email,
            'phone' => (string) $registration->visitor_phone,
        ];
    }

    protected function getApi(): object
    {
        if (app()->bound('razorpay.api')) {
            return app('razorpay.api');
        }

        return new Api((string) config('razorpay.key_id'), (string) config('razorpay.key_secret'));
    }

    private function filterRegistrationColumns(array $data): array
    {
        return array_filter($data, fn ($value, $key) => Schema::hasColumn('event_registrations', $key), ARRAY_FILTER_USE_BOTH);
    }
}
