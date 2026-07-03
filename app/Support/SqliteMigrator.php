<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class SqliteMigrator
{
    public static function run(string $sql): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $translatedSql = self::translate($sql);
            
            // Execute statements individually
            $statements = explode(';', $translatedSql);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if ($statement !== '') {
                    try {
                        DB::unprepared($statement);
                    } catch (\Throwable $e) {
                        // Ignore some drop index or specific errors for non-existent columns in SQLite helper
                        if (stripos($statement, 'DROP INDEX') === false && stripos($statement, 'DROP COLUMN') === false) {
                            throw $e;
                        }
                    }
                }
            }
        } else {
            DB::unprepared($sql);
        }
    }

    public static function translate(string $sql): string
    {
        // Check for ALTER TABLE ADD COLUMN and skip if column already exists
        if (preg_match('/ALTER\s+TABLE\s+([a-z0-9_]+)\s+ADD\s+(?:COLUMN\s+)?(?:IF\s+NOT\s+EXISTS\s+)?([a-z0-9_]+)/i', $sql, $matches)) {
            $table = $matches[1];
            $column = $matches[2];
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable($table) && \Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                    return '';
                }
            } catch (\Throwable $e) {}
        }

        // Check for ALTER TABLE DROP COLUMN and skip if column does not exist
        if (preg_match('/ALTER\s+TABLE\s+([a-z0-9_]+)\s+DROP\s+(?:COLUMN\s+)?(?:IF\s+EXISTS\s+)?([a-z0-9_]+)/i', $sql, $matches)) {
            $table = $matches[1];
            $column = $matches[2];
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable($table) && !\Illuminate\Support\Facades\Schema::hasColumn($table, $column)) {
                    return '';
                }
            } catch (\Throwable $e) {}
        }

        // 1. Remove PostgreSQL extensions
        $sql = preg_replace('/CREATE EXTENSION.*/i', '', $sql);
        
        // 2. Remove DO blocks (used to declare enum types in pgsql)
        $sql = preg_replace('/DO\s+\$\$.*?END\s+\$\$;/is', '', $sql);
        
        // 3. Remove ALTER TYPE commands (which don't apply to SQLite)
        $sql = preg_replace('/ALTER TYPE.*/i', '', $sql);
        
        // 4. Remove custom enum creation statements
        $sql = preg_replace('/CREATE TYPE [a-z0-9_]+ AS ENUM\s*\([^)]*\);/is', '', $sql);
        
        // 5. Remove USING GIN or USING GIST method specifiers, keeping the index columns
        $sql = preg_replace('/USING\s+(GIN|GIST|BTREE|HASH)\b/is', '', $sql);
        // Remove operator classes like gin_trgm_ops inside indexes
        $sql = preg_replace('/\((.*?)\s+[a-z0-9_]+_ops\)/is', '($1)', $sql);
        
        // 6. Remove plpgsql functions and triggers
        $sql = preg_replace('/CREATE OR REPLACE FUNCTION.*?\$\$ LANGUAGE plpgsql;/is', '', $sql);
        $sql = preg_replace('/CREATE TRIGGER.*?;/is', '', $sql);
        
        // 7. Convert Materialized Views to standard Tables in SQLite to avoid alter/rename locks
        $sql = preg_replace('/CREATE MATERIALIZED VIEW\s+([a-z0-9_]+)\s+AS/is', 'CREATE TABLE $1 AS', $sql);
        $sql = preg_replace('/CREATE UNIQUE INDEX [a-z0-9_]+ ON [a-z0-9_]+\([^)]*\);/is', '', $sql);
        
        // Remove PostgreSQL type casting syntax (e.g. '[]'::jsonb or '[]'::text)
        $sql = preg_replace('/::[a-z0-9_]+/i', '', $sql);
        
        // 8. SQLite does not support IF NOT EXISTS in ADD COLUMN/DROP COLUMN.
        $sql = preg_replace('/ADD COLUMN IF NOT EXISTS\s+([a-z0-9_]+)/i', 'ADD COLUMN $1', $sql);
        $sql = preg_replace('/ADD IF NOT EXISTS\s+([a-z0-9_]+)/i', 'ADD COLUMN $1', $sql);
        $sql = preg_replace('/DROP COLUMN IF EXISTS\s+([a-z0-9_]+)/i', 'DROP COLUMN $1', $sql);
        
        // 9. Remove other ALTER COLUMN/ALTER TYPE syntax that SQLite doesn't support
        // SQLite doesn't support ALTER COLUMN SET DEFAULT, so we can ignore those statements.
        if (stripos($sql, 'ALTER COLUMN') !== false && (stripos($sql, 'SET DEFAULT') !== false || stripos($sql, 'TYPE') !== false)) {
            return '';
        }
        
        // 10. Replace types and default values
        $replacements = [
            'UUID PRIMARY KEY DEFAULT gen_random_uuid()' => 'TEXT PRIMARY KEY',
            'UUID PRIMARY KEY' => 'TEXT PRIMARY KEY',
            'UUID' => 'TEXT',
            'TIMESTAMPTZ' => 'DATETIME',
            'JSONB' => 'TEXT',
            'INET' => 'TEXT',
            'tsvector' => 'TEXT',
            'DEFAULT NOW()' => 'DEFAULT CURRENT_TIMESTAMP',
            'DEFAULT (NOW())' => 'DEFAULT CURRENT_TIMESTAMP',
            'p2p_meeting_status_enum' => 'TEXT',
            'membership_status_enum' => 'TEXT',
            'circle_status_enum' => 'TEXT',
            'circle_member_role_enum' => 'TEXT',
            'circle_member_status_enum' => 'TEXT',
            'post_moderation_status_enum' => 'TEXT',
            'post_visibility_enum' => 'TEXT',
            'event_rsvp_status_enum' => 'TEXT',
            'activity_type_enum' => 'TEXT',
            'activity_status_enum' => 'TEXT',
            'wallet_tx_type_enum' => 'TEXT',
            'wallet_tx_status_enum' => 'TEXT',
            'notification_type_enum' => 'TEXT',
            'referral_status_enum' => 'TEXT',
            'visitor_status_enum' => 'TEXT',
            'admin_role_key_enum' => 'TEXT',
        ];
        
        foreach ($replacements as $search => $replace) {
            $sql = str_ireplace($search, $replace, $sql);
        }
        
        return $sql;
    }
}
