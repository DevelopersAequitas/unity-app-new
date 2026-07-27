@extends('admin.layouts.app')

@section('title', 'Account Deletion Requests')

@include('admin.partials.grid-head')

@section('content')
    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 p-3 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700">
            {{ session('error') }}
        </div>
    @endif

    <div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
        <div class="flex flex-wrap justify-between items-center gap-3">
            <div>
                <h2 class="font-display font-semibold text-xs text-rose-500 uppercase tracking-wider m-0">Account Deletion Requests</h2>
                <p class="text-xs t3 m-0 mt-0.5">Review and manage user requests to delete or deactivate their accounts.</p>
            </div>
            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200">
                Total: {{ number_format($requests->total()) }}
            </span>
        </div>

        <!-- Filter Bar -->
        <div class="p-3 rounded-lg border bs surface-2">
            <form method="GET" action="{{ route('admin.account-deletion.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2.5 items-end">
                <div>
                    <label class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Filter by Status</label>
                    <select name="status" id="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring">
                        <option value="all"     {{ $status === 'all'     ? 'selected' : '' }}>All Requests</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="ongoing" {{ $status === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                        <option value="approved"{{ $status === 'approved'? 'selected' : '' }}>Approved</option>
                        <option value="rejected"{{ $status === 'rejected'? 'selected' : '' }}>Rejected</option>
                    </select>
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('admin.account-deletion.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline">
                        Clear
                    </a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border bs surface overflow-hidden">
            <div class="overflow-x-auto relative">
                <table class="min-w-full border-collapse text-[13px]">
                    <thead>
                        <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">User</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Email</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Reason</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Request Status</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submitted</th>
                            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="grid-body" class="divide-y divide-gray-200/50">
                        @forelse($requests as $req)
                            @php
                                $linkedUser        = $req->linked_user ?? null;
                                $userIsDeactivated = $linkedUser && $linkedUser->trashed();
                                $userIsActive      = $linkedUser && !$linkedUser->trashed();

                                if ($linkedUser) {
                                    $nameParts = explode(' ', trim($linkedUser->display_name ?? ($linkedUser->first_name . ' ' . $linkedUser->last_name)));
                                    $initials  = strtoupper(substr($nameParts[0] ?? 'U', 0, 1) . substr($nameParts[1] ?? '', 0, 1));
                                } else {
                                    $initials = '?';
                                }

                                $statusClass = match($req->status) {
                                    'pending'  => 'chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200',
                                    'ongoing'  => 'chip px-2.5 py-0.5 text-xs font-semibold bg-sky-50 text-sky-700 border-sky-200',
                                    'approved' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200',
                                    'rejected' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200',
                                    default    => 'chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200',
                                };
                            @endphp
                            <tr class="hover:surface-2 transition border-b bs">
                                <td class="px-3 py-2.5 text-xs">
                                    <div class="flex items-center gap-2.5">
                                        <div class="w-8 h-8 rounded-full surface-2 text-indigo-600 font-bold flex items-center justify-center border bs text-xs flex-shrink-0">
                                            {{ $initials }}
                                        </div>
                                        <div>
                                            @if($linkedUser)
                                                <div class="font-semibold t1">
                                                    {{ $linkedUser->display_name ?? trim($linkedUser->first_name . ' ' . $linkedUser->last_name) }}
                                                </div>
                                                <div class="mt-0.5">
                                                    @if($userIsDeactivated)
                                                        <span class="chip px-2 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-600 border-gray-200">Deactivated</span>
                                                    @else
                                                        <span class="chip px-2 py-0.5 text-[10px] font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">Active</span>
                                                    @endif
                                                </div>
                                            @else
                                                <div class="t2 font-medium">
                                                    {{ $req->email ?? 'Unknown User' }}
                                                </div>
                                                <span class="chip px-2 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-600 border-gray-200">No Account</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                <td class="px-3 py-2.5 text-xs">
                                    @if($linkedUser)
                                        <a href="mailto:{{ $linkedUser->email }}" class="text-indigo-600 hover:underline no-underline">{{ $linkedUser->email }}</a>
                                    @elseif($req->email)
                                        <a href="mailto:{{ $req->email }}" class="t2 hover:underline no-underline">{{ $req->email }}</a>
                                    @else
                                        <span class="t3">—</span>
                                    @endif
                                </td>

                                <td class="px-3 py-2.5 text-xs">
                                    <div class="t2 max-w-[250px] truncate" title="{{ $req->reason }}">
                                        {{ Str::limit($req->reason, 120) }}
                                    </div>
                                </td>

                                <td class="px-3 py-2.5 text-xs">
                                    <span class="{{ $statusClass }}">{{ ucfirst($req->status) }}</span>
                                </td>

                                <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                    <div class="t1 font-medium">{{ $req->created_at->format('d M Y') }}</div>
                                    <div class="t3 text-[10px]">{{ $req->created_at->format('H:i') }} · {{ $req->created_at->diffForHumans() }}</div>
                                </td>

                                <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                    <div class="flex justify-end gap-2 items-center">
                                        <form action="{{ route('admin.account-deletion.update-status', $req->id) }}" method="POST" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <select name="status" class="px-2 py-1 text-xs rounded border bs surface t1 outline-none focus-ring">
                                                <option value="pending" {{ $req->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                <option value="ongoing" {{ $req->status === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                                                @if(!in_array($req->status, ['pending', 'ongoing']))
                                                    <option value="{{ $req->status }}" selected>{{ ucfirst($req->status) }}</option>
                                                @endif
                                            </select>
                                        </form>

                                        @if($userIsDeactivated)
                                            <form action="{{ route('admin.account-deletion.activate-account', $req->id) }}" method="POST" class="inline" onsubmit="return confirm('Activate this user account?')">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded bg-emerald-600 hover:bg-emerald-500 text-white transition focus-ring">Activate</button>
                                            </form>
                                        @elseif($userIsActive)
                                            <form action="{{ route('admin.account-deletion.deactivate-account', $req->id) }}" method="POST" class="inline" onsubmit="return confirm('Deactivate this user account?')">
                                                @csrf
                                                <button type="submit" class="px-2.5 py-1 text-xs font-semibold rounded border border-rose-200 text-rose-700 hover:bg-rose-50 transition focus-ring">Deactivate</button>
                                            </form>
                                        @else
                                            <span class="chip px-2 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-600 border-gray-200">No Account</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-8 text-xs t3">
                                    No account deletion requests found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
                {{ $requests->withQueryString()->links() }}
            </div>
        </div>
    </div>
@endsection

