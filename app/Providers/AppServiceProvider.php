<?php

namespace App\Providers;

use App\Models\AdminCampaign;
use App\Models\EmailLog;
use App\Policies\AdminCampaignPolicy;
use App\Policies\SponsorshipMilestonePolicy;
use App\Support\SqliteMigrator;
use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        Connection::resolverFor('sqlite', function ($connection, $database, $prefix, $config) {
            return new class($connection, $database, $prefix, $config) extends SQLiteConnection
            {
                public function __construct($pdo, $database = '', $tablePrefix = '', array $config = [])
                {
                    parent::__construct($pdo, $database, $tablePrefix, $config);

                    $this->getPdo()->sqliteCreateFunction('now', function () {
                        return date('Y-m-d H:i:s');
                    });
                }

                public function statement($query, $bindings = [])
                {
                    $query = SqliteMigrator::translate($query);
                    $query = str_ireplace('sqlite_autoindex_', 'idx_autoindex_', $query);
                    if (empty(trim($query))) {
                        return true;
                    }

                    return parent::statement($query, $bindings);
                }

                public function unprepared($query)
                {
                    $query = SqliteMigrator::translate($query);
                    $query = str_ireplace('sqlite_autoindex_', 'idx_autoindex_', $query);
                    if (empty(trim($query))) {
                        return true;
                    }

                    return parent::unprepared($query);
                }

                protected function run($query, $bindings, \Closure $callback)
                {
                    $query = SqliteMigrator::translate($query);
                    $query = str_ireplace('sqlite_autoindex_', 'idx_autoindex_', $query);

                    return parent::run($query, $bindings, $callback);
                }
            };
        });

        Paginator::useBootstrapFive();
        Gate::policy(AdminCampaign::class, AdminCampaignPolicy::class);
        Gate::define('view-sponsored-milestones', [SponsorshipMilestonePolicy::class, 'viewAny']);
        Gate::define('view-member-sponsored-milestones', [SponsorshipMilestonePolicy::class, 'view']);

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
            ],
        ]);

        // Register global listener to capture outgoing email bodies and save them to email_logs
        Event::listen(
            MessageSending::class,
            function (MessageSending $event) {
                Log::info('Mail Listener triggered');
                try {
                    $message = $event->message;
                    $subject = $message->getSubject();
                    $to = collect($message->getTo())->map(fn ($addr) => $addr->getAddress())->first();

                    Log::info('Mail listener processing', [
                        'to' => $to,
                        'subject' => $subject,
                    ]);

                    if (empty($to)) {
                        Log::warning('Mail listener: No recipient found.');

                        return;
                    }

                    $html = $message->getHtmlBody();
                    $text = $message->getTextBody();

                    // Find a recently created log within the last 30 seconds for this recipient
                    $log = EmailLog::where('to_email', $to)
                        ->where(function ($query) use ($subject) {
                            if (! empty($subject)) {
                                $query->where('subject', $subject)
                                    ->orWhereNull('subject')
                                    ->orWhere('subject', '');
                            }
                        })
                        ->where('created_at', '>=', now()->subSeconds(30))
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($log) {
                        Log::info('Mail listener: Found matching email log, updating body', ['log_id' => $log->id]);
                        $updates = [];
                        if (empty($log->body_html) && ! empty($html)) {
                            $updates['body_html'] = $html;
                        }
                        if (empty($log->body_text) && ! empty($text)) {
                            $updates['body_text'] = $text;
                        }
                        if (! empty($updates)) {
                            $log->update($updates);
                        }
                    } else {
                        Log::info('Mail listener: No matching log found, creating a new one.');
                        // Create a new log if none exists
                        EmailLog::create([
                            'id' => (string) Str::uuid(),
                            'to_email' => $to,
                            'template_key' => 'raw_email',
                            'subject' => $subject,
                            'body_html' => $html,
                            'body_text' => $text,
                            'status' => 'sent',
                            'sent_at' => now(),
                            'created_at' => now(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Error logging outgoing mail body: '.$e->getMessage(), [
                        'exception' => $e,
                    ]);
                }
            }
        );
    }
}
