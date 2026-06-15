<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppMembershipLabel extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'app_membership_labels';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'is_enabled' => 'boolean',
        'sort_order' => 'integer',
    ];
}
