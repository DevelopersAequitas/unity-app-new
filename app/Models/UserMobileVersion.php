<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMobileVersion extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'user_mobile_versions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'platform',
        'app_version',
        'device_model',
        'os_version',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
