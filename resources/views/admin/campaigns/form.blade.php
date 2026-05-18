@extends('admin.layouts.app')

@section('title', $mode === 'edit' ? 'Edit Campaign' : 'Create Campaign')

@section('content')
    @include('admin.campaigns.partials.flash')
    @php
        $filters = old('filters', $campaign->filters ?: []);
        $campaignType = old('campaign_type', $campaign->campaign_type ?: 'email_only');
        $audienceType = old('audience_type', $campaign->audience_type ?: 'all_members');
        $showEmailFields = in_array($campaignType, ['email_only', 'email_and_notification'], true);
        $showNotificationFields = in_array($campaignType, ['notification_only', 'email_and_notification'], true);
        $selectedBusinessCategoryIds = collect($filters['business_category_ids'] ?? $filters['category_ids'] ?? [])->map(fn ($id) => (string) $id)->all();
        $selectedPamphletId = old('pamphlet_id', $campaign->pamphlet_id ?? '');
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $mode === 'edit' ? 'Edit Campaign' : 'Create Campaign' }}</h1>
        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    <form id="campaignForm" method="POST" action="{{ $mode === 'edit' ? route('admin.campaigns.update', $campaign) : route('admin.campaigns.store') }}">
        @csrf
        @if ($mode === 'edit') @method('PUT') @endif
        <input type="hidden" name="action" id="campaignAction" value="draft">
        <input type="hidden" name="pamphlet_id" id="pamphletId" value="{{ $selectedPamphletId }}">

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
                    <div id="emailFields" class="email-fields {{ $showEmailFields ? '' : 'd-none' }}">
                        <div class="mb-3">
                            <label class="form-label" for="campaignSubject">Subject</label>
                            <input type="text" id="campaignSubject" name="subject" class="form-control" value="{{ old('subject', $campaign->subject ?? '') }}" @required($showEmailFields)>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0" for="campaignEmailBody">Email Body</label>
                                <button type="button" class="btn btn-sm btn-outline-primary select-pamphlet-btn" data-target="email">Select Pamphlet</button>
                            </div>
                            <textarea id="campaignEmailBody" name="email_body" rows="10" class="form-control" placeholder="HTML content is supported" @required($showEmailFields)>{{ old('email_body', $campaign->email_body ?? '') }}</textarea>
                        </div>
                    </div>
                    <div id="notificationFields" class="notification-fields {{ $showNotificationFields ? '' : 'd-none' }}">
                        <div class="mb-3">
                            <label class="form-label" for="campaignNotificationTitle">Notification Title</label>
                            <input type="text" id="campaignNotificationTitle" name="notification_title" class="form-control" value="{{ old('notification_title', $campaign->notification_title ?? '') }}" @required($showNotificationFields)>
                        </div>
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label mb-0" for="campaignNotificationMessage">Notification Message</label>
                                <button type="button" class="btn btn-sm btn-outline-primary select-pamphlet-btn" data-target="notification">Select Pamphlet</button>
                            </div>
                            <textarea id="campaignNotificationMessage" name="notification_message" rows="4" class="form-control" @required($showNotificationFields)>{{ old('notification_message', $campaign->notification_message ?? '') }}</textarea>
                        </div>
                    </div>
                </div></div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm mb-3"><div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h2 class="h6 mb-0">Audience Selection</h2>
                        <button type="button" id="importAudienceBtn" class="btn btn-sm btn-outline-primary">Import</button>
                    </div>
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

                    <div class="filter-block" data-filter="business_category_ids">
                        <label class="form-label">Business Category Wise</label>
                        <select name="filters[business_category_ids][]" class="form-select select2" multiple>
                            @foreach ($filterOptions['categories'] as $category)
                                <option value="{{ $category['id'] }}" @selected(in_array((string) $category['id'], $selectedBusinessCategoryIds, true))>{{ $category['name'] }}</option>
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
        <div id="previewDebug" class="small text-muted px-3 py-2 border-bottom" style="display:none;"></div>
        <div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>City</th><th>Company</th><th>Membership</th><th>Circle</th></tr></thead><tbody id="previewBody"></tbody></table></div>
    </div>

    <div class="modal fade" id="audienceImportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Import Audience Values</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><input type="search" id="audienceImportSearch" class="form-control" placeholder="Search values"></div>
                <div id="audienceImportList" class="list-group"></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>

    <div class="modal fade" id="pamphletSelectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Select Pamphlet</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body"><div id="pamphletList" class="row g-3"></div></div>
            <div class="modal-footer"><a href="{{ route('admin.campaign-pamphlets.create') }}" class="btn btn-outline-primary">Add Pamphlet</a><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
        </div></div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const campaignType = document.getElementById('campaignType');
    const audienceType = document.getElementById('audienceType');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const filterOptions = @json($filterOptions);
    let pamphlets = [];
    let pamphletTarget = 'both';

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

    function setSectionVisibility(section, visible, requiredFieldNames) {
        section.classList.toggle('d-none', !visible);
        section.querySelectorAll('input, textarea, select').forEach((field) => {
            field.required = visible && requiredFieldNames.includes(field.name);
        });
    }

    function syncTypeFields() {
        const type = campaignType.value;
        const emailVisible = type === 'email_only' || type === 'email_and_notification';
        const notificationVisible = type === 'notification_only' || type === 'email_and_notification';

        setSectionVisibility(document.getElementById('emailFields'), emailVisible, ['subject', 'email_body']);
        setSectionVisibility(document.getElementById('notificationFields'), notificationVisible, ['notification_title', 'notification_message']);
    }

    function syncFilterFields() {
        const type = audienceType.value;
        const visible = {
            city: ['cities'], circle: ['circle_ids'], company: ['companies'], category: ['business_category_ids'], membership_status: ['membership_statuses'], specific_members: ['user_ids'],
            custom_filter: ['cities','circle_ids','companies','business_category_ids','membership_statuses']
        }[type] || [];
        document.querySelectorAll('.filter-block').forEach(el => el.style.display = visible.includes(el.dataset.filter) ? 'block' : 'none');
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[char]));
    }

    function selectForFilter(filterKey) {
        return document.querySelector(`.filter-block[data-filter="${filterKey}"] select`);
    }

    function addSelectValue(select, value, label) {
        if (!select) return;
        const stringValue = String(value);
        if (!Array.from(select.options).some(option => option.value === stringValue)) {
            select.add(new Option(label || stringValue, stringValue, true, true));
        }
        const current = $(select).val() || [];
        if (!current.includes(stringValue)) current.push(stringValue);
        $(select).val(current).trigger('change');
    }

    function audienceImportItems(type) {
        if (type === 'all_members') return [{ value: 'all_members', label: 'Import all members', filter: null }];
        if (type === 'city') return (filterOptions.cities || []).map(value => ({ value, label: value, filter: 'cities' }));
        if (type === 'circle') return (filterOptions.circles || []).map(item => ({ value: item.id, label: item.name, filter: 'circle_ids' }));
        if (type === 'company') return (filterOptions.companies || []).map(value => ({ value, label: value, filter: 'companies' }));
        if (type === 'category') return (filterOptions.categories || []).map(item => ({ value: item.id, label: item.name, filter: 'business_category_ids' }));
        if (type === 'membership_status') return (filterOptions.membership_statuses || []).map(value => ({ value, label: value, filter: 'membership_statuses' }));
        return [];
    }

    async function renderAudienceImportList(search = '') {
        const list = document.getElementById('audienceImportList');
        const type = audienceType.value;
        let items = audienceImportItems(type);
        if (type === 'specific_members') {
            const response = await fetch(`{{ route('admin.campaigns.member-search') }}?search=${encodeURIComponent(search)}`, { headers: { 'Accept': 'application/json' } });
            const data = await response.json();
            items = (data.items || []).map(item => ({ value: item.id, label: `${item.display_name} (${item.email || item.phone || 'No contact'})`, filter: 'user_ids' }));
        } else if (search) {
            const needle = search.toLowerCase();
            items = items.filter(item => String(item.label).toLowerCase().includes(needle));
        }

        list.innerHTML = items.map(item => `<button type="button" class="list-group-item list-group-item-action audience-import-item" data-filter="${escapeHtml(item.filter || '')}" data-value="${escapeHtml(item.value)}" data-label="${escapeHtml(item.label)}">${escapeHtml(item.label)}</button>`).join('') || '<div class="text-muted p-3">No values found.</div>';
    }

    document.getElementById('importAudienceBtn').addEventListener('click', async () => {
        document.getElementById('audienceImportSearch').value = '';
        await renderAudienceImportList();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('audienceImportModal')).show();
    });

    document.getElementById('audienceImportSearch').addEventListener('input', async (event) => {
        await renderAudienceImportList(event.target.value || '');
    });

    document.getElementById('audienceImportList').addEventListener('click', (event) => {
        const item = event.target.closest('.audience-import-item');
        if (!item) return;
        if (!item.dataset.filter) {
            bootstrap.Modal.getOrCreateInstance(document.getElementById('audienceImportModal')).hide();
            return;
        }
        addSelectValue(selectForFilter(item.dataset.filter), item.dataset.value, item.dataset.label);
    });

    function pamphletImageHtml(url) {
        return url ? `<p><img src="${url}" style="max-width:100%;height:auto;"></p>` : '';
    }

    function applyPamphlet(pamphlet, target = 'both') {
        const type = campaignType.value;
        document.getElementById('pamphletId').value = pamphlet.id;
        if ((target === 'email' || target === 'both') && (type === 'email_only' || type === 'email_and_notification')) {
            document.getElementById('campaignEmailBody').value = `${pamphlet.content || ''}${pamphletImageHtml(pamphlet.image_url || '')}`;
        }
        if ((target === 'notification' || target === 'both') && (type === 'notification_only' || type === 'email_and_notification')) {
            document.getElementById('campaignNotificationMessage').value = pamphlet.short_message || pamphlet.title || '';
        }
    }

    async function loadPamphlets() {
        if (pamphlets.length) return pamphlets;
        const response = await fetch('{{ route('admin.campaign-pamphlets.select-list') }}', { headers: { 'Accept': 'application/json' } });
        pamphlets = await response.json();
        return pamphlets;
    }

    async function renderPamphlets() {
        const items = await loadPamphlets();
        const list = document.getElementById('pamphletList');
        list.innerHTML = items.map(item => `
            <div class="col-md-6">
                <div class="card h-100">
                    ${item.image_url ? `<img src="${escapeHtml(item.image_url)}" class="card-img-top" style="height:140px;object-fit:cover;" alt="${escapeHtml(item.title)}">` : ''}
                    <div class="card-body">
                        <h6 class="card-title">${escapeHtml(item.title)}</h6>
                        <p class="small text-muted">${escapeHtml(item.short_message || '')}</p>
                        <button type="button" class="btn btn-sm btn-primary pamphlet-choose" data-id="${item.id}">Select</button>
                    </div>
                </div>
            </div>`).join('') || '<div class="col-12 text-muted">No active pamphlets found.</div>';
    }

    document.querySelectorAll('.select-pamphlet-btn').forEach(button => {
        button.addEventListener('click', async () => {
            pamphletTarget = button.dataset.target || 'both';
            await renderPamphlets();
            bootstrap.Modal.getOrCreateInstance(document.getElementById('pamphletSelectModal')).show();
        });
    });

    document.getElementById('pamphletList').addEventListener('click', (event) => {
        const button = event.target.closest('.pamphlet-choose');
        if (!button) return;
        const pamphlet = pamphlets.find(item => item.id === button.dataset.id);
        if (!pamphlet) return;
        applyPamphlet(pamphlet, campaignType.value === 'email_and_notification' ? 'both' : pamphletTarget);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('pamphletSelectModal')).hide();
    });

    campaignType.addEventListener('change', syncTypeFields);
    audienceType.addEventListener('change', syncFilterFields);
    syncTypeFields(); syncFilterFields();

    document.getElementById('previewRecipientsBtn').addEventListener('click', async () => {
        const formData = new FormData(document.getElementById('campaignForm'));
        const response = await fetch('{{ route('admin.campaigns.preview-recipients') }}', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' }, body: formData });
        const data = await response.json();
        if (!response.ok) { alert(data.message || 'Preview failed.'); return; }
        document.getElementById('previewTotal').textContent = data.total;
        const debug = data.debug || {};
        const selectedCategoryIds = debug.selected_business_category_ids || [];
        const debugEl = document.getElementById('previewDebug');
        if (selectedCategoryIds.length) {
            debugEl.textContent = `Selected category id(s): ${selectedCategoryIds.join(', ')} | Matched users: ${debug.matched_users_count ?? data.total}`;
            debugEl.style.display = 'block';
        } else {
            debugEl.textContent = '';
            debugEl.style.display = 'none';
        }
        document.getElementById('previewBody').innerHTML = (data.recipients || []).map(row => `<tr><td>${row.display_name || '-'}</td><td>${row.email || '-'}</td><td>${row.phone || '-'}</td><td>${row.city || '-'}</td><td>${row.company_name || '-'}</td><td>${row.membership_status || '-'}</td><td>${row.circle_name || '-'}</td></tr>`).join('') || '<tr><td colspan="7" class="text-center text-muted">No recipients found.</td></tr>';
        document.getElementById('previewCard').style.display = 'block';
    });
})();
</script>
@endpush
