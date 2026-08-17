<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Database state expiration / stale cleaning (no notifications)
Schedule::command('memberships:expire')->daily();
Schedule::command('memberships:expire-users')->hourly();
Schedule::command('users:expire-trial')->hourly();
Schedule::command('collaborations:expire')->dailyAt('00:10');
Schedule::command('members:mark-offline-stale')->everyMinute();

// App Update reminders (push + in-app)
Schedule::command('app:update-reminder-notifications')->hourly();

// Membership expiry reminders (mail + push + in-app)
Schedule::command('memberships:send-expiry-reminders')->dailyAt('11:25')->timezone(config('app.timezone', 'UTC'));
Schedule::command('memberships:send-upcoming-expiry-reminders')->dailyAt('11:25')->timezone(config('app.timezone', 'UTC'));
Schedule::command('memberships:send-circle-expiry-reminders')->dailyAt('11:25')->timezone(config('app.timezone', 'UTC'));

// Connections pending reminders (push + in-app)
Schedule::command('connections:send-pending-reminders')->dailyAt('09:00')->timezone(config('app.timezone', 'UTC'));

// Brand Partner Offer Expiry reminders (in-app notifications)
Schedule::command('PGU:brand-partner-expiry-alerts')->dailyAt('08:00')->timezone(config('app.timezone', 'UTC'));

// Engagement reminders (push + in-app)
Schedule::command('app:send-daily-engagement-reminders')->hourly();

// Notification campaigns scheduler (mail + push + in-app campaigns)
Schedule::command('campaigns:run')->everyMinute();
Schedule::command('notifications:campaigns every-five-minutes')->everyFiveMinutes();
Schedule::command('notifications:campaigns hourly')->hourly();
Schedule::command('notifications:campaigns daily')->dailyAt('09:15')->timezone(config('app.timezone', 'UTC'));
Schedule::command('notifications:campaigns weekly')->sundays()->at('18:00')->timezone(config('app.timezone', 'UTC'));
Schedule::command('app:send-anniversary-notifications')
    ->dailyAt('09:00')
    ->timezone(config('app.timezone', 'UTC'));

// Profile completion reminders (WhatsApp 48-hour cycle)
Schedule::command('profile-completion:send-reminders')->hourly();

// Circle recommendation reminders (WhatsApp 3-day cycle)
Schedule::command('circle-recommendation:send-reminders')->dailyAt('10:00')->timezone(config('app.timezone', 'UTC'));
