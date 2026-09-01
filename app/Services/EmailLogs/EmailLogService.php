<?php

declare(strict_types=1);

namespace App\Services\EmailLogs;

use App\Models\EmailLog;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
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
        ]));
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

    private function persist(array $data): ?EmailLog
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

            $subject = Arr::get($data, 'subject');
            $templateKey = Arr::get($data, 'template_key');
            $userId = Arr::get($data, 'user_id') ?: $this->resolveUserId($toEmail);
            $toName = Arr::get($data, 'to_name');
            $sourceModule = Arr::get($data, 'source_module');
            $relatedType = Arr::get($data, 'related_type');
            $relatedId = $this->stringValue(Arr::get($data, 'related_id'));
            $sourceType = Arr::get($data, 'source_type');
            $sourceId = $this->stringValue(Arr::get($data, 'source_id'));
            $sourceEvent = Arr::get($data, 'source_event');
            $status = Arr::get($data, 'status', 'sent');
            $bodyHtml = Arr::get($data, 'body_html');
            $bodyText = Arr::get($data, 'body_text');
            $errorMessage = Arr::get($data, 'error_message');
            $triggeredBy = Arr::get($data, 'triggered_by');
            $triggeredUserId = Arr::get($data, 'triggered_user_id');
            $mailProvider = Arr::get($data, 'mail_provider', config('mail.default'));
            $queueId = Arr::get($data, 'queue_id');
            $messageId = Arr::get($data, 'message_id');
            $sentAt = Arr::get($data, 'sent_at', now());

            // 1. Check if a recent 'raw_email' log was auto-created for this recipient (e.g., during Mail::send() via AppServiceProvider)
            $existingLog = EmailLog::query()
                ->where('to_email', $toEmail)
                ->where('template_key', 'raw_email')
                ->where('created_at', '>=', now()->subSeconds(60))
                ->when(! empty($subject), function ($query) use ($subject) {
                    $query->where(function ($q) use ($subject) {
                        $q->where('subject', $subject)
                            ->orWhereNull('subject')
                            ->orWhere('subject', '');
                    });
                })
                ->orderByDesc('created_at')
                ->first();

            // 2. Alternatively, check if a duplicate log with the same template_key / related entity was already created recently (<15s)
            if (! $existingLog && ! empty($templateKey)) {
                $existingLog = EmailLog::query()
                    ->where('to_email', $toEmail)
                    ->where('template_key', $templateKey)
                    ->where('created_at', '>=', now()->subSeconds(15))
                    ->when(! empty($relatedType) && ! empty($relatedId), function ($query) use ($relatedType, $relatedId) {
                        $query->where('related_type', $relatedType)
                            ->where('related_id', $relatedId);
                    })
                    ->orderByDesc('created_at')
                    ->first();
            }

            if ($existingLog) {
                $updates = array_filter([
                    'user_id' => $userId ?: $existingLog->user_id,
                    'to_name' => $toName ?: $existingLog->to_name,
                    'template_key' => $templateKey ?: $existingLog->template_key,
                    'subject' => $subject ?: $existingLog->subject,
                    'source_module' => $sourceModule ?: $existingLog->source_module,
                    'related_type' => $relatedType ?: $existingLog->related_type,
                    'related_id' => $relatedId ?: $existingLog->related_id,
                    'source_type' => $sourceType ?: $existingLog->source_type,
                    'source_id' => $sourceId ?: $existingLog->source_id,
                    'source_event' => $sourceEvent ?: $existingLog->source_event,
                    'status' => $status,
                    'body_html' => $bodyHtml ?: $existingLog->body_html,
                    'body_text' => $bodyText ?: $existingLog->body_text,
                    'payload' => is_array($payload) ? $payload : $existingLog->payload,
                    'error_message' => $errorMessage,
                    'triggered_by' => $triggeredBy ?: $existingLog->triggered_by,
                    'triggered_user_id' => $triggeredUserId ?: $existingLog->triggered_user_id,
                    'mail_provider' => $mailProvider ?: $existingLog->mail_provider,
                    'queue_id' => $queueId ?: $existingLog->queue_id,
                    'message_id' => $messageId ?: $existingLog->message_id,
                    'sent_at' => $sentAt ?: $existingLog->sent_at,
                ], fn ($val) => $val !== null);

                $existingLog->update($updates);

                return $existingLog->fresh();
            }

            $record = [
                'id' => Arr::get($data, 'id', (string) Str::uuid()),
                'user_id' => $userId,
                'to_email' => $toEmail,
                'to_name' => $toName,
                'template_key' => $templateKey,
                'subject' => $subject,
                'source_module' => $sourceModule,
                'related_type' => $relatedType,
                'related_id' => $relatedId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_event' => $sourceEvent,
                'status' => $status,
                'body_html' => $bodyHtml,
                'payload' => is_array($payload) ? $payload : null,
                'error_message' => $errorMessage,
                'triggered_by' => $triggeredBy,
                'triggered_user_id' => $triggeredUserId,
                'mail_provider' => $mailProvider,
                'queue_id' => $queueId,
                'message_id' => $messageId,
                'body_text' => $bodyText,
                'sent_at' => $sentAt,
                'created_at' => Arr::get($data, 'created_at', now()),
            ];

            return EmailLog::query()->create($record);
        } catch (Throwable $exception) {
            Log::warning('Email logging failed', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
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
