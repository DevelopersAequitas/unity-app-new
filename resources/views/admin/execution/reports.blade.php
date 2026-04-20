@extends('admin.layouts.app')
@section('title','Reports Center')
@section('content')
<div class="row g-3 mb-3">
 @foreach($reportCards as $label => $value)
  <div class="col-md-3"><div class="card p-3 h-100"><small class="text-muted text-uppercase">{{ str_replace('_',' ', $label) }}</small><h5 class="mb-0">{{ is_numeric($value) ? number_format((float)$value, 2) : $value }}</h5></div></div>
 @endforeach
</div>
<div class="card p-0 overflow-auto">
 <div class="p-3 border-bottom d-flex justify-content-between"><h6 class="mb-0">Latest Payments</h6><a href="{{ route('admin.execution.finance') }}" class="btn btn-sm btn-outline-primary">Open Revenue & Billing</a></div>
 <table class="table table-sm mb-0"><thead><tr><th>ID</th><th>User</th><th>Category</th><th>Status</th><th>Amount</th></tr></thead><tbody>@forelse($latestPayments as $p)<tr><td>{{ $p->id }}</td><td>{{ $p->user_id }}</td><td>{{ $p->display_source ?? '-' }}</td><td>{{ $p->status }}</td><td>₹{{ number_format((float)$p->display_amount,2) }}</td></tr>@empty<tr><td colspan="5" class="text-center text-muted py-3">No payment records.</td></tr>@endforelse</tbody></table>
</div>
@endsection
