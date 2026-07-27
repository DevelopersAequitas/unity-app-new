@extends('admin.layouts.app')
@section('title','Leadership Control')

@include('admin.partials.grid-head')

@section('content')
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif

<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card space-y-6">
  <div>
    <h2 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Leadership Control</h2>
    <p class="text-xs t3 m-0 mt-0.5">Manage leadership applications, active assignments, and impact performance scores.</p>
  </div>

  {{-- Applications Section --}}
  <div class="rounded-xl border bs surface overflow-hidden">
    <div class="px-4 py-3 surface-2 border-b bs flex items-center justify-between">
      <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Leadership Applications</h6>
    </div>
    <div class="overflow-x-auto relative">
      <table class="min-w-full border-collapse text-[13px]">
        <thead>
          <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Name</th>
            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Email</th>
            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Date</th>
          </tr>
        </thead>
        <tbody id="grid-body" class="divide-y divide-gray-200/50">
          @forelse($applications as $app)
            <tr class="hover:surface-2 transition border-b bs">
              <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $app->applicant_name ?? '-' }}</td>
              <td class="px-3 py-2.5 text-[12.5px] t2">{{ $app->applicant_email ?? '-' }}</td>
              <td class="px-3 py-2.5 text-xs">
                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700 border-gray-200">{{ $app->status ?? 'pending' }}</span>
              </td>
              <td class="px-3 py-2.5 text-xs t3 whitespace-nowrap">{{ optional($app->created_at)->format('d M Y') }}</td>
            </tr>
          @empty
            <tr><td colspan="4" class="text-center py-8 text-xs t3">No applications found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
      {{ $applications->withQueryString()->links() }}
    </div>
  </div>

  {{-- Assignments and Performance Grid Row --}}
  <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
    <div class="lg:col-span-7 rounded-xl border bs surface overflow-hidden">
      <div class="px-4 py-3 surface-2 border-b bs">
        <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Active Assignments</h6>
      </div>
      <div class="overflow-x-auto relative">
        <table class="min-w-full border-collapse text-[13px]">
          <thead>
            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">User</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Role</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Circle</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200/50">
            @forelse($assignments as $row)
              <tr class="hover:surface-2 transition border-b bs">
                <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $row->user_name }}</td>
                <td class="px-3 py-2.5 text-xs">
                  <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $row->role }}</span>
                </td>
                <td class="px-3 py-2.5 text-[12.5px] t2">{{ $row->circle_name }}</td>
                <td class="px-3 py-2.5 text-xs t3">{{ $row->status }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center py-8 text-xs t3">No assignments found.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
      <div class="p-3 border-t bs flex justify-between items-center">
        {{ $assignments->withQueryString()->links() }}
      </div>
    </div>

    <div class="lg:col-span-5 rounded-xl border bs surface overflow-hidden">
      <div class="px-4 py-3 surface-2 border-b bs">
        <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Top Performance (Impact Score)</h6>
      </div>
      <div class="overflow-x-auto relative">
        <table class="min-w-full border-collapse text-[13px]">
          <thead>
            <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">User</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Role</th>
              <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Score</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200/50">
            @forelse($performance as $p)
              <tr class="hover:surface-2 transition border-b bs">
                <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $p->display_name }}</td>
                <td class="px-3 py-2.5 text-xs t2">{{ $p->role }}</td>
                <td class="px-3 py-2.5 text-right font-semibold text-indigo-600 text-[12.5px]">{{ $p->impact_score }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center py-8 text-xs t3">No performance data.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection

