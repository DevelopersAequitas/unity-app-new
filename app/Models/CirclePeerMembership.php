<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CirclePeerMembership extends Model
{
    use HasFactory;

    protected $table = 'circle_peer_memberships';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'circle_id',
        'platform_membership_start',
        'platform_membership_end',
        'circle_joining_date',
        'circle_renewal_date',
        'status',
    ];

    protected $casts = [
        'platform_membership_start' => 'date',
        'platform_membership_end' => 'date',
        'circle_joining_date' => 'date',
        'circle_renewal_date' => 'date',
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

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class, 'circle_id');
    }
}
