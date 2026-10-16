<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $contact_phone
 * @property string|null $contact_name
 * @property int|null $assigned_user_id
 * @property string $status
 * @property int $unread_count
 * @property string|null $last_message_preview
 * @property Carbon|null $last_message_at
 * @property Carbon|null $window_expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, WhatsappMessage> $messages
 * @property-read AdminUser|null $assignedUser
 */
class WhatsappConversation extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_conversations';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'contact_phone',
        'contact_name',
        'assigned_user_id',
        'status',
        'unread_count',
        'last_message_preview',
        'last_message_at',
        'window_expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unread_count' => 'integer',
            'last_message_at' => 'datetime',
            'window_expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WhatsappConversation $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class, 'conversation_id', 'id')->orderBy('created_at', 'asc');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assigned_user_id', 'id');
    }

    public function isWindowActive(): bool
    {
        return $this->window_expires_at !== null && $this->window_expires_at->isFuture();
    }

    public function getWindowRemainingFormattedAttribute(): string
    {
        if (! $this->isWindowActive() || $this->window_expires_at === null) {
            return 'Expired';
        }

        $now = Carbon::now();
        $diffHours = (int) $now->diffInHours($this->window_expires_at);
        $diffMinutes = (int) ($now->diffInMinutes($this->window_expires_at) % 60);

        return "{$diffHours}h {$diffMinutes}m";
    }
}
