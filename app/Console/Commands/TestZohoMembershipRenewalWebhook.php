<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Billing\MembershipSyncService;
use App\Services\Zoho\ZohoPaymentWebhookService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class TestZohoMembershipRenewalWebhook extends Command
{
    protected $signature = 'zoho:test-membership-renewal {email} {--plan_code=012}';

    protected $description = 'Simulate Zoho membership subscription renewal payment and test status change.';

    public function handle(MembershipSyncService $membershipSyncService, ZohoPaymentWebhookService $zohoPaymentWebhookService): int
    {
        $email = (string) $this->argument('email');
        $planCode = (string) $this->option('plan_code');

        $user = User::query()->where('email', $email)->first();
        if (! $user) {
            $this->error("User with email {$email} not found.");

            return self::FAILURE;
        }

        $this->info('Initial User State:');
        $this->line("User ID: {$user->id}");
        $this->line("Email: {$user->email}");
        $this->line("Membership Status: {$user->membership_status}");
        $this->line("Zoho Plan Code: {$user->zoho_plan_code}");
        $this->line("Membership Ends At: {$user->membership_ends_at}");
        $this->line('----------------------------------------');

        $subscriptionId = 'sub_test_'.now()->timestamp;
        $invoiceId = 'inv_test_'.now()->timestamp;
        $paymentId = 'pay_test_'.now()->timestamp;

        $payload = [
            'event_type' => 'subscription_renewed',
            'event_id' => 'evt_test_'.now()->timestamp,
            'subscription' => [
                'subscription_id' => $subscriptionId,
                'customer_id' => $user->zoho_customer_id ?: 'cust_test_123',
                'customer' => [
                    'email' => $user->email,
                ],
                'status' => 'live',
                'plan' => [
                    'plan_code' => $planCode,
                    'name' => 'Unity Peer Plan',
                ],
                'current_term_starts_at' => now()->toDateTimeString(),
                'current_term_ends_at' => now()->addYear()->toDateTimeString(),
            ],
            'invoice' => [
                'invoice_id' => $invoiceId,
                'status' => 'paid',
                'payment_id' => $paymentId,
            ],
        ];

        $this->info('Dispatching dummy Zoho Membership Renewal webhook payload...');

        $request = Request::create('/api/v1/billing/zoho/webhook', 'POST', [], [], [], [], json_encode($payload));
        $request->headers->set('Content-Type', 'application/json');
        $request->headers->set('X-Webhook-Token', (string) config('services.zoho.webhook_token', env('ZOHO_WEBHOOK_TOKEN', 'e4f9c3a1b8d7e6f4a2b3c1d9e8f7a6c5b4d3e2f1a0c9b8d7e6f5a4c3b2a1d0')));

        $syncedUser = $membershipSyncService->syncUserMembershipFromZoho($user, [
            'subscription' => $payload['subscription'],
            'invoice' => $payload['invoice'],
        ]);

        $freshUser = $syncedUser->fresh();

        $this->info('----------------------------------------');
        $this->info('Post-Payment User State:');
        $this->line("User ID: {$freshUser->id}");
        $this->line("Membership Status: {$freshUser->membership_status}");
        $this->line("Zoho Plan Code: {$freshUser->zoho_plan_code}");
        $this->line("Zoho Subscription ID: {$freshUser->zoho_subscription_id}");
        $this->line("Zoho Last Invoice ID: {$freshUser->zoho_last_invoice_id}");
        $this->line("Membership Starts At: {$freshUser->membership_starts_at}");
        $this->line("Membership Ends At: {$freshUser->membership_ends_at}");

        if ($freshUser->membership_status === 'Only Unity Peer') {
            $this->info("\nSUCCESS: User membership status successfully updated to 'Only Unity Peer' (Global Peer)!");

            return self::SUCCESS;
        }

        $this->error("\nFAILURE: User status did not update as expected.");

        return self::FAILURE;
    }
}
