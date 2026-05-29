<?php

namespace App\Console\Commands;

use App\Support\Zoho\ZohoBillingClient;
use Illuminate\Console\Command;

class TestZohoConvertInvoice extends Command
{
    protected $signature = 'zoho:test-convert-invoice {invoice_id}';

    protected $description = 'Test Zoho Billing converttoopen action endpoint for an invoice id.';

    public function handle(ZohoBillingClient $client): int
    {
        $invoiceId = (string) $this->argument('invoice_id');
        $path = '/invoices/'.$invoiceId.'/converttoopen';
        $url = rtrim((string) config('zoho_billing.base_url'), '/') . $path;

        $this->line('URL: '.$url);
        try {
            $response = $client->postZohoAction($path, []);
            $this->info('status: success');
            $this->line('response: '.json_encode($response));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('status: failed');
            $this->error('response: '.$e->getMessage());
            return self::FAILURE;
        }
    }
}
