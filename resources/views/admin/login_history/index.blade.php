@extends('admin.layouts.app')

@section('title', 'Login History')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
    <form id="loginHistoryFiltersForm" method="GET" action="{{ route('admin.login-history.index') }}"></form>

    <div class="flex flex-wrap justify-between items-center mb-4 gap-3">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Login History</h2>
            <p class="text-xs t3 m-0 mt-0.5">Audit log of peer access times, circles, and login locations.</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <div class="flex items-center gap-1.5">
                <label for="perPage" class="text-xs t3 m-0 font-medium">Rows per page:</label>
                <select id="perPage" name="per_page" form="loginHistoryFiltersForm" class="px-2.5 py-1 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                    @foreach ([10, 20, 50, 100] as $size)
                        <option value="{{ $size }}" @selected(($filters['per_page'] ?? 20) == $size)>{{ $size }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input
                    type="datetime-local"
                    name="from"
                    form="loginHistoryFiltersForm"
                    value="{{ $filters['from'] ?? '' }}"
                    class="px-2.5 py-1 rounded-lg border bs surface t1 text-xs outline-none focus-ring"
                    title="From Time"
                >
                <span class="t3 text-xs">to</span>
                <input
                    type="datetime-local"
                    name="to"
                    form="loginHistoryFiltersForm"
                    value="{{ $filters['to'] ?? '' }}"
                    class="px-2.5 py-1 rounded-lg border bs surface t1 text-xs outline-none focus-ring"
                    title="To Time"
                >
                <a href="{{ route('admin.login-history.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition">Clear</a>
            </div>
        </div>
    </div>

    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Peer Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">City</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Last Login</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse ($records as $record)
                        @php
                            $peerName = $record->peer_name ?: '-';
                            $avatar = $record->profile_photo_url ?? ($record->profile_photo_file_id ? url('/api/v1/files/' . $record->profile_photo_file_id) : null);
                            
                            // Parse values and strip out standard empty string placeholders
                            $rawCity = $record->city ?? '';
                            if (is_string($rawCity)) {
                                $rawCity = trim($rawCity);
                                if (str_starts_with($rawCity, '{')) {
                                    $decodedCity = json_decode($rawCity, true);
                                    if (is_array($decodedCity)) {
                                        $cityName = $decodedCity['name'] ?? $decodedCity['label'] ?? $rawCity;
                                    } elseif (preg_match('/name:\s*([^,}]+)/', $rawCity, $matches)) {
                                        $cityName = trim($matches[1], " \t\n\r\0\x0B\"'");
                                    } else {
                                        $cityName = $rawCity;
                                    }
                                } else {
                                    $cityName = $rawCity;
                                }
                            } elseif (is_array($rawCity)) {
                                $cityName = $rawCity['name'] ?? $rawCity['label'] ?? '';
                            } elseif (is_object($rawCity)) {
                                $cityName = $rawCity->name ?? $rawCity->label ?? '';
                            } else {
                                $cityName = $rawCity;
                            }
                            
                            if (in_array(strtolower(trim((string)$cityName)), ['', 'no city', 'none', 'null', 'no_city'], true)) {
                                $cityName = null;
                            }
                            
                            $company = $record->company ?? '';
                            if (in_array(strtolower(trim((string)$company)), ['', 'no company', 'none', 'null', 'no_company', 'peers global'], true)) {
                                $company = null;
                            }
                            
                            $gradientIndex = abs(crc32((string) $record->id)) % 5;
                        @endphp
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full overflow-hidden flex-none border bs">
                                        @if ($avatar)
                                            <img src="{{ $avatar }}" alt="{{ $peerName }}" class="w-full h-full object-cover">
                                        @else
                                            <div class="w-full h-full bg-indigo-600 text-white font-bold flex items-center justify-center text-xs">
                                                {{ strtoupper(substr($peerName !== '' ? $peerName : 'U', 0, 1)) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="font-medium t1 text-[12.5px] whitespace-nowrap">
                                        <a href="#" onclick="event.preventDefault(); event.stopPropagation(); openActivityPeerModal('{{ $record->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">{{ $peerName }}</a>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($company)
                                    <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                        <i class="bi bi-building t3 text-xs"></i>{{ $company }}
                                    </span>
                                @else
                                    <span class="t3">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if ($cityName)
                                    <span class="t1 inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]">
                                        <i class="bi bi-geo-alt t3 text-xs"></i>{{ $cityName }}
                                    </span>
                                @else
                                    <span class="t3">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5">
                                @if (!empty($record->circles_names))
                                    <span class="text-indigo-600 font-semibold inline-flex items-center gap-1 whitespace-nowrap text-[12.5px]" title="{{ $record->circles_names }}">
                                        <i class="bi bi-people text-indigo-500 text-xs"></i>{{ explode(', ', $record->circles_names)[0] }}
                                        @if ((int) $record->circles_count > 1)
                                            <span class="chip px-2 py-0.5 text-[10px] font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">+{{ (int) $record->circles_count - 1 }}</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="t3">-</span>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                @if ($record->last_login_at)
                                    <span class="t2 inline-flex items-center gap-1 text-[12.5px]">
                                        <i class="bi bi-clock t3 text-xs"></i>{{ \Illuminate\Support\Carbon::parse($record->last_login_at)->format('d M Y h:i A') }}
                                    </span>
                                @else
                                    <span class="t3">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-8 text-xs t3">No records found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Bottom Toolbar & Pagination --}}
        <div id="grid-pagination" class="flex justify-between items-center p-3 flex-wrap gap-2 border-t bs">
            <div>
                {{ $records->links() }}
            </div>
            <div class="text-xs t3">
                @if($records->total() > 0)
                    Showing <span class="font-semibold t1">{{ $records->firstItem() }}-{{ $records->lastItem() }}</span> of <span class="font-semibold t1">{{ $records->total() }}</span> records
                @else
                    No records found
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

