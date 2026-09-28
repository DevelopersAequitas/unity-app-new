@php
    $bd = $dealBreakdown ?? [
        'peer_count' => 0,
        'peer_total' => 0,
        'peer_avg' => 0,
        'team_count' => 0,
        'team_total' => 0,
        'team_avg' => 0,
        'combined_count' => 0,
        'combined_total' => 0,
        'top_team_deals' => collect(),
        'top_peer_deals' => collect(),
        'team_member_count' => 0,
    ];

    $combTotal = $bd['combined_total'] > 0 ? $bd['combined_total'] : 1;
    $peerPct = $bd['combined_total'] > 0 ? round(($bd['peer_total'] / $combTotal) * 100, 1) : 0;
    $teamPct = $bd['combined_total'] > 0 ? round(($bd['team_total'] / $combTotal) * 100, 1) : 0;
@endphp

<!-- Deal Value Breakdown Modal -->
<div class="modal fade" id="dealValueBreakdownModal" tabindex="-1" aria-labelledby="dealValueBreakdownModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-2xl rounded-3xl overflow-hidden" style="border-radius: 20px;">
            {{-- Modal Header --}}
            <div class="modal-header px-4 py-3.5 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 text-white border-0">
                <div class="d-flex align-items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 d-flex align-items-center justify-content-center text-xl shrink-0">
                        <i class="bi bi-pie-chart-fill"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-white mb-0" id="dealValueBreakdownModalLabel" style="font-family: 'Outfit', sans-serif;">
                            Business Deals Value Breakdown
                        </h5>
                        <p class="text-xs text-indigo-200/80 mb-0">
                            Separation of deals done by <strong class="text-white">Regular Peers</strong> vs <strong class="text-white">Assigned Team Members</strong>
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white shadow-none" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Modal Body --}}
            <div class="modal-body p-4 bg-slate-50 space-y-4">
                {{-- Platform Combined Overview Bar --}}
                <div class="bg-white rounded-2xl p-4 border border-slate-200 shadow-sm space-y-3">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <div>
                            <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Overall Platform Deals Total</span>
                            <div class="d-flex align-items-baseline gap-2 mt-0.5">
                                <h3 class="fw-bold text-slate-900 mb-0 font-display">₹{{ number_format($bd['combined_total'], 2) }}</h3>
                                <span class="text-xs font-semibold text-slate-500">({{ number_format($bd['combined_count']) }} Total Deals recorded)</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-3 text-xs">
                            <span class="d-inline-flex align-items-center gap-1.5 font-semibold text-indigo-700">
                                <span class="w-3 h-3 rounded-full bg-indigo-600 inline-block"></span> Regular Peers ({{ $peerPct }}%)
                            </span>
                            <span class="d-inline-flex align-items-center gap-1.5 font-semibold text-teal-700">
                                <span class="w-3 h-3 rounded-full bg-teal-600 inline-block"></span> Team Members ({{ $teamPct }}%)
                            </span>
                        </div>
                    </div>

                    {{-- Split Visual Progress Bar --}}
                    <div class="w-full bg-slate-100 rounded-full h-3.5 overflow-hidden d-flex p-0.5 border border-slate-200">
                        <div class="bg-indigo-600 h-full rounded-l-full transition-all duration-500" style="width: {{ max($peerPct, 3) }}%" title="Regular Peers: {{ $peerPct }}%"></div>
                        <div class="bg-teal-500 h-full rounded-r-full transition-all duration-500" style="width: {{ max($teamPct, 3) }}%" title="Team Members: {{ $teamPct }}%"></div>
                    </div>
                </div>

                {{-- 2 Main Side-by-Side Sections --}}
                <div class="row g-4">
                    {{-- Section 1: Non-Team Members (Regular Peers) --}}
                    <div class="col-12 col-lg-6">
                        <div class="bg-white rounded-2xl p-4 border border-indigo-100 shadow-sm h-100 d-flex flex-column" style="background: linear-gradient(180deg, rgba(99,102,241,0.02) 0%, #ffffff 100%);">
                            {{-- Section Header --}}
                            <div class="d-flex justify-content-between align-items-start pb-3 border-b border-indigo-100">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-600 border border-indigo-200 d-flex align-items-center justify-content-center text-lg shrink-0">
                                        <i class="bi bi-people-fill"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-slate-900 mb-0">Regular Peers (Non-Team)</h6>
                                        <span class="text-[11px] text-indigo-600 font-medium">Standard registered peer accounts</span>
                                    </div>
                                </div>
                                <span class="badge bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs px-2.5 py-1">
                                    {{ $peerPct }}% of Total
                                </span>
                            </div>

                            {{-- Metrics Grid --}}
                            <div class="row g-2.5 my-3">
                                <div class="col-6">
                                    <div class="bg-indigo-50/50 rounded-xl p-3 border border-indigo-100">
                                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Deals</div>
                                        <div class="fs-4 fw-bold text-indigo-700 mt-1">{{ number_format($bd['peer_count']) }}</div>
                                        <div class="text-[10px] text-slate-400">Deals by peers</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-indigo-50/50 rounded-xl p-3 border border-indigo-100">
                                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Total Value</div>
                                        <div class="fs-4 fw-bold text-indigo-700 mt-1 truncate" title="₹{{ number_format($bd['peer_total'], 2) }}">
                                            ₹{{ number_format($bd['peer_total'], 2) }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">Total peer revenue</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-200 d-flex justify-content-between align-items-center text-xs">
                                        <span class="text-slate-500 font-medium">Average Deal Size:</span>
                                        <span class="fw-bold text-slate-800">₹{{ number_format($bd['peer_avg'], 2) }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Top Peer Deals Sample --}}
                            <div class="flex-grow-1">
                                <div class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-2 d-flex justify-content-between">
                                    <span>Top Peer Deals</span>
                                    <span class="text-muted font-normal text-[11px]">Ranked by deal value</span>
                                </div>
                                <div class="space-y-1.5 overflow-y-auto max-h-[220px] pr-1">
                                    @forelse($bd['top_peer_deals'] as $pDeal)
                                        <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-indigo-50/40 transition d-flex justify-content-between align-items-center gap-2">
                                            <div class="min-w-0">
                                                <div class="fw-semibold text-xs text-slate-800 truncate">
                                                    {{ $pDeal->actor_display_name ?: ($pDeal->actor_first_name.' '.$pDeal->actor_last_name) }}
                                                    <span class="text-slate-400 font-normal">→</span>
                                                    <span class="text-slate-600">{{ $pDeal->peer_display_name ?: ($pDeal->peer_first_name.' '.$pDeal->peer_last_name) }}</span>
                                                </div>
                                                <div class="text-[10px] text-slate-400">
                                                    {{ $pDeal->deal_date ? \Carbon\Carbon::parse($pDeal->deal_date)->format('d M Y') : '—' }} • {{ ucfirst($pDeal->business_type ?? 'Business') }}
                                                </div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <span class="font-bold text-xs text-indigo-700">₹{{ number_format($pDeal->deal_amount, 2) }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center py-4 text-xs text-slate-400">No regular peer deals found.</div>
                                    @endforelse
                                </div>
                            </div>

                            {{-- Action Link --}}
                            <div class="pt-3 mt-3 border-t border-slate-100">
                                <a href="{{ route('admin.activities.business-deals.index', array_merge(request()->query(), ['member_type' => 'peer'])) }}" 
                                   class="btn btn-sm btn-outline-primary w-100 d-flex align-items-center justify-content-center gap-1.5 py-2 font-semibold" style="border-radius: 10px;">
                                    <i class="bi bi-funnel"></i> View Regular Peer Deals in Table
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Section 2: Team Members --}}
                    <div class="col-12 col-lg-6">
                        <div class="bg-white rounded-2xl p-4 border border-teal-100 shadow-sm h-100 d-flex flex-column" style="background: linear-gradient(180deg, rgba(13,148,136,0.02) 0%, #ffffff 100%);">
                            {{-- Section Header --}}
                            <div class="d-flex justify-content-between align-items-start pb-3 border-b border-teal-100">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="w-9 h-9 rounded-xl bg-teal-50 text-teal-600 border border-teal-200 d-flex align-items-center justify-content-center text-lg shrink-0">
                                        <i class="bi bi-shield-lock-fill"></i>
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-slate-900 mb-0">Assigned Team Members</h6>
                                        <span class="text-[11px] text-teal-600 font-medium">Users tagged as team members ({{ $bd['team_member_count'] }} users)</span>
                                    </div>
                                </div>
                                <span class="badge bg-teal-50 text-teal-700 border border-teal-200 text-xs px-2.5 py-1">
                                    {{ $teamPct }}% of Total
                                </span>
                            </div>

                            {{-- Metrics Grid --}}
                            <div class="row g-2.5 my-3">
                                <div class="col-6">
                                    <div class="bg-teal-50/50 rounded-xl p-3 border border-teal-100">
                                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Team Deals</div>
                                        <div class="fs-4 fw-bold text-teal-700 mt-1">{{ number_format($bd['team_count']) }}</div>
                                        <div class="text-[10px] text-slate-400">By team members</div>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="bg-teal-50/50 rounded-xl p-3 border border-teal-100">
                                        <div class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Team Value</div>
                                        <div class="fs-4 fw-bold text-teal-700 mt-1 truncate" title="₹{{ number_format($bd['team_total'], 2) }}">
                                            ₹{{ number_format($bd['team_total'], 2) }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">Team deals revenue</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="bg-slate-50 rounded-xl p-2.5 border border-slate-200 d-flex justify-content-between align-items-center text-xs">
                                        <span class="text-slate-500 font-medium">Average Deal Size:</span>
                                        <span class="fw-bold text-slate-800">₹{{ number_format($bd['team_avg'], 2) }}</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Top Team Member Deals Sample --}}
                            <div class="flex-grow-1">
                                <div class="text-xs font-bold text-slate-600 uppercase tracking-wider mb-2 d-flex justify-content-between">
                                    <span>Top Team Member Deals</span>
                                    <span class="text-muted font-normal text-[11px]">Ranked by deal value</span>
                                </div>
                                <div class="space-y-1.5 overflow-y-auto max-h-[220px] pr-1">
                                    @forelse($bd['top_team_deals'] as $tDeal)
                                        <div class="p-2.5 rounded-xl border border-slate-100 bg-slate-50/60 hover:bg-teal-50/40 transition d-flex justify-content-between align-items-center gap-2">
                                            <div class="min-w-0">
                                                <div class="fw-semibold text-xs text-slate-800 truncate">
                                                    {{ $tDeal->actor_display_name ?: ($tDeal->actor_first_name.' '.$tDeal->actor_last_name) }}
                                                    <span class="badge bg-teal-100 text-teal-800 text-[9px] px-1 py-0 ms-0.5">Team</span>
                                                    <span class="text-slate-400 font-normal">→</span>
                                                    <span class="text-slate-600">{{ $tDeal->peer_display_name ?: ($tDeal->peer_first_name.' '.$tDeal->peer_last_name) }}</span>
                                                </div>
                                                <div class="text-[10px] text-slate-400">
                                                    {{ $tDeal->deal_date ? \Carbon\Carbon::parse($tDeal->deal_date)->format('d M Y') : '—' }} • {{ ucfirst($tDeal->business_type ?? 'Business') }}
                                                </div>
                                            </div>
                                            <div class="text-right shrink-0">
                                                <span class="font-bold text-xs text-teal-700">₹{{ number_format($tDeal->deal_amount, 2) }}</span>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="text-center py-4 text-xs text-slate-400">No team member deals found.</div>
                                    @endforelse
                                </div>
                            </div>

                            {{-- Action Link --}}
                            <div class="pt-3 mt-3 border-t border-slate-100">
                                <a href="{{ route('admin.activities.business-deals.index', array_merge(request()->query(), ['member_type' => 'team_member'])) }}" 
                                   class="btn btn-sm btn-outline-teal w-100 d-flex align-items-center justify-content-center gap-1.5 py-2 font-semibold" 
                                   style="color: #0d9488; border-color: #0d9488; border-radius: 10px;">
                                    <i class="bi bi-funnel"></i> View Team Member Deals in Table
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Modal Footer --}}
            <div class="modal-footer px-4 py-3 bg-white border-t border-slate-200 d-flex justify-content-between align-items-center">
                <div class="text-xs text-slate-500">
                    <i class="bi bi-info-circle-fill text-indigo-500 me-1"></i>
                    Team member deals are separated to prevent inflation of organic peer activity totals.
                </div>
                <div class="d-flex align-items-center gap-2">
                    <a href="{{ route('admin.activities.business-deals.index', array_merge(request()->query(), ['member_type' => 'all'])) }}" class="btn btn-sm btn-light border px-3 fw-semibold text-slate-700" style="border-radius: 8px;">
                        Show All Deals in Table
                    </a>
                    <button type="button" class="btn btn-sm btn-secondary px-4 fw-semibold" data-bs-dismiss="modal" style="border-radius: 8px;">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openDealValueBreakdownModal() {
    const modalEl = document.getElementById('dealValueBreakdownModal');
    if (!modalEl) return;
    if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
        let modal = bootstrap.Modal.getInstance(modalEl);
        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }
        modal.show();
    } else {
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
    }
}
</script>
