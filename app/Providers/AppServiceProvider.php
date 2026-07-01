<?php

namespace App\Providers;

use App\Models\AdminCampaign;
use App\Policies\AdminCampaignPolicy;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        require_once app_path('Support/helpers.php');

        // Load newly created models manually to prevent Class Not Found errors
        // when composer optimized autoloader has not been refreshed on staging.
        foreach ([
            'Models/UserPushToken.php',
            'Models/EventNotificationLog.php',
            'Models/Notifications/AppNotification.php',
            'Models/Notifications/NotificationCampaign.php',
            'Models/Notifications/NotificationCampaignRun.php',
            'Models/Notifications/NotificationDeliveryLog.php',
            'Models/Notifications/NotificationPreference.php',
            'Models/Notifications/NotificationSuppressionLog.php',
        ] as $file) {
            $path = app_path($file);
            if (file_exists($path)) {
                require_once $path;
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Database\Connection::resolverFor('sqlite', function ($connection, $database, $prefix, $config) {
            return new class($connection, $database, $prefix, $config) extends \Illuminate\Database\SQLiteConnection {
                public function statement($query, $bindings = []) {
                    $query = \App\Support\SqliteMigrator::translate($query);
                    $query = str_ireplace('sqlite_autoindex_', 'idx_autoindex_', $query);
                    if (empty(trim($query))) return true;
                    return parent::statement($query, $bindings);
                }
                public function unprepared($query) {
                    $query = \App\Support\SqliteMigrator::translate($query);
                    $query = str_ireplace('sqlite_autoindex_', 'idx_autoindex_', $query);
                    if (empty(trim($query))) return true;
                    return parent::unprepared($query);
                }
                protected function run($query, $bindings, \Closure $callback) {
                    $query = \App\Support\SqliteMigrator::translate($query);
                    $query = str_ireplace('sqlite_autoindex_', 'idx_autoindex_', $query);
                    return parent::run($query, $bindings, $callback);
                }
            };
        });

        Paginator::useBootstrapFive();
        Gate::policy(AdminCampaign::class, AdminCampaignPolicy::class);



        $fromAddress = (string) config('mail.from.address');
        $fromName = (string) config('mail.from.name', 'Peers Global Unity');
        $smtpUsername = (string) config('mail.mailers.smtp.username');

        if (
            (bool) config('mail.force_smtp_username_as_from', true)
            && config('mail.default') === 'smtp'
            && filter_var($smtpUsername, FILTER_VALIDATE_EMAIL)
        ) {
            $fromAddress = $smtpUsername;
            config(['mail.from.address' => $fromAddress]);
        }

        Mail::alwaysFrom($fromAddress, $fromName);

        config([
            'mail.mailers.pravin' => [
                'transport' => 'smtp',
                'host' => env('MAIL_HOST_PRAVIN', 'smtppro.zoho.in'),
                'port' => env('MAIL_PORT_PRAVIN', 587),
                'encryption' => env('MAIL_ENCRYPTION_PRAVIN', 'tls'),
                'username' => env('MAIL_USERNAME_PRAVIN', 'pravin@peersunity.com'),
                'password' => env('MAIL_PASSWORD_PRAVIN'),
                'timeout' => null,
            ]
        ]);
    }
}
