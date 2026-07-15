@php
    $admin = auth('admin')->user();
    $isSuper = \App\Support\AdminAccess::isSuper($admin);
    $isCircleScoped = \App\Support\AdminAccess::isCircleScoped($admin);
    $isDed = \App\Support\AdminAccess::isDed($admin);
    $roleKeys = \App\Support\AdminAccess::adminRoleKeys($admin);
    $isIndustryDirector = in_array('industry_director', $roleKeys);
    $isGlobalAdmin = in_array('global_admin', $roleKeys);

    $roleBadge = request()->is('admin/industry-director*')
        ? 'ID'
        : ($isGlobalAdmin
            ? 'Global Admin'
            : ($isIndustryDirector
                ? 'IDE'
                : ($isDed ? 'DED' : ($isCircleScoped ? \App\Support\AdminAccess::primaryCircleRoleLabel($admin) : 'Admin'))));


    $hour = (int) now()->format('H');
    $greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
    $greetingIcon = $hour < 12 ? 'bi-brightness-high' : ($hour < 17 ? 'bi-sun' : 'bi-moon-stars');

    // Fetch real pending action items for administrative alerts
    $topNotifications = collect();
    if ($admin) {
        // 1. Pending Circle Join Requests
        if (\Illuminate\Support\Facades\Schema::hasTable('circle_join_requests')) {
            try {
                $joinRequests = \App\Models\CircleJoinRequest::visibleToAdminUser($admin)
                    ->pending()
                    ->with(['user', 'circle'])
                    ->latest('requested_at')
                    ->limit(5)
                    ->get();
                foreach ($joinRequests as $req) {
                    $topNotifications->push((object)[
                        'type' => 'circle_join_request',
                        'title' => 'Circle Join Request',
                        'message' => ($req->user?->name ?? 'A Peer') . ' requested to join ' . ($req->circle?->name ?? 'a Circle'),
                        'time' => $req->requested_at ?: $req->created_at ?: now(),
                        'link' => route('admin.circle-joining-requests.index'),
                        'icon' => 'bi-diagram-3-fill',
                        'color' => 'var(--stat-indigo)'
                    ]);
                }
            } catch (\Exception $e) {
                // Fail silently to avoid breaking the application topbar
            }
        }

        // 2. Pending Post Reports
        if (\Illuminate\Support\Facades\Schema::hasTable('post_reports')) {
            try {
                $reports = \App\Models\PostReport::whereNull('reviewed_at')
                    ->with(['reporter', 'post'])
                    ->latest()
                    ->limit(5)
                    ->get();
                foreach ($reports as $report) {
                    $topNotifications->push((object)[
                        'type' => 'post_report',
                        'title' => 'Reported Post',
                        'message' => 'Post reported by ' . ($report->reporter?->name ?? 'a Peer') . ': ' . ($report->reason ?? 'Inappropriate content'),
                        'time' => $report->created_at ?: now(),
                        'link' => route('admin.post-reports.index'),
                        'icon' => 'bi-exclamation-triangle-fill',
                        'color' => 'var(--stat-rose)'
                    ]);
                }
            } catch (\Exception $e) {
            }
        }

        // 3. Pending Coin Claim Requests
        if (\Illuminate\Support\Facades\Schema::hasTable('coin_claim_requests')) {
            try {
                $claims = \App\Models\CoinClaimRequest::where('status', 'pending')
                    ->with('user')
                    ->latest()
                    ->limit(5)
                    ->get();
                foreach ($claims as $claim) {
                    $topNotifications->push((object)[
                        'type' => 'coin_claim',
                        'title' => 'Coin Claim Request',
                        'message' => ($claim->user?->name ?? 'A Peer') . ' claimed coins for activity: ' . $claim->activity_code,
                        'time' => $claim->created_at ?: now(),
                        'link' => route('admin.coin-claims.index'),
                        'icon' => 'bi-coin',
                        'color' => 'var(--stat-amber)'
                    ]);
                }
            } catch (\Exception $e) {
            }
        }

        // 4. Pending Support Requests
        if (\Illuminate\Support\Facades\Schema::hasTable('support_requests')) {
            try {
                $tickets = \App\Models\SupportRequest::where('status', 'pending')
                    ->with('user')
                    ->latest()
                    ->limit(5)
                    ->get();
                foreach ($tickets as $ticket) {
                    $topNotifications->push((object)[
                        'type' => 'support_request',
                        'title' => 'Support Request',
                        'message' => 'New ticket from ' . ($ticket->user?->name ?? 'A Peer') . ': ' . \Illuminate\Support\Str::limit($ticket->details, 40),
                        'time' => $ticket->created_at ?: now(),
                        'link' => route('admin.contacts.index'),
                        'icon' => 'bi-question-circle-fill',
                        'color' => 'var(--stat-purple)'
                    ]);
                }
            } catch (\Exception $e) {
            }
        }
    }

    $topNotifications = $topNotifications->sortByDesc('time')->take(5);
    $notificationCount = $topNotifications->count();
    $joinedCircles = collect();
    $requiresCircleDropdown = false;
    $selectedCircleId = session('activeScopeId', 'All');

    if ($admin && ! $isSuper) {
        $allowedCircleIds = \App\Support\AdminAccess::allowedCircleIds($admin);
        if (count($allowedCircleIds) > 1) {
            $requiresCircleDropdown = true;
            $joinedCircles = \App\Models\Circle::whereIn('id', $allowedCircleIds)
                ->orderBy('name')
                ->get();
        }
    }
