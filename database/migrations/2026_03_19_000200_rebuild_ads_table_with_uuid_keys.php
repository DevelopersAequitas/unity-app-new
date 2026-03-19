<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('ads')) {
            return;
        }

        $idType = $this->columnType('id');
        $createdByType = $this->columnType('created_by');

        $requiresRebuild = in_array($idType, ['bigint', 'integer', 'smallint'], true)
            || in_array($createdByType, ['bigint', 'integer', 'smallint'], true);

        if (! $requiresRebuild) {
            return;
        }

        $legacyTable = 'ads_legacy_bigint_' . now()->format('YmdHis');
        Schema::rename('ads', $legacyTable);

        Schema::create('ads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('redirect_url', 500)->nullable();
            $table->string('button_text', 100)->nullable();
            $table->string('placement', 50)->default('timeline');
            $table->string('page_name', 100)->nullable();
            $table->unsignedInteger('timeline_position')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index('placement');
            $table->index('is_active');
            $table->index('starts_at');
            $table->index('ends_at');
            $table->index('timeline_position');
            $table->index('sort_order');
            $table->index(['placement', 'is_active', 'sort_order']);
            $table->index(['placement', 'timeline_position', 'sort_order']);
        });
    }

    public function down(): void
    {
        // Intentionally left empty: rebuild keeps legacy table as backup.
    }

    private function columnType(string $column): ?string
    {
        $connection = Schema::getConnection();
        $table = $connection->getTablePrefix() . 'ads';

        $result = DB::table('information_schema.columns')
            ->select('data_type')
            ->whereRaw('table_schema = current_schema()')
            ->where('table_name', $table)
            ->where('column_name', $column)
            ->first();

        return $result?->data_type;
    }
};
