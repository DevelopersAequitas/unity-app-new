@extends('admin.layouts.app')

@section('title', $credential->exists ? 'Edit Scan Credential' : 'Create Scan Credential')

@section('content')
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1 font-bold text-slate-800">{{ $credential->exists ? 'Edit Scan Credential' : 'Create Scan Credential' }}</h1>
            <p class="text-xs text-muted mb-0">Configure scanner login credentials and assign event permissions</p>
        </div>
        <a href="{{ route('admin.event-scan-credentials.index') }}" class="btn btn-outline-secondary px-3 py-1.5 text-xs font-medium rounded-3">
            <i class="bi bi-arrow-left me-1"></i>Back to Credentials
        </a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show text-xs py-2 px-3 mb-3" role="alert">
            <div class="font-bold mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Please fix the following errors:</div>
            <ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding:0.75rem;"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show text-xs py-2 px-3 mb-3" role="alert">
            <i class="bi bi-check-circle me-1"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding:0.75rem;"></button>
        </div>
    @endif

    <div class="row g-3">
        <!-- Main Form Column -->
        <div class="col-lg-8">
            <form method="POST" action="{{ $credential->exists ? route('admin.event-scan-credentials.update', $credential->id) : route('admin.event-scan-credentials.store') }}" class="card card-body border-0 shadow-sm rounded-3 p-4" autocomplete="off">
                @csrf
                @if($credential->exists) @method('PUT') @endif

                <h6 class="text-xs uppercase font-bold text-indigo-600 mb-3 tracking-wider pb-2 border-bottom">
                    <i class="bi bi-person-badge me-1"></i>User & Login Credentials
                </h6>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-xs font-semibold text-slate-700">Person Name <span class="text-danger">*</span></label>
                        <input class="form-control text-xs" name="name" value="{{ old('name', $credential->name) }}" placeholder="e.g. John Doe" required autocomplete="off">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-xs font-semibold text-slate-700">Username / Login ID <span class="text-danger">*</span></label>
                        <input class="form-control text-xs font-mono" name="username" value="{{ old('username', $credential->username) }}" placeholder="e.g. johndoe@gmail.com" required autocomplete="off">
                    </div>

                    <!-- Display Current / Old Password if Editing -->
                    @if($credential->exists)
                        <div class="col-12">
                            <div class="p-3 rounded-3 bg-amber-50/60 border border-amber-200">
                                <label class="form-label text-xs font-bold text-amber-900 mb-1">
                                    <i class="bi bi-key-fill me-1 text-amber-600"></i>Current / Old Password
                                </label>
                                @if(!empty($credential->plain_password))
                                    <div class="input-group input-group-sm max-w-sm mt-1">
                                        <input type="password" class="form-control text-xs font-mono bg-white" value="{{ $credential->plain_password }}" id="oldPasswordInput" readonly>
                                        <button class="btn btn-outline-secondary text-xs px-3" type="button" onclick="const input = document.getElementById('oldPasswordInput'); const icon = this.querySelector('i'); if(input.type==='password'){ input.type='text'; icon.className='bi bi-eye-slash'; } else { input.type='password'; icon.className='bi bi-eye'; }">
                                            <i class="bi bi-eye"></i> Show
                                        </button>
                                        <button class="btn btn-outline-secondary text-xs px-2.5" type="button" onclick="navigator.clipboard.writeText('{{ $credential->plain_password }}'); alert('Password copied to clipboard!');" title="Copy Password">
                                            <i class="bi bi-clipboard"></i> Copy
                                        </button>
                                    </div>
                                    <div class="text-[11px] text-amber-700 mt-1">This is the current plain-text login password for this scanner user.</div>
                                @else
                                    <div class="text-xs text-amber-800 italic">Password is encrypted/hashed in database. Enter a new password below if you wish to change it.</div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <div class="col-md-6">
                        <label class="form-label text-xs font-semibold text-slate-700">
                            {{ $credential->exists ? 'New Password (leave blank to keep current)' : 'Password' }} <span class="text-danger">{{ $credential->exists ? '' : '*' }}</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control text-xs" name="password" id="inputPassword" value="{{ old('password') }}" {{ $credential->exists ? '' : 'required' }} placeholder="{{ $credential->exists ? '••••••••' : 'Min 8 characters' }}" autocomplete="new-password">
                            <button class="btn btn-outline-secondary text-xs px-3" type="button" onclick="togglePasswordVisibility('inputPassword', this)" title="Show/Hide Password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label text-xs font-semibold text-slate-700">
                            Confirm {{ $credential->exists ? 'New Password' : 'Password' }} <span class="text-danger">{{ $credential->exists ? '' : '*' }}</span>
                        </label>
                        <div class="input-group">
                            <input type="password" class="form-control text-xs" name="password_confirmation" id="inputConfirmPassword" value="{{ old('password_confirmation') }}" {{ $credential->exists ? '' : 'required' }} placeholder="{{ $credential->exists ? '••••••••' : 'Re-enter password' }}" autocomplete="new-password">
                            <button class="btn btn-outline-secondary text-xs px-3" type="button" onclick="togglePasswordVisibility('inputConfirmPassword', this)" title="Show/Hide Password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="col-md-12 mt-4">
                        <h6 class="text-xs uppercase font-bold text-indigo-600 mb-3 tracking-wider pb-2 border-bottom">
                            <i class="bi bi-building me-1"></i>Hotel & Assigned Events (Multi-Select)
                        </h6>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label text-xs font-semibold text-slate-700">Hotel / Venue Name <span class="text-danger">*</span></label>
                        <input class="form-control text-xs" name="hotel_name" value="{{ old('hotel_name', $credential->hotel_name) }}" placeholder="e.g. Hyatt Ahmedabad" required>
                    </div>

                    <!-- Multiple Assigned Events Select -->
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <label class="form-label text-xs font-semibold text-slate-700 mb-0">
                                Assigned Events (Select Multiple) <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none text-indigo-600 font-medium" onclick="selectAllEvents(true)">
                                    Select All
                                </button>
                                <span class="text-slate-300">|</span>
                                <button type="button" class="btn btn-link btn-xs p-0 text-decoration-none text-slate-500 font-medium" onclick="selectAllEvents(false)">
                                    Deselect All
                                </button>
                            </div>
                        </div>

                        @php
                            $selectedEventIds = old('event_ids', $credential->assigned_event_ids);
                            if (empty($selectedEventIds) && $credential->event_id) {
                                $selectedEventIds = [$credential->event_id];
                            }
                        @endphp

                        <div class="border rounded-3 p-3 bg-slate-50/50" style="max-height: 260px; overflow-y: auto;">
                            <div class="row g-2">
                                @foreach($events as $event)
                                    @php
                                        $isChecked = in_array((string) $event->id, array_map('strval', $selectedEventIds), true);
                                    @endphp
                                    <div class="col-md-6">
                                        <div class="event-card-item position-relative rounded-3 transition cursor-pointer"
                                             style="padding: 10px; cursor: pointer; transition: all 0.2s; border: {{ $isChecked ? '2px solid #4f46e5' : '1px solid #e2e8f0' }}; background-color: {{ $isChecked ? '#eef2ff' : '#ffffff' }}; box-shadow: {{ $isChecked ? '0 2px 4px rgba(79, 70, 229, 0.15)' : 'none' }};"
                                             onclick="toggleEventCard(this)">
                                            <input class="assigned-event-cb d-none" type="checkbox" name="event_ids[]" value="{{ $event->id }}" @checked($isChecked)>
                                            
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="pe-2">
                                                    <div class="font-semibold text-xs text-slate-900 event-title">{{ $event->title }}</div>
                                                    <div class="text-[11px] text-muted mt-0.5">
                                                        <i class="bi bi-calendar-event me-1"></i>{{ optional($event->start_at)->format('d M Y, h:i A') ?? 'Scheduled' }}
                                                        @if($event->location_text) &bull; {{ Str::limit($event->location_text, 18) }} @endif
                                                    </div>
                                                </div>
                                                <div class="selected-badge {{ $isChecked ? '' : 'd-none' }}">
                                                    <span class="badge bg-indigo-600 text-white rounded-circle p-1 d-inline-flex align-items-center justify-content-center" style="width:22px; height:22px; background-color: #4f46e5 !important;">
                                                        <i class="bi bi-check-lg text-white" style="font-size: 13px;"></i>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="text-[11px] text-muted mt-1">Click on any event card to select or deselect it. You can select multiple events.</div>
                    </div>

                    <div class="col-md-12 mt-2">
                        <div class="form-check">
                            <input type="hidden" name="is_active" value="0">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive" @checked(old('is_active', $credential->is_active))>
                            <label class="form-check-label text-xs font-semibold text-slate-700" for="isActive">
                                Active Status (Allow scanner app login)
                            </label>
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex gap-2">
                    <button type="submit" class="btn btn-primary px-4 py-2 text-xs font-semibold rounded-3">
                        <i class="bi bi-check-circle me-1"></i>{{ $credential->exists ? 'Update Credential' : 'Create Credential' }}
                    </button>
                    <a href="{{ route('admin.event-scan-credentials.index') }}" class="btn btn-outline-secondary px-3 py-2 text-xs font-medium rounded-3">
                        Cancel
                    </a>
                </div>
            </form>
        </div>

        <!-- Right Side: Reset Password Panel (if Editing) -->
        @if($credential->exists)
            <div class="col-lg-4">
                <form method="POST" action="{{ route('admin.event-scan-credentials.reset-password', $credential->id) }}" class="card card-body border-0 shadow-sm rounded-3 p-4">
                    @csrf
                    <h6 class="text-xs uppercase font-bold text-amber-600 mb-3 tracking-wider pb-2 border-bottom">
                        <i class="bi bi-shield-lock me-1"></i>Reset Password Immediately
                    </h6>
                    <p class="text-xs text-muted mb-3">Quickly update the login password for this scanner account.</p>

                    <div class="mb-3">
                        <label class="form-label text-xs font-semibold text-slate-700">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control text-xs" name="password" id="resetPasswordInput" required placeholder="Min 8 characters">
                            <button class="btn btn-outline-secondary text-xs px-3" type="button" onclick="togglePasswordVisibility('resetPasswordInput', this)" title="Show/Hide Password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-xs font-semibold text-slate-700">Confirm New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control text-xs" name="password_confirmation" id="resetConfirmPasswordInput" required placeholder="Re-enter new password">
                            <button class="btn btn-outline-secondary text-xs px-3" type="button" onclick="togglePasswordVisibility('resetConfirmPasswordInput', this)" title="Show/Hide Password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                    </div>
                    <button class="btn btn-warning text-xs font-semibold w-100 py-2 rounded-3 text-amber-950">
                        <i class="bi bi-arrow-repeat me-1"></i>Reset Password
                    </button>
                </form>
            </div>
        @endif
    </div>
