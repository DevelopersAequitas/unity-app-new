@extends('admin.layouts.app')
@section('title', 'Leadership Commission Management')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-6">
    <!-- Header Section -->
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-100 text-emerald-700">
                    <i class="bi bi-percent text-lg"></i>
                </span>
                <div>
                    <h2 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">Leadership Commission Management</h2>
                    <p class="text-xs t3 m-0 mt-0.5">Configure live commission percentages for all leadership tiers. Updates sync immediately to mobile apps & APIs.</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" class="btn btn-sm btn-outline-primary flex items-center gap-1.5" data-bs-toggle="modal" data-bs-target="#addRoleModal">
                <i class="bi bi-plus-circle"></i>
                <span>Add Role Rate</span>
            </button>
            <a href="{{ $apiEndpoint }}" target="_blank" class="btn btn-sm btn-outline-secondary flex items-center gap-1.5" title="Test Live API">
                <i class="bi bi-box-arrow-up-right"></i>
                <span>Test API JSON</span>
            </a>
            <button type="submit" form="bulkCommissionForm" class="btn btn-sm btn-success flex items-center gap-1.5 px-3">
                <i class="bi bi-check2-circle"></i>
                <span class="font-semibold">Save All Changes</span>
            </button>
        </div>
    </div>

    <!-- Alert Notifications -->
    @if(session('success'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600 text-sm"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-700 hover:text-emerald-900" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-rose-600 text-sm"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" class="text-rose-700 hover:text-rose-900" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    @if($errors->any())
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800">
            <div class="font-semibold mb-1">Please fix the following validation errors:</div>
            <ul class="list-disc pl-4 space-y-0.5">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Finance Metric Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-xl border bg-gradient-to-br from-white to-slate-50 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Total Collections</span>
                    <div class="text-xl font-bold text-slate-800 mt-1">{{ $metrics['total_collections'] }}</div>
                    <span class="text-[11px] text-emerald-600 font-medium">Live Payment Sum</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-100/70 text-emerald-600 flex items-center justify-center">
                    <i class="bi bi-wallet2 text-lg"></i>
                </div>
            </div>
        </div>

        <div class="p-4 rounded-xl border bg-gradient-to-br from-white to-slate-50 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Projected Annual</span>
                    <div class="text-xl font-bold text-indigo-700 mt-1">{{ $metrics['projected_annual_revenue'] }}</div>
                    <span class="text-[11px] text-slate-500 font-medium">Forecasted Run-rate</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-indigo-100/70 text-indigo-600 flex items-center justify-center">
                    <i class="bi bi-graph-up-arrow text-lg"></i>
                </div>
            </div>
        </div>

        <div class="p-4 rounded-xl border bg-gradient-to-br from-white to-slate-50 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Commission Dues</span>
                    <div class="text-xl font-bold text-amber-700 mt-1">{{ $metrics['commission_due'] }}</div>
                    <span class="text-[11px] text-amber-600 font-medium">Pending Leader Payouts</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-100/70 text-amber-600 flex items-center justify-center">
                    <i class="bi bi-cash-stack text-lg"></i>
                </div>
            </div>
        </div>

        <div class="p-4 rounded-xl border bg-gradient-to-br from-white to-slate-50 shadow-sm relative overflow-hidden">
            <div class="flex justify-between items-start">
                <div>
                    <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Configured Roles</span>
                    <div class="text-xl font-bold text-slate-800 mt-1">{{ $metrics['configured_roles_count'] }} Tiers</div>
                    <span class="text-[11px] text-slate-500 font-medium">{{ $metrics['active_leaders_count'] }} Active Leaders in App</span>
                </div>
                <div class="w-10 h-10 rounded-xl bg-teal-100/70 text-teal-600 flex items-center justify-center">
                    <i class="bi bi-diagram-3 text-lg"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Commission Matrix Table Section -->
    <div class="rounded-xl border bg-white shadow-sm overflow-hidden">
        <div class="p-4 border-b bg-slate-50/70 flex flex-wrap justify-between items-center gap-3">
            <div>
                <h3 class="text-sm font-bold text-slate-800 m-0">Leadership Commission Matrix</h3>
                <p class="text-xs text-slate-500 m-0 mt-0.5">Rates defined below apply automatically to referral earnings, join bonuses, and renewals.</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="text" id="matrixSearchInput" placeholder="Filter by role name or key..." class="form-control form-control-sm text-xs w-64" onkeyup="filterCommissionTable()">
            </div>
        </div>

        <form id="bulkCommissionForm" method="POST" action="{{ route('admin.commissions.update-bulk') }}">
            @csrf
            @method('PUT')

            <div class="overflow-x-auto">
                <table class="table table-hover align-middle mb-0 text-xs w-full min-w-[1050px]" id="commissionMatrixTable">
                    <thead class="bg-slate-50 text-slate-600 font-semibold uppercase text-[11px] tracking-wider border-b">
                        <tr>
                            <th class="py-3 px-4 text-left" style="min-width: 220px;">Leadership Role</th>
                            <th class="py-3 px-3 text-left" style="min-width: 140px;">Direct Referral Cut</th>
                            <th class="py-3 px-3 text-left" style="min-width: 140px;">App Join Cut</th>
                            <th class="py-3 px-3 text-left" style="min-width: 140px;">Renewal Cut</th>
                            <th class="py-3 px-3 text-left" style="min-width: 200px;">Role Description</th>
                            <th class="py-3 px-3 text-center" style="min-width: 110px; width: 110px;">Status</th>
                            <th class="py-3 px-4 text-center" style="min-width: 120px; width: 120px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($rates as $index => $rate)
                            <tr class="hover:bg-slate-50/80 transition-colors commission-row" data-search="{{ strtolower($rate['role_name'] . ' ' . $rate['role_id']) }}">
                                <!-- Hidden Identifier Inputs -->
                                <input type="hidden" name="commission_rates[{{ $index }}][role_id]" value="{{ $rate['role_id'] }}">
                                <input type="hidden" name="commission_rates[{{ $index }}][id]" value="{{ $rate['id'] }}">

                                <!-- Role & Key -->
                                <td class="py-3 px-4">
                                    <div class="flex items-center gap-2.5">
                                        <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 flex-shrink-0"></span>
                                        <div class="flex-grow">
                                            <input type="text" name="commission_rates[{{ $index }}][role_name]" value="{{ $rate['role_name'] }}" class="font-semibold text-slate-800 text-xs border border-transparent hover:border-slate-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 rounded px-2 py-1 w-full transition bg-transparent hover:bg-white focus:bg-white" placeholder="Role Name">
                                            <div class="text-[11px] text-slate-400 font-mono px-2 mt-0.5">{{ $rate['role_id'] }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Direct Referral Cut -->
                                <td class="py-3 px-3">
                                    <div class="input-group input-group-sm max-w-[130px]">
                                        <input type="number" step="0.1" min="0" max="100" name="commission_rates[{{ $index }}][direct_referral_cut_percentage]" value="{{ number_format($rate['direct_referral_cut_percentage'], 1) }}" class="form-control form-control-sm text-xs font-semibold text-slate-800 text-right pr-1" required>
                                        <span class="input-group-text bg-slate-50 text-slate-500 text-xs font-medium">%</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 pl-1">on closed deals</div>
                                </td>

                                <!-- App Join Cut -->
                                <td class="py-3 px-3">
                                    <div class="input-group input-group-sm max-w-[130px]">
                                        <input type="number" step="0.1" min="0" max="100" name="commission_rates[{{ $index }}][app_join_cut_percentage]" value="{{ number_format($rate['app_join_cut_percentage'], 1) }}" class="form-control form-control-sm text-xs font-semibold text-slate-800 text-right pr-1" required>
                                        <span class="input-group-text bg-slate-50 text-slate-500 text-xs font-medium">%</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 pl-1">per onboarding</div>
                                </td>

                                <!-- Renewal Cut -->
                                <td class="py-3 px-3">
                                    <div class="input-group input-group-sm max-w-[130px]">
                                        <input type="number" step="0.1" min="0" max="100" name="commission_rates[{{ $index }}][renewal_cut_percentage]" value="{{ number_format($rate['renewal_cut_percentage'], 1) }}" class="form-control form-control-sm text-xs font-semibold text-slate-800 text-right pr-1">
                                        <span class="input-group-text bg-slate-50 text-slate-500 text-xs font-medium">%</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 pl-1">annual renewal</div>
                                </td>

                                <!-- Description -->
                                <td class="py-3 px-3">
                                    <input type="text" name="commission_rates[{{ $index }}][description]" value="{{ $rate['description'] }}" class="form-control form-control-sm text-xs text-slate-600" placeholder="Brief scope / rule description">
                                </td>

                                <!-- Status -->
                                <td class="py-3 px-3 text-center">
                                    <div class="flex items-center justify-center">
                                        <label class="relative inline-flex items-center cursor-pointer m-0">
                                            <input type="checkbox" name="commission_rates[{{ $index }}][is_active]" value="1" {{ $rate['is_active'] ? 'checked' : '' }} class="sr-only peer">
                                            <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                                        </label>
                                    </div>
                                </td>

                                <!-- Actions -->
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="text-slate-400 hover:text-slate-600 cursor-help" title="Last updated: {{ $rate['updated_at_formatted'] }}">
                                            <i class="bi bi-clock-history text-sm"></i>
                                        </span>
                                        @if(!in_array($rate['role_id'], ['superAdmin', 'circleFounder', 'circleChair', 'countryDirector']))
                                            <button type="button" class="inline-flex items-center justify-center w-7 h-7 text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition" onclick="deleteCommissionRate('{{ $rate['id'] }}', '{{ $rate['role_name'] }}')" title="Delete Role Rate">
                                                <i class="bi bi-trash text-xs"></i>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-8 text-slate-500">
                                    <i class="bi bi-inbox text-2xl d-block mb-2 text-slate-400"></i>
                                    No commission rates configured. Click "Add Role Rate" above to create one.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="p-4 bg-slate-50/80 border-t flex flex-wrap justify-between items-center gap-3">
                <div class="text-xs text-slate-500">
                    <i class="bi bi-info-circle me-1"></i>
                    Percentages are validated between <code>0.00%</code> and <code>100.00%</code>. Changes apply immediately to all active leader sessions.
                </div>
                <div class="flex items-center gap-2">
                    <button type="reset" class="btn btn-sm btn-outline-secondary">Reset Changes</button>
                    <button type="submit" class="btn btn-sm btn-success px-4 font-semibold flex items-center gap-1.5">
                        <i class="bi bi-check2"></i>
                        <span>Save All Rates</span>
                    </button>
                </div>
            </div>
        </form>
    </div>

    <!-- Flutter / Mobile API Integration Card -->
    <div class="rounded-xl border bg-slate-900 text-slate-100 shadow-sm overflow-hidden">
        <div class="p-4 border-b border-slate-800 flex justify-between items-center">
            <div class="flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-300 m-0">Live Mobile API Contract Simulation</h4>
            </div>
            <span class="text-[11px] font-mono text-emerald-400 bg-emerald-950/80 border border-emerald-800/80 px-2 py-0.5 rounded">
                GET /api/v1/finance/metrics
            </span>
        </div>
        <div class="p-4 font-mono text-[11px] overflow-x-auto text-emerald-300/90 leading-relaxed bg-slate-950">
            <pre class="m-0"><code>{
  "success": true,
  "message": "Finance metrics retrieved successfully",
  "data": {
    "total_revenue": "{{ $metrics['total_revenue'] }}",
    "total_collections": "{{ $metrics['total_collections'] }}",
    "commission_due": "{{ $metrics['commission_due'] }}",
    "commission_structure": [
@foreach($rates as $r)
      {
        "role": "{{ $r['role_name'] }}",
        "direct_referral_cut": "{{ number_format($r['direct_referral_cut_percentage'], 1) }}%",
        "app_join_cut": "{{ number_format($r['app_join_cut_percentage'], 1) }}%"
      }@if(!$loop->last),@endif

@endforeach
    ]
  }
}</code></pre>
        </div>
    </div>
</div>

<!-- Modal: Add New Role Rate -->
<div class="modal fade" id="addRoleModal" tabindex="-1" aria-labelledby="addRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-xl border shadow-lg overflow-hidden">
            <div class="modal-header bg-slate-50 border-b px-4 py-3">
                <h5 class="modal-title text-sm font-bold text-slate-800 flex items-center gap-2" id="addRoleModalLabel">
                    <i class="bi bi-plus-circle-fill text-emerald-600"></i>
                    Add Leadership Role Commission
                </h5>
                <button type="button" class="btn-close text-xs" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.commissions.store') }}">
                @csrf
                <div class="modal-body p-4 space-y-3.5 text-xs">
                    <div>
                        <label class="form-label font-semibold text-slate-700 mb-1">Role Identifier Key (CamelCase / snake_case) <span class="text-rose-500">*</span></label>
                        <input type="text" name="role_id" required placeholder="e.g. regionalDirector, mentorLeader" class="form-control form-control-sm text-xs">
                        <div class="text-[10px] text-slate-400 mt-1">Unique key used in JWT token & Flutter app mapping.</div>
                    </div>

                    <div>
                        <label class="form-label font-semibold text-slate-700 mb-1">Display Role Name <span class="text-rose-500">*</span></label>
                        <input type="text" name="role_name" required placeholder="e.g. Regional Director" class="form-control form-control-sm text-xs">
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="form-label font-semibold text-slate-700 mb-1">Referral Cut (%) <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.1" min="0" max="100" name="direct_referral_cut_percentage" value="7.5" required class="form-control form-control-sm text-xs">
                        </div>
                        <div>
                            <label class="form-label font-semibold text-slate-700 mb-1">App Join Cut (%) <span class="text-rose-500">*</span></label>
                            <input type="number" step="0.1" min="0" max="100" name="app_join_cut_percentage" value="3.0" required class="form-control form-control-sm text-xs">
                        </div>
                        <div>
                            <label class="form-label font-semibold text-slate-700 mb-1">Renewal Cut (%)</label>
                            <input type="number" step="0.1" min="0" max="100" name="renewal_cut_percentage" value="2.0" class="form-control form-control-sm text-xs">
                        </div>
                    </div>

                    <div>
                        <label class="form-label font-semibold text-slate-700 mb-1">Description</label>
                        <textarea name="description" rows="2" placeholder="Brief details about leadership scope..." class="form-control form-control-sm text-xs"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-slate-50 border-t px-4 py-2.5 flex justify-between">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-success px-3 font-semibold">Create Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Hidden Delete Form -->
<form id="deleteCommissionForm" method="POST" action="" style="display:none;">
    @csrf
    @method('DELETE')
</form>

<script>
function filterCommissionTable() {
    const query = document.getElementById('matrixSearchInput').value.toLowerCase().trim();
    const rows = document.querySelectorAll('.commission-row');
    rows.forEach(row => {
        const searchData = row.getAttribute('data-search') || '';
        if (!query || searchData.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function deleteCommissionRate(id, name) {
    if (confirm(`Are you sure you want to remove the commission rate for "${name}"?`)) {
        const form = document.getElementById('deleteCommissionForm');
        form.action = `{{ url('/admin/commissions') }}/${id}`;
        form.submit();
    }
}
</script>
@endsection
