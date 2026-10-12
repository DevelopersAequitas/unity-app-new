<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MemberJourneySend extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'member_journey_sends';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'day_number',
        'template_name',
        'language',
        'scheduled_at',
        'sent_at',
        'status',
        'skip_reason',
        'payload_json',
        'provider_response_json',
        'provider_message_id',
        'error_message',
        'is_test',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_number' => 'integer',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
            'payload_json' => 'array',
            'provider_response_json' => 'array',
            'is_test' => 'boolean',
        ];
    }

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
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}
