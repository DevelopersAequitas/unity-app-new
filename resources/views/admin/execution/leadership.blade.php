@extends('admin.layouts.app')
@section('title','Leadership Control')
@section('content')
@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
<div class="row g-3 mb-3">
  <div class="col-12"><div class="card p-3"><h5 class="mb-0">Leadership Applications</h5></div></div>
  <div class="col-12"><div class="card p-0 overflow-auto"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Date</th></tr></thead><tbody>@forelse($applications as $app)<tr><td>{{ $app->applicant_name ?? '-' }}</td><td>{{ $app->applicant_email ?? '-' }}</td><td><span class="badge bg-secondary">{{ $app->status ?? 'pending' }}</span></td><td>{{ optional($app->created_at)->format('d M Y') }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-3">No applications found.</td></tr>@endforelse</tbody></table></div>{{ $applications->withQueryString()->links() }}</div>
</div>
<div class="row g-3">
  <div class="col-lg-7"><div class="card p-0 overflow-auto"><div class="p-3 border-bottom"><h6 class="mb-0">Active Assignments</h6></div><table class="table table-sm mb-0"><thead><tr><th>User</th><th>Role</th><th>Circle</th><th>Status</th></tr></thead><tbody>@forelse($assignments as $row)<tr><td>{{ $row->user_name }}</td><td><span class="badge bg-info text-dark">{{ $row->role }}</span></td><td>{{ $row->circle_name }}</td><td>{{ $row->status }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-3">No assignments found.</td></tr>@endforelse</tbody></table></div>{{ $assignments->withQueryString()->links() }}</div>
  <div class="col-lg-5"><div class="card p-0 overflow-auto"><div class="p-3 border-bottom"><h6 class="mb-0">Top Performance (Impact Score)</h6></div><table class="table table-sm mb-0"><thead><tr><th>User</th><th>Role</th><th>Score</th></tr></thead><tbody>@forelse($performance as $p)<tr><td>{{ $p->display_name }}</td><td>{{ $p->role }}</td><td>{{ $p->impact_score }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-3">No performance data.</td></tr>@endforelse</tbody></table></div></div>
</div>
@endsection
