@extends('admin.layouts.app')
@section('title','Revenue & Billing')
@section('content')
<div class="row g-3 mb-3">
 @foreach(['membership'=>'Membership','circle_fee'=>'Circle Fee','event'=>'Event','sponsor'=>'Sponsor','total'=>'Total'] as $k=>$label)
  <div class="col-md-2"><div class="card p-3"><small class="text-muted">{{ $label }}</small><h6 class="mb-0">@if(is_null($summary[$k] ?? null)) - @else ₹{{ number_format($summary[$k] ?? 0,2) }} @endif</h6></div></div>
 @endforeach
</div>
<div class="card p-3 mb-3">
 <form class="row g-2">
   @if($summary['supports_source_split'])
   <div class="col-md-3"><input class="form-control form-control-sm" name="source" value="{{ request('source') }}" placeholder="{{ $summary['source_column'] }}"></div>
   @endif
   <div class="col-md-3"><input class="form-control form-control-sm" name="status" value="{{ request('status') }}" placeholder="status"></div>
   <div class="col-md-2"><button class="btn btn-sm btn-primary">Filter</button></div>
   @unless($summary['supports_source_split'])
   <div class="col-md-4"><small class="text-muted">Category split is not available on current payments schema.</small></div>
   @endunless
 </form>
</div>
<div class="card p-0 overflow-auto mb-3"><table class="table table-sm mb-0"><thead><tr><th>ID</th><th>User</th>@if($summary['supports_source_split'])<th>{{ $summary['source_column'] ? ucfirst(str_replace('_', ' ', $summary['source_column'])) : 'Category' }}</th>@endif<th>Status</th><th>{{ ucfirst(str_replace('_', ' ', $summary['amount_column'])) }}</th><th>Date</th></tr></thead><tbody>@forelse($payments as $p)<tr><td>{{ $p->id }}</td><td>{{ $p->display_user }}</td>@if($summary['supports_source_split'])<td>{{ $p->display_source }}</td>@endif<td>{{ $p->status }}</td><td>₹{{ number_format((float)$p->display_amount,2) }}</td><td>{{ optional($p->created_at)->format('d M Y') }}</td></tr>@empty<tr><td colspan="{{ $summary['supports_source_split'] ? 6 : 5 }}" class="text-center text-muted py-3">No payments.</td></tr>@endforelse</tbody></table></div>
{{ $payments->links() }}
<div class="card p-0 overflow-auto"><div class="p-3 border-bottom"><h6 class="mb-0">Active Subscriptions (Zoho)</h6></div><table class="table table-sm mb-0"><thead><tr><th>User</th><th>Subscription</th><th>Plan</th><th>Status</th></tr></thead><tbody>@forelse($subscriptions as $s)<tr><td>{{ $s->display_user }}</td><td>{{ $s->zoho_subscription_id }}</td><td>{{ $s->zoho_plan_code }}</td><td>{{ $s->membership_status }}</td></tr>@empty<tr><td colspan="4" class="text-center text-muted py-3">No subscriptions found.</td></tr>@endforelse</tbody></table></div>
@endsection
