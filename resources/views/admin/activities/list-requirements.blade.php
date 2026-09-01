@extends('admin.layouts.app')

@section('title', 'Requirements')

@include('admin.partials.grid-head')

@section('content')
    @php
        $getInitials = function($name) {
            $words = explode(' ', trim($name));
            $initials = '';
            foreach ($words as $w) {
                if(!empty($w)) $initials .= strtoupper(substr($w, 0, 1));
            }
            return substr($initials, 0, 2) ?: 'P';
        };
        $getAvatarBg = function($name) {
            $colors = ['#6366f1', '#06b6d4', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#3b82f6'];
            $hash = crc32($name);
            return $colors[abs($hash) % count($colors)];
        };

        $resolveFileUrl = function ($value) use ($validMediaIds) {
            if (! $value) {
                return null;
            }

            if (is_string($value) && (str_starts_with($value, 'http://') || str_starts_with($value, 'https://'))) {
                return $value;
            }

            if (is_string($value) && \Illuminate\Support\Str::isUuid($value)) {
                if (in_array($value, $validMediaIds ?? [], true)) {
                    return url('/api/v1/files/' . $value);
                }
            }

            return null;
        };

        $extractMediaUrl = function ($media) use ($resolveFileUrl) {
            if (! $media) {
                return null;
            }

            if (is_array($media)) {
                $first = $media[0] ?? null;
                if (is_array($first)) {
                    $id = $first['id'] ?? null;
                    $url = $first['url'] ?? null;
                    return $resolveFileUrl($url ?: $id);
                }
            }

            return $resolveFileUrl($media);
        };

        $decodeFilter = function ($value): array {
            if (is_array($value)) {
                return $value;
            }

            if (is_string($value)) {
                $decoded = json_decode($value, true);
                return is_array($decoded) ? $decoded : [];
            }

            return [];
        };

        $peerName = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: $member->display_name ?: 'Unnamed Peer';
    @endphp

    <!-- Activities Hub Header -->
    @include('admin.activities.partials.header', ['title' => 'Requirements'])

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Requirements Log</h2>
                <p class="text-xs t1 font-medium m-0 mt-0.5">
                    @if(!empty($member->id))
                        <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $member->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">{{ $peerName }}</a>
                    @else
                        {{ $peerName }}
                    @endif
                    • {{ $member->email ?? '-' }}
                </p>
            </div>
            <a href="{{ route('admin.activities.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">
                Back to Activities
            </a>
        </div>

        <div class="border bs rounded-xl p-3.5 surface-2">
            <form method="GET" class="flex flex-wrap gap-3 items-center">
                <div>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" placeholder="From">
                </div>
                <div>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" placeholder="To">
                </div>
                <div class="flex justify-end">
                    <a href="{{ route('admin.activities.requirements', $member) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-[900px] w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:120px;">Subject</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:150px;">Description</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:100px;">Region</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:140px;">Business Category</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:100px;">Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:90px;">Attachment</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left" style="min-width:130px;">Created At</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $requirement)
                            @php
                                $attachmentUrl = $extractMediaUrl($requirement->media ?? null);
                                $regionFilter = $decodeFilter($requirement->region_filter ?? null);
                                $categoryFilter = $decodeFilter($requirement->category_filter ?? null);
                                $regionLabel = $regionFilter['region_label'] ?? $regionFilter['region_name'] ?? $regionFilter['city_name'] ?? null;
                                $category = $categoryFilter['category'] ?? null;
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs font-semibold t1 text-[12.5px] align-middle" style="min-width:120px;"><x-admin-grid-text :text="$requirement->subject ?? '-'" :lines="2" /></td>
                                <td class="px-3 py-2.5 text-xs t2 align-middle" style="min-width:150px;"><x-admin-grid-text :text="$requirement->description ?? '-'" :lines="2" /></td>
                                <td class="px-3 py-2.5 text-xs t2 align-middle" style="min-width:100px;"><x-admin-grid-text :text="$regionLabel ?? '-'" :lines="2" /></td>
                                 <td class="px-3 py-2.5 text-xs font-medium t1 align-middle whitespace-nowrap" style="min-width:140px;">
                                     <x-admin-grid-text :text="$category ?: '-'" :lines="2" />
                                 </td>
                                 <td class="px-3 py-2.5 text-xs align-middle whitespace-nowrap" style="min-width:100px;">
                                     @php
                                         $st = strtolower((string)($requirement->status ?? ''));
                                     @endphp
                                     @if($st === 'open' || $st === 'active')
                                         <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-emerald-50 text-emerald-700 border border-emerald-200">
                                             <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>{{ ucfirst($requirement->status ?? 'Active') }}
                                         </span>
                                     @elseif($st === 'completed' || $st === 'closed')
                                         <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-sky-50 text-sky-700 border border-sky-200">
                                             <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>{{ ucfirst($requirement->status ?? 'Completed') }}
                                         </span>
                                     @else
                                         <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 text-xs font-semibold rounded-md whitespace-nowrap bg-slate-100 text-slate-700 border border-slate-200">
                                             <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>{{ ucfirst($requirement->status ?? '-') }}
                                         </span>
                                     @endif
                                 </td>
                                <td class="px-3 py-2.5 text-xs align-middle" style="min-width:90px;">
                                    @if($attachmentUrl)
                                        <a href="{{ $attachmentUrl }}" target="_blank" class="text-indigo-600 font-semibold no-underline">View Attachment</a>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap align-middle" style="min-width:130px;">
                                    {{ optional($requirement->created_at)->format('Y-m-d H:i') ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-8 text-xs t3">No requirements found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $items->links() }}
            </div>
        </div>
    </div>
@endsection
