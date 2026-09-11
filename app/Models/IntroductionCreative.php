<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class IntroductionCreative extends Model
{
    protected $table = 'introduction_creatives';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'introduction_request_id',
        'introducer_id',
        'requester_id',
        'introduced_count',
        'image_url',
    ];

    protected $casts = [
        'introduced_count' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function introducer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'introducer_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function introductionRequest(): BelongsTo
    {
        return $this->belongsTo(IntroductionRequest::class, 'introduction_request_id');
    }
}
