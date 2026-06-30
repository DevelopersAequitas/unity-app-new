<?php

namespace App\Services\EmailLogs;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class EmailLogService
{
    private const SENSITIVE_KEYS = [
        'password',
        'token',
        'access_token',
        'refresh_token',
        'secret',
    ];

    public function logSent(array $data): ?EmailLog
    {
        return $this->persist(array_merge($data, [
            'status' => Arr::get($data, 'status', 'sent'),
            'sent_at' => Arr::get($data, 'sent_at', now()),
            'created_at' => Arr::get($data, 'created_at', now()),
        ]));
    }

    public function logFailed(array $data, Throwable|string $error): ?EmailLog
    {
        $message = $error instanceof Throwable ? $error->getMessage() : (string) $error;

        return $this->persist(array_merge($data, [
            'status' => 'failed',
            'error_message' => Str::limit($message, 5000, ''),
            'sent_at' => Arr::get($data, 'sent_at', now()),
            'created_at' => Arr::get($data, 'created_at', now()),
        ]), $error);
    }

    public function logMailableSent(Mailable $mailable, array $data): ?EmailLog
    {
        $payload = Arr::get($data, 'payload', []);
        if (! is_array($payload)) {
            $payload = [];
        }

        $payload['mailable_class'] = get_class($mailable);

        return $this->logSent(array_merge($data, [
            'template_key' => Arr::get($data, 'template_key', Str::snake(class_basename($mailable))),
            'subject' => Arr::get($data, 'subject', $this->extractSubject($mailable)),
            'body_html' => Arr::get($data, 'body_html', $this->renderMailableSafely($mailable)),
            'payload' => $payload,
        ]));
    }

    public function logMailableFailed(Mailable $mailable, array $data, Throwable|string $error): ?EmailLog
    {
        $payload = Arr::get($data, 'payload', []);
        if (! is_array($payload)) {
            $payload = [];
        }

        $payload['mailable_class'] = get_class($mailable);

        return $this->logFailed(array_merge($data, [
            'template_key' => Arr::get($data, 'template_key', Str::snake(class_basename($mailable))),
            'subject' => Arr::get($data, 'subject', $this->extractSubject($mailable)),
            'body_html' => Arr::get($data, 'body_html', $this->renderMailableSafely($mailable)),
            'payload' => $payload,
        ]), $error);
    }

