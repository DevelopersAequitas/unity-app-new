<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('user_push_tokens') || ! Schema::hasTable('users')) {
            return;
        }

        $userIdCol = Schema::hasColumn('user_push_tokens', 'usr_id') ? 'usr_id' : 'user_id';

        // Retrieve the latest active token for each user and platform
        $query = DB::table('user_push_tokens')
            ->whereNotNull('token')
            ->where('token', '!=', '');

        if (Schema::hasColumn('user_push_tokens', 'is_active')) {
            $query->where('is_active', true);
        }
        if (Schema::hasColumn('user_push_tokens', 'status')) {
            $query->where('status', 'active');
        }
        if (Schema::hasColumn('user_push_tokens', 'token_status')) {
            $query->where('token_status', 'active');
        }

        $latestColumn = 'updated_at';
        if (Schema::hasColumn('user_push_tokens', 'last_used_at')) {
            $latestColumn = 'last_used_at';
        } elseif (Schema::hasColumn('user_push_tokens', 'last_seen_at')) {
            $latestColumn = 'last_seen_at';
        }

        $tokens = $query->orderBy($userIdCol)
            ->orderBy('platform')
            ->orderBy($latestColumn, 'desc')
            ->get();

        // Group by user_id and platform to get the latest one
        $updates = [];
        foreach ($tokens as $tokenRow) {
            $userId = $tokenRow->{$userIdCol};
            $platform = strtolower((string) ($tokenRow->platform ?? ''));
            $token = $tokenRow->token;

            if (empty($userId) || empty($platform)) {
                continue;
            }

            if (! isset($updates[$userId])) {
                $updates[$userId] = [
                    'android_fcm_token' => null,
                    'ios_fcm_token' => null,
                ];
            }

            if ($platform === 'android' && $updates[$userId]['android_fcm_token'] === null) {
                $updates[$userId]['android_fcm_token'] = $token;
            } elseif (in_array($platform, ['ios', 'apple', 'iphone']) && $updates[$userId]['ios_fcm_token'] === null) {
                $updates[$userId]['ios_fcm_token'] = $token;
            }
        }

        // Perform the updates in chunks/transactions
        DB::transaction(function () use ($updates) {
            foreach ($updates as $userId => $fields) {
                $filteredFields = array_filter($fields, fn ($v) => $v !== null);
                if (! empty($filteredFields)) {
                    DB::table('users')
                        ->where('id', $userId)
                        ->update($filteredFields);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No action needed for rolling back backfilled data.
    }
};
