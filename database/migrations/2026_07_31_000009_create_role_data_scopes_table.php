<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_data_scopes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('role_id');
            $table->enum('scope_type', ['circle', 'district', 'industry', 'country', 'global']);
            // null means "all of that scope_type". A specific UUID means one record.
            $table->string('scope_value')->nullable();
            $table->timestamps();

            $table->unique(['role_id', 'scope_type', 'scope_value'], 'rds_unique');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_data_scopes');
    }
};
