@extends('admin.layouts.app')

@section('title', $mode === 'edit' ? 'Edit Campaign' : 'Create Campaign')

@section('content')
    @include('admin.campaigns.partials.flash')
    @php
        $filters = old('filters', $campaign->filters ?: []);
        $campaignType = old('campaign_type', $campaign->campaign_type ?: 'email_only');
        $audienceType = old('audience_type', $campaign->audience_type ?: 'all_members');
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $mode === 'edit' ? 'Edit Campaign' : 'Create Campaign' }}</h1>
        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form id="campaignForm" method="POST" action="{{ $mode === 'edit' ? route('admin.campaigns.update', $campaign) : route('admin.campaigns.store') }}">
        @csrf
        @if ($mode === 'edit') @method('PUT') @endif
        <input type="hidden" name="action" id="campaignAction" value="draft">

        <div class="row g-3">
            <div class="col-lg-8">
                <div class="card shadow-sm mb-3"><div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Campaign Title</label>
                        <input type="text" name="title" class="form-control" value="{{ old('title', $campaign->title) }}" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Campaign Type</label>
                        <select name="campaign_type" id="campaignType" class="form-select" required>
                            <option value="email_only" @selected($campaignType === 'email_only')>Email Only</option>
                            <option value="notification_only" @selected($campaignType === 'notification_only')>Notification Only</option>
                            <option value="email_and_notification" @selected($campaignType === 'email_and_notification')>Email + Notification</option>
                        </select>
                    </div>
                    <div class="email-fields">
                        <div class="mb-3">
                            <label class="form-label">Subject</label>
                            <input type="text" name="subject" class="form-control" value="{{ old('subject', $campaign->subject) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email Body</label>
                            <textarea name="email_body" rows="10" class="form-control" placeholder="HTML content is supported">{{ old('email_body', $campaign->email_body) }}</textarea>
                        </div>
                    </div>
                    <div class="notification-fields">
                        <div class="mb-3">
                            <label class="form-label">Notification Title</label>
                            <input type="text" name="notification_title" class="form-control" value="{{ old('notification_title', $campaign->notification_title) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notification Message</label>
                            <textarea name="notification_message" rows="4" class="form-control">{{ old('notification_message', $campaign->notification_message) }}</textarea>
                        </div>
                    </div>
                </div></div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-3"><div class="card-body">
                    <h2 class="h6">Audience Selection</h2>
                    <div class="mb-3">
                        <label class="form-label">Audience</label>
                        <select name="audience_type" id="audienceType" class="form-select" required>
                            <option value="all_members" @selected($audienceType === 'all_members')>All Members</option>
                            <option value="city" @selected($audienceType === 'city')>City Wise</option>
                            <option value="circle" @selected($audienceType === 'circle')>Circle Wise</option>
                            <option value="company" @selected($audienceType === 'company')>Company Wise</option>
                            <option value="category" @selected($audienceType === 'category')>Business Category Wise</option>
                            <option value="membership_status" @selected($audienceType === 'membership_status')>Membership Status Wise</option>
                            <option value="specific_members" @selected($audienceType === 'specific_members')>Specific Members</option>
                            <option value="custom_filter" @selected($audienceType === 'custom_filter')>Custom Filter</option>
                        </select>
                    </div>

                    @foreach ([['cities','City Wise',$filterOptions['cities']], ['companies','Company Wise',$filterOptions['companies']], ['membership_statuses','Membership Status Wise',$filterOptions['membership_statuses']]] as [$key,$label,$options])
                        <div class="filter-block" data-filter="{{ $key }}">
                            <label class="form-label">{{ $label }}</label>
                            <select name="filters[{{ $key }}][]" class="form-select select2" multiple>
                                @foreach ($options as $option)
                                    <option value="{{ $option }}" @selected(in_array($option, $filters[$key] ?? [], true))>{{ $option }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endforeach

                    <div class="filter-block" data-filter="circle_ids">
                        <label class="form-label">Circle Wise</label>
                        <select name="filters[circle_ids][]" class="form-select select2" multiple>
                            @foreach ($filterOptions['circles'] as $circle)
                                <option value="{{ $circle['id'] }}" @selected(in_array($circle['id'], $filters['circle_ids'] ?? [], true))>{{ $circle['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-block" data-filter="category_ids">
                        <label class="form-label">Business Category Wise</label>
                        <select name="filters[category_ids][]" class="form-select select2" multiple>
                            @foreach ($filterOptions['categories'] as $category)
                                <option value="{{ $category['id'] }}" @selected(in_array($category['id'], $filters['category_ids'] ?? [], true))>{{ $category['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="filter-block" data-filter="user_ids">
                        <label class="form-label">Specific Members</label>
                        <select name="filters[user_ids][]" id="memberSelect" class="form-select select2" multiple>
                            @foreach (($filters['user_ids'] ?? []) as $userId)
                                <option value="{{ $userId }}" selected>{{ $userId }}</option>
                            @endforeach
                        </select>
                    </div>
                </div></div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-outline-primary" onclick="document.getElementById('campaignAction').value='draft'">Save Draft</button>
                    <button type="button" id="previewRecipientsBtn" class="btn btn-outline-secondary">Preview Recipients</button>
                    <button type="submit" class="btn btn-success" onclick="document.getElementById('campaignAction').value='send'; return confirm('Send this campaign now? This cannot be undone.');">Send Campaign</button>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm mt-4" id="previewCard" style="display:none;">
        <div class="card-header d-flex justify-content-between"><strong>Preview Recipients</strong><span>Total: <span id="previewTotal">0</span></span></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>City</th><th>Company</th><th>Membership</th><th>Circle</th></tr></thead><tbody id="previewBody"></tbody></table></div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const campaignType = document.getElementById('campaignType');
    const audienceType = document.getElementById('audienceType');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;

    $('.select2').select2({ width: '100%' });
    $('#memberSelect').select2({
        width: '100%',
        ajax: {
            url: '{{ route('admin.campaigns.member-search') }}',
            dataType: 'json',
            delay: 250,
            data: params => ({ search: params.term || '' }),
            processResults: data => ({ results: (data.items || []).map(item => ({ id: item.id, text: `${item.display_name} (${item.email || item.phone || 'No contact'})` })) })
        }
    });

    function syncTypeFields() {
        const type = campaignType.value;
        document.querySelectorAll('.email-fields').forEach(el => el.style.display = (type === 'email_only' || type === 'email_and_notification') ? '' : 'none');
        document.querySelectorAll('.notification-fields').forEach(el => el.style.display = (type === 'notification_only' || type === 'email_and_notification') ? '' : 'none');
    }
    function syncFilterFields() {
        const type = audienceType.value;
        const visible = {
            city: ['cities'], circle: ['circle_ids'], company: ['companies'], category: ['category_ids'], membership_status: ['membership_statuses'], specific_members: ['user_ids'],
            custom_filter: ['cities','circle_ids','companies','category_ids','membership_statuses']
        }[type] || [];
        document.querySelectorAll('.filter-block').forEach(el => el.style.display = visible.includes(el.dataset.filter) ? 'block' : 'none');
    }
    campaignType.addEventListener('change', syncTypeFields);
    audienceType.addEventListener('change', syncFilterFields);
    syncTypeFields(); syncFilterFields();

    document.getElementById('previewRecipientsBtn').addEventListener('click', async () => {
        const formData = new FormData(document.getElementById('campaignForm'));
        const response = await fetch('{{ route('admin.campaigns.preview-recipients') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: formData });
        const data = await response.json();
        if (!response.ok) { alert(data.message || 'Preview failed.'); return; }
        document.getElementById('previewTotal').textContent = data.total;
        document.getElementById('previewBody').innerHTML = (data.recipients || []).map(row => `<tr><td>${row.display_name || '-'}</td><td>${row.email || '-'}</td><td>${row.phone || '-'}</td><td>${row.city || '-'}</td><td>${row.company_name || '-'}</td><td>${row.membership_status || '-'}</td><td>${row.circle_name || '-'}</td></tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted">No recipients found.</td></tr>';
        document.getElementById('previewCard').style.display = 'block';
    });
})();
</script>
@endpush
