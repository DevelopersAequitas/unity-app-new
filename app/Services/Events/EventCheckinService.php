<?php

namespace App\Services\Events;

use App\Models\EventOccurrence;
use App\Models\EventRegistration;
use App\Models\ScanAppUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventCheckinService
{
    public function __construct(private readonly EventService $events) {}

    public function scan(string $qrToken, User $scanner, bool $force = false): EventRegistration
    {
        return $this->scanRegistration($qrToken, $scanner, $force);
    }

    public function scanForScannerApp(string $qrToken, ScanAppUser $scanner, string $expectedEventId): EventRegistration
    {
        return $this->scanRegistration($qrToken, null, false, $expectedEventId, 'scan_app');
    }

    public function extractToken(string $rawToken): string
    {
        $token = trim($rawToken);
        $token = trim($token, " \t\n\r\0\x0B\"'/");

        if (str_contains($token, '%')) {
            $token = urldecode($token);
            $token = trim($token, " \t\n\r\0\x0B\"'/");
        }

        $path = parse_url($token, PHP_URL_PATH) ?: $token;

        $checkinMarkers = [
            '/api/v1/events/checkin/qr/',
            '/events/checkin/qr/',
            'events/checkin/qr/',
        ];

        foreach ($checkinMarkers as $marker) {
            if (($pos = strpos($path, $marker)) !== false) {
                $extracted = substr($path, $pos + strlen($marker));
                $extracted = trim(urldecode($extracted), " \t\n\r\0\x0B\"'/");

                if (! empty($extracted)) {
                    return $extracted;
                }
            }
        }

        $qrcodeMarkers = [
            '/api/v1/event-qrcodes/',
            '/event-qrcodes/',
            'event-qrcodes/',
        ];

        foreach ($qrcodeMarkers as $marker) {
            if (($pos = strpos($path, $marker)) !== false) {
                $filename = basename($path);
                $extracted = pathinfo($filename, PATHINFO_FILENAME);
                $extracted = trim(urldecode($extracted), " \t\n\r\0\x0B\"'/");

                if (! empty($extracted)) {
                    return $extracted;
                }
            }
        }

        if (str_ends_with(strtolower($path), '.png') || str_ends_with(strtolower($path), '.svg')) {
            $path = pathinfo($path, PATHINFO_FILENAME);
        }

        return trim(urldecode($path), " \t\n\r\0\x0B\"'/");
    }

    public function registrationForToken(string $qrToken): ?EventRegistration
    {
        $cleanToken = $this->extractToken($qrToken);

        Log::info('event_qr_lookup_start', [
            'raw_scanned_token' => $qrToken,
            'extracted_token' => $cleanToken,
        ]);

        $query = EventRegistration::query();
        $this->applyQrLookupQuery($query, $cleanToken, $qrToken);

        $registration = $query->first(['id', 'event_id', 'occurrence_id', 'user_id', 'checkin_status', 'status', 'payment_status', 'payment_required', 'qr_code_path', 'qr_code_url', 'qr_token']);

        Log::info('event_qr_lookup_result', [
            'raw_scanned_token' => $qrToken,
            'extracted_token' => $cleanToken,
            'found_registration_id' => (string) ($registration?->id ?? ''),
            'found_qr_token' => (string) ($registration?->qr_token ?? ''),
            'found_event_id' => (string) ($registration?->event_id ?? ''),
            'found_occurrence_id' => (string) ($registration?->occurrence_id ?? ''),
            'found_user_id' => (string) ($registration?->user_id ?? ''),
            'found_checkin_status' => (string) ($registration?->checkin_status ?? ''),
            'found_payment_status' => (string) ($registration?->payment_status ?? ''),
            'is_found' => (bool) $registration,
        ]);

        return $registration;
    }

    private function scanRegistration(string $qrToken, ?User $scanner = null, bool $force = false, ?string $expectedEventId = null, string $attendanceSource = 'qr_scan'): EventRegistration
    {
        $cleanToken = $this->extractToken($qrToken);

        return DB::transaction(function () use ($cleanToken, $qrToken, $scanner, $force, $expectedEventId, $attendanceSource): EventRegistration {
            $query = EventRegistration::query()->with(['event.circle', 'occurrence', 'user']);
            $this->applyQrLookupQuery($query, $cleanToken, $qrToken);

            $registration = $query->lockForUpdate()->first();

            if (! $registration) {
                Log::warning('event_qr_validation_failed', [
                    'scanned_token' => $qrToken,
                    'extracted_token' => $cleanToken,
                    'validation_failure_reason' => 'QR token not found in event_registrations database table.',
                ]);

                throw ValidationException::withMessages(['qr_token' => 'QR token not found.']);
            }

            if ($expectedEventId !== null && (string) $registration->event_id !== (string) $expectedEventId) {
                Log::warning('event_qr_validation_failed', [
                    'scanned_token' => $qrToken,
                    'extracted_token' => $cleanToken,
                    'registration_id' => (string) $registration->id,
                    'matched_qr_token' => (string) ($registration->qr_token ?? ''),
                    'registration_event_id' => (string) $registration->event_id,
                    'occurrence_id' => (string) $registration->occurrence_id,
                    'expected_event_id' => (string) $expectedEventId,
                    'validation_failure_reason' => 'QR code does not belong to this event.',
                ]);

                throw ValidationException::withMessages(['event' => 'QR code does not belong to this event.']);
            }

            if ($registration->status === 'cancelled') {
                Log::warning('event_qr_validation_failed', [
                    'scanned_token' => $qrToken,
                    'extracted_token' => $cleanToken,
                    'registration_id' => (string) $registration->id,
                    'matched_qr_token' => (string) ($registration->qr_token ?? ''),
                    'event_id' => (string) $registration->event_id,
                    'occurrence_id' => (string) $registration->occurrence_id,
                    'validation_failure_reason' => 'Registration is cancelled.',
                ]);

                throw ValidationException::withMessages(['registration' => 'Registration is cancelled.']);
            }

            if ($registration->status === 'pending_payment' || (($registration->payment_required ?? false) && ($registration->payment_status ?? null) !== 'paid')) {
                Log::warning('event_qr_validation_failed', [
                    'scanned_token' => $qrToken,
                    'extracted_token' => $cleanToken,
                    'registration_id' => (string) $registration->id,
                    'matched_qr_token' => (string) ($registration->qr_token ?? ''),
                    'event_id' => (string) $registration->event_id,
                    'occurrence_id' => (string) $registration->occurrence_id,
                    'validation_failure_reason' => 'Payment is required before QR check-in.',
                ]);

                throw ValidationException::withMessages(['registration' => 'Payment is required before QR check-in.']);
            }

            if (empty($registration->qr_code_path) && empty($registration->qr_code_url)) {
                $registration = app(EventRegistrationQrService::class)->ensureQrGenerated($registration);
            }

            if (empty($registration->qr_code_path) && empty($registration->qr_code_url) && empty($registration->qr_code_svg) && empty($registration->qr_token)) {
                Log::warning('event_qr_validation_failed', [
                    'scanned_token' => $qrToken,
                    'extracted_token' => $cleanToken,
                    'registration_id' => (string) $registration->id,
                    'matched_qr_token' => (string) ($registration->qr_token ?? ''),
                    'event_id' => (string) $registration->event_id,
                    'occurrence_id' => (string) $registration->occurrence_id,
                    'validation_failure_reason' => 'QR code has not been generated for this registration.',
                ]);

                throw ValidationException::withMessages(['registration' => 'QR code has not been generated for this registration.']);
            }

            if (! $registration->occurrence) {
                Log::warning('event_qr_validation_failed', [
                    'scanned_token' => $qrToken,
                    'extracted_token' => $cleanToken,
                    'registration_id' => (string) $registration->id,
                    'matched_qr_token' => (string) ($registration->qr_token ?? ''),
                    'event_id' => (string) $registration->event_id,
                    'occurrence_id' => (string) $registration->occurrence_id,
                    'validation_failure_reason' => 'Event occurrence not found.',
                ]);

                throw ValidationException::withMessages(['occurrence' => 'Event occurrence not found.']);
            }

            if (! $registration->event || ! $registration->event->qr_checkin_enabled) {
                Log::warning('event_qr_validation_failed', [
                    'scanned_token' => $qrToken,
                    'extracted_token' => $cleanToken,
                    'registration_id' => (string) $registration->id,
                    'matched_qr_token' => (string) ($registration->qr_token ?? ''),
                    'event_id' => (string) $registration->event_id,
                    'occurrence_id' => (string) $registration->occurrence_id,
                    'validation_failure_reason' => 'QR check-in is not enabled for this event.',
                ]);

                throw ValidationException::withMessages(['event' => 'QR check-in is not enabled for this event.']);
            }

            if ($registration->checkin_status === 'checked_in' && ! ($force && $scanner && $this->events->isAdmin($scanner))) {
                Log::warning('event_qr_validation_failed', [
                    'scanned_token' => $qrToken,
                    'extracted_token' => $cleanToken,
                    'registration_id' => (string) $registration->id,
                    'matched_qr_token' => (string) ($registration->qr_token ?? ''),
                    'event_id' => (string) $registration->event_id,
                    'occurrence_id' => (string) $registration->occurrence_id,
                    'validation_failure_reason' => 'Attendance already marked.',
                ]);

                throw ValidationException::withMessages(['registration' => 'Attendance already marked.']);
            }

            Log::info('event_qr_validation_passed', [
                'scanned_token' => $qrToken,
                'extracted_token' => $cleanToken,
                'registration_id' => (string) $registration->id,
                'matched_qr_token' => (string) ($registration->qr_token ?? ''),
                'user_id' => (string) ($registration->user_id ?? ''),
                'event_id' => (string) $registration->event_id,
                'occurrence_id' => (string) $registration->occurrence_id,
            ]);

            $updates = [
                'status' => 'attended',
                'checkin_status' => 'checked_in',
                'checked_in_at' => now(),
            ];

            if ($scanner) {
                $updates['checked_in_by_user_id'] = $scanner->id;
            }

            if (Schema::hasColumn('event_registrations', 'last_qr_scan_at')) {
                $updates['last_qr_scan_at'] = now();
            }
            if (Schema::hasColumn('event_registrations', 'attendance_source')) {
                $updates['attendance_source'] = $attendanceSource;
            }

            $registration->forceFill($updates)->save();

            $occurrence = EventOccurrence::query()
                ->where('id', $registration->occurrence_id)
                ->lockForUpdate()
                ->first();

            if ($occurrence) {
                $checkedInCount = EventRegistration::query()
                    ->where('occurrence_id', $registration->occurrence_id)
                    ->where('checkin_status', 'checked_in')
                    ->whereNull('deleted_at')
                    ->count();

                $registeredCount = EventRegistration::query()
                    ->where('occurrence_id', $registration->occurrence_id)
                    ->where('status', '!=', 'cancelled')
                    ->whereNull('deleted_at')
                    ->count();

                $occurrenceUpdates = ['registered_count' => $registeredCount];
                if (Schema::hasColumn('event_occurrences', 'checked_in_count')) {
                    $occurrenceUpdates['checked_in_count'] = $checkedInCount;
                }
                $occurrence->forceFill($occurrenceUpdates)->save();
            }

            return $registration->fresh(['event.circle', 'occurrence', 'user', 'checkedInBy']);
        });
    }

    private function applyQrLookupQuery(Builder $query, string $cleanToken, string $qrToken): Builder
    {
        $isCleanTokenUuid = ! empty($cleanToken) && Str::isUuid($cleanToken);
        $isQrTokenUuid = ! empty($qrToken) && Str::isUuid($qrToken);

        return $query->where(function (Builder $q) use ($cleanToken, $qrToken, $isCleanTokenUuid, $isQrTokenUuid): void {
            $q->where('qr_token', $cleanToken);

            if ($isCleanTokenUuid) {
                $q->orWhere('id', $cleanToken);
            }

            $q->orWhere('qr_token', $qrToken);

            if ($isQrTokenUuid && $qrToken !== $cleanToken) {
                $q->orWhere('id', $qrToken);
            }

            if (! empty($cleanToken)) {
                $q->orWhere('qr_code_url', 'LIKE', '%'.$cleanToken.'%')
                    ->orWhere('qr_code_path', 'LIKE', '%'.$cleanToken.'%');
            }

            if (! empty($qrToken) && $qrToken !== $cleanToken) {
                $q->orWhere('qr_code_url', 'LIKE', '%'.$qrToken.'%')
                    ->orWhere('qr_code_path', 'LIKE', '%'.$qrToken.'%');
            }
        });
    }
}
