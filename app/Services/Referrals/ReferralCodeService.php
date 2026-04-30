<?php

namespace App\Services\Referrals;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralCodeService
{
    public function generateUniqueCode(string $name = '', ?string $codeColumn = null): string
    {
        $attempts = 0;
        $column = $codeColumn ?: 'token';
        $prefix = $this->buildPrefix($name);

        do {
            $attempts++;
            $randomDigits = str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
            $code = $prefix . $randomDigits;
            $exists = DB::table('referral_links')->where($column, $code)->exists();
        } while ($exists && $attempts < 100);

        if ($exists) {
            throw new \RuntimeException('Unable to generate a unique referral token.');
        }

        return $code;
    }

    public function buildReferralLink(string $code): string
    {
        $base = (string) config('referrals.register_url', rtrim((string) config('app.url'), '/') . '/register');
        $param = (string) config('referrals.query_param', 'ref');

        return rtrim($base, '?&') . '?' . http_build_query([$param => $code]);
    }

    private function buildPrefix(string $name): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9]/', '', $name) ?? '';
        $sanitized = strtoupper($sanitized);

        if ($sanitized === '') {
            $sanitized = 'PEERS';
        }

        return Str::padRight(Str::substr($sanitized, 0, 5), 5, 'X');
    }
}
