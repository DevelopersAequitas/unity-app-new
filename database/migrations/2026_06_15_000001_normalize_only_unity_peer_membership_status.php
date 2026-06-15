<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("\n            UPDATE users\n            SET membership_status = 'only_unity_peer'\n            WHERE LOWER(REPLACE(REPLACE(membership_status, '-', '_'), ' ', '_')) IN (\n                'only_unity_peer',\n                'onlyunitypeer',\n                'only_unity_peers',\n                'only_unity',\n                'unity_peer'\n            )\n        ");
    }

    public function down(): void
    {
        // Data normalization is intentionally irreversible.
    }
};
