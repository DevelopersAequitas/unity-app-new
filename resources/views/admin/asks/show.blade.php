@extends('admin.layouts.app')

@section('title', 'Ask Details: ' . $ask->title)

@include('admin.partials.grid-head')

@push('styles')
<style>
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

        $user = $ask->user;
        $creatorName = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : '—';
        if ($creatorName === '' && $user) {
            $creatorName = $user->display_name ?: $user->name ?: 'Peer';
        }
        $creatorAvatar = $getAvatarUrl($user);
        $creatorInitials = $getInitials($creatorName);
        $creatorBg = $getAvatarBg($creatorName);
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

    <div class="space-y-4">
        <!-- Top Navigation & Action Bar -->
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.asks.index') }}" class="p-2 text-slate-500 hover:text-slate-800 hover:bg-slate-100 rounded-lg transition" title="Back to list">
                    <i class="bi bi-arrow-left text-lg"></i>
                </a>
                <div>
                    <div class="flex items-center gap-2">
                        <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold {{ match(strtolower($ask->flow?->code ?? '')) { 'collaborate' => 'flow-badge-collaborate', 'referral' => 'flow-badge-referral', 'help' => 'flow-badge-help', default => 'flow-badge-default' } }}">
                            {{ $ask->flow?->name ?? 'Custom Flow' }}
                        </span>
                        @if($ask->type)
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-700 border border-slate-200">
                                {{ $ask->type->name }}
                            </span>
                        @endif
                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ match(strtolower($ask->status)) { 'published' => 'status-badge-published', 'closed' => 'status-badge-closed', 'cancelled' => 'status-badge-cancelled', 'expired' => 'status-badge-expired', default => 'status-badge-draft' } }}">
                            {{ ucfirst($ask->status) }}
                        </span>
                    </div>
                    <h1 class="text-xl font-bold text-slate-800 m-0 mt-1">{{ $ask->title }}</h1>
                    <div class="text-[11px] text-slate-400 font-mono mt-0.5">UUID: {{ $ask->id }}</div>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <!-- Change Status Trigger -->
                <button type="button" onclick="document.getElementById('statusModal').classList.remove('hidden')" class="px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition shadow-sm flex items-center gap-1.5">
                    <i class="bi bi-arrow-repeat"></i> Change Status
                </button>

                <!-- Delete -->
                <form method="POST" action="{{ route('admin.asks.destroy', $ask->id) }}" onsubmit="return confirm('Are you sure you want to delete this Ask?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3 py-1.5 text-xs font-semibold text-rose-600 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg transition flex items-center gap-1.5">
                        <i class="bi bi-trash"></i> Delete Ask
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Layout (2 Columns) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            
            <!-- Left Column: Creator Profile & Brief Config (1 Col) -->
            <div class="space-y-4">
                
                <!-- Creator Profile Card -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-3">Creator / Peer Profile</h3>
                    <div class="flex items-center gap-3 pb-3 border-b border-slate-100">
                        @if($creatorAvatar)
                            <img src="{{ $creatorAvatar }}" alt="{{ $creatorName }}" class="w-12 h-12 rounded-full object-cover border border-slate-200 shadow-sm" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-12 h-12 rounded-full flex items-center justify-center text-white text-base font-bold\' style=\'background-color: {{ $creatorBg }}\'>{{ $creatorInitials }}</div>'">
                        @else
                            <div class="w-12 h-12 rounded-full flex items-center justify-center text-white text-base font-bold shadow-sm" style="background-color: {{ $creatorBg }}">
                                {{ $creatorInitials }}
                            </div>
                        @endif
                        <div class="overflow-hidden">
                            <h4 class="font-bold text-sm text-slate-800 m-0 truncate">{{ $creatorName }}</h4>
                            <p class="text-xs text-slate-500 m-0 truncate">{{ $user?->company_name ?: 'Individual Peer' }}</p>
                            @if($user?->city)
                                <div class="text-[11px] text-slate-400 mt-0.5 flex items-center gap-1">
                                    <i class="bi bi-geo-alt"></i> {{ $user->city->name ?? (is_string($user->city) ? $user->city : '—') }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="pt-3 space-y-2 text-xs">
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Email:</span>
                            <span class="font-medium truncate max-w-[170px]" title="{{ $user?->email }}">{{ $user?->email ?? '—' }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Phone:</span>
                            <span class="font-medium">{{ $user?->phone ?: ($user?->mobile ?? '—') }}</span>
                        </div>
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Industry / Category:</span>
                            <span class="font-medium text-right truncate max-w-[160px]">{{ $user?->level4Category?->name ?? '—' }}</span>
                        </div>
                        @if($user)
                            <div class="pt-2">
                                <a href="{{ route('admin.users.show', $user->id) }}" class="w-full py-1.5 px-3 block text-center text-xs font-semibold rounded-lg bg-slate-50 text-indigo-600 hover:bg-slate-100 transition border border-slate-200 no-underline">
                                    View Peer In Admin
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Requirement Details Card -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm space-y-3">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider m-0">Ask Overview</h3>
                    
                    @if($ask->description)
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-400 mb-0.5">Description</label>
                            <p class="text-xs text-slate-700 bg-slate-50 p-2.5 rounded-lg border border-slate-100 leading-relaxed whitespace-pre-line m-0">{{ $ask->description }}</p>
                        </div>
                    @endif

                    <div class="space-y-2 text-xs pt-1">
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Visibility:</span>
                            <span class="font-semibold text-slate-700 capitalize px-2 py-0.5 rounded bg-slate-100 border border-slate-200">{{ $ask->visibility_type }}</span>
                        </div>
                        @if($ask->district)
                            <div class="flex justify-between items-center">
                                <span class="text-slate-400">District:</span>
                                <span class="font-medium text-slate-700">{{ $ask->district->name ?? $ask->district_id }}</span>
                            </div>
                        @endif
                        @if($ask->circle)
                            <div class="flex justify-between items-center">
                                <span class="text-slate-400">Circle:</span>
                                <span class="font-medium text-slate-700">{{ $ask->circle->name ?? $ask->circle_id }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Timeline Broadcast:</span>
                            <span class="font-medium {{ $ask->post_to_timeline ? 'text-emerald-600 font-semibold' : 'text-slate-400' }}">
                                {{ $ask->post_to_timeline ? 'Yes (Public Post)' : 'No (Direct Only)' }}
                            </span>
                        </div>
                        @if($ask->timelineLink?->post)
                            <div class="flex justify-between items-center">
                                <span class="text-slate-400">Linked Post:</span>
                                <a href="{{ route('admin.posts.show', $ask->timelineLink->post_id) }}" class="text-indigo-600 font-semibold hover:underline">
                                    #{{ substr($ask->timelineLink->post_id, 0, 8) }}
                                </a>
                            </div>
                        @endif
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Created At:</span>
                            <span class="text-slate-600 font-mono">{{ $ask->created_at->format('d M Y, H:i') }}</span>
                        </div>
                        @if($ask->published_at)
                            <div class="flex justify-between items-center">
                                <span class="text-slate-400">Published At:</span>
                                <span class="text-slate-600 font-mono">{{ $ask->published_at->format('d M Y, H:i') }}</span>
                            </div>
                        @endif
                        @if($ask->closed_at)
                            <div class="flex justify-between items-center">
                                <span class="text-slate-400">Closed At:</span>
                                <span class="text-slate-600 font-mono">{{ $ask->closed_at->format('d M Y, H:i') }}</span>
                            </div>
                        @endif
                        @if($ask->expires_at)
                            <div class="flex justify-between items-center">
                                <span class="text-slate-400">Expires At:</span>
                                <span class="text-slate-600 font-mono">{{ $ask->expires_at->format('d M Y, H:i') }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Dynamic Form Answers -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Configured Options &amp; Criteria</h3>
                    @if($ask->answers->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($ask->answers as $answer)
                                <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-100">
                                    <div class="text-[10px] font-semibold uppercase text-slate-400 tracking-wider">
                                        {{ $answer->optionGroup?->name ?: ($answer->option?->group?->name ?? 'Option Group') }}
                                    </div>
                                    <div class="text-xs font-semibold text-slate-800 mt-0.5">
                                        {{ $answer->option?->label ?? $answer->option?->code ?? 'Selected' }}
                                    </div>
                                    @if($answer->custom_value)
                                        <div class="text-xs text-slate-600 mt-1 italic">
                                            "{{ $answer->custom_value }}"
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-400 m-0">No dynamic answers registered for this ask.</p>
                    @endif
                </div>

            </div>

            <!-- Right Column: Matches, Responses & Audit Trail (2 Cols) -->
            <div class="lg:col-span-2 space-y-4">
                
                <!-- Section 1: Algorithmic Matched Peers -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-purple-600"></span>
                            <h3 class="font-bold text-sm text-slate-800 m-0">Algorithmic Matched Peers</h3>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-purple-100 text-purple-700">
                                {{ $ask->matches->count() }}
                            </span>
                        </div>
                    </div>

                    @if($ask->matches->isNotEmpty())
                        <div class="overflow-x-auto rounded-lg border border-slate-100">
                            <table class="w-full text-left text-xs text-slate-700 border-collapse">
                                <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase text-[10px]">
                                    <tr>
                                        <th class="py-2.5 px-3">Matched Peer</th>
                                        <th class="py-2.5 px-3 text-center">Score</th>
                                        <th class="py-2.5 px-3">Match Type</th>
                                        <th class="py-2.5 px-3 text-center">Status</th>
                                        <th class="py-2.5 px-3">Matched Date</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($ask->matches as $match)
                                        @php
                                            $mUser = $match->matchedUser;
                                            $mName = $mUser ? trim(($mUser->first_name ?? '') . ' ' . ($mUser->last_name ?? '')) : '—';
                                            if ($mName === '' && $mUser) $mName = $mUser->display_name ?: $mUser->name ?: 'Peer';
                                            $mAvatar = $getAvatarUrl($mUser);
                                            $mInitials = $getInitials($mName);
                                            $mBg = $getAvatarBg($mName);
                                            $score = (int) $match->match_score;
                                        @endphp
                                        <tr class="hover:bg-slate-50/60 transition">
                                            <td class="py-2.5 px-3 whitespace-nowrap">
                                                <div class="flex items-center gap-2">
                                                    @if($mAvatar)
                                                        <img src="{{ $mAvatar }}" alt="{{ $mName }}" class="w-7 h-7 rounded-full object-cover border border-slate-200" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold\' style=\'background-color: {{ $mBg }}\'>{{ $mInitials }}</div>'">
                                                    @else
                                                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-white text-[10px] font-bold" style="background-color: {{ $mBg }}">
                                                            {{ $mInitials }}
                                                        </div>
                                                    @endif
                                                    <div>
                                                        <div class="font-semibold text-slate-800">{{ $mName }}</div>
                                                        <div class="text-[10px] text-slate-400">{{ $mUser?->company_name ?: ($mUser?->city?->name ?? '—') }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                                <span class="px-2 py-0.5 rounded text-[11px] font-bold {{ $score >= 80 ? 'bg-emerald-100 text-emerald-800' : ($score >= 50 ? 'bg-indigo-100 text-indigo-800' : 'bg-slate-100 text-slate-600') }}">
                                                    {{ $score }}%
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 whitespace-nowrap capitalize text-slate-600">
                                                {{ $match->match_type }}
                                            </td>
                                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                                <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $match->status === 'accepted' ? 'bg-emerald-100 text-emerald-700' : ($match->status === 'passed' ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-600') }}">
                                                    {{ ucfirst($match->status) }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 px-3 whitespace-nowrap text-slate-400 font-mono text-[11px]">
                                                {{ $match->created_at->format('d M Y, H:i') }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="p-6 text-center text-slate-400 bg-slate-50 rounded-lg border border-dashed border-slate-200">
                            <i class="bi bi-people text-2xl block mb-1 text-slate-300"></i>
                            <span class="text-xs">No algorithmic peer matches generated yet.</span>
                        </div>
                    @endif
                </div>

                <!-- Section 2: Peer Responses -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
                            <h3 class="font-bold text-sm text-slate-800 m-0">Peer Responses &amp; Introductions</h3>
                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                {{ $ask->responses->count() }}
                            </span>
                        </div>
                    </div>

                    @if($ask->responses->isNotEmpty())
                        <div class="space-y-3">
                            @foreach($ask->responses as $response)
                                @php
                                    $respUser = $response->responder;
                                    $respName = $respUser ? trim(($respUser->first_name ?? '') . ' ' . ($respUser->last_name ?? '')) : '—';
                                    if ($respName === '' && $respUser) $respName = $respUser->display_name ?: $respUser->name ?: 'Peer';
                                    $respAvatar = $getAvatarUrl($respUser);
                                    $respInitials = $getInitials($respName);
                                    $respBg = $getAvatarBg($respName);
                                @endphp
                                <div class="p-3 rounded-lg border border-slate-200 bg-slate-50/50 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2.5">
                                            @if($respAvatar)
                                                <img src="{{ $respAvatar }}" alt="{{ $respName }}" class="w-8 h-8 rounded-full object-cover border border-slate-200" onerror="this.onerror=null; this.parentElement.innerHTML='<div class=\'w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold\' style=\'background-color: {{ $respBg }}\'>{{ $respInitials }}</div>'">
                                            @else
                                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background-color: {{ $respBg }}">
                                                    {{ $respInitials }}
                                                </div>
                                            @endif
                                            <div>
                                                <div class="font-semibold text-xs text-slate-800">{{ $respName }}</div>
                                                <div class="text-[10px] text-slate-400">{{ $respUser?->company_name ?: ($respUser?->city?->name ?? '—') }}</div>
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $response->response_type === 'direct' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                                {{ $response->response_type === 'direct' ? 'Direct Help' : 'Introduced Connection' }}
                                            </span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $response->status === 'accepted' ? 'bg-emerald-100 text-emerald-700' : ($response->status === 'declined' ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-700') }}">
                                                {{ ucfirst($response->status) }}
                                            </span>
                                        </div>
                                    </div>

                                    @if($response->message)
                                        <div class="text-xs text-slate-700 bg-white p-2.5 rounded border border-slate-100">
                                            "{{ $response->message }}"
                                        </div>
                                    @endif

                                    <!-- If introduced contact -->
                                    @if($response->contact)
                                        <div class="p-2.5 rounded bg-purple-50/60 border border-purple-100 text-xs">
                                            <div class="text-[10px] font-bold uppercase text-purple-600 tracking-wider mb-1">Introduced Connection Details</div>
                                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                                <div><span class="text-slate-400">Name:</span> <strong class="text-slate-700">{{ $response->contact->name }}</strong></div>
                                                <div><span class="text-slate-400">Email:</span> <span class="text-slate-700">{{ $response->contact->email ?: '—' }}</span></div>
                                                <div><span class="text-slate-400">Phone:</span> <span class="text-slate-700">{{ $response->contact->phone ?: '—' }}</span></div>
                                            </div>
                                            @if($response->contact->notes)
                                                <div class="text-[11px] text-slate-500 mt-1 italic">Notes: {{ $response->contact->notes }}</div>
                                            @endif
                                        </div>
                                    @endif

                                    <div class="text-[10px] text-slate-400 flex items-center justify-end font-mono">
                                        Received: {{ $response->created_at->format('d M Y, H:i') }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-6 text-center text-slate-400 bg-slate-50 rounded-lg border border-dashed border-slate-200">
                            <i class="bi bi-chat-dots text-2xl block mb-1 text-slate-300"></i>
                            <span class="text-xs">No responses received from peers yet.</span>
                        </div>
                    @endif
                </div>

                <!-- Section 3: Status History / Audit Trail -->
                <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-2.5 h-2.5 rounded-full bg-slate-500"></span>
                        <h3 class="font-bold text-sm text-slate-800 m-0">Lifecycle Audit Trail</h3>
                    </div>

                    @if($ask->statusHistories->isNotEmpty())
                        <div class="space-y-2">
                            @foreach($ask->statusHistories as $history)
                                @php
                                    $actor = $history->changedBy;
                                    $actorName = $actor ? trim(($actor->first_name ?? '') . ' ' . ($actor->last_name ?? '')) : 'System / Admin';
                                    if ($actorName === '' && $actor) $actorName = $actor->display_name ?: $actor->name ?: 'Admin';
                                @endphp
                                <div class="flex items-start gap-3 p-2.5 rounded-lg bg-slate-50 border border-slate-100 text-xs">
                                    <div class="w-6 h-6 rounded-full bg-indigo-50 text-indigo-600 flex items-center justify-center text-xs flex-none mt-0.5">
                                        <i class="bi bi-clock-history"></i>
                                    </div>
                                    <div class="flex-1">
                                        <div class="flex items-center justify-between">
                                            <div class="font-semibold text-slate-800">
                                                Status changed: <span class="text-slate-500">{{ $history->old_status ?: 'Initial' }}</span> &rarr; <span class="text-indigo-600 font-bold">{{ $history->new_status }}</span>
                                            </div>
                                            <span class="text-[10px] text-slate-400 font-mono">{{ $history->created_at->format('d M Y, H:i') }}</span>
                                        </div>
                                        <div class="text-[11px] text-slate-500 mt-0.5">
                                            By: <span class="font-medium text-slate-700">{{ $actorName }}</span>
                                            @if($history->reason)
                                                &mdash; <span class="italic text-slate-600">"{{ $history->reason }}"</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-slate-400 m-0">No status changes recorded in audit history.</p>
                    @endif
                </div>

            </div>
        </div>
    </div>

    <!-- Status Change Modal -->
    <div id="statusModal" class="fixed inset-0 z-50 hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                <h3 class="font-bold text-sm text-slate-800 m-0">Change Ask Status</h3>
                <button type="button" onclick="document.getElementById('statusModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600 border-0 bg-transparent text-lg">&times;</button>
            </div>
            <form method="POST" action="{{ route('admin.asks.status', $ask->id) }}" class="p-5 space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">New Status</label>
                    <select name="status" required class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white">
                        <option value="draft" {{ $ask->status === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="published" {{ $ask->status === 'published' ? 'selected' : '' }}>Published</option>
                        <option value="closed" {{ $ask->status === 'closed' ? 'selected' : '' }}>Closed</option>
                        <option value="cancelled" {{ $ask->status === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        <option value="expired" {{ $ask->status === 'expired' ? 'selected' : '' }}>Expired</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-600 mb-1">Reason / Audit Note</label>
                    <textarea name="reason" rows="3" placeholder="Enter reason for status change (stored in audit log)..." class="w-full px-3 py-2 text-xs rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-indigo-500 bg-white"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-100">
                    <button type="button" onclick="document.getElementById('statusModal').classList.add('hidden')" class="px-3 py-1.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition">Cancel</button>
                    <button type="submit" class="px-4 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition shadow-sm">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
@endsection
