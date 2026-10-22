@extends('admin.layouts.app')

@section('title', 'Application ' . $application->application_number)

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600"></i>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" class="text-emerald-700 hover:text-emerald-900 border-0 bg-transparent cursor-pointer text-sm" onclick="this.parentElement.remove()">&times;</button>
        </div>
    @endif

    <div class="space-y-4">
        {{-- Top Bar --}}
        <div class="rounded-xl border bs p-4 bg-white shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="text-xs font-mono font-bold px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ $application->application_number }}
                    </span>
                    @php
                        $badgeColor = match($application->status) {
                            'submitted' => 'bg-sky-50 text-sky-700 border-sky-200',
                            'under_review' => 'bg-amber-50 text-amber-700 border-amber-200',
                            'changes_requested' => 'bg-purple-50 text-purple-700 border-purple-200',
                            'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                            'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                            default => 'bg-slate-100 text-slate-700 border-slate-200',
                        };
                    @endphp
                    <span class="px-2.5 py-0.5 rounded-full border text-xs font-semibold uppercase {{ $badgeColor }}">
                        {{ str_replace('_', ' ', $application->status) }}
                    </span>
                </div>
                <h1 class="text-xl font-bold t1 mt-1">{{ $application->company_name }}</h1>
                <p class="text-xs t3 mt-0.5">Submitted via {{ ucfirst($application->source) }} on {{ $application->created_at->format('M d, Y H:i') }}</p>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.advertiser.applications.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs t2 hover:t1 hover:surface-2 transition no-underline">
                    &larr; Back
                </a>

                @if($application->status === 'submitted')
                    <form method="POST" action="{{ route('admin.advertiser.applications.start-review', $application) }}">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold transition border-0 cursor-pointer shadow-sm">
                            <i class="bi bi-play-fill"></i> Start Review
                        </button>
                    </form>
                @endif

                @if(in_array($application->status, ['submitted', 'under_review', 'changes_requested']))
                    {{-- Request corrections button --}}
                    <button type="button" onclick="document.getElementById('correctionModal').classList.remove('hidden')" class="px-3 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold transition border-0 cursor-pointer shadow-sm">
                        <i class="bi bi-chat-left-dots"></i> Request Corrections
                    </button>

                    {{-- Approve button --}}
                    <button type="button" onclick="document.getElementById('approveModal').classList.remove('hidden')" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition border-0 cursor-pointer shadow-sm">
                        <i class="bi bi-check-lg"></i> Approve Application
                    </button>

                    {{-- Reject button --}}
                    <button type="button" onclick="document.getElementById('rejectModal').classList.remove('hidden')" class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold transition border-0 cursor-pointer shadow-sm">
                        <i class="bi bi-x-lg"></i> Reject
                    </button>
                @endif
            </div>
        </div>

        {{-- Main Grid --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Left 2 Columns: Company & Campaign & Assets --}}
            <div class="lg:col-span-2 space-y-4">
                {{-- Company Profile --}}
                <div class="rounded-xl border bs p-4 bg-white shadow-sm space-y-3">
                    <h2 class="text-sm font-bold t1 uppercase tracking-wider text-slate-500 border-b border-slate-100 pb-2">
                        Company & Business Profile
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-slate-400 block font-medium">Company Name:</span>
                            <span class="font-bold text-slate-800 text-sm">{{ $application->company_name }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Legal Name:</span>
                            <span class="text-slate-800 font-semibold">{{ $application->company_legal_name ?: '—' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Industry / Category:</span>
                            <span class="text-slate-800 font-semibold">{{ $application->industry ?: '—' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Business Type:</span>
                            <span class="text-slate-800 font-semibold">{{ $application->company_type ?: '—' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Official Website:</span>
                            @if($application->company_website)
                                <a href="{{ $application->company_website }}" target="_blank" class="text-indigo-600 font-semibold hover:underline">
                                    {{ $application->company_website }} <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                                </a>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Official Email:</span>
                            <span class="text-slate-800 font-semibold">{{ $application->company_email ?: '—' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Phone & WhatsApp:</span>
                            <span class="text-slate-800 font-semibold">{{ $application->company_phone ?: $application->applicant_phone }} (WA: {{ $application->whatsapp_number ?: 'Same' }})</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Location / City:</span>
                            <span class="text-slate-800 font-semibold">{{ $application->city ?: '' }}, {{ $application->state ?: '' }} ({{ $application->country ?: 'India' }})</span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100">
                        <span class="text-slate-400 block font-medium text-xs mb-1">Brand Description:</span>
                        <div class="text-xs text-slate-700 bg-slate-50 p-2.5 rounded-lg border border-slate-100 leading-relaxed">
                            {{ $application->brand_description }}
                        </div>
                    </div>
                </div>

                {{-- Campaign Requirements --}}
                <div class="rounded-xl border bs p-4 bg-white shadow-sm space-y-3">
                    <h2 class="text-sm font-bold t1 uppercase tracking-wider text-slate-500 border-b border-slate-100 pb-2">
                        Advertising & Campaign Objectives
                    </h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-slate-400 block font-medium">Advertising Objective:</span>
                            <span class="font-semibold text-slate-800 capitalize">{{ str_replace('_', ' ', $application->advertising_objective ?: 'Brand Awareness') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Preferred Start Date:</span>
                            <span class="font-semibold text-slate-800">{{ $application->requested_start_date?->format('M d, Y') ?: 'Immediate' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Target Audience:</span>
                            <span class="text-slate-800">{{ $application->target_audience ?: 'All Community Members' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Target Locations:</span>
                            <span class="text-slate-800">
                                @if(is_array($application->target_locations) && count($application->target_locations) > 0)
                                    {{ implode(', ', $application->target_locations) }}
                                @else
                                    Pan-India / All
                                @endif
                            </span>
                        </div>
                    </div>
                    @if($application->additional_requirements)
                        <div class="pt-2 border-t border-slate-100 text-xs">
                            <span class="text-slate-400 block font-medium mb-1">Additional Requirements:</span>
                            <div class="text-slate-700 bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                {{ $application->additional_requirements }}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Uploaded Creatives & Brand Assets --}}
                <div class="rounded-xl border bs p-4 bg-white shadow-sm space-y-3">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                        <h2 class="text-sm font-bold t1 uppercase tracking-wider text-slate-500">
                            Uploaded Creatives & Assets ({{ $application->assets->count() }})
                        </h2>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @forelse($application->assets as $asset)
                            @php
                                $assetStatusBadge = match($asset->review_status) {
                                    'approved' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'rejected' => 'bg-rose-50 text-rose-700 border-rose-200',
                                    default => 'bg-amber-50 text-amber-700 border-amber-200',
                                };
                            @endphp
                            <div class="p-3 rounded-lg border bs bg-slate-50/50 space-y-2">
                                <div class="flex items-center justify-between">
                                    <span class="px-2 py-0.5 rounded uppercase font-bold text-[10px] bg-slate-200 text-slate-700">
                                        {{ $asset->asset_type }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-full border text-[11px] font-semibold {{ $assetStatusBadge }}">
                                        {{ ucfirst($asset->review_status) }}
                                    </span>
                                </div>

                                @if(in_array(strtolower(pathinfo($asset->storage_key, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif']))
                                    <div class="w-full h-36 bg-slate-200 rounded-lg overflow-hidden flex items-center justify-center border border-slate-200">
                                        <img src="{{ $asset->url }}" alt="{{ $asset->original_filename }}" class="max-h-full max-w-full object-contain">
                                    </div>
                                @else
                                    <div class="p-4 bg-slate-100 rounded-lg text-center text-xs text-slate-600">
                                        <i class="bi bi-file-earmark-pdf text-2xl text-rose-500 block mb-1"></i>
                                        {{ $asset->original_filename }}
                                    </div>
                                @endif

                                <div class="text-[11px] text-slate-500 truncate" title="{{ $asset->original_filename }}">
                                    {{ $asset->original_filename }} ({{ round($asset->file_size_bytes / 1024, 1) }} KB)
                                </div>

                                {{-- Asset Review Actions --}}
                                <div class="flex items-center justify-between pt-1 border-t border-slate-200">
                                    <a href="{{ $asset->url }}" target="_blank" class="text-indigo-600 text-xs hover:underline font-semibold">
                                        View Full <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                                    </a>
                                    <div class="flex items-center gap-1">
                                        <form method="POST" action="{{ route('admin.advertiser.applications.assets.review', [$application, $asset]) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="review_status" value="approved">
                                            <button type="submit" class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 text-[11px] font-semibold cursor-pointer">
                                                Approve
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.advertiser.applications.assets.review', [$application, $asset]) }}" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="review_status" value="rejected">
                                            <button type="submit" class="px-2 py-0.5 rounded bg-rose-50 text-rose-700 border border-rose-200 hover:bg-rose-100 text-[11px] font-semibold cursor-pointer">
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="col-span-2 p-6 text-center text-slate-400 text-xs">
                                <i class="bi bi-images text-2xl block mb-1"></i>
                                No assets uploaded with this application yet.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- Right Column: Package Snapshot, Identity, Status History --}}
            <div class="space-y-4">
                {{-- Package Snapshot Card --}}
                <div class="rounded-xl border bs p-4 bg-white shadow-sm space-y-3">
                    <h2 class="text-sm font-bold t1 uppercase tracking-wider text-slate-500 border-b border-slate-100 pb-2">
                        Package Snapshot
                    </h2>
                    <div class="p-3 rounded-lg bg-indigo-50/50 border border-indigo-100">
                        <div class="text-sm font-bold text-slate-900">{{ $application->requested_package_name ?: 'Standard Package' }}</div>
                        <div class="text-xl font-bold text-emerald-600 font-mono mt-1">
                            {{ $application->requested_currency ?: 'INR' }} {{ number_format((float)$application->requested_package_price, 2) }}
                        </div>
                        <div class="text-xs text-slate-600 mt-1">
                            Duration: <span class="font-bold">{{ $application->requested_duration_months ?: 1 }} Month(s)</span>
                        </div>
                    </div>
                </div>

                {{-- Applicant & Identity State --}}
                <div class="rounded-xl border bs p-4 bg-white shadow-sm space-y-3">
                    <h2 class="text-sm font-bold t1 uppercase tracking-wider text-slate-500 border-b border-slate-100 pb-2">
                        Applicant & Peer Identity
                    </h2>
                    <div class="space-y-2 text-xs">
                        <div>
                            <span class="text-slate-400 block font-medium">Contact Person:</span>
                            <span class="font-bold text-slate-800">{{ $application->applicant_name }}</span>
                            @if($application->contact_person_designation)
                                <span class="text-slate-500 text-[11px]">({{ $application->contact_person_designation }})</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Email Address:</span>
                            <span class="font-semibold text-slate-800">{{ $application->applicant_email }}</span>
                            @if($application->applicant_email_verified)
                                <span class="ms-1 px-1.5 py-0.2 rounded bg-emerald-50 text-emerald-700 text-[10px] font-bold">Verified</span>
                            @else
                                <span class="ms-1 px-1.5 py-0.2 rounded bg-amber-50 text-amber-700 text-[10px] font-bold">Unverified</span>
                            @endif
                        </div>
                        <div>
                            <span class="text-slate-400 block font-medium">Phone Number:</span>
                            <span class="font-semibold text-slate-800">{{ $application->applicant_phone }}</span>
                        </div>
                        <div class="pt-2 border-t border-slate-100">
                            <span class="text-slate-400 block font-medium">Linked Peer User:</span>
                            @if($application->user)
                                <span class="font-bold text-indigo-600">{{ $application->user->display_name ?: $application->user->first_name }}</span>
                                <div class="text-slate-400 font-mono text-[10px] truncate">{{ $application->user->id }}</div>
                            @else
                                <span class="text-slate-400 italic">Website Guest (No Peer account linked yet)</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Status & Audit Timeline --}}
                <div class="rounded-xl border bs p-4 bg-white shadow-sm space-y-3">
                    <h2 class="text-sm font-bold t1 uppercase tracking-wider text-slate-500 border-b border-slate-100 pb-2">
                        Audit & Status History
                    </h2>
                    <div class="space-y-3">
                        @forelse($application->statusHistory as $hist)
                            <div class="relative pl-4 border-l-2 border-indigo-200 text-xs space-y-0.5">
                                <div class="flex items-center justify-between">
                                    <span class="font-bold text-slate-800 uppercase text-[11px]">{{ str_replace('_', ' ', $hist->new_status) }}</span>
                                    <span class="text-[10px] text-slate-400">{{ $hist->created_at->format('M d, H:i') }}</span>
                                </div>
                                <div class="text-slate-500 text-[11px]">
                                    By: {{ $hist->user?->display_name ?: ucfirst($hist->actor_type) }}
                                </div>
                                @if($hist->notes)
                                    <div class="text-slate-700 bg-slate-50 p-1.5 rounded text-[11px] border border-slate-100 mt-1">
                                        {{ $hist->notes }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="text-slate-400 text-xs italic">No status transitions recorded yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Request Corrections Modal --}}
    <div id="correctionModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-5 space-y-4">
            <h3 class="text-base font-bold t1 flex items-center gap-2">
                <i class="bi bi-chat-left-dots text-purple-600"></i> Request Corrections from Applicant
            </h3>
            <p class="text-xs text-slate-500">The applicant will be notified with your feedback and prompted to update their details or re-upload creatives.</p>
            <form method="POST" action="{{ route('admin.advertiser.applications.request-corrections', $application) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Feedback / Action Required for Applicant <span class="text-rose-500">*</span></label>
                    <textarea name="feedback" rows="4" required placeholder="e.g. Please upload a higher resolution company logo (minimum 500x500px) and provide your valid GST/business address." class="w-full px-3 py-2 text-xs rounded-lg border bs focus:ring-1 focus:ring-purple-500 outline-none"></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('correctionModal').classList.add('hidden')" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs hover:bg-slate-50 cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-xs font-semibold transition border-0 cursor-pointer">Send Correction Request</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Approve Modal --}}
    <div id="approveModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-5 space-y-4">
            <h3 class="text-base font-bold t1 flex items-center gap-2">
                <i class="bi bi-check-circle text-emerald-600"></i> Approve Brand Partner Application
            </h3>
            <p class="text-xs text-slate-500">
                Approving this application will authorize the package and prompt the applicant to complete payment via Razorpay.
                <strong class="text-slate-800 block mt-1">Note: Approving does NOT publish the campaign until payment is verified.</strong>
            </p>
            <form method="POST" action="{{ route('admin.advertiser.applications.approve', $application) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Admin Internal Note</label>
                    <input type="text" name="admin_note" value="Company profile and creatives approved." class="w-full px-3 py-2 text-xs rounded-lg border bs outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Applicant Message</label>
                    <textarea name="applicant_message" rows="3" class="w-full px-3 py-2 text-xs rounded-lg border bs outline-none">Congratulations! Your Brand Partner application has been approved. Please sign in to Peers Global Unity to complete payment and campaign activation.</textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('approveModal').classList.add('hidden')" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs hover:bg-slate-50 cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold transition border-0 cursor-pointer">Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Reject Modal --}}
    <div id="rejectModal" class="hidden fixed inset-0 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-lg w-full p-5 space-y-4">
            <h3 class="text-base font-bold t1 flex items-center gap-2">
                <i class="bi bi-x-circle text-rose-600"></i> Reject Application
            </h3>
            <p class="text-xs text-slate-500">Please provide a clear reason for the rejection.</p>
            <form method="POST" action="{{ route('admin.advertiser.applications.reject', $application) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Reason for Rejection <span class="text-rose-500">*</span></label>
                    <textarea name="reason" rows="3" required placeholder="e.g. Ineligible business category or incomplete verification credentials." class="w-full px-3 py-2 text-xs rounded-lg border bs outline-none"></textarea>
                </div>
                <div>
                    <label class="block text-xs font-semibold t2 mb-1">Applicant-Visible Message</label>
                    <textarea name="applicant_message" rows="2" class="w-full px-3 py-2 text-xs rounded-lg border bs outline-none">Thank you for your interest. Unfortunately, your application does not meet the Brand Partner criteria at this time.</textarea>
                </div>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="px-3 py-1.5 text-xs font-semibold rounded-lg border bs hover:bg-slate-50 cursor-pointer">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold transition border-0 cursor-pointer">Reject Application</button>
                </div>
            </form>
        </div>
    </div>
@endsection
