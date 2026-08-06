<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS event_circles (
                id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                event_id UUID NOT NULL REFERENCES events(id) ON DELETE CASCADE,
                circle_id UUID NOT NULL REFERENCES circles(id) ON DELETE CASCADE,
                created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
                CONSTRAINT unique_event_circle UNIQUE (event_id, circle_id)
            );
        ");
    }

    public function down(): void
    {
        DB::statement("DROP TABLE IF EXISTS event_circles;");
    }
};
