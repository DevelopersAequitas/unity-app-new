@extends('admin.layouts.app')

@section('title', 'Web Dashboard Overview - Peers Global')

@section('content')
<div class="container-fluid px-0">
    {{-- 1. Header Overview Bar --}}
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-indigo-subtle text-indigo rounded-pill px-2.5 py-1" style="background: rgba(99, 102, 241, 0.12); color: #6366f1; font-weight: 600; font-size: 0.72rem;">
                    <i class="bi bi-globe2 me-1"></i> Peers Global Website
                </span>
                <span class="text-muted" style="font-size: 0.8rem;">● Live Sync</span>
            </div>
            <h1 class="h4 fw-bold text-dark mb-0 tracking-tight">Dashboard Overview</h1>
            <p class="text-muted small mb-0 mt-0.5">Today is {{ now()->format('l, M d, Y') }}</p>
        </div>

        <div class="d-flex align-items-center gap-2">
            <a href="https://peersglobal.com" target="_blank" class="btn btn-outline-secondary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs">
                <i class="bi bi-box-arrow-up-right"></i>
                <span class="fw-semibold">Visit Live Site</span>
            </a>
            <button onclick="window.location.reload()" class="btn btn-light border btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs">
                <i class="bi bi-arrow-clockwise"></i>
                <span class="fw-semibold">Refresh</span>
            </button>
        </div>
    </div>

    {{-- 2. Quick Access Action Hub --}}
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('admin.web.partnerships.index') }}" class="card border-0 shadow-xs rounded-4 p-3 text-center text-decoration-none h-100 bg-white hover-shadow transition">
                <div class="d-flex align-items-center justify-content-center mx-auto mb-2 rounded-3" style="width: 44px; height: 44px; background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                    <i class="bi bi-people-fill fs-5"></i>
                </div>
                <span class="fw-bold text-dark small">Partnerships</span>
                <span class="text-muted" style="font-size: 0.7rem;">{{ $partnershipCount }} Active</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('admin.web.opportunities.index') }}" class="card border-0 shadow-xs rounded-4 p-3 text-center text-decoration-none h-100 bg-white hover-shadow transition">
                <div class="d-flex align-items-center justify-content-center mx-auto mb-2 rounded-3" style="width: 44px; height: 44px; background: rgba(16, 185, 129, 0.1); color: #10b981;">
                    <i class="bi bi-compass fs-5"></i>
                </div>
                <span class="fw-bold text-dark small">Opportunities</span>
                <span class="text-muted" style="font-size: 0.7rem;">{{ $opportunityCount }} Open</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('admin.web.companies.index') }}" class="card border-0 shadow-xs rounded-4 p-3 text-center text-decoration-none h-100 bg-white hover-shadow transition">
                <div class="d-flex align-items-center justify-content-center mx-auto mb-2 rounded-3" style="width: 44px; height: 44px; background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                    <i class="bi bi-building fs-5"></i>
                </div>
                <span class="fw-bold text-dark small">Companies</span>
                <span class="text-muted" style="font-size: 0.7rem;">{{ $companyCount }} Verified</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('admin.web.blogs.index') }}" class="card border-0 shadow-xs rounded-4 p-3 text-center text-decoration-none h-100 bg-white hover-shadow transition">
                <div class="d-flex align-items-center justify-content-center mx-auto mb-2 rounded-3" style="width: 44px; height: 44px; background: rgba(244, 63, 94, 0.1); color: #f43f5e;">
                    <i class="bi bi-file-earmark-richtext fs-5"></i>
                </div>
                <span class="fw-bold text-dark small">Publications</span>
                <span class="text-muted" style="font-size: 0.7rem;">{{ $blogCount }} Articles</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('admin.web.media.index') }}" class="card border-0 shadow-xs rounded-4 p-3 text-center text-decoration-none h-100 bg-white hover-shadow transition">
                <div class="d-flex align-items-center justify-content-center mx-auto mb-2 rounded-3" style="width: 44px; height: 44px; background: rgba(6, 182, 212, 0.1); color: #06b6d4;">
                    <i class="bi bi-images fs-5"></i>
                </div>
                <span class="fw-bold text-dark small">Media Library</span>
                <span class="text-muted" style="font-size: 0.7rem;">Global Assets</span>
            </a>
        </div>
        <div class="col-6 col-md-4 col-lg-2">
            <a href="{{ route('admin.web.page-media.index') }}" class="card border-0 shadow-xs rounded-4 p-3 text-center text-decoration-none h-100 bg-white hover-shadow transition">
                <div class="d-flex align-items-center justify-content-center mx-auto mb-2 rounded-3" style="width: 44px; height: 44px; background: rgba(168, 85, 247, 0.1); color: #a855f7;">
                    <i class="bi bi-collection-play fs-5"></i>
                </div>
                <span class="fw-bold text-dark small">Page Medias</span>
                <span class="text-muted" style="font-size: 0.7rem;">{{ $pageMediaCount }} Assigned</span>
            </a>
        </div>
    </div>

    {{-- 3. KPI Stat Cards with Gradient Accent --}}
    <div class="row g-4 mb-4">
        {{-- Total Peers --}}
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white position-relative overflow-hidden h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.7rem;">TOTAL PEERS</span>
                    <div class="p-2 rounded-3" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6;">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <div class="my-1">
                    <div class="h2 fw-bold text-dark mb-0">{{ number_format($totalPeers) }}</div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-3 mt-2 border-top">
                    <span class="text-muted small">Registered directory</span>
                    <span class="badge rounded-pill" style="background: rgba(59, 130, 246, 0.12); color: #2563eb; font-weight: 600;">+12%</span>
                </div>
                <div class="position-absolute bottom-0 start-0 end-0" style="height: 4px; background: linear-gradient(90deg, #3b82f6, #6366f1);"></div>
            </div>
        </div>

        {{-- Active Circles --}}
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white position-relative overflow-hidden h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.7rem;">ACTIVE CIRCLES</span>
                    <div class="p-2 rounded-3" style="background: rgba(16, 185, 129, 0.1); color: #10b981;">
                        <i class="bi bi-diagram-3-fill"></i>
                    </div>
                </div>
                <div class="my-1">
                    <div class="h2 fw-bold text-dark mb-0">{{ $activeCircles }}</div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-3 mt-2 border-top">
                    <span class="text-muted small">Operational hubs</span>
                    <span class="badge rounded-pill" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 600;">● Healthy</span>
                </div>
                <div class="position-absolute bottom-0 start-0 end-0" style="height: 4px; background: linear-gradient(90deg, #10b981, #14b8a6);"></div>
            </div>
        </div>

        {{-- Awaiting Review --}}
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white position-relative overflow-hidden h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.7rem;">AWAITING REVIEW</span>
                    <div class="p-2 rounded-3" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b;">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
                <div class="my-1">
                    <div class="h2 fw-bold text-dark mb-0">{{ $awaitingReview }}</div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-3 mt-2 border-top">
                    <span class="text-muted small">Awaiting action</span>
                    <span class="badge rounded-pill" style="background: rgba(245, 158, 11, 0.12); color: #d97706; font-weight: 600;">● Pending</span>
                </div>
                <div class="position-absolute bottom-0 start-0 end-0" style="height: 4px; background: linear-gradient(90deg, #f59e0b, #ea580c);"></div>
            </div>
        </div>

        {{-- New Signups --}}
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white position-relative overflow-hidden h-100">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.7rem;">NEW SIGNUPS</span>
                    <div class="p-2 rounded-3" style="background: rgba(168, 85, 247, 0.1); color: #a855f7;">
                        <i class="bi bi-person-plus-fill"></i>
                    </div>
                </div>
                <div class="my-1">
                    <div class="h2 fw-bold text-dark mb-0">{{ $newSignupsToday }}</div>
                </div>
                <div class="d-flex align-items-center justify-content-between pt-3 mt-2 border-top">
                    <span class="text-muted small">Registered today</span>
                    <span class="badge rounded-pill" style="background: rgba(168, 85, 247, 0.12); color: #7c3aed; font-weight: 600;">⚡ Active</span>
                </div>
                <div class="position-absolute bottom-0 start-0 end-0" style="height: 4px; background: linear-gradient(90deg, #a855f7, #ec4899);"></div>
            </div>
        </div>
    </div>

    {{-- 4. Analytics: Partnership Growth & Status --}}
    <div class="row g-4 mb-4">
        {{-- Area Chart --}}
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white h-100">
                <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mb-4">
                    <div>
                        <h2 class="h6 fw-bold text-dark mb-1">Partnership Growth</h2>
                        <p class="text-muted small mb-0">Monthly cross-border collaborative venture volume</p>
                    </div>
                    <div class="btn-group btn-group-sm p-1 rounded-3 bg-light" role="group">
                        <button type="button" class="btn btn-white shadow-xs fw-semibold text-primary">Last 6 Months</button>
                        <button type="button" class="btn btn-light text-muted">Year 2026</button>
                        <button type="button" class="btn btn-light text-muted">All Time</button>
                    </div>
                </div>

                {{-- Custom SVG Area Chart --}}
                <div class="position-relative pt-2">
                    <div style="height: 240px; width: 100%;">
                        <svg class="w-100 h-100" viewBox="0 0 600 200" preserveAspectRatio="none">
                            <defs>
                                <linearGradient id="webAreaGrad" x1="0" y1="0" x2="0" y2="1">
                                    <stop offset="0%" stop-color="#6366f1" stop-opacity="0.35" />
                                    <stop offset="60%" stop-color="#818cf8" stop-opacity="0.10" />
                                    <stop offset="100%" stop-color="#818cf8" stop-opacity="0.0" />
                                </linearGradient>
                            </defs>
                            <path d="M0,170 C100,140 150,150 200,90 C250,30 300,110 380,60 C460,10 520,70 600,20 L600,200 L0,200 Z" fill="url(#webAreaGrad)"></path>
                            <path d="M0,170 C100,140 150,150 200,90 C250,30 300,110 380,60 C460,10 520,70 600,20" fill="none" stroke="#6366f1" stroke-width="3.5" stroke-linecap="round"></path>
                            <circle cx="200" cy="90" r="5" fill="#6366f1"></circle>
                            <circle cx="380" cy="60" r="5" fill="#818cf8"></circle>
                            <circle cx="600" cy="20" r="5.5" fill="#4f46e5"></circle>
                        </svg>
                    </div>
                    <div class="d-flex justify-content-between text-muted fw-semibold small pt-2 px-1" style="font-size: 0.75rem;">
                        <span>Apr</span>
                        <span>May</span>
                        <span>Jun</span>
                        <span>Jul</span>
                        <span>Aug</span>
                        <span>Sep</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Donut Status --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <h2 class="h6 fw-bold text-dark mb-0">Partnership Status</h2>
                        <i class="bi bi-three-dots text-muted"></i>
                    </div>
                    <p class="text-muted small mb-4">Distribution across active collaboration pipelines</p>

                    <div class="position-relative mx-auto my-3" style="width: 170px; height: 170px;">
                        <svg class="w-100 h-100" viewBox="0 0 36 36" style="transform: rotate(-90deg);">
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#f1f5f9" stroke-width="4"></path>
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#6366f1" stroke-width="4.2" stroke-dasharray="58, 100" stroke-linecap="round"></path>
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#06b6d4" stroke-width="4.2" stroke-dasharray="24, 100" stroke-dashoffset="-59" stroke-linecap="round"></path>
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#f59e0b" stroke-width="4.2" stroke-dasharray="12, 100" stroke-dashoffset="-84" stroke-linecap="round"></path>
                            <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#ef4444" stroke-width="4.2" stroke-dasharray="6, 100" stroke-dashoffset="-97" stroke-linecap="round"></path>
                        </svg>
                        <div class="position-absolute top-50 start-50 translate-middle text-center">
                            <span class="h3 fw-bold text-dark d-block mb-0">{{ $partnershipCount ?: 86 }}</span>
                            <span class="text-uppercase tracking-wider text-muted fw-bold" style="font-size: 0.65rem;">Total Deals</span>
                        </div>
                    </div>
                </div>

                <div class="row g-2 pt-3 border-top" style="font-size: 0.78rem;">
                    <div class="col-6 d-flex align-items-center gap-1.5">
                        <span class="rounded-circle" style="width: 8px; height: 8px; background: #6366f1;"></span>
                        <span class="text-muted">Active (58%)</span>
                    </div>
                    <div class="col-6 d-flex align-items-center gap-1.5">
                        <span class="rounded-circle" style="width: 8px; height: 8px; background: #06b6d4;"></span>
                        <span class="text-muted">Completed (24%)</span>
                    </div>
                    <div class="col-6 d-flex align-items-center gap-1.5">
                        <span class="rounded-circle" style="width: 8px; height: 8px; background: #f59e0b;"></span>
                        <span class="text-muted">Pending (12%)</span>
                    </div>
                    <div class="col-6 d-flex align-items-center gap-1.5">
                        <span class="rounded-circle" style="width: 8px; height: 8px; background: #ef4444;"></span>
                        <span class="text-muted">Cancelled (6%)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- 5. Recent Activity & Top Performing Partners --}}
    <div class="row g-4 mb-4">
        {{-- Recent Activity --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h2 class="h6 fw-bold text-dark mb-0">Recent Activity</h2>
                        <p class="text-muted small mb-0">Live timeline across collaboration nodes</p>
                    </div>
                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669; font-weight: 600; font-size: 0.7rem;">Live Stream</span>
                </div>

                <div class="position-relative ps-4 py-1" style="border-left: 2px solid #e2e8f0; margin-left: 10px;">
                    @foreach($recentActivities as $act)
                        <div class="position-relative mb-3.5 pb-2">
                            <span class="position-absolute translate-middle rounded-circle bg-primary" style="left: -17px; top: 10px; width: 10px; height: 10px; border: 2px solid #fff;"></span>
                            <div class="fw-bold text-dark small">{{ $act['title'] }}</div>
                            <div class="text-muted" style="font-size: 0.78rem;">{{ $act['company'] }}</div>
                            <small class="text-muted" style="font-size: 0.7rem;">{{ $act['time'] }}</small>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Top Performing Partners --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-xs rounded-4 p-4 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between pb-3 border-bottom mb-3">
                    <div>
                        <h2 class="h6 fw-bold text-dark mb-0">Top Performing Partners</h2>
                        <p class="text-muted small mb-0">Ranked by collaborative deal volume & growth</p>
                    </div>
                    <a href="{{ route('admin.web.partnerships.index') }}" class="text-primary text-decoration-none fw-bold small">
                        View All <i class="bi bi-chevron-right ms-1"></i>
                    </a>
                </div>

                <div class="d-flex flex-column gap-2">
                    @foreach($topPartners as $partner)
                        <div class="d-flex align-items-center justify-content-between p-2.5 rounded-3 bg-light hover-shadow transition">
                            <div class="d-flex align-items-center gap-2.5">
                                <div class="rounded-3 d-flex align-items-center justify-content-center fw-bold text-white small" style="width: 32px; height: 32px; background: {{ $partner['rank'] === 1 ? '#6366f1' : ($partner['rank'] === 2 ? '#1e293b' : '#64748b') }};">
                                    {{ $partner['rank'] }}
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small">{{ $partner['name'] }}</div>
                                    <div class="text-muted" style="font-size: 0.72rem;">{{ $partner['sector'] }}</div>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold text-dark small">{{ $partner['deals'] }} Deals</div>
                                <div class="text-success fw-bold" style="font-size: 0.72rem;">{{ $partner['growth'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- 6. Partnership Requests Table --}}
    <div class="card border-0 shadow-xs rounded-4 p-4 bg-white">
        <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pb-3 border-bottom mb-3">
            <div>
                <h2 class="h6 fw-bold text-dark mb-0">Partnership Requests</h2>
                <p class="text-muted small mb-0">Manage incoming promoter collaborations and alliances</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('admin.web.partnerships.index') }}" class="btn btn-light border btn-sm rounded-3 px-3 py-1.5 fw-semibold d-flex align-items-center gap-1.5">
                    <i class="bi bi-sliders"></i>
                    <span>All Partnerships</span>
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" style="font-size: 0.82rem;">
                <thead class="table-light text-uppercase tracking-wider text-muted" style="font-size: 0.7rem;">
                    <tr>
                        <th>Company</th>
                        <th>Partner With</th>
                        <th>Sector</th>
                        <th>Value</th>
                        <th>Status</th>
                        <th>Stage</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($partnerships as $req)
                        <tr>
                            <td class="fw-bold text-dark">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 30px; height: 30px; background: #6366f1; font-size: 0.7rem;">
                                        {{ substr($req->company_a, 0, 2) }}
                                    </div>
                                    <span>{{ $req->company_a }}</span>
                                </div>
                            </td>
                            <td class="text-secondary fw-semibold">{{ $req->company_b }}</td>
                            <td class="text-muted">{{ $req->sector ?? 'Cross-Border Tech' }}</td>
                            <td class="fw-bold text-dark">{{ $req->value ?? '—' }}</td>
                            <td>
                                @if($req->status === 'Active')
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(16, 185, 129, 0.12); color: #059669;">● Active</span>
                                @elseif($req->status === 'Negotiation')
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(99, 102, 241, 0.12); color: #4f46e5;">● Negotiation</span>
                                @elseif($req->status === 'Completed')
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(59, 130, 246, 0.12); color: #2563eb;">● Completed</span>
                                @else
                                    <span class="badge rounded-pill px-2.5 py-1" style="background: rgba(245, 158, 11, 0.12); color: #d97706;">● Under Review</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $req->stage }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.web.partnerships.index', ['q' => $req->company_a]) }}" class="btn btn-sm btn-primary rounded-2 px-2.5 py-1 fw-bold" style="font-size: 0.72rem;">
                                    Review
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No partnership requests found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
