<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Rbac;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Support\AdminAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class RoleLifespanController extends Controller
{
    public function index(Request $request): View
    {
        // 1. Fetch search and filter inputs
        $search = $request->input('search');
        $roleFilter = $request->input('role_id');
        $statusFilter = $request->input('status'); // active, historical

        // 2. Fetch all roles for the dropdown filter
        $roles = Role::query()->where('status', 'active')->orderBy('name')->get();
        $rolesMap = $roles->pluck('name', 'key')->toArray();

        // 3. Fetch all admin users
        $adminUsersQuery = DB::table('admin_users');
        if ($search) {
            $adminUsersQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('email', 'like', '%' . $search . '%');
            });
        }
        $adminUsers = $adminUsersQuery->get();
        $adminUsersMap = $adminUsers->pluck('name', 'id')->toArray();
        $adminUserEmails = $adminUsers->pluck('email', 'id')->toArray();

        // 4. Fetch scope associations
        // DED Districts
        $dedDistricts = [];
        if (Schema::hasTable('admin_ded_districts')) {
            $dedDistricts = DB::table('admin_ded_districts')
                ->select('admin_user_id', 'district_name', 'state_name')
                ->get()
                ->groupBy('admin_user_id')
                ->toArray();
        }

        // ID Industries
        $idIndustries = [];
        if (Schema::hasTable('industry_director_assignments')) {
            $idIndustries = DB::table('industry_director_assignments')
                ->select('admin_user_id', 'industry_name')
                ->where('is_active', true)
                ->get()
                ->groupBy('admin_user_id')
                ->toArray();
        }

        // Circle scopes for Circle-based roles (CD, CF, Chair, etc.)
        // Linked by email to admin_users
        $circleMemberships = [];
        if (Schema::hasTable('circle_members') && Schema::hasTable('circles')) {
            $circleMemberships = DB::table('circle_members')
                ->join('circles', 'circles.id', '=', 'circle_members.circle_id')
                ->join('users', 'users.id', '=', 'circle_members.user_id')
                ->select('users.email', 'circles.name as circle_name', 'circle_members.role as circle_role')
                ->whereNull('circle_members.deleted_at')
                ->get()
                ->groupBy(function ($item) {
                    return strtolower($item->email);
                })
                ->toArray();
        }

        // 5. Gather Active Assignments
        $activeAssignmentsQuery = DB::table('admin_user_roles')
            ->join('roles', 'roles.id', '=', 'admin_user_roles.role_id')
            ->join('admin_users', 'admin_users.id', '=', 'admin_user_roles.user_id')
            ->select(
                'admin_user_roles.id as assignment_id',
                'admin_user_roles.user_id',
                'admin_user_roles.role_id',
                'admin_user_roles.created_at as assigned_at',
                'roles.name as role_name',
                'roles.key as role_key',
                'admin_users.name as user_name',
                'admin_users.email as user_email'
            );

        if ($search) {
            $activeAssignmentsQuery->where(function ($q) use ($search) {
                $q->where('admin_users.name', 'like', '%' . $search . '%')
                  ->orWhere('admin_users.email', 'like', '%' . $search . '%');
            });
        }

        if ($roleFilter) {
            $activeAssignmentsQuery->where('admin_user_roles.role_id', $roleFilter);
        }

        $activeAssignmentsRaw = $activeAssignmentsQuery->get();

        // 6. Fetch Audit Logs to reconstruct history and determine who performed the actions
        $auditLogs = DB::table('admin_audit_logs')
            ->where('target_table', 'admin_user_roles')
            ->orderBy('created_at', 'asc')
            ->get();

        // Group audit logs by user_id (target_id)
        $auditLogsByUser = $auditLogs->groupBy('target_id');

        $activeAssignments = [];
        foreach ($activeAssignmentsRaw as $active) {
            // Find who assigned it
            $assignedBy = 'System';
            $userLogs = $auditLogsByUser->get($active->user_id) ?? collect();
            
            // Find the latest assign log for this role key
            $assignLog = $userLogs->filter(function ($log) use ($active) {
                if ($log->action !== 'admin.rbac.role.assign') return false;
                $details = json_decode($log->details, true);
                $roleKey = $details['new_values']['role_key'] ?? '';
                return strtolower(str_replace(' ', '_', $roleKey)) === strtolower(str_replace(' ', '_', $active->role_key));
            })->last();

            if ($assignLog) {
                $assignedBy = $adminUsersMap[$assignLog->admin_user_id] ?? 'Administrator';
            }

            // Resolve scope details
            $scopes = [];
            $roleKeyLower = strtolower($active->role_key);

            $isDed = $roleKeyLower === 'ded' || str_contains($roleKeyLower, 'ded') || str_contains($roleKeyLower, 'district');
            $isId = $roleKeyLower === 'id' || $roleKeyLower === 'ied' || str_contains($roleKeyLower, 'industry');
            $isCircle = in_array($roleKeyLower, ['cd', 'cf', 'chair', 'vice_chair', 'secretary', 'circle_leader'], true) ||
                str_contains($roleKeyLower, 'circle') ||
                str_contains($roleKeyLower, 'leader') ||
                str_contains($roleKeyLower, 'founder') ||
                str_contains($roleKeyLower, 'chair') ||
                str_contains($roleKeyLower, 'secretary');

            if ($isDed && isset($dedDistricts[$active->user_id])) {
                foreach ($dedDistricts[$active->user_id] as $d) {
                    $scopes[] = $d->district_name . ' (' . ($d->state_name ?? 'District') . ')';
                }
            } elseif ($isId && isset($idIndustries[$active->user_id])) {
                foreach ($idIndustries[$active->user_id] as $i) {
                    $scopes[] = $i->industry_name;
                }
            } elseif ($isCircle && isset($circleMemberships[strtolower($active->user_email)])) {
                foreach ($circleMemberships[strtolower($active->user_email)] as $c) {
                    $scopes[] = $c->circle_name . ' (' . ucfirst(str_replace('_', ' ', $c->circle_role)) . ')';
                }
            }

            $assignedAt = Carbon::parse($active->assigned_at);
            $activeAssignments[] = [
                'user_name' => $active->user_name,
                'user_email' => $active->user_email,
                'role_name' => $active->role_name,
                'role_key' => $active->role_key,
                'assigned_at' => $assignedAt->toDayDateTimeString(),
                'assigned_by' => $assignedBy,
                'duration' => $assignedAt->diffForHumans(now(), true),
                'scopes' => empty($scopes) ? ['Global Scope'] : $scopes,
            ];
        }

        // 7. Reconstruct Historical Lifespans from Audit Logs
        $historicalLifespans = [];
        $timelineEvents = [];

        foreach ($auditLogsByUser as $targetUserId => $logs) {
            $userEmail = $adminUserEmails[$targetUserId] ?? null;
            $userName = $adminUsersMap[$targetUserId] ?? null;
            if (!$userName) continue;

            if ($search && !str_contains(strtolower($userName), strtolower($search)) && !str_contains(strtolower($userEmail ?? ''), strtolower($search))) {
                continue;
            }

            // We will track the open assignments for this user
            $openAssignments = [];

            foreach ($logs as $log) {
                $details = json_decode($log->details, true);
                $createdAt = Carbon::parse($log->created_at);
                $actorName = $adminUsersMap[$log->admin_user_id] ?? 'Administrator';

                if ($log->action === 'admin.rbac.role.assign') {
                    $roleKey = $details['new_values']['role_key'] ?? 'unknown';
                    $roleName = $rolesMap[$roleKey] ?? ucfirst(str_replace('_', ' ', $roleKey));

                    // Check if this role key is already opened, if so, close it first (superceded)
                    if (isset($openAssignments[$roleKey])) {
                        $start = $openAssignments[$roleKey]['assigned_at'];
                        $duration = $start->diffInDays($createdAt);
                        
                        $historicalLifespans[] = [
                            'user_name' => $userName,
                            'user_email' => $userEmail,
                            'role_name' => $roleName,
                            'role_key' => $roleKey,
                            'assigned_at' => $start->toDayDateTimeString(),
                            'removed_at' => $createdAt->toDayDateTimeString(),
                            'assigned_by' => $openAssignments[$roleKey]['assigned_by'],
                            'removed_by' => $actorName,
                            'duration' => $duration . ' days',
                            'reason' => 'Superceded by update',
                        ];
                    }

                    $openAssignments[$roleKey] = [
                        'assigned_at' => $createdAt,
                        'assigned_by' => $actorName,
                        'role_name' => $roleName,
                    ];

                    $timelineEvents[] = [
                        'date' => $createdAt->toDayDateTimeString(),
                        'timestamp' => $createdAt->timestamp,
                        'user_name' => $userName,
                        'role_name' => $roleName,
                        'action' => 'Assigned',
                        'icon' => 'bi-plus-circle-fill text-success',
                        'description' => "Role <strong>{$roleName}</strong> was assigned to <strong>{$userName}</strong> by <strong>{$actorName}</strong>",
                    ];
                } elseif ($log->action === 'admin.rbac.role.remove_assignment') {
                    $roleKey = $details['old_values']['role_key'] ?? 'unknown';
                    $roleName = $rolesMap[$roleKey] ?? ucfirst(str_replace('_', ' ', $roleKey));

                    if (isset($openAssignments[$roleKey])) {
                        $start = $openAssignments[$roleKey]['assigned_at'];
                        $duration = $start->diffInDays($createdAt);

                        $historicalLifespans[] = [
                            'user_name' => $userName,
                            'user_email' => $userEmail,
                            'role_name' => $roleName,
                            'role_key' => $roleKey,
                            'assigned_at' => $start->toDayDateTimeString(),
                            'removed_at' => $createdAt->toDayDateTimeString(),
                            'assigned_by' => $openAssignments[$roleKey]['assigned_by'],
                            'removed_by' => $actorName,
                            'duration' => $duration > 0 ? ($duration . ' days') : 'Less than a day',
                            'reason' => 'Removed by admin',
                        ];

                        unset($openAssignments[$roleKey]);
                    }

                    $timelineEvents[] = [
                        'date' => $createdAt->toDayDateTimeString(),
                        'timestamp' => $createdAt->timestamp,
                        'user_name' => $userName,
                        'role_name' => $roleName,
                        'action' => 'Removed',
                        'icon' => 'bi-dash-circle-fill text-danger',
                        'description' => "Role <strong>{$roleName}</strong> was removed from <strong>{$userName}</strong> by <strong>{$actorName}</strong>",
                    ];
                } elseif ($log->action === 'admin.profile.remove_current_role') {
                    // This removes ALL roles and defaults to 'user'
                    foreach ($openAssignments as $roleKey => $open) {
                        $start = $open['assigned_at'];
                        $duration = $open['assigned_at']->diffInDays($createdAt);

                        $historicalLifespans[] = [
                            'user_name' => $userName,
                            'user_email' => $userEmail,
                            'role_name' => $open['role_name'],
                            'role_key' => $roleKey,
                            'assigned_at' => $start->toDayDateTimeString(),
                            'removed_at' => $createdAt->toDayDateTimeString(),
                            'assigned_by' => $open['assigned_by'],
                            'removed_by' => $userName, // Self removed
                            'duration' => $duration > 0 ? ($duration . ' days') : 'Less than a day',
                            'reason' => 'Self-removed / fallback to User',
                        ];
                    }
                    $openAssignments = [];

                    $timelineEvents[] = [
                        'date' => $createdAt->toDayDateTimeString(),
                        'timestamp' => $createdAt->timestamp,
                        'user_name' => $userName,
                        'role_name' => 'All Roles',
                        'action' => 'Removed All',
                        'icon' => 'bi-x-circle-fill text-warning',
                        'description' => "<strong>{$userName}</strong> self-removed their roles and fell back to the default User role",
                    ];
                }
            }
        }

        // Apply filters to historical lifespans
        if ($roleFilter) {
            $filteredRole = Role::find($roleFilter);
            if ($filteredRole) {
                $historicalLifespans = array_filter($historicalLifespans, function ($item) use ($filteredRole) {
                    return strtolower(str_replace(' ', '_', $item['role_key'])) === strtolower(str_replace(' ', '_', $filteredRole->key));
                });
            }
        }

        // Sort timeline events newest first
        usort($timelineEvents, function ($a, $b) {
            return $b['timestamp'] <=> $a['timestamp'];
        });

        // Compute Overview Widgets
        $totalActiveCount = count($activeAssignmentsRaw);
        $totalUniqueUsers = count(array_unique(array_merge(
            $activeAssignmentsRaw->pluck('user_id')->toArray(),
            $logs->pluck('target_id')->toArray()
        )));

        // Calculate average duration from historical lifespans
        $totalDays = 0;
        $completedCount = 0;
        foreach ($historicalLifespans as $history) {
            $days = intval($history['duration']);
            $totalDays += $days;
            $completedCount++;
        }
        $avgDurationText = $completedCount > 0 ? round($totalDays / $completedCount, 1) . ' Days' : 'N/A';

        // Active breakdown list
        $breakdown = [];
        foreach ($activeAssignmentsRaw as $active) {
            $breakdown[$active->role_name] = ($breakdown[$active->role_name] ?? 0) + 1;
        }

        return view('admin.rbac.lifespan', [
            'activeAssignments' => $activeAssignments,
            'historicalLifespans' => $historicalLifespans,
            'timelineEvents' => array_slice($timelineEvents, 0, 50), // Limit to last 50 events
            'totalActiveCount' => $totalActiveCount,
            'totalUniqueUsers' => $totalUniqueUsers,
            'avgDurationText' => $avgDurationText,
            'breakdown' => $breakdown,
            'roles' => $roles,
            'selectedRoleId' => $roleFilter,
            'search' => $search,
            'statusFilter' => $statusFilter,
        ]);
    }
}
