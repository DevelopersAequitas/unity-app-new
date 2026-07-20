<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntroductionRequest extends Model
{
    protected $table = 'introduction_requests';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'requester_id',
        'introducer_id',
        'status',
        'admin_note',
        'reviewed_by',
        'requested_at',
        'reviewed_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $request): void {
            if (! $request->id) {
                $request->id = (string) Str::uuid();
            }
            if (! $request->requested_at) {
                $request->requested_at = now();
            }
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function introducer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'introducer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by');
    }
}
