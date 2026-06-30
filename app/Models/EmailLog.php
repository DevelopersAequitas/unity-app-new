<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'recipient_user_type',
        'triggered_by',
        'trigger_user_id',
        'trigger_user_type',
        'trigger_user_name',
        'trigger_user_email',
        'trigger_user_role',
        'ip_address',
        'user_agent',
        'admin_id',
        'admin_name',
        'admin_email',
        'admin_role',
        'admin_session_id',
        'admin_login_time',
        'admin_last_activity',
        'admin_ip_address',
        'admin_user_agent',
        'mail_provider',
        'mail_driver',
        'smtp_host',
        'queue_id',
        'message_id',
        'queue_name',
        'queue_job_id',
        'attempts',
        'processing_time_ms',
        'provider_response',
        'plain_text',
        'template_name',
        'template_version',
        'variables_used',
        'attachments',
        'exception_class',
        'stack_trace',
        'retry_count',
        'last_retry_at',
        'created_by',
        'updated_by',
        'sent_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'variables_used' => 'array',
        'attachments' => 'array',
        'sent_at' => 'datetime',
        'admin_login_time' => 'datetime',
        'admin_last_activity' => 'datetime',
        'last_retry_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
