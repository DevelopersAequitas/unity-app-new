<?php

declare(strict_types=1);

use App\Models\EmailLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('email_logs')) {
            return;
        }

        $rawLogs = EmailLog::query()->where('template_key', 'raw_email')->get();

        foreach ($rawLogs as $raw) {
            $matching = EmailLog::query()
                ->where('to_email', $raw->to_email)
                ->where('id', '!=', $raw->id)
                ->where('template_key', '!=', 'raw_email')
                ->whereBetween('created_at', [
                    $raw->created_at->copy()->subSeconds(30),
                    $raw->created_at->copy()->addSeconds(30),
                ])
                ->first();

            if ($matching) {
                if (empty($matching->body_html) && ! empty($raw->body_html)) {
                    $matching->body_html = $raw->body_html;
                    $matching->save();
                }
                if (empty($matching->body_text) && ! empty($raw->body_text)) {
                    $matching->body_text = $raw->body_text;
                    $matching->save();
                }
                $raw->delete();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op: duplicate raw logs do not need to be restored.
    }
};
