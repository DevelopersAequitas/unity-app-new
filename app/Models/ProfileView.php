<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfileView extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'profile_views';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'viewed_id',
        'viewer_id',
    ];

    public function viewed(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewed_id');
    }

    public function viewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'viewer_id');
    }
}