    private function persist(array $data, Throwable|string|null $error = null): ?EmailLog
    {
        try {
            $toEmail = trim((string) Arr::get($data, 'to_email', ''));
            if ($toEmail === '') {
                return null;
            }

            $payload = Arr::get($data, 'payload');
            if (is_array($payload)) {
                $payload = $this->sanitizePayload($payload);
            }

            $record = [
                'id' => Arr::get($data, 'id', (string) Str::uuid()),
                'user_id' => Arr::get($data, 'user_id') ?: $this->resolveUserId($toEmail),
                'to_email' => $toEmail,
                'to_name' => Arr::get($data, 'to_name'),
                'template_key' => Arr::get($data, 'template_key'),
                'subject' => Arr::get($data, 'subject'),
                'source_module' => Arr::get($data, 'source_module'),
                'related_type' => Arr::get($data, 'related_type'),
                'related_id' => $this->stringValue(Arr::get($data, 'related_id')),
                'source_type' => Arr::get($data, 'source_type'),
                'source_id' => $this->stringValue(Arr::get($data, 'source_id')),
                'source_event' => Arr::get($data, 'source_event'),
                'status' => Arr::get($data, 'status', 'sent'),
                'body_html' => Arr::get($data, 'body_html'),
                'payload' => is_array($payload) ? $payload : null,
                'error_message' => Arr::get($data, 'error_message'),
                'sent_at' => Arr::get($data, 'sent_at', now()),
                'created_at' => Arr::get($data, 'created_at', now()),
                'updated_at' => Arr::get($data, 'updated_at', now()),
            ];

            $record = array_merge($record, $this->extendedRecord($data, $error));
            $columns = Schema::getColumnListing('email_logs');
            $record = array_intersect_key($record, array_flip($columns));

            return EmailLog::query()->create($record);
        } catch (Throwable $exception) {
            Log::warning('Email logging failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function extendedRecord(array $data, mixed $error = null): array
    {
        $request = app()->bound('request') ? request() : null;
        $session = $request?->hasSession() ? $request->session() : null;
        $admin = auth('admin')->user();
        $adminRole = null;

        if ($admin) {
            $admin->loadMissing('roles:id,key,name');
            $adminRole = $admin->roles->pluck('name')->filter()->implode(', ') ?: $admin->roles->pluck('key')->implode(', ');
        }

        return [
            'recipient_user_type' => Arr::get($data, 'recipient_user_type', Arr::get($data, 'user_type')),
            'triggered_by' => Arr::get($data, 'triggered_by', $admin ? 'Admin' : 'System'),
            'trigger_user_id' => Arr::get($data, 'trigger_user_id', $admin?->id),
            'trigger_user_type' => Arr::get($data, 'trigger_user_type', $admin ? 'admin' : null),
            'trigger_user_name' => Arr::get($data, 'trigger_user_name', $admin?->name),
            'trigger_user_email' => Arr::get($data, 'trigger_user_email', $admin?->email),
            'trigger_user_role' => Arr::get($data, 'trigger_user_role', $adminRole),
            'ip_address' => Arr::get($data, 'ip_address', $request?->ip()),
            'user_agent' => Arr::get($data, 'user_agent', $request?->userAgent()),
            'admin_id' => Arr::get($data, 'admin_id', $admin?->id),
            'admin_name' => Arr::get($data, 'admin_name', $admin?->name),
            'admin_email' => Arr::get($data, 'admin_email', $admin?->email),
            'admin_role' => Arr::get($data, 'admin_role', $adminRole),
            'admin_session_id' => Arr::get($data, 'admin_session_id', $session?->getId()),
            'admin_login_time' => Arr::get($data, 'admin_login_time', $session?->get('admin_login_time')),
            'admin_last_activity' => Arr::get($data, 'admin_last_activity', now()),
            'admin_ip_address' => Arr::get($data, 'admin_ip_address', $request?->ip()),
            'admin_user_agent' => Arr::get($data, 'admin_user_agent', $request?->userAgent()),
            'mail_provider' => Arr::get($data, 'mail_provider', config('mail.default')),
            'mail_driver' => Arr::get($data, 'mail_driver', config('mail.default')),
            'smtp_host' => Arr::get($data, 'smtp_host', config('mail.mailers.smtp.host')),
            'queue_id' => Arr::get($data, 'queue_id'),
            'message_id' => Arr::get($data, 'message_id'),
            'queue_name' => Arr::get($data, 'queue_name', config('queue.default')),
            'queue_job_id' => Arr::get($data, 'queue_job_id'),
            'attempts' => Arr::get($data, 'attempts'),
            'processing_time_ms' => Arr::get($data, 'processing_time_ms'),
            'provider_response' => Arr::get($data, 'provider_response'),
            'plain_text' => Arr::get($data, 'plain_text'),
            'template_name' => Arr::get($data, 'template_name'),
            'template_version' => Arr::get($data, 'template_version'),
            'variables_used' => Arr::get($data, 'variables_used'),
            'attachments' => Arr::get($data, 'attachments'),
            'exception_class' => $error instanceof Throwable ? $error::class : Arr::get($data, 'exception_class'),
            'stack_trace' => $error instanceof Throwable ? Str::limit($error->getTraceAsString(), 20000, '') : Arr::get($data, 'stack_trace'),
            'retry_count' => Arr::get($data, 'retry_count'),
            'last_retry_at' => Arr::get($data, 'last_retry_at'),
            'created_by' => Arr::get($data, 'created_by', $admin?->email ?: 'System'),
            'updated_by' => Arr::get($data, 'updated_by'),
        ];
    }

    private function renderMailableSafely(Mailable $mailable): ?string
    {
        try {
            return $mailable->render();
        } catch (Throwable) {
            return null;
        }
    }

    private function extractSubject(Mailable $mailable): ?string
    {
        if (property_exists($mailable, 'subjectLine') && filled($mailable->subjectLine)) {
            return (string) $mailable->subjectLine;
        }

        if (property_exists($mailable, 'subject') && filled($mailable->subject)) {
            return (string) $mailable->subject;
        }

        return null;
    }

    private function resolveUserId(string $email): ?string
    {
        if ($email === '') {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [Str::lower($email)])
            ->value('id');
    }

    private function sanitizePayload(array $payload): array
    {
        $sanitized = [];

        foreach ($payload as $key => $value) {
            $normalizedKey = Str::lower((string) $key);

            if ($this->isSensitiveKey($normalizedKey)) {
                $sanitized[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizePayload($value);
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
            if ($key === $sensitiveKey || Str::contains($key, $sensitiveKey)) {
                return true;
            }
        }

        return false;
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = trim((string) $value);

        return $stringValue === '' ? null : $stringValue;
    }
}
