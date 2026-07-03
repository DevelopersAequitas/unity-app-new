@if (session('success'))<div class="alert alert-success shadow-sm"><i class="bi bi-check-circle me-1"></i>{{ session('success') }}</div>@endif
@if (session('error'))<div class="alert alert-danger shadow-sm"><i class="bi bi-exclamation-triangle me-1"></i>{{ session('error') }}</div>@endif
@if ($errors->any())<div class="alert alert-danger shadow-sm"><strong>Please fix the errors below.</strong><ul class="mb-0 mt-2">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
