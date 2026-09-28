<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContactInvitation extends Model
{
    use HasFactory;

    protected $table = 'contact_invitations';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'contact_post_id',
        'contact_name',
        'contact_phone',
        'contact_email',
        'mobile_normalized',
        'invitation_message',
        'status',
        'whatsapp_status',
        'whatsapp_sent_at',
        'whatsapp_provider_id',
        'whatsapp_response_payload',
        'error_message',
    ];

    protected $casts = [
        'whatsapp_sent_at' => 'datetime',
        'whatsapp_response_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function contactPost(): BelongsTo
    {
        return $this->belongsTo(ContactPost::class, 'contact_post_id');
    }
}
