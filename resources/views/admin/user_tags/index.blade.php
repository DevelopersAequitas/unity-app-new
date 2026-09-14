@extends('admin.layouts.app')

@section('title', 'User Tags & Team Member Management')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 md:p-5 relative admin-grid-card space-y-4 w-full">
    {{-- Header & Action Buttons --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 pb-1">
        <div>
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-50 text-indigo-600 border border-indigo-100">
                    <i class="bi bi-tags-fill text-base"></i>
                </span>
                <div>
                    <h2 class="font-display font-semibold text-xs text-indigo-500 uppercase tracking-wider m-0">User Tags Management</h2>
                    <p class="text-xs t3 m-0 mt-0.5">Manage user tags, team member flags, and leaderboard exclusion assignments.</p>
                </div>
            </div>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <a href="{{ route('admin.user-tags.create') }}" class="px-3.5 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition focus-ring no-underline flex items-center gap-1.5 shadow-sm">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Add Tag
            </a>
        </div>
    </div>

    {{-- Notification Alerts --}}
    @if(session('error'))
        <div class="p-3.5 rounded-lg bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-center gap-2 shadow-xs">
            <i class="bi bi-exclamation-octagon-fill text-sm text-rose-600 flex-shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if(session('success'))
        <div class="p-3.5 rounded-lg bg-emerald-50 border border-emerald-200 text-xs text-emerald-700 flex items-center gap-2 shadow-xs">
            <i class="bi bi-check-circle-fill text-sm text-emerald-600 flex-shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- Info Alert for Team Member Tag --}}
    <div class="p-3.5 rounded-xl bg-amber-50/70 border border-amber-200/80 text-xs text-amber-900 flex items-start gap-2.5">
        <i class="bi bi-shield-lock-fill text-amber-600 text-sm mt-0.5 flex-shrink-0"></i>
        <div>
            <strong class="font-semibold text-amber-950">Leaderboard Exclusion Logic:</strong>
            Users assigned to the <code>team_member</code> tag are automatically excluded from the public Coins and Impacts mobile leaderboards (<code>/api/v1/leaderboards/coins</code> and <code>/api/v1/leaderboards/impacts</code>).
        </div>
    </div>

    {{-- Search & Filter Toolbar --}}
    <div class="border bs rounded-xl p-3.5 surface-2">
        <form method="GET" action="{{ route('admin.user-tags.index') }}" class="flex items-center gap-2">
            <div style="position: relative; flex: 1; max-width: 28rem;">
                <i class="bi bi-search text-gray-400" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 0.75rem; pointer-events: none;"></i>
                <input type="text" name="q" value="{{ $search }}" class="rounded-lg border bs surface t1 text-xs outline-none focus-ring w-full" style="padding: 0.375rem 0.75rem 0.375rem 2rem;" placeholder="Search tag name, slug or description...">
            </div>
            <button type="submit" class="px-3.5 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition bg-white shadow-xs">
                Search
            </button>
            @if($search !== '')
                <a href="{{ route('admin.user-tags.index') }}" class="px-3 py-1.5 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline bg-white">
                    Clear
                </a>
            @endif
        </form>
    </div>

    {{-- User Tags Table --}}
    <div class="rounded-xl border bs surface overflow-hidden w-full shadow-sm">
        <div class="overflow-x-auto relative w-full">
            <table class="w-full min-w-full border-collapse text-[13px]">
                <thead>
                    <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
                        <th class="th-cell px-4 py-3 text-left font-semibold" style="width: 220px;">Tag Name</th>
                        <th class="th-cell px-4 py-3 text-left font-semibold" style="width: 180px;">Slug</th>
                        <th class="th-cell px-4 py-3 text-left font-semibold">Description</th>
                        <th class="th-cell px-4 py-3 text-center font-semibold" style="width: 120px;">Status</th>
                        <th class="th-cell px-4 py-3 text-center font-semibold" style="width: 140px;">Assigned Users</th>
                        <th class="th-cell px-4 py-3 text-right font-semibold" style="width: 260px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="grid-body" class="divide-y divide-gray-200/60">
                    @forelse ($tags as $tag)
                        <tr class="hover:surface-2 transition border-b bs">
                            <td class="px-4 py-3 font-medium t1 text-[13px]">
                                <div class="flex items-center gap-2">
                                    <div class="w-7 h-7 rounded-lg {{ $tag->slug === 'team_member' ? 'bg-amber-100 text-amber-700 border-amber-200' : 'bg-indigo-50 text-indigo-600 border-indigo-100' }} flex items-center justify-center flex-shrink-0 text-xs border font-bold">
                                        <i class="bi {{ $tag->slug === 'team_member' ? 'bi-shield-check' : 'bi-tag' }}"></i>
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.user-tags.show', $tag) }}" class="font-semibold t1 hover:text-indigo-600 no-underline transition">
                                            {{ $tag->name }}
                                        </a>
                                        @if($tag->slug === 'team_member')
                                            <span class="ml-1.5 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 border border-amber-300">
                                                System Tag
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-600">
                                <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-700 font-mono text-[11px] font-medium border bs">
                                    {{ $tag->slug }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-xs t2 max-w-xs truncate">
                                {{ $tag->description ?: '—' }}
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if($tag->is_active)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Active
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-gray-100 text-gray-600 border border-gray-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactive
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                <a href="{{ route('admin.user-tags.show', $tag) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-semibold {{ $tag->users_count > 0 ? 'bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100' : 'bg-gray-100 text-gray-600 border border-gray-200 hover:bg-gray-200' }} transition no-underline">
                                    <i class="bi bi-people-fill text-xs"></i>
                                    <span>{{ $tag->users_count }} {{ Str::plural('User', $tag->users_count) }}</span>
                                </a>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ route('admin.user-tags.show', $tag) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold text-indigo-600 hover:text-indigo-700 bg-indigo-50/60 hover:bg-indigo-50 transition no-underline inline-flex items-center gap-1 shadow-xs" title="Manage assigned users">
                                        <i class="bi bi-people"></i> Manage Users
                                    </a>
                                    <a href="{{ route('admin.user-tags.edit', $tag) }}" class="px-2.5 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline inline-flex items-center gap-1 bg-white shadow-xs" title="Edit tag">
                                        <i class="bi bi-pencil"></i> Edit
                                    </a>
                                    @if(! $tag->isSystemTag())
                                        <form method="POST" action="{{ route('admin.user-tags.destroy', $tag) }}" class="inline-block m-0" onsubmit="return confirm('Are you sure you want to delete the tag \'{{ $tag->name }}\'?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1 rounded-lg border border-rose-200 text-xs font-semibold text-rose-600 hover:text-rose-700 bg-rose-50/50 hover:bg-rose-50 transition inline-flex items-center gap-1 shadow-xs" title="Delete tag">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    @else
                                        <span class="px-2 py-1 text-xs text-gray-400 cursor-not-allowed inline-flex items-center gap-1" title="System tag cannot be deleted">
                                            <i class="bi bi-lock-fill"></i>
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-8 text-xs t3">
                                <div class="flex flex-col items-center justify-center">
                                    <i class="bi bi-tags text-2xl text-gray-300 mb-2"></i>
                                    <span>No user tags found.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($tags->hasPages())
            <div class="px-4 py-3 border-t bs bg-gray-50/50">
                {{ $tags->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
