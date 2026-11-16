<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add handshake & preview columns to speed_networking_matches
        if (Schema::hasTable('speed_networking_matches')) {
            Schema::table('speed_networking_matches', function (Blueprint $table): void {
                if (! Schema::hasColumn('speed_networking_matches', 'match_status')) {
                    $table->string('match_status', 32)->default('preview')->index();
                }
                if (! Schema::hasColumn('speed_networking_matches', 'user_a_decision')) {
                    $table->string('user_a_decision', 16)->default('pending');
                }
                if (! Schema::hasColumn('speed_networking_matches', 'user_b_decision')) {
                    $table->string('user_b_decision', 16)->default('pending');
                }
                if (! Schema::hasColumn('speed_networking_matches', 'preview_duration_seconds')) {
                    $table->integer('preview_duration_seconds')->default(15);
                }
                if (! Schema::hasColumn('speed_networking_matches', 'preview_expires_at')) {
                    $table->timestamp('preview_expires_at')->nullable();
                }
                if (! Schema::hasColumn('speed_networking_matches', 'call_started_at')) {
                    $table->timestamp('call_started_at')->nullable();
                }
            });

            // Backfill historical matches so existing completed/active matches preserve their status
            try {
                DB::table('speed_networking_matches')
                    ->where('status', 'completed')
                    ->update([
                        'match_status' => 'completed',
                        'user_a_decision' => 'accepted',
                        'user_b_decision' => 'accepted',
                    ]);

                DB::table('speed_networking_matches')
                    ->where('status', 'skipped')
                    ->where('match_status', 'preview')
                    ->update([
                        'match_status' => 'skipped',
                    ]);
            } catch (Throwable) {
                // Ignore backfill errors
            }
        }

        // 2. Create speed_networking_blocked_peers table (Speed-Networking-only permanent blocks)
        if (! Schema::hasTable('speed_networking_blocked_peers')) {
            Schema::create('speed_networking_blocked_peers', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('blocker_id', 64)->index();
                $table->string('blocked_id', 64)->index();
                $table->string('event_id', 64)->nullable()->index();
                $table->string('reason', 255)->nullable();
                $table->timestamps();

                $table->unique(['blocker_id', 'blocked_id'], 'uq_sn_blocker_blocked');
                $table->index(['blocked_id', 'blocker_id'], 'idx_sn_blocked_blocker');
            });
        }

        // 3. Create speed_networking_event_skips table (Per-event skips)
        if (! Schema::hasTable('speed_networking_event_skips')) {
            Schema::create('speed_networking_event_skips', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('event_id', 64)->index();
                $table->string('user_id', 64)->index();
                $table->string('skipped_peer_id', 64)->index();
                $table->timestamps();

                $table->unique(['event_id', 'user_id', 'skipped_peer_id'], 'uq_sn_event_skip');
                $table->index(['event_id', 'skipped_peer_id', 'user_id'], 'idx_sn_event_skip_reverse');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('speed_networking_event_skips');
        Schema::dropIfExists('speed_networking_blocked_peers');

        if (Schema::hasTable('speed_networking_matches')) {
            Schema::table('speed_networking_matches', function (Blueprint $table): void {
                $cols = [
                    'match_status',
                    'user_a_decision',
                    'user_b_decision',
                    'preview_duration_seconds',
                    'preview_expires_at',
                    'call_started_at',
                ];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('speed_networking_matches', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
