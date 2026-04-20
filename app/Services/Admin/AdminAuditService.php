<?php

namespace App\Services\Admin;

use App\Models\AdminAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminAuditService
{
    public function log(User $actor, string $action, string $resourceType, string $resourceId, array $oldValues = [], array $newValues = [], ?Request $request = null): void
    {
        AdminAuditLog::create([
            'id' => (string) Str::uuid(),
            'admin_user_id' => $actor->id,
            'action' => $action,
            'target_table' => $resourceType,
            'target_id' => $resourceId,
            'details' => [
                'actor_role' => $actor->roles()->pluck('roles.key')->implode(','),
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ],
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'created_at' => now(),
        ]);
    }
}
