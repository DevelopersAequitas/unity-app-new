<?php

namespace App\Console\Commands;

use App\Services\Store\StoreReconciliationService;
use Illuminate\Console\Command;

class StoreReconciliationCommand extends Command
{
    protected $signature = 'store:reconcile-wallets';
    protected $description = 'Perform nightly wallet reconciliation comparing cached balance against coins ledger';

    public function handle(StoreReconciliationService $service): int
    {
        $this->info('Starting store wallet reconciliation...');
        $result = $service->reconcileWallets();

        if ($result['status'] === 'OK') {
            $this->info('All peer wallets reconciled perfectly with coins ledger! (0 mismatches)');
            return 0;
        }

        $this->error("Found {$result['mismatch_count']} wallet mismatches! Critical alerts logged.");
        foreach ($result['mismatches'] as $mismatch) {
            $this->warn("User: {$mismatch['user_id']} | Cached: {$mismatch['cached_balance']} | Ledger: {$mismatch['ledger_balance']} | Diff: {$mismatch['diff']}");
        }

        return 1;
    }
}
