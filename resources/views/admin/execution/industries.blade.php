@extends('admin.layouts.app')
@section('title','Industry Management')

@include('admin.partials.grid-head')

@section('content')
<div id="grid-root-container" class="light rounded-xl border bs p-4 relative admin-grid-card">
  <div class="rounded-xl border bs surface overflow-hidden">
    <div class="px-4 py-3 surface-2 border-b bs flex items-center justify-between">
      <h6 class="font-display font-semibold text-xs text-indigo-400 uppercase tracking-wider m-0">Industries</h6>
      <a href="{{ route('admin.circles.index') }}" class="px-3 py-1 rounded-lg border bs text-xs font-semibold t2 hover:t1 hover:surface-2 transition no-underline">Open Circles</a>
    </div>
    <div class="overflow-x-auto relative">
      <table class="min-w-full border-collapse text-[13px]">
        <thead>
          <tr class="text-[11px] uppercase tracking-wider t3 font-semibold surface-2 border-b bs">
            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Name</th>
            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Total Circles</th>
            <th class="th-cell surface-2 border-b bs px-3 py-2 text-left">Status</th>
            <th class="th-cell surface-2 border-b bs px-3 py-2 text-right">Updated</th>
          </tr>
        </thead>
        <tbody id="grid-body" class="divide-y divide-gray-200/50">
          @forelse($industries as $industry)
            <tr class="hover:surface-2 transition border-b bs">
              <td class="px-3 py-2.5 font-semibold t1 text-[12.5px] whitespace-nowrap">{{ $industry->name }}</td>
              <td class="px-3 py-2.5 text-[12.5px]">
                <span class="chip px-2.5 py-0.5 text-xs font-semibold bg-indigo-50 text-indigo-700 border-indigo-200">{{ $industry->circles_count ?? 0 }}</span>
              </td>
              <td class="px-3 py-2.5 text-xs">
                <span class="chip px-2.5 py-0.5 text-xs font-semibold {{ ($industry->is_active ?? true) ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-700 border-gray-200' }}">
                  {{ ($industry->is_active ?? true) ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-3 py-2.5 text-right text-xs t3 whitespace-nowrap">{{ optional($industry->updated_at)->format('d M Y') }}</td>
            </tr>
          @empty 
            <tr><td colspan="4" class="text-center py-8 text-xs t3">No industries found.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    <div id="grid-pagination" class="p-3 border-t bs flex justify-between items-center">
      {{ $industries->links() }}
    </div>
  </div>
</div>
@endsection