</div>

<script>
    window.togglePasswordVisibility = function(inputId, btn) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const icon = btn.querySelector('i');
        if (input.type === 'password') {
            input.type = 'text';
            if (icon) icon.className = 'bi bi-eye-slash';
        } else {
            input.type = 'password';
            if (icon) icon.className = 'bi bi-eye';
        }
    };

    window.toggleEventCard = function(card) {
        const cb = card.querySelector('.assigned-event-cb');
        if (!cb) return;
        cb.checked = !cb.checked;
        window.updateCardState(card, cb.checked);
    };

    window.updateCardState = function(card, isChecked) {
        const badge = card.querySelector('.selected-badge');
        if (isChecked) {
            card.style.borderColor = '#4f46e5';
            card.style.borderWidth = '2px';
            card.style.backgroundColor = '#eef2ff';
            card.style.boxShadow = '0 2px 4px rgba(79, 70, 229, 0.15)';
            if (badge) badge.classList.remove('d-none');
        } else {
            card.style.borderColor = '#e2e8f0';
            card.style.borderWidth = '1px';
            card.style.backgroundColor = '#ffffff';
            card.style.boxShadow = 'none';
            if (badge) badge.classList.add('d-none');
        }
    };

    window.selectAllEvents = function(select) {
        document.querySelectorAll('.event-card-item').forEach(card => {
            const cb = card.querySelector('.assigned-event-cb');
            if (cb) {
                cb.checked = select;
                window.updateCardState(card, select);
            }
        });
    };
</script>
@endsection
