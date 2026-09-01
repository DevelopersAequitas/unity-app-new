<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "--- ADMIN USERS AND ROLES ---\n";
$users = DB::table('admin_users')->get();
foreach ($users as $u) {
    echo "\nUser: {$u->name} ({$u->email})\n";
    $roles = DB::table('admin_user_roles')
        ->join('roles', 'admin_user_roles.role_id', '=', 'roles.id')
        ->select('roles.name as role_name', 'roles.key as role_key')
        ->where('admin_user_roles.user_id', $u->id)
        ->get();
    if ($roles->isEmpty()) {
        echo "  (No roles assigned)\n";
    } else {
        foreach ($roles as $r) {
            echo "  Role: {$r->role_name} (key: {$r->role_key})\n";
        }
    }
}
