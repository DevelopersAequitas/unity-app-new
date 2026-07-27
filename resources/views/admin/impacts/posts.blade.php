@extends('admin.layouts.app')

@section('title', 'Impact All Posts')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-4">
    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Impact All Posts (Approved)</h2>
            <p class="text-xs t3 m-0 mt-0.5">Approved peer life impact timeline posts.</p>
        </div>
    </div>

    <div class="rounded-xl border bs surface overflow-hidden">
        <div class="overflow-x-auto relative">
            <table class="min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Posted At</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">User</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Impacted Peer</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Action</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
                        <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/50">
                @forelse($impacts as $impact)
                    <tr class="hover:surface-2 transition border-b bs">
                        <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($impact->timeline_posted_at)->toDateTimeString() }}</td>
                        <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $impact->user->display_name ?? $impact->user->first_name }}</td>
                        <td class="px-3 py-2.5 text-xs t2">{{ $impact->impactedPeer->display_name ?? $impact->impactedPeer->first_name }}</td>
                        <td class="px-3 py-2.5 text-xs">
                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $impact->action }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-xs">
                            <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-emerald-50 text-emerald-700 border-emerald-200">{{ $impact->status }}</span>
                        </td>
                        <td class="px-3 py-2.5 text-right whitespace-nowrap">
                            <a href="{{ route('admin.impacts.show', $impact->id) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-medium text-indigo-600 hover:text-indigo-700 surface-2 transition no-underline">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center py-8 text-xs t3">No approved impacts yet.</td></tr>
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

