@extends('admin.layouts.app')
@section('title','Meetings & Warnings')
@section('content')
<div class="row g-3">
 <div class="col-lg-8"><div class="card p-0 overflow-auto"><div class="p-3 border-bottom"><h5 class="mb-0">Circle Meetings</h5></div><table class="table table-sm mb-0"><thead><tr><th>Date</th><th>Circle</th><th>Status</th><th>Mode</th></tr></thead><tbody>@forelse($meetings as $m)<tr><td>{{ \Illuminate\Support\Carbon::parse($m->meeting_date)->format('d M Y') }}</td><td>{{ $m->circle_name ?? $m->circle_id }}</td><td>{{ $m->status ?? '-' }}</td><td>{{ $m->mode ?? '-' }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-3">No meetings found.</td></tr>@endforelse</tbody></table></div>{{ $meetings->links() }}</div>
 <div class="col-lg-4"><div class="card p-0 overflow-auto"><div class="p-3 border-bottom"><h6 class="mb-0">Absence Warnings</h6></div><table class="table table-sm mb-0"><thead><tr><th>User</th><th>Level</th><th>Resolved</th></tr></thead><tbody>@forelse($warnings as $w)<tr><td>{{ $w->user_id }}</td><td>{{ $w->warning_level ?? '-' }}</td><td><span class="badge bg-{{ !empty($w->resolved) ? 'success':'warning text-dark' }}">{{ !empty($w->resolved) ? 'yes':'no' }}</span></td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-3">No warnings found.</td></tr>@endforelse</tbody></table></div>{{ $warnings->links() }}</div>
</div>
@endsection
