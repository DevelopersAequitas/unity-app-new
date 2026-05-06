@extends('admin.layouts.app')
@section('title','Event Management')
@section('content')
<div class="card p-0 overflow-auto">
 <div class="p-3 border-bottom"><h5 class="mb-0">Events with P&L</h5></div>
 <table class="table table-sm mb-0"><thead><tr><th>Event</th><th>Date</th><th>Status</th><th>Revenue</th><th>Expenses</th><th>Net</th></tr></thead><tbody>
 @forelse($eventRows as $row)
 <tr>
   <td>{{ $row['event']->title ?? $row['event']->name ?? 'Untitled' }}</td>
   <td>{{ optional($row['event']->start_at)->format('d M Y') }}</td>
   <td><span class="badge bg-secondary">{{ $row['event']->status ?? 'draft' }}</span></td>
   <td>₹{{ number_format($row['revenue'],2) }}</td>
   <td>₹{{ number_format($row['expense'],2) }}</td>
   <td class="fw-semibold {{ $row['net'] >= 0 ? 'text-success':'text-danger' }}">₹{{ number_format($row['net'],2) }}</td>
 </tr>
 @empty <tr><td colspan="6" class="text-center text-muted py-3">No events found.</td></tr>@endforelse
 </tbody></table>
</div>
{{ $events->links() }}
@endsection
