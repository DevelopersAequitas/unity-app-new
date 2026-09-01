<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReferralStatus extends Model
{
    use HasFactory;

    protected $table = 'referral_status';

    protected $fillable = [
        'name',
    ];

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'status_id');
    }
}
