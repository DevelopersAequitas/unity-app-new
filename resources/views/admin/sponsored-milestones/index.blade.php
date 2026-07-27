@extends('admin.layouts.app')

@section('title', 'Sponsored Member Milestone Awards')

@include('admin.partials.grid-head')

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="px-4 py-3 surface-2 border-b bs flex items-center justify-between">
            <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0 flex items-center gap-2">
                <i class="bi bi-trophy-fill text-amber-500"></i>Sponsored Member Milestone Awards
            </h6>
        </div>
        <div class="p-4">
            {{-- Filter Form --}}
            <form id="milestonesFiltersForm" method="GET" class="border bs rounded-xl p-3.5 mb-4 surface-2">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="milestoneSearch">Search</label>
                        <input type="text" id="milestoneSearch" name="q" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring" placeholder="Search by name, email, company, phone..." value="{{ $filters['search'] ?? '' }}">
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="milestoneFilter">Milestone Threshold</label>
                        <select id="milestoneFilter" name="milestone" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                            <option value="">All Milestones</option>
                            @foreach([0, 1, 2, 3, 4, 5, 6, 8, 10, 12, 15, 20, 25] as $val)
                                <option value="{{ $val }}" @selected(($filters['milestone'] !== null && $filters['milestone'] !== '') && (int)$filters['milestone'] === $val)>{{ $val === 0 ? '0 (No Milestone)' : $val . '+ Sponsored' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="block text-[11px] t3 mb-1 font-medium" for="awardFilter">Award Name</label>
                        <select id="awardFilter" name="award_name" class="w-full px-3 py-1.5 rounded-lg border bs surface t1 text-xs outline-none focus-ring">
                            <option value="">All Awards</option>
                            @foreach([
                                'The Connector Award',
                                'Rising Voice Award',
                                'Community Catalyst Award',
                                'Voice of Change Award',
                                'Influencer Award',
                                'Inspiration Icon Award',
                                'Super Star Award',
                                'Global Star',
                                'Legacy Creator',
                                'Impact Creator Award',
                                'Nation Builder Award',
                                'Peers Global Hall of Fame 👑'
                            ] as $award)
                                <option value="{{ $award }}" @selected(($filters['award_name'] ?? '') === $award)>{{ $award }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-2 d-flex justify-content-end">
                        <a href="{{ route('admin.sponsored-milestones.index') }}" class="w-full px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition text-center no-underline">Clear</a>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">
                                <a href="{{ route('admin.sponsored-milestones.index', array_merge(request()->query(), ['sort' => 'display_name', 'dir' => ($filters['sort'] ?? '') === 'display_name' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="no-underline t1 hover:text-indigo-600 inline-flex items-center gap-1">
                                    Member Name
                                    @if (($filters['sort'] ?? '') === 'display_name')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }}-short text-xs"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Contact / Info</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Company Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-center">
                                <a href="{{ route('admin.sponsored-milestones.index', array_merge(request()->query(), ['sort' => 'total_sponsored_members', 'dir' => ($filters['sort'] ?? '') === 'total_sponsored_members' && ($filters['dir'] ?? '') === 'asc' ? 'desc' : 'asc'])) }}" class="no-underline t1 hover:text-indigo-600 inline-flex items-center gap-1">
                                    Sponsored Members
                                    @if (($filters['sort'] ?? '') === 'total_sponsored_members')
                                        <i class="bi bi-arrow-{{ ($filters['dir'] ?? '') === 'asc' ? 'up' : 'down' }}-short text-xs"></i>
                                    @endif
                                </a>
                            </th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Current Milestone</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Award Name</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Recognition / Benefits</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Milestone Progress</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse ($members as $member)
                            @php
                                $name = $member->display_name ?: trim(($member->first_name ?? '') . ' ' . ($member->last_name ?? ''));
                                $avatar = $member->profile_photo_url ?? ($member->profile_photo_file_id ? url('/api/v1/files/' . $member->profile_photo_file_id) : null);
                                $gradientIndex = abs(crc32((string) $member->id)) % 5;
                                $details = $member->milestoneDetails;
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full overflow-hidden flex-none border bs">
                                            @if ($avatar)
                                                <img src="{{ $avatar }}" alt="{{ $name }}" class="w-full h-full object-cover">
                                            @else
                                                <div class="w-full h-full bg-indigo-600 text-white font-bold flex items-center justify-center text-xs">
                                                    {{ strtoupper(substr($name ?: 'U', 0, 1)) }}
                                                </div>
                                            @endif
                                        </div>
                                        <div class="font-medium t1 text-[12.5px] whitespace-nowrap">{{ $name }}</div>
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="flex flex-col text-xs t3">
                                        <span class="t2">{{ $member->email }}</span>
                                        @if ($member->phone)
                                            <span class="t3">{{ $member->phone }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="t2 text-[12.5px] whitespace-nowrap">{{ $member->company_name ?: '-' }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $member->total_sponsored_members }}</span>
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="font-semibold t1 text-[12.5px]">{{ $details['current_milestone'] }}</span>
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($details['award_name'])
                                        <span class="text-indigo-600 font-semibold text-[12.5px] whitespace-nowrap">{{ $details['award_name'] }}</span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($details['recognition'])
                                        <span class="t2 text-xs">{{ $details['recognition'] }}</span>
                                    @else
                                        <span class="t3">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5">
                                    @if ($details['next_milestone'])
                                        <div class="flex flex-col w-28">
                                            <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden">
                                                @php
                                                    $prevMilestone = $details['current_milestone'];
                                                    $target = $details['next_milestone'];
                                                    $progressVal = $member->total_sponsored_members - $prevMilestone;
                                                    $totalNeeded = $target - $prevMilestone;
                                                    $percent = $totalNeeded > 0 ? ($progressVal / $totalNeeded) * 100 : 0;
                                                @endphp
                                                <div class="bg-emerald-500 h-1.5 rounded-full" style="width: {{ $percent }}%;"></div>
                                            </div>
                                            <span class="t3 text-[10px] mt-1 whitespace-nowrap">{{ $details['members_remaining'] }} remaining to {{ $details['next_milestone'] }}</span>
                                        </div>
                                    @else
                                        <span class="chip px-2 py-0.5 text-[11px] font-semibold bg-emerald-50 text-emerald-700 border-emerald-200 inline-flex items-center gap-1">
                                            <i class="bi bi-patch-check-fill"></i>Max Milestone
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <a href="{{ route('admin.sponsored-milestones.show', $member->id) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1">
                                        <i class="bi bi-eye-fill"></i>View Details
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-8 text-xs t3">
                                    <div class="mb-2"><i class="bi bi-inbox text-2xl t3"></i></div>
                                    <div class="font-semibold t1">No sponsored milestone records found</div>
                                    <div class="t3 mt-0.5">Try adjusting your filters or search terms.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Bottom Toolbar & Pagination --}}
            <div id="grid-pagination" class="flex justify-between items-center mt-4 flex-wrap gap-2 pt-3 border-t bs">
                <div>
                    {{ $members->links() }}
                </div>
                <div class="text-xs t3">
                    @if($members->total() > 0)
                        Showing <span class="font-semibold t1">{{ $members->firstItem() }}-{{ $members->lastItem() }}</span> of <span class="font-semibold t1">{{ $members->total() }}</span> records
                    @else
                        No records
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

