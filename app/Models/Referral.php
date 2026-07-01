<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Referral extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'referrals';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'referral_type',
        'referral_date',
        'referral_of',
        'phone',
        'email',
        'address',
        'hot_value',
        'remarks',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function givenByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function receivedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}
