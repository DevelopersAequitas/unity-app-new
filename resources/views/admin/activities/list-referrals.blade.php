@extends('admin.layouts.app')

@section('title', 'Referrals')

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

        $formatDateTime = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i') : '-';
        };

        $formatDate = function ($value): string {
            return $value ? \Illuminate\Support\Carbon::parse($value)->format('Y-m-d') : '-';
        };

        $formatReferralType = function (?string $type): array {
            $raw = strtolower(trim((string) $type));
            return match ($raw) {
                'customer_referral', 'customer' => [
                    'label' => 'Customer',
                    'badgeClass' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'dotClass' => 'bg-emerald-500',
                ],
                'b2b_referral', 'b2b' => [
                    'label' => 'B2B Referral',
                    'badgeClass' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    'dotClass' => 'bg-indigo-500',
                ],
                'b2g_referral', 'b2g' => [
                    'label' => 'B2G Referral',
                    'badgeClass' => 'bg-purple-50 text-purple-700 border-purple-200',
                    'dotClass' => 'bg-purple-500',
                ],
                'collaborative_projects', 'collaborative' => [
                    'label' => 'Collaboration',
                    'badgeClass' => 'bg-cyan-50 text-cyan-700 border-cyan-200',
                    'dotClass' => 'bg-cyan-500',
                ],
                'referral_partnerships', 'partnerships' => [
                    'label' => 'Partnership',
                    'badgeClass' => 'bg-sky-50 text-sky-700 border-sky-200',
                    'dotClass' => 'bg-sky-500',
                ],
                'vendor_referrals', 'vendor' => [
                    'label' => 'Vendor',
                    'badgeClass' => 'bg-teal-50 text-teal-700 border-teal-200',
                    'dotClass' => 'bg-teal-500',
                ],
                'business' => [
                    'label' => 'Business',
                    'badgeClass' => 'bg-blue-50 text-blue-700 border-blue-200',
                    'dotClass' => 'bg-blue-500',
                ],
                'service' => [
                    'label' => 'Service',
                    'badgeClass' => 'bg-violet-50 text-violet-700 border-violet-200',
                    'dotClass' => 'bg-violet-500',
                ],
                'others', 'other' => [
                    'label' => 'Other',
                    'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
                    'dotClass' => 'bg-slate-400',
                ],
                '' => [
                    'label' => '—',
                    'badgeClass' => '',
                    'dotClass' => '',
                ],
                default => [
                    'label' => ucwords(str_replace('_', ' ', $raw)),
                    'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
                    'dotClass' => 'bg-slate-500',
                ],
            };
        };

        $getHotBadge = function ($value): array {
            $val = (int) $value;
            if ($val <= 0) {
                return [
                    'value' => null,
                    'label' => '—',
                    'badgeClass' => '',
                    'iconClass' => '',
                    'title' => '',
                ];
            }

            return match ($val) {
                5 => [
                    'value' => 5,
                    'label' => '5',
                    'badgeClass' => 'bg-rose-50 text-rose-700 border-rose-200',
                    'iconClass' => 'text-rose-600 animate-pulse',
                    'title' => 'Hotness: 5/5 (Very High)',
                ],
                4 => [
                    'value' => 4,
                    'label' => '4',
                    'badgeClass' => 'bg-orange-50 text-orange-700 border-orange-200',
                    'iconClass' => 'text-orange-500',
                    'title' => 'Hotness: 4/5 (High)',
                ],
                3 => [
                    'value' => 3,
                    'label' => '3',
                    'badgeClass' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'iconClass' => 'text-amber-500',
                    'title' => 'Hotness: 3/5 (Medium)',
                ],
                2 => [
                    'value' => 2,
                    'label' => '2',
                    'badgeClass' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
                    'iconClass' => 'text-yellow-600',
                    'title' => 'Hotness: 2/5 (Low)',
                ],
                1 => [
                    'value' => 1,
                    'label' => '1',
                    'badgeClass' => 'bg-slate-100 text-slate-700 border-slate-200',
                    'iconClass' => 'text-slate-400',
                    'title' => 'Hotness: 1/5 (Cold)',
                ],
                default => [
                    'value' => $val,
                    'label' => (string) $val,
                    'badgeClass' => 'bg-rose-50 text-rose-700 border-rose-200',
                    'iconClass' => 'text-rose-500',
                    'title' => "Hotness: {$val}",
                ],
            };
        };

        $peerName = trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? '')) ?: $member->display_name ?: 'Unnamed Peer';
    @endphp

    <!-- Activities Hub Header -->
    @include('admin.activities.partials.header', ['title' => 'Referrals'])

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Referrals Log</h2>
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
                    <a href="{{ route('admin.activities.referrals', $member) }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Clear</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Referred Peer</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Referral Of</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Referral Type</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Referral Date</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Phone</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Address</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Hot Value</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Remarks</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Created At</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($items as $referral)
                            @php
                                $toName = $referral->toUser->display_name ?? trim(($referral->toUser->first_name ?? '') . ' ' . ($referral->toUser->last_name ?? '')) ?: '-';
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2">
                                        <div class="w-7 h-7 rounded-full text-white text-xs font-bold flex items-center justify-center shrink-0" style="background-color: {{ $getAvatarBg($toName) }}">
                                            {{ $getInitials($toName) }}
                                        </div>
                                        <div>
                                            <div class="font-semibold t1 text-[12.5px]">
                                                @if(!empty($referral->toUser?->id))
                                                    <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $referral->toUser->id }}', event);" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                                        {{ $toName }}
                                                    </a>
                                                @else
                                                    {{ $toName }}
                                                @endif
                                            </div>
                                            @php $toCompany = $referral->toUser->company_name ?? $referral->toUser->company ?? null; @endphp
                                            @if($toCompany)
                                                <x-admin-grid-text :text="$toCompany" class="t3 text-[10px]" />
                                            @else
                                                <div class="t3 text-[10px]">{{ $referral->toUser->email ?? '-' }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5 text-xs font-semibold t1"><x-admin-grid-text :text="$referral->referral_of ?? '-'" /></td>
                                <td class="px-3 py-2.5 text-xs whitespace-nowrap">
                                    @php $typeInfo = $formatReferralType($referral->referral_type ?? null); @endphp
                                    @if(!empty($typeInfo['label']) && $typeInfo['label'] !== '—')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11.5px] font-semibold border {{ $typeInfo['badgeClass'] }}">
                                            <span class="w-1.5 h-1.5 rounded-full {{ $typeInfo['dotClass'] }}"></span>
                                            <span>{{ $typeInfo['label'] }}</span>
                                        </span>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                    {{ $referral->referral_date ? $formatDate($referral->referral_date) : '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-xs t2 whitespace-nowrap">{{ $referral->phone ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-xs t2">{{ $referral->email ?? '-' }}</td>
                                <td class="px-3 py-2.5 text-xs t2 max-w-[160px]"><x-admin-grid-text :text="$referral->address ?? '-'" /></td>
                                <td class="px-3 py-2.5 text-xs text-center align-middle whitespace-nowrap">
                                    @php $hotInfo = $getHotBadge($referral->hot_value ?? null); @endphp
                                    @if($hotInfo['value'] !== null)
                                        <span class="inline-flex items-center justify-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $hotInfo['badgeClass'] }}" title="{{ $hotInfo['title'] }}">
                                            <i class="bi bi-fire text-[11px] {{ $hotInfo['iconClass'] }}" aria-hidden="true"></i>
                                            <span>{{ $hotInfo['label'] }}</span>
                                        </span>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-xs t2 max-w-[160px]"><x-admin-grid-text :text="$referral->remarks ?? '-'" /></td>
                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                    {{ $formatDateTime($referral->created_at ?? null) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-8 text-xs t3">No referrals found.</td>
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

