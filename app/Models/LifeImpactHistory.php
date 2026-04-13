<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LifeImpactHistory extends Model
{
    use HasFactory;

    protected $table = 'life_impact_histories';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'triggered_by_user_id',
        'activity_type',
        'activity_id',
        'impact_value',
        'title',
        'description',
        'meta',
    ];

    protected $casts = [
        'impact_value' => 'integer',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function triggeredByUser()
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }
}
