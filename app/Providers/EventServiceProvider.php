<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * In Laravel 11+, listeners in app/Listeners are auto-discovered.
     * Keep manual mappings here empty to prevent duplicate listener execution.
     */
    protected $listen = [];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
