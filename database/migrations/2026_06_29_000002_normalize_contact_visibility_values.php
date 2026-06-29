<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'contact_visibility')) {
            return;
        }

        DB::table('users')->whereIn('contact_visibility', ['public', 'everyone'])->update(['contact_visibility' => 'everyone']);
        DB::table('users')->whereIn('contact_visibility', ['connections', 'connected', 'contacts', 'contact_only', 'contacts_only', 'connected_only'])->update(['contact_visibility' => 'connected_only']);
        DB::table('users')->whereIn('contact_visibility', ['circle', 'circles', 'circle_members', 'circle_member', 'circle_only'])->update(['contact_visibility' => 'circle_only']);
        DB::table('users')->whereIn('contact_visibility', ['private', 'leadership_only', 'hidden'])->update(['contact_visibility' => 'hidden']);

        try {
            DB::statement("ALTER TABLE users ALTER COLUMN contact_visibility SET DEFAULT 'connected_only'");
        } catch (Throwable) {
            // Some database drivers used in tests do not support altering column defaults.
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'contact_visibility')) {
            return;
        }

        DB::table('users')->where('contact_visibility', 'connected_only')->update(['contact_visibility' => 'connections']);
        DB::table('users')->where('contact_visibility', 'circle_only')->update(['contact_visibility' => 'circle_members']);
        DB::table('users')->where('contact_visibility', 'hidden')->update(['contact_visibility' => 'private']);

        try {
            DB::statement("ALTER TABLE users ALTER COLUMN contact_visibility SET DEFAULT 'connections'");
        } catch (Throwable) {
            // Some database drivers used in tests do not support altering column defaults.
        }
    }
};
