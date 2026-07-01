<?php

namespace App\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $activityType,
        public Model $activityModel,
        public string $actorUserId,
        public ?string $otherUserId = null,
    ) {}
}
