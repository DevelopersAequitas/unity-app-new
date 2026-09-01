@extends('admin.layouts.app')

@section('title', 'Testimonials')

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

        $peerName = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: $member->display_name ?: 'Unnamed Peer';
    @endphp

    <!-- Activities Hub Header -->
    @include('admin.activities.partials.header', ['title' => 'Testimonials'])

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Testimonials Log</h2>
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
                    <a href="{{ route('admin.activities.testimonials', $member) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Target Peer</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Content</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Media</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $testimonial)
                            @php
                                $attachmentUrl = $extractMediaUrl($testimonial->media ?? null);
                                $toName = $testimonial->toUser->display_name ?? trim(($testimonial->toUser->first_name ?? '') . ' ' . ($testimonial->toUser->last_name ?? '')) ?: '-';
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($toName) }}">
                                            {{ $getInitials($toName) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold t1 text-[12.5px]">
                                                @if(!empty($testimonial->toUser?->id))
                                                    <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $testimonial->toUser->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                                        {{ $toName }}
                                                    </a>
                                                @else
                                                    {{ $toName }}
                                                @endif
                                            </div>
                                            @php $toCompany = $testimonial->toUser->company_name ?? $testimonial->toUser->company ?? null; @endphp
                                            @if($toCompany)
                                                <x-admin-grid-text :text="$toCompany" class="t3 text-[10px]" />
                                            @else
                                                <div class="t3 text-[10px]">{{ $testimonial->toUser->email ?? '-' }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-xs t2 max-w-[320px]">
                                    <x-admin-grid-text :text="$testimonial->content ?? '-'" />
                                </td>
                                <td class="px-3 py-2.5 text-xs">
                                    @if($attachmentUrl)
                                        <a href="{{ $attachmentUrl }}" target="_blank" class="text-indigo-600 font-semibold no-underline">View Attachment</a>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                    {{ optional($testimonial->created_at)->format('Y-m-d H:i') ?? '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center py-8 text-xs t3">No testimonials found.</td>
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

