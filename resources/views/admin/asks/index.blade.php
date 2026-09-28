@extends('admin.layouts.app')

@section('title', 'Asks & Discovery')

@include('admin.partials.grid-head')

@push('styles')
<style>
  .ask-kpi-card {
      background: var(--surface, #ffffff);
      border: 1px solid var(--border, #e2e8f0);
      border-radius: 12px;
      padding: 14px 16px;
      transition: all .2s ease;
      display: block;
      width: 100%;
      text-decoration: none !important;
  }
  .ask-kpi-card:hover {
      border-color: #6366f1;
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(99, 102, 241, 0.08);
  }
  .ask-kpi-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
  }
  .ask-kpi-icon {
      width: 36px;
      height: 36px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.15rem;
  }
  .ask-kpi-num {
      font-family: 'Lexend', sans-serif;
      font-weight: 700;
      font-size: 1.5rem;
      line-height: 1.1;
      color: #0f172a;
      font-variant-numeric: tabular-nums;
  }
  .ask-kpi-title {
      font-size: 11px;
      font-weight: 600;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: 0.5px;
  }
  .ask-kpi-sub {
      font-size: 11px;
      color: #94a3b8;
      margin-top: 2px;
  }
  .flow-badge-collaborate { background-color: #ede9fe; color: #6d28d9; border: 1px solid #ddd6fe; }
  .flow-badge-referral { background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
  .flow-badge-help { background-color: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
  .flow-badge-default { background-color: #f1f5f9; color: #475569; border: 1px solid #e2e8f0; }
  
  .status-badge-draft { background-color: #f1f5f9; color: #475569; }
  .status-badge-published { background-color: #dcfce7; color: #15803d; }
  .status-badge-closed { background-color: #e0e7ff; color: #4338ca; }
  .status-badge-cancelled { background-color: #fee2e2; color: #b91c1c; }
  .status-badge-expired { background-color: #ffedd5; color: #c2410c; }
</style>
@endpush

@section('content')
    @php
        $getInitials = function (?string $name): string {
            $words = explode(' ', trim($name ?? ''));
            $initials = '';
            foreach ($words as $w) {
                if (!empty($w)) $initials .= strtoupper(substr($w, 0, 1));
            }
            return substr($initials, 0, 2) ?: 'P';
        };

        $getAvatarBg = function (?string $name): string {
            $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
            $hash = crc32($name ?? 'peer');
            return $colors[abs($hash) % count($colors)];
        };

        $getAvatarUrl = function ($user): ?string {
            if (!$user) return null;
            $fileId = $user->profile_photo_image ?? $user->profile_photo_file_id ?? null;
            if ($fileId) {
                return str_starts_with($fileId, 'http') ? $fileId : url('/api/v1/files/' . $fileId);
            }
            return null;
        };

        $getFlowBadgeClass = function (?string $code): string {
            return match (strtolower($code ?? '')) {
                'collaborate' => 'flow-badge-collaborate',
                'referral' => 'flow-badge-referral',
                'help' => 'flow-badge-help',
                default => 'flow-badge-default',
            };
        };

        $getStatusBadgeClass = function (?string $status): string {
            return match (strtolower($status ?? '')) {
                'published' => 'status-badge-published',
                'closed' => 'status-badge-closed',
                'cancelled' => 'status-badge-cancelled',
                'expired' => 'status-badge-expired',
                default => 'status-badge-draft',
            };
        };
    @endphp

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600"></i>
                <span class="font-medium">{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-700 hover:text-emerald-900 border-0 bg-transparent cursor-pointer text-sm" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-exclamation-octagon-fill text-rose-600"></i>
                <span class="font-medium">{{ session('error') }}</span>
            </div>
            <button type="button" class="text-rose-700 hover:text-rose-900 border-0 bg-transparent cursor-pointer text-sm" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <!-- Header -->
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-indigo-600"></span>
                    <h2 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">Discovery Engine</h2>
                </div>
                <h1 class="text-xl font-bold text-slate-800 m-0 mt-1">Asks & Requirements Management</h1>
                <p class="text-xs text-slate-500 m-0 mt-0.5">Manage Collaboration, Referral, and Help asks, view algorithmic peer matches, responses, and audit trails.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.asks.config') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition no-underline flex items-center gap-1.5">
                    <i class="bi bi-sliders2"></i> Flow &amp; Option Config
                </a>
                <a href="{{ route('admin.asks.export', request()->query()) }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 transition no-underline flex items-center gap-1.5">
                    <i class="bi bi-download"></i> Export CSV
                </a>
            </div>
        </div>

        <!-- KPI Summary Cards Section -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            <!-- Total Asks -->
            <a href="{{ route('admin.asks.index') }}" class="ask-kpi-card {{ empty($filters['status']) && empty($filters['flow']) ? 'ring-2 ring-indigo-500 shadow-sm' : '' }}">
                <div class="ask-kpi-top">
                    <span class="ask-kpi-title">Total Asks</span>
                    <span class="ask-kpi-icon bg-indigo-50 text-indigo-600"><i class="bi bi-collection-fill"></i></span>
                </div>
                <div class="ask-kpi-num text-indigo-600">{{ number_format($stats['total']) }}</div>
                <div class="ask-kpi-sub">All Created Asks</div>
            </a>

            <!-- Published -->
            <a href="{{ route('admin.asks.index', array_merge($filters, ['status' => 'published'])) }}" class="ask-kpi-card {{ ($filters['status'] ?? '') === 'published' ? 'ring-2 ring-emerald-500 shadow-sm bg-emerald-50/20' : '' }}">
                <div class="ask-kpi-top">
                    <span class="ask-kpi-title text-emerald-700">Published</span>
                    <span class="ask-kpi-icon bg-emerald-50 text-emerald-600"><i class="bi bi-broadcast"></i></span>
                </div>
                <div class="ask-kpi-num text-emerald-600">{{ number_format($stats['published']) }}</div>
                <div class="ask-kpi-sub">Active in Discovery</div>
            </a>

            <!-- Closed -->
            <a href="{{ route('admin.asks.index', array_merge($filters, ['status' => 'closed'])) }}" class="ask-kpi-card {{ ($filters['status'] ?? '') === 'closed' ? 'ring-2 ring-blue-500 shadow-sm bg-blue-50/20' : '' }}">
                <div class="ask-kpi-top">
                    <span class="ask-kpi-title text-blue-700">Closed</span>
                    <span class="ask-kpi-icon bg-blue-50 text-blue-600"><i class="bi bi-check2-all"></i></span>
                </div>
                <div class="ask-kpi-num text-blue-600">{{ number_format($stats['closed']) }}</div>
                <div class="ask-kpi-sub">Completed &amp; Fulfilled</div>
            </a>

            <!-- Drafts -->
            <a href="{{ route('admin.asks.index', array_merge($filters, ['status' => 'draft'])) }}" class="ask-kpi-card {{ ($filters['status'] ?? '') === 'draft' ? 'ring-2 ring-slate-500 shadow-sm bg-slate-50/50' : '' }}">
                <div class="ask-kpi-top">
                    <span class="ask-kpi-title text-slate-700">Drafts</span>
                    <span class="ask-kpi-icon bg-slate-100 text-slate-600"><i class="bi bi-pencil-square"></i></span>
                </div>
                <div class="ask-kpi-num text-slate-700">{{ number_format($stats['draft']) }}</div>
                <div class="ask-kpi-sub">Unpublished / In Progress</div>
            </a>

            <!-- Total Matches -->
            <div class="ask-kpi-card">
                <div class="ask-kpi-top">
                    <span class="ask-kpi-title text-purple-700">Peer Matches</span>
                    <span class="ask-kpi-icon bg-purple-50 text-purple-600"><i class="bi bi-people-fill"></i></span>
                </div>
                <div class="ask-kpi-num text-purple-600">{{ number_format($stats['total_matches']) }}</div>
                <div class="ask-kpi-sub">Algorithmic Matches</div>
            </div>

            <!-- Responses -->
            <div class="ask-kpi-card">
                <div class="ask-kpi-top">
                    <span class="ask-kpi-title text-amber-700">Responses</span>
                    <span class="ask-kpi-icon bg-amber-50 text-amber-600"><i class="bi bi-chat-dots-fill"></i></span>
                </div>
                <div class="ask-kpi-num text-amber-600">{{ number_format($stats['total_responses']) }}</div>
                <div class="ask-kpi-sub">Peer Offers &amp; Intros</div>
            </div>
        </div>

        <!-- Filter Bar -->
        <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl">
            <form method="GET" action="{{ route('admin.asks.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-2">
                <!-- Search -->
                <div class="lg:col-span-2">
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Search</label>
                    <div class="relative">
                        <i class="bi bi-search absolute left-3 top-2.5 text-slate-400 text-xs"></i>
                        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Search title, peer, company, email..." class="w-full pl-8 pr-3 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white">
                    </div>
                </div>

                <!-- Flow Filter -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Flow</label>
                    <select name="flow" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white">
                        <option value="">All Flows</option>
                        @foreach($flows as $flow)
                            <option value="{{ $flow->code }}" {{ $filters['flow'] === $flow->code ? 'selected' : '' }}>{{ $flow->name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Status</label>
                    <select name="status" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white">
                        <option value="">All Statuses</option>
                        @foreach($statuses as $st)
                            <option value="{{ $st }}" {{ $filters['status'] === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Date Range -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">From Date</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="w-full px-2.5 py-1.5 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white">
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Actions</label>
                    <div class="flex items-center gap-1.5">
                        <button type="submit" class="flex-1 py-1.5 px-3 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs rounded-lg transition">
                            <i class="bi bi-funnel-fill"></i> Filter
                        </button>
                        <a href="{{ route('admin.asks.index') }}" class="py-1.5 px-2.5 bg-slate-200 hover:bg-slate-300 text-slate-700 text-xs rounded-lg transition" title="Clear Filters">
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Asks Table -->
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-left text-xs text-slate-700 border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold uppercase text-[11px] tracking-wider">
                    <tr>
                        <th class="py-3 px-4">Creator / Peer</th>
                        <th class="py-3 px-4">Ask Title &amp; Details</th>
                        <th class="py-3 px-4">Flow &amp; Type</th>
                        <th class="py-3 px-4 text-center">Visibility</th>
                        <th class="py-3 px-4 text-center">Matches</th>
                        <th class="py-3 px-4 text-center">Responses</th>
                        <th class="py-3 px-4 text-center">Status</th>
                        <th class="py-3 px-4">Created Date</th>
                        <th class="py-3 px-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($asks as $ask)
                        @php
                            $user = $ask->user;
                            $creatorName = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : '—';
                            if ($creatorName === '' && $user) {
                                $creatorName = $user->display_name ?: $user->name ?: 'Peer';
                            }
                            $avatarUrl = $getAvatarUrl($user);
                            $initials = $getInitials($creatorName);
                            $avatarBg = $getAvatarBg($creatorName);
                            $flowCode = $ask->flow?->code ?? '';
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <!-- Creator -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                <div class="flex items-center gap-2.5">
                                    @if($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="{{ $creatorName }}" class="w-8 h-8 rounded-full object-cover border border-slate-200 shadow-sm" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-8 h-8 rounded-full flex items-center justify-center text-white text-[11px] font-bold\' style=\'background-color: {{ $avatarBg }}\'>{{ $initials }}</div>'">
                                    @else
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-[11px] font-bold shadow-sm" style="background-color: {{ $avatarBg }}">
                                            {{ $initials }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="font-semibold text-slate-800 hover:text-indigo-600 cursor-pointer" onclick="window.location='{{ route('admin.asks.show', $ask->id) }}'">
                                            {{ $creatorName }}
                                        </div>
                                        <div class="text-[11px] text-slate-400">
                                            {{ $user?->company_name ?: ($user?->city?->name ?? '—') }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Title & Summary -->
                            <td class="py-3 px-4 max-w-xs">
                                <a href="{{ route('admin.asks.show', $ask->id) }}" class="font-semibold text-slate-800 hover:text-indigo-600 no-underline line-clamp-1">
                                    {{ $ask->title }}
                                </a>
                                @if($ask->description)
                                    <p class="text-[11px] text-slate-400 m-0 line-clamp-1">{{ Str::limit($ask->description, 70) }}</p>
                                @endif
                                <div class="text-[10px] text-slate-400 font-mono mt-0.5">{{ substr($ask->id, 0, 8) }}...</div>
                            </td>

                            <!-- Flow & Type -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $getFlowBadgeClass($flowCode) }}">
                                    {{ $ask->flow?->name ?? 'Custom' }}
                                </span>
                                @if($ask->type)
                                    <div class="text-[11px] text-slate-500 mt-1 flex items-center gap-1">
                                        <i class="bi bi-tag-fill text-[9px] text-slate-400"></i>
                                        <span>{{ $ask->type->name }}</span>
                                    </div>
                                @endif
                            </td>

                            <!-- Visibility -->
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 capitalize border border-slate-200">
                                    {{ $ask->visibility_type }}
                                </span>
                                @if($ask->post_to_timeline)
                                    <div class="text-[10px] text-emerald-600 mt-0.5" title="Synced to Peer Timeline">
                                        <i class="bi bi-clock-history"></i> Timeline
                                    </div>
                                @endif
                            </td>

                            <!-- Matches Count -->
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $ask->matches_count > 0 ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-500' }}">
                                    <i class="bi bi-people me-1"></i> {{ $ask->matches_count }}
                                </span>
                            </td>

                            <!-- Responses Count -->
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $ask->responses_count > 0 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-500' }}">
                                    <i class="bi bi-chat me-1"></i> {{ $ask->responses_count }}
                                </span>
                            </td>

                            <!-- Status -->
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold {{ $getStatusBadgeClass($ask->status) }}">
                                    {{ ucfirst($ask->status) }}
                                </span>
                            </td>

                            <!-- Created Date -->
                            <td class="py-3 px-4 whitespace-nowrap text-slate-500">
                                <div>{{ $ask->created_at->format('d M Y') }}</div>
                                <div class="text-[10px] text-slate-400">{{ $ask->created_at->format('H:i') }}</div>
                            </td>

                            <!-- Actions -->
                            <td class="py-3 px-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.asks.show', $ask->id) }}" class="p-1.5 text-slate-500 hover:text-indigo-600 hover:bg-slate-100 rounded-lg transition" title="View Full Ask Details">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>

                                    <!-- Quick Status Modal Trigger -->
                                    <button type="button" onclick="openStatusModal('{{ $ask->id }}', '{{ $ask->title }}', '{{ $ask->status }}')" class="p-1.5 text-slate-500 hover:text-amber-600 hover:bg-slate-100 rounded-lg transition" title="Change Status">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>

                                    <!-- Delete Form -->
                                    <form method="POST" action="{{ route('admin.asks.destroy', $ask->id) }}" class="inline-block" onsubmit="return confirm('Are you sure you want to remove this Ask?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition" title="Remove Ask">
                                            <i class="bi bi-trash-fill"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-12 text-center text-slate-400">
                                <i class="bi bi-inbox text-4xl block mb-2 text-slate-300"></i>
                                <span class="text-sm font-medium">No Asks found matching your search or filters.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pt-2">
            {{ $asks->links() }}
        </div>
    </div>

    <!-- Quick Status Update Modal -->
    <div id="statusModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-sm text-slate-800 m-0">Update Ask Status</h3>
                <button type="button" onclick="closeStatusModal()" class="text-slate-400 hover:text-slate-600 border-0 bg-transparent text-lg">&times;</button>
            </div>
            <form id="statusForm" method="POST" action="" class="p-5 space-y-4">
                @csrf
                @method('PATCH')
                
                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Ask Title</label>
                    <div id="modalAskTitle" class="text-xs font-medium text-slate-800 bg-slate-50 p-2.5 rounded-lg border border-slate-200"></div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">New Status</label>
                    <select name="status" id="modalStatusSelect" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white">
                        <option value="draft">Draft</option>
                        <option value="published">Published</option>
                        <option value="closed">Closed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="expired">Expired</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Reason / Note (Optional)</label>
                    <textarea name="reason" rows="3" placeholder="Enter reason for status change (stored in audit log)..." class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" onclick="closeStatusModal()" class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function openStatusModal(askId, title, currentStatus) {
            const form = document.getElementById('statusForm');
            form.action = '{{ url("admin/asks") }}/' + askId + '/status';
            document.getElementById('modalAskTitle').textContent = title;
            document.getElementById('modalStatusSelect').value = currentStatus;
            document.getElementById('statusModal').classList.remove('hidden');
        }

        function closeStatusModal() {
            document.getElementById('statusModal').classList.add('hidden');
        }

        // Close on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeStatusModal();
        });
    </script>
    @endpush
@endsection
