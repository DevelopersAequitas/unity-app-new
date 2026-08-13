@extends('admin.layouts.app')

@section('title', 'Ad Booking Request Details')

@include('admin.partials.grid-head')

@section('content')
    @php
        $user = $adBooking->user;
        $userName = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : 'Guest/Unknown';
        if (empty($userName)) {
            $userName = $user->email ?? 'User #' . substr($adBooking->user_id, 0, 8);
        }
        $reviewer = $adBooking->reviewer;
        $reviewerName = $reviewer ? trim(($reviewer->first_name ?? '') . ' ' . ($reviewer->last_name ?? '')) : null;
        if (empty($reviewerName)) {
            $reviewerName = $reviewer->email ?? null;
        }
    @endphp

    <div class="space-y-4">
        <!-- Top Action Bar -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.ad-bookings.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline flex items-center gap-1.5">
                    <i class="bi bi-arrow-left"></i> Back to Requests
                </a>
                <span class="text-xs t3">/</span>
                <span class="text-xs font-semibold t1">Booking #{{ substr($adBooking->id, 0, 8) }}</span>
            </div>

            <div class="flex items-center gap-2">
                @if($adBooking->status === 'pending')
                    <span class="chip px-3 py-1 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span> Pending Review
                    </span>
                @elseif($adBooking->status === 'approved')
                    <span class="chip px-3 py-1 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Approved
                    </span>
                    @if($adBooking->ad_id)
                        <a href="{{ route('admin.ads.show', $adBooking->ad_id) }}" class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition no-underline flex items-center gap-1">
                            <i class="bi bi-megaphone me-1"></i>View Live Ad
                        </a>
                    @endif
                @elseif($adBooking->status === 'rejected')
                    <span class="chip px-3 py-1 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200 flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-rose-500"></span> Rejected
                    </span>
                @endif
            </div>
        </div>

        @if(session('success'))
            <div class="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Main Content: Ad Details -->
            <div class="lg:col-span-2 space-y-4">
                <div class="light rounded-xl border bs p-5 surface space-y-4">
                    <div class="border-b pb-3 flex items-start justify-between gap-3">
                        <div>
                            <h2 class="text-base font-bold t1 m-0">{{ $adBooking->title }}</h2>
                            @if($adBooking->subtitle)
                                <p class="text-xs t3 m-0 mt-0.5">{{ $adBooking->subtitle }}</p>
                            @endif
                        </div>
                        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">
                            {{ ucfirst($adBooking->placement ?? 'General') }} Placement
                        </span>
                    </div>

                    <!-- Image Preview -->
                    <div>
                        <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1.5">Ad Image Preview</label>
                        @if($adBooking->image_url)
                            <div class="rounded-lg overflow-hidden border bs bg-gray-900/5 p-2 flex justify-center max-h-72">
                                <img src="{{ $adBooking->image_url }}" alt="{{ $adBooking->title }}" class="max-h-64 object-contain rounded">
                            </div>
                        @else
                            <div class="p-8 rounded-lg border bs bg-gray-50 text-center text-xs t3">
                                <i class="bi bi-image text-2xl text-gray-400 mb-1 block"></i>
                                No image attached to this request
                            </div>
                        @endif
                    </div>

                    <!-- Description -->
                    @if($adBooking->description)
                        <div>
                            <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Description / Copy</label>
                            <div class="p-3 rounded-lg border bs surface-2 text-xs t1 whitespace-pre-line leading-relaxed">
                                {{ $adBooking->description }}
                            </div>
                        </div>
                    @endif

                    <!-- URLs and Buttons -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
                        <div class="p-3 rounded-lg border bs surface-2">
                            <span class="block text-[10px] uppercase tracking-wider font-semibold t3">Redirect / Target URL</span>
                            @if($adBooking->redirect_url)
                                <a href="{{ $adBooking->redirect_url }}" target="_blank" class="text-xs text-indigo-600 hover:underline font-medium break-all flex items-center gap-1 mt-0.5">
                                    {{ $adBooking->redirect_url }} <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                                </a>
                            @else
                                <span class="text-xs t3 italic">Not provided</span>
                            @endif
                        </div>

                        <div class="p-3 rounded-lg border bs surface-2">
                            <span class="block text-[10px] uppercase tracking-wider font-semibold t3">Call-to-Action Button</span>
                            <span class="text-xs t1 font-medium block mt-0.5">
                                {{ $adBooking->button_text ?: 'Default (e.g. Learn More)' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Admin Action Card (If Pending) -->
                @if($adBooking->status === 'pending')
                    <div class="light rounded-xl border bs p-5 surface space-y-4">
                        <div class="border-b pb-3">
                            <h3 class="text-sm font-bold t1 m-0 flex items-center gap-2">
                                <i class="bi bi-shield-check text-indigo-500"></i> Admin Review & Decision
                            </h3>
                            <p class="text-xs t3 m-0 mt-0.5">Approve this request to create a live ad, or reject with feedback.</p>
                        </div>

                        <form method="POST" action="{{ route('admin.ad-bookings.review', $adBooking->id) }}" class="space-y-4">
                            @csrf
                            <div>
                                <label for="admin_remarks" class="block text-xs font-semibold t2 mb-1">Admin Remarks / Reason</label>
                                <textarea id="admin_remarks" name="admin_remarks" rows="3" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Enter optional notes for approval or rejection..."></textarea>
                            </div>

                            <div class="flex items-center gap-3 pt-2">
                                <button type="submit" name="status" value="approved" class="px-4 py-2 text-xs font-semibold rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring flex items-center gap-1.5">
                                    <i class="bi bi-check-lg"></i> Approve & Publish Ad
                                </button>
                                <button type="submit" name="status" value="rejected" class="px-4 py-2 text-xs font-semibold rounded-lg bg-rose-600 hover:bg-rose-500 text-white transition focus-ring flex items-center gap-1.5">
                                    <i class="bi bi-x-lg"></i> Reject Request
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>

            <!-- Sidebar: Requester & Schedule Info -->
            <div class="space-y-4">
                <!-- Requester Info -->
                <div class="light rounded-xl border bs p-5 surface space-y-3">
                    <h3 class="text-xs uppercase tracking-wider font-bold t3 border-b pb-2 m-0">Requester Info</h3>

                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-sm border bs shrink-0">
                            <i class="bi bi-person-fill"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold t1">{{ $userName }}</div>
                            @if($user?->email)
                                <div class="text-[11px] t3">{{ $user->email }}</div>
                            @endif
                            @if($user?->phone)
                                <div class="text-[11px] t3">{{ $user->phone }}</div>
                            @endif
                        </div>
                    </div>

                    @if($user)
                        <div class="pt-2">
                            <a href="{{ route('admin.users.index', ['search' => $user->email]) }}" class="text-xs text-indigo-600 hover:underline font-semibold flex items-center gap-1">
                                View User Profile <i class="bi bi-chevron-right text-[10px]"></i>
                            </a>
                        </div>
                    @endif
                </div>

                <!-- Schedule & Target Info -->
                <div class="light rounded-xl border bs p-5 surface space-y-3">
                    <h3 class="text-xs uppercase tracking-wider font-bold t3 border-b pb-2 m-0">Schedule & Targeting</h3>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between py-1 border-b bs">
                            <span class="t3">Placement:</span>
                            <span class="font-semibold t1">{{ ucfirst($adBooking->placement ?? 'General') }}</span>
                        </div>

                        <div class="flex justify-between py-1 border-b bs">
                            <span class="t3">Page Target:</span>
                            <span class="font-semibold t1">{{ $adBooking->page_name ?: 'All Pages' }}</span>
                        </div>

                        <div class="flex justify-between py-1 border-b bs">
                            <span class="t3">Start Date:</span>
                            <span class="font-semibold t1">{{ $adBooking->starts_at ? $adBooking->starts_at->format('M d, Y H:i') : 'Immediate' }}</span>
                        </div>

                        <div class="flex justify-between py-1 border-b bs">
                            <span class="t3">End Date:</span>
                            <span class="font-semibold t1">{{ $adBooking->ends_at ? $adBooking->ends_at->format('M d, Y H:i') : 'Indefinite' }}</span>
                        </div>

                        <div class="flex justify-between py-1">
                            <span class="t3">Submitted On:</span>
                            <span class="font-semibold t1">{{ $adBooking->created_at ? $adBooking->created_at->format('M d, Y H:i') : '-' }}</span>
                        </div>
                    </div>
                </div>

                <!-- Audit / Review Log -->
                @if($adBooking->status !== 'pending')
                    <div class="light rounded-xl border bs p-5 surface space-y-3">
                        <h3 class="text-xs uppercase tracking-wider font-bold t3 border-b pb-2 m-0">Review History</h3>

                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between py-1 border-b bs">
                                <span class="t3">Decision:</span>
                                <span class="font-bold uppercase {{ $adBooking->status === 'approved' ? 'text-emerald-600' : 'text-rose-600' }}">
                                    {{ $adBooking->status }}
                                </span>
                            </div>

                            @if($reviewerName)
                                <div class="flex justify-between py-1 border-b bs">
                                    <span class="t3">Reviewed By:</span>
                                    <span class="font-semibold t1">{{ $reviewerName }}</span>
                                </div>
                            @endif

                            @if($adBooking->reviewed_at)
                                <div class="flex justify-between py-1 border-b bs">
                                    <span class="t3">Reviewed At:</span>
                                    <span class="font-semibold t1">{{ $adBooking->reviewed_at->format('M d, Y H:i') }}</span>
                                </div>
                            @endif

                            @if($adBooking->admin_remarks)
                                <div class="pt-1">
                                    <span class="t3 block mb-1">Admin Remarks:</span>
                                    <div class="p-2.5 rounded border bs surface-2 text-xs t1">
                                        {{ $adBooking->admin_remarks }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
