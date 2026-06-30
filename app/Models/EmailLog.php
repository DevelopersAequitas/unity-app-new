<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class EmailLog extends Model
{
    use HasFactory;

    protected $table = 'email_logs';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'to_email',
        'to_name',
        'template_key',
        'subject',
        'source_module',
        'related_type',
        'related_id',
        'source_type',
        'source_id',
        'source_event',
        'status',
        'body_html',
        'payload',
        'error_message',
        'sent_at',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected $appends = [
        'triggered_by_label',
        'trigger_user_name',
        'trigger_user_email',
        'trigger_user_role',
        'mail_provider',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getTriggeredByLabelAttribute(): string
    {
        $sourceType = Str::of((string) $this->source_type)->replace(['-', '_'], ' ')->title()->toString();

        return $sourceType !== '' ? $sourceType : 'System';
    }

    public function getTriggerUserNameAttribute(): ?string
    {
        return $this->payloadValue(['trigger.name', 'trigger_user.name', 'admin.name', 'user.name'])
            ?? $this->user?->name;
    }

    public function getTriggerUserEmailAttribute(): ?string
    {
        return $this->payloadValue(['trigger.email', 'trigger_user.email', 'admin.email', 'user.email'])
            ?? $this->user?->email;
    }

    public function getTriggerUserRoleAttribute(): ?string
    {
        return $this->payloadValue(['trigger.role', 'trigger_user.role', 'admin.role', 'user.role']);
    }

    public function getMailProviderAttribute(): ?string
    {
        return $this->payloadValue(['mail_provider', 'provider', 'mail.driver', 'mail_driver']);
    }

    public function payloadValue(array $keys, mixed $default = null): mixed
    {
        $payload = is_array($this->payload) ? $this->payload : [];

        foreach ($keys as $key) {
            $value = Arr::get($payload, $key);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $default;
    }

    public function maskedPayloadValue(array $keys, mixed $default = null): mixed
    {
        $value = $this->payloadValue($keys, $default);

        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return preg_replace('/\.\d+$/', '.***', $value);
        }

        $host = preg_replace('/^(smtp|mail)\./i', '$1.***.', $value, 1);

        return $host ?: '***';
    }
}
