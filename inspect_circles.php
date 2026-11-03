<?php
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', '123123');
    $stmt = $pdo->query('SELECT datname FROM pg_database WHERE datistemplate = false;');
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Databases: " . implode(', ', $dbs) . "\n";

    foreach ($dbs as $db) {
        try {
            $p = new PDO("pgsql:host=127.0.0.1;port=5432;dbname={$db}", 'postgres', '123123');
            $c = $p->query("SELECT count(*) FROM information_schema.tables WHERE table_name = 'circles'")->fetchColumn();
            if ($c > 0) {
                $count = $p->query("SELECT count(*) FROM circles")->fetchColumn();
                echo "DB: {$db} has circles table with {$count} rows.\n";
                if ($count > 0) {
                    $names = $p->query("SELECT name FROM circles LIMIT 10")->fetchAll(PDO::FETCH_COLUMN);
                    echo "  Sample names: " . implode(', ', $names) . "\n";
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
