@extends('admin.layouts.app')

@section('title', 'Impact Detail')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    @php
        $displayUser = function ($user): string {
            if (! $user) return '—';
            if (! empty($user->display_name)) return (string) $user->display_name;
            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            return $name !== '' ? $name : ((string) ($user->email ?? '—'));
        };

        $st = strtolower((string) $impact->status);
        $stBadge = match($st) {
            'approved' => [
                'label' => 'Approved',
                'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'dotClass' => 'bg-emerald-500',
            ],
            'rejected' => [
                'label' => 'Rejected',
                'badgeClass' => 'bg-rose-50 text-rose-700 border-rose-200',
                'dotClass' => 'bg-rose-500',
            ],
            default => [
                'label' => 'Pending',
                'badgeClass' => 'bg-amber-50 text-amber-700 border-amber-200',
                'dotClass' => 'bg-amber-500',
            ],
        };
    @endphp

    <!-- Header -->
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <div class="flex items-center gap-2.5">
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Impact Detail</h2>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold border {{ $stBadge['badgeClass'] }}">
                    <span class="w-1.5 h-1.5 rounded-full {{ $stBadge['dotClass'] }}"></span>
                    <span>{{ $stBadge['label'] }}</span>
                </span>
            </div>
            <p class="text-xs t3 m-0 mt-0.5">Impact Record #{{ $impact->id }}</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.impacts.index') }}" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border bs t2 hover:t1 hover:surface-2 transition text-xs font-semibold no-underline">
                <i class="bi bi-arrow-left text-[11px]"></i> Back
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Core Details -->
        <div class="lg:col-span-6 space-y-4">
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="px-4 py-3 surface-2 border-b bs">
                    <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Impact Overview</h6>
                </div>
                <div class="p-4">
                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs mb-0">
                        <dt class="t3 font-medium">Date</dt>
                        <dd class="sm:col-span-2 t1 font-semibold">{{ optional($impact->impact_date)->toDateString() }}</dd>

                        <dt class="t3 font-medium">Action</dt>
                        <dd class="sm:col-span-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                <i class="bi bi-lightning-charge text-indigo-500 text-[11px]"></i>
                                <span>{{ $impact->action }}</span>
                            </span>
                        </dd>

                        <dt class="t3 font-medium">Impacted Peer</dt>
                        <dd class="sm:col-span-2">
                            <div class="font-semibold t1 text-[13px]">{{ $displayUser($impact->impactedPeer) }}</div>
                            @if($impact->impactedPeer?->company_name || $impact->impactedPeer?->company)
                                <div class="text-[11px] t3">{{ $impact->impactedPeer->company_name ?? $impact->impactedPeer->company }}</div>
                            @endif
                        </dd>

                        <dt class="t3 font-medium">Submitted By</dt>
                        <dd class="sm:col-span-2">
                            <div class="font-semibold t1 text-[13px]">{{ $displayUser($impact->user) }}</div>
                            @if($impact->user?->company_name || $impact->user?->company)
                                <div class="text-[11px] t3">{{ $impact->user->company_name ?? $impact->user->company }}</div>
                            @endif
                        </dd>

                        <dt class="t3 font-medium">Life Impacted</dt>
                        <dd class="sm:col-span-2">
                            <span class="inline-flex items-center justify-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                {{ (int) ($total_life_impacted ?? $impact->life_impacted ?? 1) }}
                            </span>
                        </dd>

                        <dt class="t3 font-medium">Status</dt>
                        <dd class="sm:col-span-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold border {{ $stBadge['badgeClass'] }}">
                                <span class="w-1.5 h-1.5 rounded-full {{ $stBadge['dotClass'] }}"></span>
                                <span>{{ $stBadge['label'] }}</span>
                            </span>
                        </dd>

                        @if($impact->approvedBy)
                            <dt class="t3 font-medium">Approved By</dt>
                            <dd class="sm:col-span-2 t1 font-medium">{{ $impact->approvedBy->name }}</dd>

                            <dt class="t3 font-medium">Approved At</dt>
                            <dd class="sm:col-span-2 t2">{{ optional($impact->approved_at)->format('Y-m-d H:i') }}</dd>
                        @endif

                        <dt class="t3 font-medium">Created At</dt>
                        <dd class="sm:col-span-2 t2">{{ optional($impact->created_at)->format('Y-m-d H:i') }} ({{ optional($impact->created_at)->diffForHumans() }})</dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Story & Remarks Column -->
        <div class="lg:col-span-6 space-y-4">
            <div class="rounded-xl border bs surface overflow-hidden">
                <div class="px-4 py-3 surface-2 border-b bs">
                    <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Story & Details</h6>
                </div>
                <div class="p-4 space-y-4 text-xs">
                    <div>
                        <span class="block font-semibold t3 uppercase tracking-wider text-[11px] mb-1.5">Story to Share</span>
                        <div class="p-3 rounded-xl surface-2 border bs t1 leading-relaxed whitespace-pre-wrap">{{ $impact->story_to_share }}</div>
                    </div>

                    @if($impact->additional_remarks)
                        <div>
                            <span class="block font-semibold t3 uppercase tracking-wider text-[11px] mb-1.5">Additional Remarks</span>
                            <div class="p-3 rounded-xl surface-2 border bs t2 leading-relaxed whitespace-pre-wrap">{{ $impact->additional_remarks }}</div>
                        </div>
                    @endif

                    @if($impact->review_remarks)
                        <div>
                            <span class="block font-semibold t3 uppercase tracking-wider text-[11px] mb-1.5">Review Remarks</span>
                            <div class="p-3 rounded-xl bg-amber-50/50 border border-amber-200/60 text-amber-900 leading-relaxed whitespace-pre-wrap">{{ $impact->review_remarks }}</div>
                        </div>
                    @endif
                </div>
            </div>

            @if($impact->status === 'pending')
                <div class="rounded-xl border bs surface overflow-hidden">
                    <div class="px-4 py-3 surface-2 border-b bs">
                        <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Review Submission</h6>
                    </div>
                    <div class="p-4 space-y-3">
                        <form method="POST" action="{{ route('admin.impacts.approve', $impact->id) }}" class="space-y-2">
                            @csrf
                            <input type="text" name="review_remarks" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" placeholder="Review remarks (optional for approval)...">
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-full bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition shadow-xs">
                                <i class="bi bi-check-circle-fill text-[11px]"></i> Approve Impact
                            </button>
                        </form>

                        <div class="border-t bs my-2"></div>

                        <form method="POST" action="{{ route('admin.impacts.reject', $impact->id) }}" class="space-y-2" onsubmit="return confirm('Are you sure you want to reject this impact submission?');">
                            @csrf
                            <input type="text" name="review_remarks" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" placeholder="Reason for rejection (optional)...">
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2 rounded-full bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold transition">
                                <i class="bi bi-x-circle-fill text-[11px]"></i> Reject Impact
                            </button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

