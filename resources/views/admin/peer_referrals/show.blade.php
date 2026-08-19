@extends('admin.layouts.app')

@section('title', 'Peer Referral Details')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.peer-referrals.index') }}" class="inline-flex items-center text-xs font-semibold text-indigo-600 hover:text-indigo-500 no-underline transition">
            <i class="bi bi-arrow-left me-1"></i> Back to Referral List
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Details (Left/Center) -->
        <div class="lg:col-span-2 space-y-6">
            <div class="light rounded-xl border bs p-5 surface space-y-4">
                <div class="flex justify-between items-start gap-4">
                    <div>
                        <span class="text-[10px] uppercase tracking-wider font-semibold t3">Peer Referral Detail</span>
                        <h2 class="text-lg font-semibold font-display mt-0.5 mb-1 text-slate-800">{{ $peerReferral->referred_name }}</h2>
                        <div class="flex items-center gap-2 text-xs t3">
                            <span>Status:</span>
                            @php
                                $statusClasses = [
                                    'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'contacted' => 'bg-blue-50 text-blue-700 border-blue-200',
                                    'accepted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    'converted' => 'bg-purple-50 text-purple-700 border-purple-200',
                                ];
                                $badgeClass = $statusClasses[$peerReferral->status] ?? 'bg-gray-50 text-gray-700 border-gray-200';
                            @endphp
                            <span class="px-2 py-0.5 rounded text-[11px] font-semibold border {{ $badgeClass }}">
                                {{ ucfirst($peerReferral->status) }}
                            </span>
                        </div>
                    </div>
                    <span class="text-xs t3">{{ $peerReferral->created_at?->format('d M Y, h:i A') }}</span>
                </div>

                <hr class="border-t bs my-4">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Phone Number</span>
                        <span class="text-xs font-semibold t1">{{ $peerReferral->referred_phone }}</span>
                    </div>
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Email Address</span>
                        <span class="text-xs t1">{{ $peerReferral->referred_email ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Company / Business</span>
                        <span class="text-xs t1">{{ $peerReferral->referred_company_name ?? '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Designation / Role</span>
                        <span class="text-xs t1">{{ $peerReferral->referred_designation ?? '—' }}</span>
                    </div>
                </div>

                @if($peerReferral->message)
                    <div class="mt-4 pt-3 border-t bs">
                        <span class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Referral Note / Message</span>
                        <div class="p-3 rounded-lg border bs surface-2 text-xs t1 italic">{{ $peerReferral->message }}</div>
                    </div>
                @endif
            </div>

            <!-- Circles / Context Card -->
            <div class="light rounded-xl border bs p-5 surface space-y-4">
                <h3 class="text-sm font-semibold font-display mb-3">Referral Context</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="p-3 rounded-lg border bs surface-2">
                        <span class="block text-[10px] uppercase tracking-wider font-semibold t3 mb-1">Parent Circle</span>
                        <span class="text-xs font-semibold t1">{{ $peerReferral->mainCircle?->name ?? '—' }}</span>
                    </div>
                    <div class="p-3 rounded-lg border bs surface-2">
                        <span class="block text-[10px] uppercase tracking-wider font-semibold t3 mb-1">Specific Circle</span>
                        <span class="text-xs font-semibold t1">{{ $peerReferral->circle?->name ?? 'Main Circle Referral' }}</span>
                    </div>
                    <div class="p-3 rounded-lg border bs surface-2">
                        <span class="block text-[10px] uppercase tracking-wider font-semibold t3 mb-1">Open Category</span>
                        <span class="text-xs font-semibold t1">{{ $peerReferral->category?->name ?? '—' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar / Referrer & Action Panel (Right) -->
        <div class="space-y-6">
            <!-- Referrer Member Profile Card -->
            <div class="light rounded-xl border bs p-5 surface space-y-4">
                <h3 class="text-sm font-semibold font-display mb-3">Referrer Details</h3>
                @if($peerReferral->referrer)
                    @php
                        $refName = $peerReferral->referrer->display_name ?: trim(($peerReferral->referrer->first_name ?? '') . ' ' . ($peerReferral->referrer->last_name ?? ''));
                        $words = explode(' ', trim($refName));
                        $initials = '';
                        foreach ($words as $w) {
                            if (! empty($w)) $initials .= strtoupper(substr($w, 0, 1));
                        }
                        $initials = substr($initials, 0, 2) ?: 'P';
                        $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
                        $hash = crc32($refName);
                        $avatarBg = $colors[abs($hash) % count($colors)];
                    @endphp
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full text-white text-sm font-bold flex items-center justify-center shrink-0" style="background-color: {{ $avatarBg }}">
                            {{ $initials }}
                        </div>
                        <div>
                            <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $peerReferral->referrer->id }}', event);" class="text-indigo-600 font-semibold hover:underline no-underline text-xs">
                                {{ $refName }}
                            </a>
                            <div class="text-[11px] t3 mt-0.5">{{ $peerReferral->referrer->email }}</div>
                            <div class="text-[11px] t3">{{ $peerReferral->referrer->phone ?? 'No phone' }}</div>
                        </div>
                    </div>
                @else
                    <span class="text-xs t3">No referrer user record found.</span>
                @endif
            </div>

            <!-- Action Panel: Status Update -->
            <div class="light rounded-xl border bs p-5 surface space-y-4">
                <h3 class="text-sm font-semibold font-display mb-3">Update Status</h3>
                <form method="POST" action="{{ route('admin.peer-referrals.status-update', $peerReferral->id) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Referral Status</label>
                        <select name="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                            @foreach(['pending', 'contacted', 'accepted', 'rejected', 'converted'] as $opt)
                                <option value="{{ $opt }}" {{ $peerReferral->status === $opt ? 'selected' : '' }}>{{ ucfirst($opt) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="w-full px-3 py-2 text-xs font-semibold rounded bg-indigo-600 hover:bg-indigo-500 text-white transition focus-ring text-center">
                        Update Status
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
