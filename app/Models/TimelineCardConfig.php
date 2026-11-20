<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimelineCardConfig extends Model
{
    protected $table = 'timeline_card_configs';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'profile_completion_enabled',
        'you_can_help_enabled',
        'events_spotlight_enabled',
        'circle_vacancy_enabled',
        'suggested_peers_enabled',
        'updated_by_user_id',
    ];

    protected $casts = [
        'profile_completion_enabled' => 'boolean',
        'you_can_help_enabled' => 'boolean',
        'events_spotlight_enabled' => 'boolean',
        'circle_vacancy_enabled' => 'boolean',
        'suggested_peers_enabled' => 'boolean',
    ];
}
