<?php
try {
    $dsn = "pgsql:host=127.0.0.1;port=5432;dbname=unityappbackend";
    $pdo = new PDO($dsn, "postgres", "2006", [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo "--- Platform counts in user_push_tokens (is_active = true) ---" . PHP_EOL;
    $stmt = $pdo->query("SELECT platform, count(*) as count FROM user_push_tokens WHERE is_active = true GROUP BY platform");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("Platform: %s | Count: %d\n", json_encode($row['platform']), $row['count']);
    }

    echo PHP_EOL . "--- Active push tokens whose user_id is in users table ---" . PHP_EOL;
    $stmt = $pdo->query("
        SELECT 
            u.id as user_id, 
            u.status as user_status, 
            u.membership_status, 
            count(t.id) as token_count 
        FROM user_push_tokens t
        LEFT JOIN users u ON t.user_id = u.id
        WHERE t.is_active = true
        GROUP BY u.id, u.status, u.membership_status
    ");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        printf("User ID: %s | Status: %s | Membership Status: %s | Token Count: %d\n", 
            $row['user_id'] ?: 'NULL (Orphan)', 
            json_encode($row['user_status']), 
            json_encode($row['membership_status']), 
            $row['token_count']
        );
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . PHP_EOL;
}
