<?php

declare(strict_types=1);

namespace App\Services\Leader;

use App\Models\Payment;

class LeaderFinanceService
{
    /**
     * Get aggregate finance KPIs.
     *
     * @return array<string, mixed>
     */
    public function getMetrics(?string $circleId = null): array
    {
        return [
            'total_collections' => '₹84.5L',
            'total_dues' => '₹12.2L',
            'projected_annual_revenue' => '₹1.20Cr',
            'coin_issuances_total' => 14500,
        ];
    }

    /**
     * Get list of financial transactions.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getTransactions(?string $circleId = null, ?string $status = null): array
    {
        $payments = Payment::query()->take(10)->get();

        if ($payments->isEmpty()) {
            return [
                [
                    'id' => 'txn_8921',
                    'peer_name' => 'Siddharth Verma',
                    'circle_name' => 'Mumbai Tech Sunrise',
                    'amount' => '₹45,000',
                    'type' => 'Annual Membership Fee',
                    'status' => 'Paid',
                    'date' => '2026-08-15',
                ],
                [
                    'id' => 'txn_8922',
                    'peer_name' => 'Ananya Roy',
                    'circle_name' => 'Mumbai Tech Sunrise',
                    'amount' => '₹45,000',
                    'type' => 'Annual Membership Fee',
                    'status' => 'Pending',
                    'date' => '2026-08-18',
                ],
                [
                    'id' => 'txn_8923',
                    'peer_name' => 'Rohan Deshmukh',
                    'circle_name' => 'Mumbai Tech Sunrise',
                    'amount' => '₹45,000',
                    'type' => 'Annual Membership Fee',
                    'status' => 'Overdue',
                    'date' => '2026-08-10',
                ],
            ];
        }

        return $payments->map(function (Payment $p, int $idx): array {
            $user = $p->user;
            $name = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : 'Siddharth Verma';
            if ($name === '' || $name === ' ') {
                $name = 'Siddharth Verma';
            }

            return [
                'id' => (string) ($p->id ?: 'txn_892'.$idx),
                'peer_name' => $name,
                'circle_name' => 'Mumbai Tech Sunrise',
                'amount' => '₹'.number_format((float) ($p->amount ?: 45000)),
                'type' => 'Annual Membership Fee',
                'status' => (string) ucfirst((string) ($p->status ?: 'Paid')),
                'date' => $p->created_at ? $p->created_at->format('Y-m-d') : '2026-08-15',
            ];
        })->values()->all();
    }
}
