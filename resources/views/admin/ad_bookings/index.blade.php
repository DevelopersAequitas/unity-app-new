@extends('admin.layouts.app')

@section('title', 'Ad Booking Requests')

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill text-rose-600"></i>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    @endif

    <!-- Metric / Stats Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <a href="{{ route('admin.ad-bookings.index', ['status' => 'pending']) }}" class="p-3.5 rounded-xl border bs surface hover:border-amber-400/60 transition no-underline block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] uppercase tracking-wider font-semibold text-amber-600">Pending Requests</span>
                <div class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center text-sm">
                    <i class="bi bi-hourglass-split"></i>
                </div>
            </div>
            <div class="text-xl font-bold t1 mt-2">{{ number_format($totalPending) }}</div>
            <span class="text-[11px] t3">Requires admin review</span>
        </a>

        <a href="{{ route('admin.ad-bookings.index', ['status' => 'approved']) }}" class="p-3.5 rounded-xl border bs surface hover:border-emerald-400/60 transition no-underline block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] uppercase tracking-wider font-semibold text-emerald-600">Approved</span>
                <div class="w-8 h-8 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center text-sm">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
            <div class="text-xl font-bold t1 mt-2">{{ number_format($totalApproved) }}</div>
            <span class="text-[11px] t3">Active/Live ad campaigns</span>
        </a>

        <a href="{{ route('admin.ad-bookings.index', ['status' => 'rejected']) }}" class="p-3.5 rounded-xl border bs surface hover:border-rose-400/60 transition no-underline block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] uppercase tracking-wider font-semibold text-rose-600">Rejected</span>
                <div class="w-8 h-8 rounded-lg bg-rose-50 text-rose-600 flex items-center justify-center text-sm">
                    <i class="bi bi-x-circle"></i>
                </div>
            </div>
            <div class="text-xl font-bold t1 mt-2">{{ number_format($totalRejected) }}</div>
            <span class="text-[11px] t3">Declined booking submissions</span>
        </a>

        <a href="{{ route('admin.ad-bookings.index') }}" class="p-3.5 rounded-xl border bs surface hover:border-indigo-400/60 transition no-underline block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] uppercase tracking-wider font-semibold text-indigo-600">Total Requests</span>
                <div class="w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center text-sm">
                    <i class="bi bi-megaphone"></i>
                </div>
            </div>
            <div class="text-xl font-bold t1 mt-2">{{ number_format($totalCount) }}</div>
            <span class="text-[11px] t3">All time ad submissions</span>
        </a>
    </div>

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Ad Booking Requests</h2>
                <p class="text-xs t3 m-0 mt-0.5">Manage and review incoming ad placement requests from users.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.ads.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline flex items-center gap-1">
                    <i class="bi bi-megaphone me-1"></i>All Ads List
                </a>
            </div>
        </div>

        <!-- Filters & Search Card -->
        <div class="p-3 rounded-lg border bs surface-2">
            <form method="GET" action="{{ route('admin.ad-bookings.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2.5 items-end">
                <div class="md:col-span-2">
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                    <input type="text" name="q" value="{{ $search }}" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Search by title, subtitle, user name or email...">
                </div>
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                    <select name="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="">All Statuses</option>
                        <option value="pending" @selected(($status ?? '') === 'pending')>Pending</option>
                        <option value="approved" @selected(($status ?? '') === 'approved')>Approved</option>
                        <option value="rejected" @selected(($status ?? '') === 'rejected')>Rejected</option>
                    </select>
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold rounded bg-indigo-600 hover:bg-indigo-500 text-white transition focus-ring">Filter</button>
                    <a href="{{ route('admin.ad-bookings.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left w-12">Preview</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Ad Title & Details</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Requested By</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Placement</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Schedule</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Requested At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse($bookings as $booking)
                            @php
                                $user = $booking->user;
                                $userName = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Guest/Unknown';
                                if (empty($userName)) {
                                    $userName = $user->email ?? 'User #' . substr($booking->user_id, 0, 8);
                                }
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    @if($booking->image_url)
                                        <img src="{{ $booking->image_url }}" alt="{{ $booking->title }}" class="w-10 h-10 rounded object-cover border bs">
                                    @else
                                        <div class="w-10 h-10 rounded bg-indigo-50 text-indigo-500 flex items-center justify-center border bs text-xs font-bold">
                                            <i class="bi bi-image"></i>
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    <div class="font-semibold t1">
                                        <a href="{{ route('admin.ad-bookings.show', $booking->id) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                            {{ $booking->title }}
                                        </a>
                                    </div>
                                    @if($booking->subtitle)
                                        <div class="t3 text-[11px] mt-0.5">{{ $booking->subtitle }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    <div class="font-medium t1">{{ $userName }}</div>
                                    @if($user?->email)
                                        <div class="t3 text-[11px]">{{ $user->email }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">
                                        {{ ucfirst($booking->placement ?? 'general') }}
                                    </span>
                                    @if($booking->page_name)
                                        <div class="t3 text-[11px] mt-0.5">Page: {{ $booking->page_name }}</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">
                                    @if($booking->starts_at || $booking->ends_at)
                                        <div><span class="t3 font-medium">From:</span> {{ $booking->starts_at ? $booking->starts_at->format('M d, Y') : 'Immediate' }}</div>
                                        <div><span class="t3 font-medium">To:</span> {{ $booking->ends_at ? $booking->ends_at->format('M d, Y') : 'Indefinite' }}</div>
                                    @else
                                        <span class="t3">No dates specified</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if($booking->status === 'pending')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200 flex items-center gap-1 w-max">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> Pending
                                        </span>
                                    @elseif($booking->status === 'approved')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200 flex items-center gap-1 w-max">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Approved
                                        </span>
                                    @elseif($booking->status === 'rejected')
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200 flex items-center gap-1 w-max">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Rejected
                                        </span>
                                    @else
                                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">
                                            {{ ucfirst($booking->status) }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                    {{ $booking->created_at ? $booking->created_at->format('M d, Y H:i') : '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-1.5 items-center">
                                        <a href="{{ route('admin.ad-bookings.show', $booking->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">
                                            View
                                        </a>

                                        @if($booking->status === 'pending')
                                            <button type="button" onclick="openReviewModal('{{ $booking->id }}', '{{ e($booking->title) }}', 'approved')" class="px-2.5 py-1 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring">
                                                Approve
                                            </button>
                                            <button type="button" onclick="openReviewModal('{{ $booking->id }}', '{{ e($booking->title) }}', 'rejected')" class="px-2.5 py-1 text-xs font-semibold rounded bg-rose-600 hover:bg-rose-500 text-white transition focus-ring">
                                                Reject
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-8 text-xs t3">No ad booking requests found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($bookings->hasPages())
                <div class="p-3 border-t bs surface-2">
                    {{ $bookings->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- Review Modal -->
    <div id="reviewModal" class="fixed inset-0 z-50 hidden bg-gray-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-xl border bs shadow-xl max-w-md w-full p-5 space-y-4">
            <div class="flex items-center justify-between border-b pb-3">
                <h3 id="modalTitle" class="text-sm font-bold t1">Review Ad Booking Request</h3>
                <button type="button" onclick="closeReviewModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form id="reviewForm" method="POST" action="" class="space-y-4">
                @csrf
                <input type="hidden" id="modalStatusInput" name="status" value="">

                <div>
                    <p class="text-xs t2 mb-2">You are about to <span id="modalActionText" class="font-bold uppercase"></span> the ad request: <strong id="modalAdTitle"></strong></p>
                </div>

                <div>
                    <label for="admin_remarks" class="block text-xs font-semibold t2 mb-1">Admin Remarks (Optional)</label>
                    <textarea id="admin_remarks" name="admin_remarks" rows="3" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Add any notes or feedback for the user..."></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" onclick="closeReviewModal()" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition">Cancel</button>
                    <button type="submit" id="modalSubmitBtn" class="px-3 py-1.5 text-xs font-semibold rounded text-white transition focus-ring">Confirm Action</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openReviewModal(bookingId, adTitle, action) {
            const modal = document.getElementById('reviewModal');
            const form = document.getElementById('reviewForm');
            const statusInput = document.getElementById('modalStatusInput');
            const actionText = document.getElementById('modalActionText');
            const titleEl = document.getElementById('modalAdTitle');
            const submitBtn = document.getElementById('modalSubmitBtn');

            form.action = `/admin/ad-bookings/${bookingId}/review`;
            statusInput.value = action;
            actionText.textContent = action;
            titleEl.textContent = adTitle;

            if (action === 'approved') {
                actionText.className = 'font-bold uppercase text-emerald-600';
                submitBtn.className = 'px-3 py-1.5 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring';
                submitBtn.textContent = 'Approve & Create Ad';
            } else {
                actionText.className = 'font-bold uppercase text-rose-600';
                submitBtn.className = 'px-3 py-1.5 text-xs font-semibold rounded bg-rose-600 hover:bg-rose-500 text-white transition focus-ring';
                submitBtn.textContent = 'Reject Request';
            }

            modal.classList.remove('hidden');
        }

        function closeReviewModal() {
            document.getElementById('reviewModal').classList.add('hidden');
        }
    </script>
@endsection
