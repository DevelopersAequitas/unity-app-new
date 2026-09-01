@extends('admin.layouts.app')

@section('title', 'Pending Impacts')

@include('admin.partials.grid-head')

@section('content')
    @php
        $displayUser = function ($user): string {
            if (! $user) {
                return '-';
            }

            if (! empty($user->display_name)) {
                return (string) $user->display_name;
            }

            $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));

            return $name !== '' ? $name : ((string) ($user->email ?? '-'));
        };

        $getInitials = function (?string $name): string {
            if (! $name) return 'P';
            $words = explode(' ', trim($name));
            $initials = '';
            foreach ($words as $w) {
                if (! empty($w)) $initials .= strtoupper(substr($w, 0, 1));
            }
            return substr($initials, 0, 2) ?: 'P';
        };

        $getAvatarBg = function (?string $name): string {
            if (! $name) return '#6366f1';
            $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
            $hash = crc32($name);
            return $colors[abs($hash) % count($colors)];
        };
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Pending Impact Requests</h2>
                <p class="text-xs t3 m-0 mt-0.5">Review and approve peer life impact submissions.</p>
            </div>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200">Total Pending: {{ number_format($impacts->total()) }}</span>
        </div>

        @if (session('success'))
            <div class="alert alert-success mb-4">{{ session('success') }}</div>
        @endif

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Action</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Impacted Peer</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Impacted Company</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Impacted City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submitted By</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submitter Company</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submitter City</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Story to Share</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Additional Remarks</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right" style="width: 320px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse($impacts as $impact)
                            @php
                                $impacted = $impact->impactedPeer;
                                $impactedName = $impacted ? ($impacted->display_name ?: trim(($impacted->first_name ?? '') . ' ' . ($impacted->last_name ?? ''))) : '—';
                                $impactedCompany = $impacted->company_name ?? $impacted->company ?? $impacted->business_name ?? '—';
                                $impactedCity = $impacted->city ?? '—';

                                $submitter = $impact->user;
                                $submitterName = $submitter ? ($submitter->display_name ?: trim(($submitter->first_name ?? '') . ' ' . ($submitter->last_name ?? ''))) : '—';
                                $submitterCompany = $submitter->company_name ?? $submitter->company ?? $submitter->business_name ?? '—';
                                $submitterCity = $submitter->city ?? '—';
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($impact->impact_date)->toDateString() }}</td>
                                <td class="px-3 py-2.5 text-xs max-w-[160px] truncate font-medium t1" title="{{ $impact->action }}">
                                    {{ $impact->action }}
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if ($impacted)
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($impactedName) }}">
                                                {{ $getInitials($impactedName) }}
                                            </div>
                                            <a href="#" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $impacted->id }}', event);" class="text-indigo-600 font-semibold hover:underline no-underline">
                                                {{ $impactedName }}
                                            </a>
                                        </div>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $impactedCompany }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $impactedCity }}</td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if ($submitter)
                                        <div class="flex items-center gap-2">
                                            <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($submitterName) }}">
                                                {{ $getInitials($submitterName) }}
                                            </div>
                                            <a href="#" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $submitter->id }}', event);" class="text-indigo-600 font-semibold hover:underline no-underline">
                                                {{ $submitterName }}
                                            </a>
                                        </div>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $submitterCompany }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $submitterCity }}</td>
                                <td class="px-3 py-2.5 text-xs t2 max-w-[220px] truncate" title="{{ (string) $impact->story_to_share }}">{{ \Illuminate\Support\Str::limit((string) $impact->story_to_share, 120) }}</td>
                                <td class="px-3 py-2.5 text-xs t3 max-w-[180px] truncate" title="{{ (string) ($impact->additional_remarks ?? '-') }}">{{ \Illuminate\Support\Str::limit((string) ($impact->additional_remarks ?? '-'), 100) }}</td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($impact->created_at)->format('Y-m-d H:i') }}</td>
                                <td class="px-3 py-2.5 text-right whitespace-nowrap" style="width: 320px;">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('admin.impacts.show', $impact->id) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium text-indigo-600 hover:text-indigo-700 surface-2 transition no-underline">View</a>
                                        <form method="POST" action="{{ route('admin.impacts.approve', $impact->id) }}" class="inline-flex gap-1">
                                            @csrf
                                            <input type="text" name="review_remarks" class="px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal" placeholder="Remarks" style="max-width: 140px;">
                                            <button class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold transition" onclick="return confirm('Approve this impact?')">Approve</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="text-center py-8 text-xs t3">No pending impact requests.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $impacts->links() }}
            </div>
        </div>
    </div>
@endsection