@endphp
<header class="admin-topbar d-flex align-items-center justify-content-between px-4 py-2">
    <div class="d-flex align-items-center gap-3 flex-grow-1">
        <button class="btn btn-light d-lg-none border-0" id="sidebarToggle" type="button" aria-label="Toggle Sidebar" style="width: 40px; height: 40px; padding: 0; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-list fs-4"></i>
        </button>

        {{-- Breadcrumb / Page Context --}}
        <div class="d-none d-md-flex align-items-center gap-2">
            <span class="d-flex align-items-center gap-1" style="font-size: 0.82rem; color: var(--text-muted);">
                <i class="bi {{ $greetingIcon }}" style="color: var(--stat-amber); font-size: 1rem;"></i>
                <span class="fw-semibold" style="color: var(--text-secondary);">{{ $greeting }},</span>
                <span style="color: var(--text-primary); font-weight: 600;">{{ $admin?->name ?? 'Admin' }}</span>
            </span>
        </div>

        {{-- Search --}}
        <div class="search-box ms-auto" style="max-width: 280px;">
            <form class="w-100">
                <div class="input-group" style="border-radius: 999px; overflow: hidden;">
                    <span class="input-group-text bg-transparent border-0" style="padding: 0 0 0 14px; background: rgba(241, 245, 249, 0.8) !important;"><i class="bi bi-search" style="font-size: 0.85rem; color: var(--text-light);"></i></span>
                    <input type="text" class="form-control border-0" placeholder="Search..." style="background: rgba(241, 245, 249, 0.8); font-size: 0.85rem; padding: 9px 16px 9px 8px;">
                </div>
            </form>
        </div>

        {{-- Right Actions --}}
        <div class="d-none d-md-flex align-items-center gap-2">
            {{-- Quick Actions --}}
            @if ($requiresCircleDropdown)
                <div class="d-flex align-items-center gap-2 me-2">
                    <label for="topbar_circle_id" class="text-muted fw-semibold mb-0 text-nowrap fs-7">Circle Context:</label>
                    <select id="topbar_circle_id" class="form-select form-select-sm rounded-3 py-1.5 px-3 border shadow-sm" style="min-width: 180px; max-width: 250px; background-color: #f8f9fa;">
                        <option value="All" @selected($selectedCircleId === 'All')>All My Circles</option>
                        @foreach ($joinedCircles as $c)
                            <option value="{{ $c->id }}" @selected($c->id === $selectedCircleId)>
                                {{ $c->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        const selectEl = document.getElementById('topbar_circle_id');
                        if (selectEl) {
                            selectEl.addEventListener('change', function () {
                                const val = selectEl.value;
                                fetch("{{ route('admin.switch-context') }}", {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                        'X-Requested-With': 'XMLHttpRequest'
                                    },
                                    body: JSON.stringify({ circle_id: val })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        window.location.reload();
                                    } else {
                                        alert(data.message || 'Failed to switch circle context.');
                                    }
                                })
                                .catch(err => {
                                    console.error(err);
                                    alert('An unexpected error occurred.');
                                });
                            });
                        }
                    });
                </script>
            @endif
            <div class="dropdown">
                <button class="btn btn-light dropdown-toggle d-flex align-items-center gap-1" type="button" data-bs-toggle="dropdown" style="font-size: 0.82rem; padding: 7px 14px;">
                    <i class="bi bi-lightning-charge-fill" style="color: var(--stat-amber); font-size: 0.9rem;"></i>
                    <span class="d-none d-xl-inline">Quick Actions</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width: 200px;">
                    @if ($isSuper && ! $isCircleScoped)
                        <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.users.index') }}"><i class="bi bi-people text-primary"></i> View Peers</a></li>
                    @endif
                    @if ($isCircleScoped || $isDed)
                        <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.users.index') }}"><i class="bi bi-people text-primary"></i> View Peers</a></li>
                        <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.activities.index') }}"><i class="bi bi-activity text-success"></i> View Activities</a></li>
                        <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.coins.index') }}"><i class="bi bi-coin text-warning"></i> View Coins</a></li>
                    @else
                        <li><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('admin.circles.index') }}"><i class="bi bi-diagram-3 text-info"></i> View Circles</a></li>
                    @endif
                    <li><hr class="dropdown-divider my-1"></li>
                    <li><a class="dropdown-item d-flex align-items-center gap-2 disabled" href="#"><i class="bi bi-megaphone text-muted"></i> Create Announcement</a></li>
                </ul>
            </div>

            {{-- Role Badge --}}
            <span class="badge" style="background: var(--primary-subtle); color: var(--primary); border: 1px solid rgba(99, 102, 241, 0.12); font-size: 0.72rem; padding: 6px 12px;">
                <i class="bi bi-shield-check me-1" style="font-size: 0.7rem;"></i>{{ $roleBadge }}
            </span>

            {{-- Notification Bell Dropdown --}}
            <div class="dropdown">
                <button class="btn btn-light position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 38px; height: 38px; padding: 0; display: flex; align-items: center; justify-content: center;">
                    <i class="bi bi-bell" style="font-size: 1.05rem;"></i>
                    @if($notificationCount > 0)
                        <span class="position-absolute translate-middle badge rounded-pill bg-danger" style="top: 6px; right: -2px; font-size: 0.62rem; padding: 3px 5px; min-width: 18px;">{{ $notificationCount }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0 shadow-lg" style="width: 320px; border-radius: var(--radius-lg); overflow: hidden; z-index: 1050;">
                    <div class="px-3 py-2 bg-light border-bottom d-flex align-items-center justify-content-between">
                        <span class="fw-semibold text-primary" style="font-size: 0.82rem;">Review & Alerts Queue</span>
                        <span class="badge bg-primary text-white" style="font-size: 0.7rem; border-radius: var(--radius-sm);">{{ $notificationCount }} Pending</span>
                    </div>
                    <div class="notification-items" style="max-height: 280px; overflow-y: auto;">
                        @if($topNotifications->isEmpty())
                            <div class="p-4 text-center text-muted small">
                                <i class="bi bi-bell-slash fs-4 d-block mb-2 text-light"></i>
                                All caught up! No pending actions.
                            </div>
                        @else
                            @foreach($topNotifications as $notif)
                                <a href="{{ $notif->link }}" class="dropdown-item px-3 py-2 border-bottom d-flex align-items-start gap-2" style="white-space: normal; transition: background 0.15s;">
                                    <div class="rounded-circle p-2 d-flex align-items-center justify-content-center" style="background: rgba(99, 102, 241, 0.08); width: 32px; height: 32px; flex-shrink: 0; color: {{ $notif->color }};">
                                        <i class="bi {{ $notif->icon }}" style="font-size: 0.9rem;"></i>
                                    </div>
                                    <div style="min-width: 0; flex-grow: 1;">
                                        <div class="fw-semibold text-dark" style="font-size: 0.78rem; line-height: 1.2;">{{ $notif->title }}</div>
                                        <div class="text-muted text-truncate-2 mt-1" style="font-size: 0.72rem; line-height: 1.3;">{{ $notif->message }}</div>
                                        <small class="text-muted d-block mt-1" style="font-size: 0.65rem;">{{ $notif->time instanceof \Carbon\Carbon ? $notif->time->diffForHumans() : \Carbon\Carbon::parse($notif->time)->diffForHumans() }}</small>
                                    </div>
                                </a>
                            @endforeach
                        @endif
                    </div>
                    @if($notificationCount > 0)
                        <div class="p-2 border-top bg-light text-center">
                            <span class="text-muted small" style="font-size: 0.72rem;">Check each module for all entries</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- User Profile --}}
            <div class="dropdown">
                <a class="d-flex align-items-center text-decoration-none dropdown-toggle gap-2" href="#" data-bs-toggle="dropdown" style="padding: 4px 6px; border-radius: var(--radius-md); transition: background 0.2s;">
                    <div style="position: relative;">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($admin?->name ?? 'Admin') }}&background=6366f1&color=fff&bold=true&font-size=0.4" class="rounded-circle" width="36" height="36" alt="Admin Avatar" style="border: 2px solid rgba(99, 102, 241, 0.2);">
                        <span style="position: absolute; bottom: 0; right: 0; width: 10px; height: 10px; background: var(--success); border: 2px solid #fff; border-radius: 50%;"></span>
                    </div>
                    <div class="d-none d-lg-block" style="line-height: 1.3;">
                        <div class="fw-semibold" style="font-size: 0.85rem; color: var(--text-primary);">{{ $admin?->name ?? 'Admin' }}</div>
                        <small style="color: var(--text-muted); font-size: 0.72rem;">{{ $admin?->email ?? '' }}</small>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" style="min-width: 220px;">
                    <li class="px-3 py-2">
                        <div class="d-flex align-items-center gap-2">
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($admin?->name ?? 'Admin') }}&background=6366f1&color=fff&bold=true&size=40" class="rounded-circle" width="40" height="40" alt="Admin Avatar">
                            <div style="line-height: 1.3;">
                                <div class="fw-bold" style="font-size: 0.85rem;">{{ $admin?->name ?? 'Admin' }}</div>
                                <small class="text-muted" style="font-size: 0.72rem;">{{ $admin?->email ?? '' }}</small>
                            </div>
                        </div>
                    </li>
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button class="dropdown-item d-flex align-items-center gap-2 text-danger">
                                <i class="bi bi-box-arrow-right"></i> Sign Out
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</header>
