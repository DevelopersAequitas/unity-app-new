<?php

namespace App\Services\Store;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreReconciliationService
{
    /**
     * Nightly job: Compare cached user coins_balance against SUM(coins_ledger.amount).
     * Never silently correct; alert & log any mismatch.
     */
    public function reconcileWallets(): array
    {
        $mismatches = [];

        $results = DB::select('
            SELECT 
                u.id as user_id, 
                COALESCE(u.coins_balance, 0) as cached_balance, 
                COALESCE(SUM(cl.amount), 0) as ledger_balance,
                COALESCE(u.coins_balance, 0) - COALESCE(SUM(cl.amount), 0) as diff
            FROM users u
            LEFT JOIN coins_ledger cl ON cl.user_id = u.id
            GROUP BY u.id, u.coins_balance
            HAVING COALESCE(u.coins_balance, 0) <> COALESCE(SUM(cl.amount), 0)
        ');

        foreach ($results as $row) {
            $mismatches[] = [
                'user_id' => $row->user_id,
                'cached_balance' => (int) $row->cached_balance,
                'ledger_balance' => (int) $row->ledger_balance,
                'diff' => (int) $row->diff,
            ];

            Log::critical('WALLET RECONCILIATION MISMATCH DETECTED', [
                'user_id' => $row->user_id,
                'cached' => $row->cached_balance,
                'ledger' => $row->ledger_balance,
                'diff' => $row->diff,
            ]);
        }

        return [
            'status' => empty($mismatches) ? 'OK' : 'MISMATCH_FOUND',
            'mismatch_count' => count($mismatches),
            'mismatches' => $mismatches,
        ];
    }
}
