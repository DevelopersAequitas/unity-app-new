<?php

$passwords = ['postgres', 'root', 'admin', 'password', '123456', '1234', '2006', ''];
foreach ($passwords as $p) {
    try {
        new PDO('pgsql:host=127.0.0.1;port=5432;dbname=postgres', 'postgres', $p);
        echo 'SUCCESS PASSWORD: '.var_export($p, true).PHP_EOL;
        exit(0);
    } catch (\Throwable $e) {
        // failed
    }
}
echo 'ALL COMMON PASSWORDS FAILED'.PHP_EOL;
exit(1);
