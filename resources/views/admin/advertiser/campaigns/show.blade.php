@extends('admin.layouts.app')

@section('title', 'Campaign: ' . $campaign->name)

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
        {{-- Top Bar with Breadcrumbs & Actions --}}
        <div class="light rounded-xl border bs p-4 flex flex-col md:flex-row md:items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                    <a href="{{ route('admin.advertiser.campaigns.index') }}" class="hover:text-indigo-600 no-underline">Campaigns</a>
                    <span>/</span>
                    <span class="font-mono text-indigo-600 font-semibold">{{ $campaign->campaign_number }}</span>
                </div>
                <h1 class="text-xl font-black text-slate-900 flex items-center gap-2">
                    {{ $campaign->name }}
                    @php
                        $badgeClasses = match($campaign->status) {
                            'active' => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                            'pending_review' => 'bg-amber-100 text-amber-800 border-amber-200 animate-pulse',
                            'paused' => 'bg-slate-100 text-slate-800 border-slate-200',
                            'changes_requested' => 'bg-sky-100 text-sky-800 border-sky-200',
                            'rejected' => 'bg-rose-100 text-rose-800 border-rose-200',
                            'approved' => 'bg-blue-100 text-blue-800 border-blue-200',
                            default => 'bg-slate-100 text-slate-600 border-slate-200',
                        };
                    @endphp
                    <span class="text-xs px-2.5 py-0.5 rounded-full font-bold border {{ $badgeClasses }}">
                        {{ ucfirst(str_replace('_', ' ', $campaign->status)) }}
                    </span>
                </h1>
            </div>

            {{-- Action Buttons --}}
            <div class="flex flex-wrap items-center gap-2">
                @if(in_array($campaign->status, ['pending_review', 'changes_requested', 'draft']))
                    {{-- Approve Modal Trigger --}}
                    <button type="button" onclick="document.getElementById('approveModal').classList.remove('hidden')" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                        <i class="bi bi-check2-circle"></i> Approve & Publish
                    </button>

                    {{-- Request Changes Modal Trigger --}}
                    <button type="button" onclick="document.getElementById('changesModal').classList.remove('hidden')" class="px-3 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                        <i class="bi bi-pencil-square"></i> Request Corrections
                    </button>

                    {{-- Reject Modal Trigger --}}
                    <button type="button" onclick="document.getElementById('rejectModal').classList.remove('hidden')" class="px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                        <i class="bi bi-x-circle"></i> Reject
                    </button>
                @endif

                @if($campaign->status === 'approved')
                    <form method="POST" action="{{ route('admin.advertiser.campaigns.publish', $campaign->id) }}">
                        @csrf
                        <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                            <i class="bi bi-broadcast"></i> Publish Now
                        </button>
                    </form>
                @endif

                @if($campaign->status === 'active')
                    <form method="POST" action="{{ route('admin.advertiser.campaigns.pause', $campaign->id) }}" onsubmit="return confirm('Are you sure you want to pause delivery of this campaign?');">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-800 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                            <i class="bi bi-pause-circle"></i> Pause Delivery
                        </button>
                    </form>
                @endif

                @if($campaign->status === 'paused')
                    <form method="POST" action="{{ route('admin.advertiser.campaigns.resume', $campaign->id) }}">
                        @csrf
                        <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition flex items-center gap-1.5">
                            <i class="bi bi-play-circle"></i> Resume Delivery
                        </button>
                    </form>
                @endif
            </div>
        </div>

        {{-- Metrics Highlights --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="light rounded-xl border bs p-3.5 bg-gradient-to-br from-indigo-50/50 to-white">
                <span class="text-[10px] uppercase font-bold text-slate-500">Impressions / Views</span>
                <div class="text-2xl font-black text-indigo-700 mt-1">{{ number_format($analytics['views']) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Unique: {{ number_format($analytics['unique_views']) }}</div>
            </div>
            <div class="light rounded-xl border bs p-3.5 bg-gradient-to-br from-emerald-50/50 to-white">
                <span class="text-[10px] uppercase font-bold text-slate-500">Clicks</span>
                <div class="text-2xl font-black text-emerald-700 mt-1">{{ number_format($analytics['clicks']) }}</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Unique: {{ number_format($analytics['unique_clicks']) }}</div>
            </div>
            <div class="light rounded-xl border bs p-3.5 bg-gradient-to-br from-purple-50/50 to-white">
                <span class="text-[10px] uppercase font-bold text-slate-500">Click-Through Rate (CTR)</span>
                <div class="text-2xl font-black text-purple-700 mt-1">{{ $analytics['ctr_percent'] }}%</div>
                <div class="text-[10px] text-slate-400 mt-0.5">Unique Clicks / Unique Views</div>
            </div>
            <div class="light rounded-xl border bs p-3.5 bg-gradient-to-br from-amber-50/50 to-white">
                <span class="text-[10px] uppercase font-bold text-slate-500">Subscription Status</span>
                <div class="text-lg font-black text-slate-800 mt-1.5 capitalize">
                    {{ str_replace('_', ' ', $campaign->subscription?->status ?? 'Active') }}
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">
                    Valid To: {{ $campaign->subscription?->expires_at ? $campaign->subscription->expires_at->format('d M Y') : 'Ongoing' }}
                </div>
            </div>
        </div>

        {{-- Main Grid: Left Details & Right Previews/History --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Left Column (2 Cols): Campaign Info & Destination --}}
            <div class="lg:col-span-2 space-y-4">
                {{-- Campaign Details Card --}}
                <div class="light rounded-xl border bs p-4 space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center gap-2">
                        <i class="bi bi-info-circle text-indigo-600"></i> Campaign Specification
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[11px]">Advertising Objective:</span>
                            <span class="font-semibold text-slate-800 capitalize">{{ str_replace('_', ' ', $campaign->objective ?? 'brand_awareness') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">Call-To-Action (CTA):</span>
                            <span class="font-semibold text-indigo-700">{{ $campaign->cta_text ?: 'Learn More' }}</span>
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-slate-400 block text-[11px]">Destination URL:</span>
                            @if($campaign->destination_url)
                                <a href="{{ $campaign->destination_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-indigo-600 hover:underline break-all inline-flex items-center gap-1">
                                    {{ $campaign->destination_url }} <i class="bi bi-box-arrow-up-right text-[10px]"></i>
                                </a>
                            @else
                                <span class="text-slate-400">None specified</span>
                            @endif
                        </div>
                        <div class="sm:col-span-2">
                            <span class="text-slate-400 block text-[11px]">Campaign Description / Ad Copy:</span>
                            <p class="text-slate-700 mt-1 leading-relaxed bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                                {{ $campaign->description ?: 'No ad copy provided.' }}
                            </p>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">Target Audience:</span>
                            <span class="text-slate-700">{{ $campaign->target_audience ?: 'All Peers Global Unity Members' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">Target Locations:</span>
                            <span class="text-slate-700">
                                @if(!empty($campaign->target_locations) && is_array($campaign->target_locations))
                                    {{ implode(', ', $campaign->target_locations) }}
                                @else
                                    Pan-India / All Regions
                                @endif
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Advertiser Application & Subscription Card --}}
                <div class="light rounded-xl border bs p-4 space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <i class="bi bi-building text-indigo-600"></i> Advertiser & Billing Linkage
                        </span>
                        @if($campaign->application_id)
                            <a href="{{ route('admin.advertiser.applications.show', $campaign->application_id) }}" class="text-xs text-indigo-600 hover:underline">
                                View Application &rarr;
                            </a>
                        @endif
                    </h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div>
                            <span class="text-slate-400 block text-[11px]">Company / Brand:</span>
                            <span class="font-bold text-slate-900">{{ $campaign->application?->company_name ?? 'N/A' }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">Applicant:</span>
                            <span class="text-slate-800">{{ $campaign->application?->applicant_name ?? $campaign->user?->name }} ({{ $campaign->application?->applicant_phone ?? 'N/A' }})</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">Package Enrolled:</span>
                            <span class="font-semibold text-slate-800">{{ $campaign->subscription?->package_name_snapshot ?? ($campaign->subscription?->package?->name ?? 'Standard Package') }}</span>
                        </div>
                        <div>
                            <span class="text-slate-400 block text-[11px]">Subscription Validity:</span>
                            <span class="text-slate-700">
                                {{ $campaign->subscription?->starts_at ? $campaign->subscription->starts_at->format('d M Y') : 'N/A' }} &mdash; 
                                {{ $campaign->subscription?->expires_at ? $campaign->subscription->expires_at->format('d M Y') : 'N/A' }}
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Linked Ads in Ads Engine --}}
                <div class="light rounded-xl border bs p-4 space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center gap-2">
                        <i class="bi bi-link-45deg text-indigo-600"></i> Linked Ads in Feed Delivery Engine
                    </h2>
                    @if($campaign->campaignAds->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($campaign->campaignAds as $link)
                                <div class="p-3 rounded-lg border border-slate-100 bg-slate-50 flex items-center justify-between text-xs">
                                    <div>
                                        <div class="font-bold text-slate-800">{{ $link->ad?->title ?? 'Ad Record' }}</div>
                                        <div class="text-[10px] text-slate-400">
                                            Placement: <span class="text-indigo-600 font-semibold">{{ $link->ad?->placement ?? 'feed' }}</span> | Role: {{ $link->creative_role }}
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold {{ $link->ad?->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700' }}">
                                            {{ $link->ad?->is_active ? 'Delivering in Feed' : 'Inactive' }}
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-400 italic">No ad records linked yet. Upon approval, an ad record will be generated automatically in the feed engine.</p>
                    @endif
                </div>
            </div>

            {{-- Right Column (1 Col): Creatives & History --}}
            <div class="space-y-4">
                {{-- Creative Assets Preview --}}
                <div class="light rounded-xl border bs p-4 space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center gap-2">
                        <i class="bi bi-images text-indigo-600"></i> Creative Assets
                    </h2>
                    @if($campaign->application && $campaign->application->assets->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($campaign->application->assets as $asset)
                                <div class="border rounded-lg p-2 bg-slate-50">
                                    <div class="flex items-center justify-between text-[11px] mb-1.5">
                                        <span class="font-semibold text-slate-700 capitalize">{{ str_replace('_', ' ', $asset->asset_type) }}</span>
                                        <span class="text-[10px] text-slate-400">{{ round($asset->file_size_bytes / 1024) }} KB</span>
                                    </div>
                                    @if(str_starts_with((string)$asset->mime_type, 'image/'))
                                        <img src="{{ url('/api/v1/files/' . ltrim($asset->storage_key, '/')) }}" alt="{{ $asset->alt_text ?: 'Ad creative' }}" class="w-full h-32 object-cover rounded-md border border-slate-200 bg-white" onerror="this.onerror=null; this.src='https://placehold.co/400x200?text=Preview+Unavailable'">
                                    @else
                                        <div class="h-16 flex items-center justify-center bg-white rounded border text-slate-400 text-xs">
                                            <i class="bi bi-file-earmark-text me-1"></i> {{ $asset->original_filename }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-400 italic">No creative files found for this campaign's application.</p>
                    @endif
                </div>

                {{-- Status History Timeline --}}
                <div class="light rounded-xl border bs p-4 space-y-3">
                    <h2 class="text-sm font-bold text-slate-900 border-b border-slate-100 pb-2 flex items-center gap-2">
                        <i class="bi bi-clock-history text-indigo-600"></i> Status Timeline
                    </h2>
                    <div class="space-y-3">
                        @forelse($campaign->statusHistory as $hist)
                            <div class="text-xs border-l-2 border-indigo-400 pl-3 relative">
                                <div class="font-semibold text-slate-800 flex items-center justify-between">
                                    <span>{{ ucfirst(str_replace('_', ' ', $hist->new_status)) }}</span>
                                    <span class="text-[10px] text-slate-400">{{ $hist->created_at?->format('d M, H:i') }}</span>
                                </div>
                                <div class="text-[11px] text-slate-500 mt-0.5">
                                    By: {{ $hist->user?->name ?? ucfirst($hist->actor_type) }}
                                </div>
                                @if($hist->notes)
                                    <p class="text-[11px] text-slate-600 bg-slate-50 p-1.5 rounded mt-1">
                                        {{ $hist->notes }}
                                    </p>
                                @endif
                            </div>
                        @empty
                            <p class="text-xs text-slate-400 italic">No history records logged yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Review Decision Modals --}}
    {{-- 1. Approve Modal --}}
    <div id="approveModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-5 shadow-2xl border border-slate-100 space-y-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="bi bi-check-circle-fill text-emerald-600"></i> Approve & Publish Campaign
            </h3>
            <p class="text-xs text-slate-500">
                Approving this campaign will immediately activate it and publish an advertisement into the Peers Global Unity feed engine.
            </p>
            <form method="POST" action="{{ route('admin.advertiser.campaigns.review', $campaign->id) }}">
                @csrf
                <input type="hidden" name="decision" value="approved">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Approval Notes (Optional):</label>
                    <textarea name="notes" rows="3" class="w-full text-xs rounded-lg border border-slate-200 p-2 focus:outline-emerald-500" placeholder="e.g. Creative complies with community advertising guidelines."></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <button type="button" onclick="document.getElementById('approveModal').classList.add('hidden')" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold">Confirm Approval</button>
                </div>
            </form>
        </div>
    </div>

    {{-- 2. Request Corrections Modal --}}
    <div id="changesModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-5 shadow-2xl border border-slate-100 space-y-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="bi bi-pencil-square text-sky-600"></i> Request Corrections
            </h3>
            <p class="text-xs text-slate-500">
                Inform the advertiser what changes or replacement creatives are needed before the campaign can go live.
            </p>
            <form method="POST" action="{{ route('admin.advertiser.campaigns.review', $campaign->id) }}">
                @csrf
                <input type="hidden" name="decision" value="changes_requested">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Feedback to Advertiser (Required):</label>
                    <textarea name="notes" required rows="3" class="w-full text-xs rounded-lg border border-slate-200 p-2 focus:outline-sky-500" placeholder="e.g. Please replace the banner with high-resolution dimensions (1200x628)."></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <button type="button" onclick="document.getElementById('changesModal').classList.add('hidden')" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold">Send Correction Request</button>
                </div>
            </form>
        </div>
    </div>

    {{-- 3. Reject Modal --}}
    <div id="rejectModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-md w-full p-5 shadow-2xl border border-slate-100 space-y-4">
            <h3 class="text-base font-bold text-slate-900 flex items-center gap-2">
                <i class="bi bi-x-circle-fill text-rose-600"></i> Reject Campaign
            </h3>
            <p class="text-xs text-slate-500">
                Disapprove this campaign. Provide the reason so the advertiser understands why.
            </p>
            <form method="POST" action="{{ route('admin.advertiser.campaigns.review', $campaign->id) }}">
                @csrf
                <input type="hidden" name="decision" value="rejected">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Rejection Reason (Required):</label>
                    <textarea name="notes" required rows="3" class="w-full text-xs rounded-lg border border-slate-200 p-2 focus:outline-rose-500" placeholder="e.g. The destination landing page violates advertising safety policies."></textarea>
                </div>
                <div class="flex items-center justify-end gap-2 mt-4">
                    <button type="button" onclick="document.getElementById('rejectModal').classList.add('hidden')" class="px-3 py-1.5 rounded-lg border border-slate-200 text-slate-600 text-xs font-semibold hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
@endsection
