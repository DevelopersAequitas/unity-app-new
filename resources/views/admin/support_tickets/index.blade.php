@extends('admin.layouts.app')

@section('title', 'Support Tickets')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Support Tickets</h2>
            <p class="text-xs t3 m-0 mt-0.5">Manage customer queries, issues, and support requests.</p>
        </div>
        <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">Total: {{ number_format($tickets->total()) }}</span>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
            {{ session('success') }}
        </div>
    @endif

    <!-- Filters and Search -->
    <div class="p-3 rounded-lg border bs surface-2">
        <form method="GET" action="{{ route('admin.support-tickets.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-2.5 items-end">
            <div>
                <label for="search" class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Search</label>
                <input type="text" name="search" id="search" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" placeholder="Ticket #, name, email, subject..." value="{{ request('search') }}">
            </div>
            <div>
                <label for="status" class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Status</label>
                <select name="status" id="status" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onchange="this.form.submit()">
                    <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                    <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="resolved" {{ request('status') === 'resolved' ? 'selected' : '' }}>Resolved</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                </select>
            </div>
            <div>
                <label for="priority" class="block text-[11px] uppercase tracking-wider font-semibold t3 mb-1">Priority</label>
                <select name="priority" id="priority" class="px-2.5 py-1.5 text-xs rounded border bs surface t1 w-full outline-none focus-ring" onchange="this.form.submit()">
                    <option value="all" {{ request('priority') === 'all' || !request('priority') ? 'selected' : '' }}>All Priorities</option>
                    <option value="low" {{ request('priority') === 'low' ? 'selected' : '' }}>Low</option>
                    <option value="normal" {{ request('priority') === 'normal' ? 'selected' : '' }}>Normal</option>
                    <option value="high" {{ request('priority') === 'high' ? 'selected' : '' }}>High</option>
                    <option value="urgent" {{ request('priority') === 'urgent' ? 'selected' : '' }}>Urgent</option>
                </select>
            </div>
            <div class="flex justify-end">
                <a href="{{ route('admin.support-tickets.index') }}" class="px-3 py-1.5 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition text-center no-underline w-full">Clear</a>
            </div>
        </form>
    </div>

    <!-- Tickets Table -->
    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Ticket Number</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Contact Name</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Email</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Subject</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Priority</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Submitted At</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                    @forelse($tickets as $ticket)
                        @php
                            $statusBadge = match($ticket->status) {
                                'open' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-sky-50 text-sky-700 border-sky-200',
                                'in_progress' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200',
                                'resolved' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200',
                                'closed' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200',
                                default => 'chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200'
                            };
                            $priorityBadge = match($ticket->priority) {
                                'low' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200',
                                'normal' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-sky-50 text-sky-700 border-sky-200',
                                'high' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-amber-50 text-amber-700 border-amber-200',
                                'urgent' => 'chip px-2.5 py-0.5 text-xs font-semibold bg-rose-50 text-rose-700 border-rose-200',
                                default => 'chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200'
                            };
                        @endphp
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-3 py-2.5 text-xs font-mono font-semibold text-indigo-600">
                                <a href="{{ route('admin.support-tickets.show', $ticket->id) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                    #{{ $ticket->ticket_number }}
                                </a>
                            </td>
                            <td class="px-3 py-2.5 text-xs font-medium t1">
                                @if(!empty($ticket->user_id ?? $ticket->user?->id))
                                    <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $ticket->user_id ?? $ticket->user?->id }}', event, 'support_tickets');" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                        {{ $ticket->contact_name }}
                                    </a>
                                @elseif(!empty($ticket->email))
                                    <a href="#" onclick="event.preventDefault(); openActivityPeerModal('{{ $ticket->email }}', event, 'support_tickets');" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                        {{ $ticket->contact_name }}
                                    </a>
                                @else
                                    {{ $ticket->contact_name }}
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-xs t2"><a href="mailto:{{ $ticket->email }}" class="no-underline hover:underline text-indigo-600">{{ $ticket->email }}</a></td>
                            <td class="px-3 py-2.5 text-xs t2 max-w-[250px] truncate" title="{{ $ticket->subject }}">
                                <a href="{{ route('admin.support-tickets.show', $ticket->id) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline font-semibold no-underline">
                                    {{ $ticket->subject }}
                                </a>
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                <span class="{{ $statusBadge }}">{{ ucwords(str_replace('_', ' ', $ticket->status)) }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-xs">
                                <span class="{{ $priorityBadge }}">{{ ucfirst($ticket->priority) }}</span>
                            </td>
                            <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">
                                <div>{{ $ticket->created_at->format('Y-m-d H:i') }}</div>
                                <div class="text-[10px] t3">{{ $ticket->created_at->diffForHumans() }}</div>
                            </td>
                            <td class="px-3 py-2.5 text-xs text-right whitespace-nowrap">
                                <a href="{{ route('admin.support-tickets.show', $ticket->id) }}" class="px-2.5 py-1 text-xs font-semibold rounded border bs t2 hover:t1 hover:surface-2 transition no-underline">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center py-8 text-xs t3">
                                No support tickets found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
            {{ $tickets->links() }}
        </div>
    </div>
</div>
@endsection
