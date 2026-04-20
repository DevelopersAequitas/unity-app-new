@extends('admin.layouts.app')
@section('title','Industry Management')
@section('content')
<div class="card p-0 overflow-auto">
 <div class="p-3 border-bottom d-flex justify-content-between"><h5 class="mb-0">Industries</h5><a href="{{ route('admin.circles.index') }}" class="btn btn-sm btn-outline-primary">Open Circles</a></div>
 <table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Total Circles</th><th>Status</th><th>Updated</th></tr></thead><tbody>
 @forelse($industries as $industry)
 <tr><td>{{ $industry->name }}</td><td>{{ $industry->circles_count ?? 0 }}</td><td><span class="badge bg-{{ ($industry->is_active ?? true) ? 'success':'secondary' }}">{{ ($industry->is_active ?? true) ? 'active':'inactive' }}</span></td><td>{{ optional($industry->updated_at)->format('d M Y') }}</td></tr>
 @empty <tr><td colspan="4" class="text-center text-muted py-3">No industries found.</td></tr>@endforelse
 </tbody></table>
</div>
{{ $industries->links() }}
@endsection
