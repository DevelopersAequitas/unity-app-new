@extends('admin.layouts.app')

@section('title', 'Coins Ledger')

@include('admin.partials.grid-head')

@section('content')
    @php
        $memberName = $member->display_name ?? trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
        $heading = $memberName ? $memberName . ' Coins Ledger' : 'Coins Ledger';
        $labelForType = function (?string $type): ?string {
            return $type ? \App\Support\Coins\CoinLedgerFormatter::why($type) : null;
        };
        $resetUrl = $activeType ? route('admin.coins.ledger.type', [$member, $activeType]) : route('admin.coins.ledger', $member);
    @endphp

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">{{ $heading }}</h2>
                <p class="text-xs t3 m-0 mt-0.5">{{ $member->adminDisplayInlineLabel() }}</p>
            </div>
            <a href="{{ route('admin.coins.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">Back to Coins</a>
        </div>

        <div class="border bs rounded-xl p-3.5 mb-4 surface-2">
            <form method="GET" class="flex flex-wrap gap-3 items-end" id="ledgerFilterForm">
                @if ($activeType)
                    <input type="hidden" name="active_type" value="{{ $activeType }}">
                @endif
                <div>
                    <label class="block text-[11px] t3 mb-1 font-medium">From</label>
                    <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                </div>
                <div>
                    <label class="block text-[11px] t3 mb-1 font-medium">To</label>
                    <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                </div>
                <div class="flex gap-2">
                    <a href="{{ $resetUrl }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
                    <a href="{{ route('admin.coins.ledger.export', array_merge(['member' => $member->id], request()->query(), ['type' => $activeType])) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold text-indigo-600 hover:text-indigo-700 surface-2 transition no-underline">Export</a>
                </div>
                @if ($activeType)
                    <span class="chip px-2.5 py-1 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200 ml-auto">Type: {{ $labelForType($activeType) }}</span>
                @endif
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Coins</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Balance After</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Why</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created By</th>
                        </tr>
                        <tr class="surface-2 border-b bs align-middle">
                            <th class="px-3 py-2">
                                <input
                                    type="date"
                                    name="date"
                                    form="ledgerFilterForm"
                                    value="{{ $filters['date'] ?? '' }}"
                                    class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal"
                                    placeholder="Date"
                                >
                            </th>
                            <th class="px-3 py-2">
                                <input
                                    type="text"
                                    name="coins"
                                    form="ledgerFilterForm"
                                    value="{{ $filters['coins'] ?? '' }}"
                                    class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 placeholder:t3 focus-ring outline-none font-normal"
                                    placeholder="Coins"
                                >
                            </th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                            <th class="px-3 py-2">
                                <select name="why" form="ledgerFilterForm" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t1 focus-ring outline-none font-normal">
                                    <option value="">All Reasons</option>
                                    <option value="testimonial" @selected(($filters['why'] ?? '') === 'testimonial')>Testimonial</option>
                                    <option value="referral" @selected(($filters['why'] ?? '') === 'referral')>Referral</option>
                                    <option value="business_deal" @selected(($filters['why'] ?? '') === 'business_deal')>Business Deal</option>
                                    <option value="p2p_meeting" @selected(($filters['why'] ?? '') === 'p2p_meeting')>P2P Meeting</option>
                                    <option value="requirement" @selected(($filters['why'] ?? '') === 'requirement')>Requirement</option>
                                </select>
                            </th>
                            <th class="px-3 py-2"><input type="text" class="w-full px-2.5 py-1 rounded-md border bs surface text-[11px] t3 focus-ring outline-none font-normal" placeholder="-" disabled></th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $item)
                            @php
                                $reasonType = trim((string) ($item->reason_type ?? ''));
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($item->created_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                <td class="px-3 py-2.5 font-semibold text-indigo-600 text-[12.5px]">{{ $item->amount }}</td>
                                <td class="px-3 py-2.5 text-[12.5px] t1 font-medium">{{ $item->balance_after }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ \App\Support\Coins\CoinLedgerFormatter::why($reasonType) }}</td>
                                <td class="px-3 py-2.5">
                                    @if ($item->createdBy)
                                        @include('admin.shared.peer_card', ['user' => $item->createdBy])
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-8 text-xs t3">No ledger entries found.</td>
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('ledgerFilterForm');

            if (!form) {
                return;
            }

            const inputs = form.querySelectorAll('input, select');

            inputs.forEach(function (input) {
                input.addEventListener('keypress', function (e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush

