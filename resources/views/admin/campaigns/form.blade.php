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
        $selectedEmailTemplateId = old('email_template_id', $campaign->email_template_id ?: optional($defaultEmailTemplate)->id);
        
        $schedule = $campaign->schedule;
        $scheduleType = old('schedule.schedule_type', $schedule ? $schedule->schedule_type : 'immediately');
        $startDate = old('schedule.start_date', $schedule ? ($schedule->start_date ? $schedule->start_date->format('Y-m-d') : '') : now()->toDateString());
        $endType = old('schedule.end_type', $schedule ? $schedule->end_type : 'never');
        $endDate = old('schedule.end_date', $schedule ? ($schedule->end_date ? $schedule->end_date->format('Y-m-d') : '') : '');
        $sendTime = old('schedule.send_time', $schedule ? substr($schedule->send_time, 0, 5) : '09:00');
        $timezone = old('schedule.timezone', $schedule ? $schedule->timezone : 'UTC');
        $recurrenceType = old('schedule.recurrence_type', $schedule ? $schedule->recurrence_type : 'daily');
        $frequencyInterval = old('schedule.frequency_interval', $schedule ? $schedule->frequency_interval : 1);
        $weekdays = old('schedule.weekdays', $schedule ? ($schedule->weekdays ? explode(',', $schedule->weekdays) : []) : []);
        $monthlyBasis = old('schedule.monthly_basis', $schedule ? $schedule->monthly_basis : 'date');
        $monthlyDayOfMonth = old('schedule.monthly_day_of_month', $schedule ? $schedule->monthly_day_of_month : 1);
        $monthlyPosition = old('schedule.monthly_position', $schedule ? $schedule->monthly_position : 'first');
        $monthlyDayOfWeek = old('schedule.monthly_day_of_week', $schedule ? $schedule->monthly_day_of_week : 'Monday');
        $yearlyMonth = old('schedule.yearly_month', $schedule ? $schedule->yearly_month : 1);
        $yearlyDay = old('schedule.yearly_day', $schedule ? $schedule->yearly_day : 1);
        $customUnit = old('schedule.custom_unit', $schedule ? $schedule->custom_unit : 'day');
        $cycleSendDays = old('schedule.cycle_send_days', $schedule ? $schedule->cycle_send_days : 2);
        $cyclePauseDays = old('schedule.cycle_pause_days', $schedule ? $schedule->cycle_pause_days : 2);
    @endphp

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">{{ $mode === 'edit' ? 'Edit Campaign' : 'Create Campaign' }}</h1>
            <p class="text-muted small mb-0">Build, customize, schedule, and launch your communication campaigns.</p>
        </div>
        <a href="{{ route('admin.campaigns.index') }}" class="btn btn-outline-secondary px-3 py-2 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Back to Campaigns
        </a>
    </div>

    <form id="campaignForm" method="POST" action="{{ $mode === 'edit' ? route('admin.campaigns.update', $campaign) : route('admin.campaigns.store') }}">
        @csrf
        @if ($mode === 'edit') @method('PUT') @endif
        <input type="hidden" name="action" id="campaignAction" value="draft">
        <input type="hidden" name="pamphlet_id" id="pamphletId" value="{{ $selectedPamphletId }}">
        <input type="hidden" name="email_template_id" id="emailTemplateId" value="{{ $selectedEmailTemplateId }}">

        <div class="row g-4">
            <!-- Left Column: Primary form sections -->
            <div class="col-lg-8">
                
                <!-- Card 1: Campaign Details -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <div class="d-flex align-items-center mb-1">
                            <div class="icon-circle me-2">
                                <i class="bi bi-file-earmark-text"></i>
                            </div>
                            <h5 class="card-title fw-bold mb-0 text-dark">Campaign Details</h5>
                        </div>
                        <p class="text-muted small mb-0 ms-5">Configure basic campaign metadata, types, and channel contents.</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label">Campaign Title</label>
                            <input type="text" name="title" class="form-control" value="{{ old('title', $campaign->title) }}" placeholder="e.g. June Monthly Newsletter" required>
                            <div class="form-text">A friendly name to identify this campaign in the list.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Campaign Type</label>
                            <select name="campaign_type" id="campaignType" class="form-select" required>
                                <option value="email_only" @selected($campaignType === 'email_only')>Email Only</option>
                                <option value="notification_only" @selected($campaignType === 'notification_only')>Notification Only</option>
                                <option value="email_and_notification" @selected($campaignType === 'email_and_notification')>Email + Notification</option>
                            </select>
                            <div class="form-text">Choose how recipients will be reached.</div>
                        </div>

                        <!-- Email specific fields -->
                        <div id="emailFields" class="email-fields {{ $showEmailFields ? '' : 'd-none' }} border-top pt-3 mt-3">
                            <div class="mb-3">
                                <label class="form-label" for="campaignSubject">Subject</label>
                                <input type="text" id="campaignSubject" name="subject" class="form-control" value="{{ old('subject', $campaign->subject ?? '') }}" placeholder="e.g. Check out our latest updates!" @required($showEmailFields)>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0" for="campaignEmailBody">Email Body</label>
                                    <button type="button" class="btn btn-sm btn-outline-primary select-pamphlet-btn fw-semibold" data-target="email">
                                        <i class="bi bi-image me-1"></i>Select Pamphlet
                                    </button>
                                </div>
                                <textarea id="campaignEmailBody" name="email_body" rows="10" class="form-control" placeholder="HTML content is supported" @required($showEmailFields)>{{ old('email_body', $campaign->email_body ?? '') }}</textarea>
                            </div>
                            
                            <!-- Template Selector -->
                            <div class="mb-4">
                                <label class="form-label d-block mb-3">Choose Email Layout Template</label>
                                <div class="row g-3">
                                    @foreach($emailTemplates as $tpl)
                                        <div class="col-md-4 col-sm-6">
                                            <div class="campaign-email-template-card card h-100 {{ $selectedEmailTemplateId == $tpl['id'] ? 'selected' : '' }}" data-template-id="{{ $tpl['id'] }}" tabindex="0">
                                                <div class="card-body p-3 d-flex flex-column justify-content-between">
                                                    <div>
                                                        <div class="campaign-template-thumb campaign-template-thumb-{{ str_replace('-', '_', $tpl['slug']) }} mb-2">
                                                            <span></span><span></span><span></span>
                                                        </div>
                                                        <h6 class="fw-bold mb-1 small">{{ $tpl['name'] }}</h6>
                                                        <p class="text-muted mb-3" style="font-size: 10px; line-height: 1.2;">{{ $tpl['description'] }}</p>
                                                    </div>
                                                    <button type="button" class="btn btn-xs w-100 template-select-label {{ $selectedEmailTemplateId == $tpl['id'] ? 'btn-primary' : 'btn-outline-primary' }}" style="font-size: 11px;">
                                                        {{ $selectedEmailTemplateId == $tpl['id'] ? 'Selected' : 'Select Template' }}
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Live Email Template Preview</label>
                                <div class="campaign-email-preview-shell shadow-sm">
                                    <div class="campaign-email-preview-header d-flex justify-content-between align-items-center px-3">
                                        <span>Peers Global Wrapper Preview</span>
                                        <span class="badge bg-light text-dark font-monospace" style="font-size: 10px;">Responsive HTML</span>
                                    </div>
                                    <div id="emailTemplatePreview" class="campaign-email-preview-body"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Notification specific fields -->
                        <div id="notificationFields" class="notification-fields {{ $showNotificationFields ? '' : 'd-none' }} border-top pt-3 mt-3">
                            <div class="mb-3">
                                <label class="form-label" for="campaignNotificationTitle">Notification Title</label>
                                <input type="text" id="campaignNotificationTitle" name="notification_title" class="form-control" value="{{ old('notification_title', $campaign->notification_title ?? '') }}" placeholder="e.g. New Announcement!" @required($showNotificationFields)>
                            </div>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label mb-0" for="campaignNotificationMessage">Notification Message</label>
                                    <button type="button" class="btn btn-sm btn-outline-primary select-pamphlet-btn fw-semibold" data-target="notification">
                                        <i class="bi bi-image me-1"></i>Select Pamphlet
                                    </button>
                                </div>
                                <textarea id="campaignNotificationMessage" name="notification_message" rows="4" class="form-control" placeholder="Type notification content here..." @required($showNotificationFields)>{{ old('notification_message', $campaign->notification_message ?? '') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Campaign Schedule -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <div class="d-flex align-items-center mb-1">
                            <div class="icon-circle me-2">
                                <i class="bi bi-calendar3"></i>
                            </div>
                            <h5 class="card-title fw-bold mb-0 text-dark">Campaign Schedule</h5>
                        </div>
                        <p class="text-muted small mb-0 ms-5">Choose when this campaign should start sending to your audience.</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="form-label d-block fw-semibold mb-3">Schedule Type</label>
                            
                            <!-- Hidden original radios for standard form submit / JS event triggers -->
                            <div class="visually-hidden">
                                <input type="radio" name="schedule[schedule_type]" id="sched_immediately" value="immediately" @checked($scheduleType === 'immediately')>
                                <input type="radio" name="schedule[schedule_type]" id="sched_once" value="once" @checked($scheduleType === 'once')>
                                <input type="radio" name="schedule[schedule_type]" id="sched_recurring" value="recurring" @checked($scheduleType === 'recurring')>
                            </div>

                            <div class="row g-3">
                                <!-- Send Immediately -->
                                <div class="col-md-4">
                                    <div class="schedule-type-card card h-100 cursor-pointer text-center py-3 px-2" data-value="immediately" tabindex="0">
                                        <div class="icon-circle bg-light text-primary mx-auto mb-2" style="width: 44px; height: 44px;">
                                            <i class="bi bi-lightning-charge fs-5"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1 small">Send Immediately</h6>
                                        <p class="text-muted mb-0" style="font-size: 11px;">Deliver now to all recipients.</p>
                                    </div>
                                </div>

                                <!-- Schedule Once -->
                                <div class="col-md-4">
                                    <div class="schedule-type-card card h-100 cursor-pointer text-center py-3 px-2" data-value="once" tabindex="0">
                                        <div class="icon-circle bg-light text-primary mx-auto mb-2" style="width: 44px; height: 44px;">
                                            <i class="bi bi-clock-history fs-5"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1 small">Schedule Once</h6>
                                        <p class="text-muted mb-0" style="font-size: 11px;">Run once at a specific date & time.</p>
                                    </div>
                                </div>

                                <!-- Recurring -->
                                <div class="col-md-4">
                                    <div class="schedule-type-card card h-100 cursor-pointer text-center py-3 px-2" data-value="recurring" tabindex="0">
                                        <div class="icon-circle bg-light text-primary mx-auto mb-2" style="width: 44px; height: 44px;">
                                            <i class="bi bi-arrow-repeat fs-5"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark mb-1 small">Recurring</h6>
                                        <p class="text-muted mb-0" style="font-size: 11px;">Repeat daily, weekly, monthly, etc.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Schedule Once fields -->
                        <div id="scheduleOnceFields" class="d-none border-top pt-3 mt-3">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="schedule[start_date]" id="once_start_date" class="form-control" value="{{ $startDate }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Start Time</label>
                                    <input type="time" name="schedule[send_time]" id="once_send_time" class="form-control" value="{{ $sendTime }}">
                                </div>
                            </div>
                            <div class="row g-3 mt-1">
                                <div class="col-md-12">
                                    <label class="form-label">Timezone</label>
                                    <select name="schedule[timezone]" id="once_timezone" class="form-select timezone-select select2">
                                        @foreach (\App\Services\AdminCampaigns\TimezonesList::all() as $tz => $tzLabel)
                                            <option value="{{ $tz }}" @selected($timezone === $tz)>{{ $tzLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Recurring fields -->
                        <div id="scheduleRecurringFields" class="d-none border-top pt-3 mt-3">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="schedule[start_date]" id="recurring_start_date" class="form-control" value="{{ $startDate }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Send Time</label>
                                    <input type="time" name="schedule[send_time]" id="recurring_send_time" class="form-control" value="{{ $sendTime }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">End Boundary</label>
                                    <select name="schedule[end_type]" id="endType" class="form-select">
                                        <option value="never" @selected($endType === 'never')>Never Ends</option>
                                        <option value="date" @selected($endType === 'date')>End On Date</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Timezone</label>
                                    <select name="schedule[timezone]" id="recurring_timezone" class="form-select timezone-select select2">
                                        @foreach (\App\Services\AdminCampaigns\TimezonesList::all() as $tz => $tzLabel)
                                            <option value="{{ $tz }}" @selected($timezone === $tz)>{{ $tzLabel }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-6 id-end-date-block d-none">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="schedule[end_date]" id="recurring_end_date" class="form-control" value="{{ $endDate }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Recurrence Settings -->
                <div id="recurrenceCard" class="card shadow-sm mb-4 d-none">
                    <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                        <div class="d-flex align-items-center mb-1">
                            <div class="icon-circle me-2">
                                <i class="bi bi-arrow-repeat"></i>
                            </div>
                            <h5 class="card-title fw-bold mb-0 text-dark">Recurrence Settings</h5>
                        </div>
                        <p class="text-muted small mb-0 ms-5">Configure the frequency and pattern for your recurring schedule.</p>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <label class="form-label d-block fw-semibold mb-3">Recurrence Pattern</label>
                            
                            <!-- Hidden original select element to preserve all form submit / JS actions -->
                            <select name="schedule[recurrence_type]" id="recurrenceType" class="d-none">
                                <option value="daily" @selected($recurrenceType === 'daily')>Daily</option>
                                <option value="weekly" @selected($recurrenceType === 'weekly')>Weekly</option>
                                <option value="monthly" @selected($recurrenceType === 'monthly')>Monthly</option>
                                <option value="yearly" @selected($recurrenceType === 'yearly')>Yearly</option>
                                <option value="custom" @selected($recurrenceType === 'custom')>Custom Repeat</option>
                                <option value="cycle" @selected($recurrenceType === 'cycle')>On/Off Cycle</option>
                            </select>

                            <div class="row row-cols-2 row-cols-md-3 g-2 mb-3">
                                <!-- Daily -->
                                <div class="col">
                                    <div class="recurrence-pattern-card card h-100 cursor-pointer text-center py-2 px-2" data-value="daily" tabindex="0">
                                        <i class="bi bi-sun fs-5 mb-1 d-block"></i>
                                        <span class="fw-semibold small">Daily</span>
                                    </div>
                                </div>
                                <!-- Weekly -->
                                <div class="col">
                                    <div class="recurrence-pattern-card card h-100 cursor-pointer text-center py-2 px-2" data-value="weekly" tabindex="0">
                                        <i class="bi bi-calendar-week fs-5 mb-1 d-block"></i>
                                        <span class="fw-semibold small">Weekly</span>
                                    </div>
                                </div>
                                <!-- Monthly -->
                                <div class="col">
                                    <div class="recurrence-pattern-card card h-100 cursor-pointer text-center py-2 px-2" data-value="monthly" tabindex="0">
                                        <i class="bi bi-calendar3 fs-5 mb-1 d-block"></i>
                                        <span class="fw-semibold small">Monthly</span>
                                    </div>
                                </div>
                                <!-- Yearly -->
                                <div class="col">
                                    <div class="recurrence-pattern-card card h-100 cursor-pointer text-center py-2 px-2" data-value="yearly" tabindex="0">
                                        <i class="bi bi-calendar-check fs-5 mb-1 d-block"></i>
                                        <span class="fw-semibold small">Yearly</span>
                                    </div>
                                </div>
                                <!-- Custom -->
                                <div class="col">
                                    <div class="recurrence-pattern-card card h-100 cursor-pointer text-center py-2 px-2" data-value="custom" tabindex="0">
                                        <i class="bi bi-sliders fs-5 mb-1 d-block"></i>
                                        <span class="fw-semibold small">Custom Repeat</span>
                                    </div>
                                </div>
                                <!-- Cycle -->
                                <div class="col">
                                    <div class="recurrence-pattern-card card h-100 cursor-pointer text-center py-2 px-2" data-value="cycle" tabindex="0">
                                        <i class="bi bi-arrow-left-right fs-5 mb-1 d-block"></i>
                                        <span class="fw-semibold small">On/Off Cycle</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Pattern details (Daily/Weekly/Monthly/Yearly/Custom/Cycle inputs) -->
                        <div class="border-top pt-3">
                            <!-- DAILY details -->
                            <div id="patternDaily" class="pattern-fields d-none">
                                <label class="form-label d-block mb-2">Frequency</label>
                                <div class="form-check form-check-inline me-4">
                                    <input class="form-check-input daily-freq-radio" type="radio" name="daily_freq_mode" id="daily_every_day" value="1" @checked($frequencyInterval == 1)>
                                    <label class="form-check-label fw-medium" for="daily_every_day">Every Day</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input daily-freq-radio" type="radio" name="daily_freq_mode" id="daily_custom_days" value="custom" @checked($frequencyInterval > 1)>
                                    <label class="form-check-label fw-medium" for="daily_custom_days">Every X Days</label>
                                </div>
                                <div class="mt-3 daily-interval-input d-none">
                                    <div class="input-group" style="max-width: 260px;">
                                        <span class="input-group-text bg-light border-end-0 text-muted">Repeat every</span>
                                        <input type="number" name="schedule[frequency_interval]" id="daily_interval" class="form-control text-center border-start-0 border-end-0 fw-semibold" value="{{ $frequencyInterval > 1 ? $frequencyInterval : 2 }}" min="1">
                                        <span class="input-group-text bg-light border-start-0 text-muted">days</span>
                                    </div>
                                </div>
                            </div>

                            <!-- WEEKLY details -->
                            <div id="patternWeekly" class="pattern-fields d-none">
                                <div class="mb-3">
                                    <label class="form-label d-block mb-3">Select Weekdays</label>
                                    <div class="weekly-days-grid">
                                        @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                                            <div class="weekly-day-btn">
                                                <input type="checkbox" name="schedule[weekdays][]" id="weekday_{{ $day }}" value="{{ $day }}" class="weekly-day-checkbox" @checked(in_array($day, $weekdays, true))>
                                                <label for="weekday_{{ $day }}" class="weekly-day-checkbox-label shadow-sm">{{ substr($day, 0, 3) }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <div class="input-group" style="max-width: 260px;">
                                        <span class="input-group-text bg-light border-end-0 text-muted">Repeat every</span>
                                        <input type="number" name="schedule[frequency_interval]" id="weekly_interval" class="form-control text-center border-start-0 border-end-0 fw-semibold" value="{{ $recurrenceType === 'weekly' ? $frequencyInterval : 1 }}" min="1">
                                        <span class="input-group-text bg-light border-start-0 text-muted">weeks</span>
                                    </div>
                                </div>
                            </div>

                            <!-- MONTHLY details -->
                            <div id="patternMonthly" class="pattern-fields d-none">
                                <div class="mb-3">
                                    <label class="form-label">Monthly Basis</label>
                                    <select name="schedule[monthly_basis]" id="monthlyBasis" class="form-select">
                                        <option value="date" @selected($monthlyBasis === 'date')>Monthly by Date</option>
                                        <option value="position" @selected($monthlyBasis === 'position')>Monthly by Position</option>
                                    </select>
                                </div>
                                
                                <div id="monthlyDateFields" class="d-none mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text bg-light border-end-0 text-muted">Send on day</span>
                                        <input type="number" name="schedule[monthly_day_of_month]" id="monthly_day_of_month" class="form-control text-center border-start-0 border-end-0 fw-semibold" value="{{ $monthlyDayOfMonth }}" min="1" max="31" style="max-width: 80px;">
                                        <span class="input-group-text bg-light border-start-0 text-muted">of the month</span>
                                    </div>
                                </div>
                                
                                <div id="monthlyPositionFields" class="d-none mb-3">
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted">Position</label>
                                            <select name="schedule[monthly_position]" id="monthly_position" class="form-select">
                                                <option value="first" @selected($monthlyPosition === 'first')>First</option>
                                                <option value="second" @selected($monthlyPosition === 'second')>Second</option>
                                                <option value="third" @selected($monthlyPosition === 'third')>Third</option>
                                                <option value="fourth" @selected($monthlyPosition === 'fourth')>Fourth</option>
                                                <option value="last" @selected($monthlyPosition === 'last')>Last</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label small text-muted">Day of Week</label>
                                            <select name="schedule[monthly_day_of_week]" id="monthly_day_of_week" class="form-select">
                                                @foreach(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                                                    <option value="{{ $day }}" @selected($monthlyDayOfWeek === $day)>{{ $day }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-2">
                                    <div class="input-group" style="max-width: 260px;">
                                        <span class="input-group-text bg-light border-end-0 text-muted">Repeat every</span>
                                        <input type="number" name="schedule[frequency_interval]" id="monthly_interval" class="form-control text-center border-start-0 border-end-0 fw-semibold" value="{{ $recurrenceType === 'monthly' ? $frequencyInterval : 1 }}" min="1">
                                        <span class="input-group-text bg-light border-start-0 text-muted">months</span>
                                    </div>
                                </div>
                            </div>

                            <!-- YEARLY details -->
                            <div id="patternYearly" class="pattern-fields d-none">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Month</label>
                                        <select name="schedule[yearly_month]" id="yearly_month" class="form-select">
                                            @foreach([1=>'January', 2=>'February', 3=>'March', 4=>'April', 5=>'May', 6=>'June', 7=>'July', 8=>'August', 9=>'September', 10=>'October', 11=>'November', 12=>'December'] as $mNum => $mName)
                                                <option value="{{ $mNum }}" @selected($yearlyMonth == $mNum)>{{ $mName }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Day of Month</label>
                                        <input type="number" name="schedule[yearly_day]" id="yearly_day" class="form-control" value="{{ $yearlyDay }}" min="1" max="31">
                                    </div>
                                </div>
                            </div>

                            <!-- CUSTOM details -->
                            <div id="patternCustom" class="pattern-fields d-none">
                                <div class="row g-3 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Repeat every</label>
                                        <input type="number" name="schedule[frequency_interval]" id="custom_interval" class="form-control" value="{{ $recurrenceType === 'custom' ? $frequencyInterval : 1 }}" min="1">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Unit</label>
                                        <select name="schedule[custom_unit]" id="custom_unit" class="form-select">
                                            <option value="day" @selected($customUnit === 'day')>Days</option>
                                            <option value="week" @selected($customUnit === 'week')>Weeks</option>
                                            <option value="month" @selected($customUnit === 'month')>Months</option>
                                            <option value="year" @selected($customUnit === 'year')>Years</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- CYCLE details -->
                            <div id="patternCycle" class="pattern-fields d-none">
                                <div class="row g-3 mb-2">
                                    <div class="col-md-6">
                                        <label class="form-label">Send For</label>
                                        <div class="input-group">
                                            <input type="number" name="schedule[cycle_send_days]" id="cycle_send_days" class="form-control text-center fw-semibold border-end-0" value="{{ $cycleSendDays }}" min="1">
                                            <span class="input-group-text bg-light text-muted">days</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Pause For</label>
                                        <div class="input-group">
                                            <input type="number" name="schedule[cycle_pause_days]" id="cycle_pause_days" class="form-control text-center fw-semibold border-end-0" value="{{ $cyclePauseDays }}" min="0">
                                            <span class="input-group-text bg-light text-muted">days</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Sidebar controls and Actions -->
            <div class="col-lg-4">
                <div class="sticky-lg-top" style="top: 1.5rem; z-index: 100;">
                    
                    <!-- Card 2: Audience Selection -->
                    <div class="card shadow-sm mb-4 bg-white">
                        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-0">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <div class="d-flex align-items-center">
                                    <div class="icon-circle me-2">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <h5 class="card-title fw-bold mb-0 text-dark">Audience</h5>
                                </div>
                                <button type="button" id="importAudienceBtn" class="btn btn-sm btn-outline-primary fw-bold py-1 px-2" style="font-size: 10px;">
                                    <i class="bi bi-file-earmark-arrow-up me-1"></i>Import
                                </button>
                            </div>
                            <p class="text-muted small mb-0 ms-5">Define who receives this campaign.</p>
                        </div>
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <label class="form-label">Audience Type</label>
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
                        </div>
                    </div>

                    <!-- Action Card -->
                    <div class="card shadow-sm mb-4 bg-white">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-uppercase text-muted mb-3" style="font-size: 11px; letter-spacing: 0.5px;">Campaign Publish</h6>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success py-2.5 fw-bold" onclick="document.getElementById('campaignAction').value='send'; return confirm('Send this campaign now? This cannot be undone.');" style="background-color: #10b981; border-color: #10b981;">
                                    <i class="bi bi-send-fill me-2"></i>Send Campaign
                                </button>
                                <button type="submit" class="btn btn-outline-secondary py-2.5 fw-bold" onclick="document.getElementById('campaignAction').value='draft'">
                                    <i class="bi bi-file-earmark-diff me-2"></i>Save Draft
                                </button>
                                <button type="button" id="previewRecipientsBtn" class="btn btn-outline-primary py-2.5 fw-bold">
                                    <i class="bi bi-eye-fill me-2"></i>Preview Recipients
                                </button>
                            </div>
                            
                            <div class="mt-3 text-muted text-center" style="font-size: 11px;">
                                <i class="bi bi-info-circle me-1"></i> Drafts can be edited later. Sent campaigns cannot be modified.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="card shadow-sm border-0 mt-4 overflow-hidden" id="previewCard" style="display:none;">
        <div class="card-header d-flex justify-content-between align-items-center bg-light border-0 py-3 px-4">
            <strong class="text-dark"><i class="bi bi-people-fill me-2 text-primary"></i>Preview Recipients</strong>
            <span class="badge bg-primary px-3 py-2 fs-7 rounded-pill">Total Matches: <span id="previewTotal">0</span></span>
        </div>
        <div id="previewDebug" class="small text-muted px-4 py-2 border-bottom" style="display:none; background-color: #fffbeb;"></div>
        <div class="table-responsive">
            <table class="table table-hover table-striped align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>City</th>
                        <th>Company</th>
                        <th>Membership</th>
                        <th class="pe-4">Circle</th>
                    </tr>
                </thead>
                <tbody id="previewBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Modals -->
    <div class="modal fade" id="audienceImportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0 bg-transparent">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-file-earmark-arrow-up me-2 text-primary"></i>Import Audience Values</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="audienceImportAlert" class="alert d-none shadow-sm mb-3" role="alert"></div>
                    <p class="text-muted small mb-4">Upload a CSV/XLSX/XLS file. Columns are detected automatically based on the selected audience type and imported values fill the current audience fields only.</p>
                    <div class="mb-4">
                        <label class="form-label" for="audienceImportFile">Audience File</label>
                        <input type="file" id="audienceImportFile" class="form-control" accept=".csv,.xlsx,.xls" required>
                        <div class="form-text mt-1 text-muted">Maximum file size: 10 MB. Supported formats: .csv, .xlsx, .xls</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Detected Columns</label>
                        <div id="audienceImportColumns" class="border rounded p-3 text-muted small bg-light">
                            Upload a file to preview detected columns.
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Download Sample CSVs</label>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('admin.campaigns.audience-samples', 'city') }}" class="btn btn-sm btn-outline-secondary fw-semibold">
                                <i class="bi bi-download me-1"></i>Download City Sample
                            </a>
                            <a href="{{ route('admin.campaigns.audience-samples', 'company') }}" class="btn btn-sm btn-outline-secondary fw-semibold">
                                <i class="bi bi-download me-1"></i>Download Company Sample
                            </a>
                            <a href="{{ route('admin.campaigns.audience-samples', 'membership_status') }}" class="btn btn-sm btn-outline-secondary fw-semibold">
                                <i class="bi bi-download me-1"></i>Download Status Sample
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary px-3 py-2 fw-semibold" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="audienceImportSubmit" class="btn btn-primary px-4 py-2 fw-semibold">Import</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pamphletSelectModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0 bg-transparent">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-image me-2 text-primary"></i>Select Pamphlet</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="pamphletList" class="row g-3"></div>
                </div>
                <div class="modal-footer border-0 bg-light d-flex justify-content-between">
                    <a href="{{ route('admin.campaign-pamphlets.create') }}" class="btn btn-outline-primary px-3 py-2 fw-semibold">
                        <i class="bi bi-plus-lg me-1"></i>Add Pamphlet
                    </a>
                    <button type="button" class="btn btn-secondary px-3 py-2 fw-semibold" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
<style>
/* Custom Campaign Builder styling */
.icon-circle {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background-color: #f5f3ff;
    color: #240e5c;
    font-size: 1.1rem;
}
.form-label {
    font-weight: 600;
    color: #4b5563;
    font-size: 0.875rem;
}
.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #d1d5db;
    padding: 0.6rem 0.75rem;
    font-size: 0.9rem;
    transition: all 0.2s;
}
.form-control:focus, .form-select:focus {
    border-color: #240e5c;
    box-shadow: 0 0 0 3px rgba(36, 14, 92, 0.15);
}

/* Schedule Type & Recurrence Option Cards */
.schedule-type-card, .recurrence-pattern-card {
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    transition: all 0.2s ease-in-out;
    cursor: pointer;
    background-color: #fff;
    outline: none;
}
.schedule-type-card:hover, .recurrence-pattern-card:hover {
    border-color: #240e5c;
    transform: translateY(-2px);
    box-shadow: 0 8px 16px -3px rgba(36, 14, 92, 0.08) !important;
}
.schedule-type-card:focus-visible, .recurrence-pattern-card:focus-visible {
    box-shadow: 0 0 0 3px rgba(36, 14, 92, 0.3) !important;
}
.schedule-type-card.active, .recurrence-pattern-card.active {
    border-color: #240e5c !important;
    border-width: 2px !important;
    background-color: #f5f3ff !important;
    color: #240e5c !important;
}
.schedule-type-card.active h6, .schedule-type-card.active i, .recurrence-pattern-card.active i {
    color: #240e5c !important;
}

/* Weekly Days Checklist Custom Buttons */
.weekly-days-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 1rem;
}
.weekly-day-btn {
    flex: 1;
    min-width: 48px;
    text-align: center;
}
.weekly-day-checkbox-label {
    display: block;
    padding: 0.6rem 0.25rem;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    cursor: pointer;
    font-size: 0.85rem;
    font-weight: 600;
    transition: all 0.2s;
    user-select: none;
    background-color: #fff;
    color: #4b5563;
}
.weekly-day-checkbox-label:hover {
    border-color: #240e5c;
    background-color: #fcfbfe;
}
.weekly-day-checkbox:checked + .weekly-day-checkbox-label {
    background-color: #240e5c;
    border-color: #240e5c;
    color: #fff;
    box-shadow: 0 4px 6px -1px rgba(36, 14, 92, 0.2);
}
.weekly-day-checkbox {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
}

/* Existing Email Template selection cards styles */
.campaign-email-template-card{border:1px solid #e5e7eb;transition:all .18s ease;cursor:pointer;border-radius: 12px;background:#fff;outline:none}.campaign-email-template-card:hover{transform:translateY(-2px);box-shadow:0 .5rem 1rem rgba(15,23,42,.12);border-color:#240e5c}.campaign-email-template-card.selected{border:2px solid #240e5c;box-shadow:0 0 0 .2rem rgba(36, 14, 92,.12)}.campaign-template-thumb{height:120px;border:1px solid #dbe3ef;border-radius:10px;background:#f8fafc;padding:12px;display:grid;gap:7px}.campaign-template-thumb span{display:block;border-radius:6px;background:#e2e8f0;border:1px solid #cbd5e1}.campaign-template-thumb-simple_text{grid-template-rows:repeat(4,1fr)}.campaign-template-thumb-single_column{grid-template-rows:2fr 1fr 1fr}.campaign-template-thumb-one_two_column,.campaign-template-thumb-one_two_column_alternate{grid-template-columns:1fr 1fr;grid-template-rows:1fr 1.5fr}.campaign-template-thumb-one_two_column span:first-child,.campaign-template-thumb-one_two_column_alternate span:first-child{grid-column:1/3}.campaign-template-thumb-one_two_one_two_column{grid-template-columns:1fr 1fr;grid-template-rows:.8fr 1fr .8fr 1fr}.campaign-template-thumb-one_two_one_two_column span:first-child,.campaign-template-thumb-one_two_one_two_column span:nth-child(4){grid-column:1/3}.campaign-template-thumb-one_three_column{grid-template-columns:repeat(3,1fr);grid-template-rows:1fr 1.5fr}.campaign-template-thumb-one_three_column span:first-child{grid-column:1/4}.campaign-template-thumb-blank span{display:none}.campaign-email-preview-shell{border:1px solid #dbe3ef;border-radius:14px;background:#f4f4f4;overflow:hidden}.campaign-email-preview-header{background:#240e5c;color:#fff;text-align:center;font-size:12px;font-weight:700;padding:8px}.campaign-email-preview-body{background:#fff;margin:16px;padding:18px;border-radius:10px;min-height:150px;overflow:auto}

/* General Layout tweaks */
.filter-block {
    margin-bottom: 1.25rem;
    border-left: 3px solid #e5e7eb;
    padding-left: 12px;
}
.filter-block select {
    margin-top: 0.25rem;
}
.btn-xs {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
    border-radius: 4px;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    const campaignType = document.getElementById('campaignType');
    const audienceType = document.getElementById('audienceType');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const filterOptions = @json($filterOptions);
    const emailTemplates = @json($emailTemplates);
    let pamphlets = [];
    let pamphletTarget = 'both';
    let currentPamphletImageUrl = @json(data_get($campaign->pamphlet_snapshot ?? [], 'image_url', ''));

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
            custom_filter: ['cities','circle_ids','companies','business_category_ids','membership_statuses','user_ids']
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

    function selectedEmailTemplate() {
        const selectedId = document.getElementById('emailTemplateId').value;
        return emailTemplates.find(template => String(template.id) === String(selectedId)) || emailTemplates.find(template => template.slug === 'simple-text') || emailTemplates[0] || null;
    }

    function splitContent(content, parts) {
        const blocks = content.split(/(?=<p\b|<h[1-6]\b|<ul\b|<ol\b|<div\b)/i).filter(Boolean);
        const chunks = Array.from({ length: parts }, () => '');
        (blocks.length ? blocks : [content]).forEach((block, index) => { chunks[index % parts] += block; });
        return chunks.map(chunk => chunk.trim());
    }

    function renderTemplateHtml(template, content, imageUrl = '') {
        if (!template) return content || '<p>Add your campaign content here.</p>';
        const safeContent = content && content.trim() ? content : '<p>Add your campaign content here.</p>';
        const imageHtml = imageUrl
            ? `<img src="${escapeHtml(imageUrl)}" alt="Campaign image" style="max-width:100%;height:auto;border-radius:12px;display:block;">`
            : '<div style="background:#f1f5f9;border:1px dashed #cbd5e1;border-radius:12px;padding:28px;text-align:center;color:#64748b;">Image / visual block</div>';
        const two = splitContent(safeContent, 2);
        const three = splitContent(safeContent, 3);
        let html = template.html_structure || '@{{content}}';
        const replacements = {
            '@{{content}}': safeContent,
            '@{{image}}': imageHtml,
            '@{{content_left}}': two[0] || safeContent,
            '@{{content_right}}': two[1] || safeContent,
            '@{{card_1}}': three[0] || safeContent,
            '@{{card_2}}': three[1] || three[0] || safeContent,
            '@{{card_3}}': three[2] || three[0] || safeContent,
        };
        Object.entries(replacements).forEach(([token, value]) => { html = html.split(token).join(value); });
        return `${template.css_styles ? `<style>${template.css_styles}</style>` : ''}${html}`;
    }

    function renderEmailTemplatePreview() {
        const preview = document.getElementById('emailTemplatePreview');
        if (!preview) return;
        preview.innerHTML = renderTemplateHtml(selectedEmailTemplate(), document.getElementById('campaignEmailBody').value, currentPamphletImageUrl);
    }

    function selectEmailTemplate(templateId) {
        document.getElementById('emailTemplateId').value = templateId || '';
        document.querySelectorAll('.campaign-email-template-card').forEach(card => {
            const selected = String(card.dataset.templateId) === String(templateId);
            card.classList.toggle('selected', selected);
            const button = card.querySelector('.template-select-label');
            if (button) {
                button.textContent = selected ? 'Selected' : 'Select Template';
                button.classList.toggle('btn-primary', selected);
                button.classList.toggle('btn-outline-primary', !selected);
            }
        });
        renderEmailTemplatePreview();
    }

    document.querySelectorAll('.campaign-email-template-card').forEach(card => {
        card.addEventListener('click', () => selectEmailTemplate(card.dataset.templateId));
        card.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectEmailTemplate(card.dataset.templateId);
            }
        });
    });

    document.getElementById('campaignEmailBody').addEventListener('input', renderEmailTemplatePreview);

    function showAudienceImportAlert(message, type = 'success') {
        const alert = document.getElementById('audienceImportAlert');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
    }

    function resetAudienceImportModal() {
        document.getElementById('audienceImportFile').value = '';
        document.getElementById('audienceImportColumns').innerHTML = '<span class="text-muted">Upload a file to preview detected columns.</span>';
        document.getElementById('audienceImportAlert').className = 'alert d-none';
        document.getElementById('audienceImportAlert').textContent = '';
    }

    function fillImportedFilters(filters) {
        Object.entries(filters || {}).forEach(([filterKey, payload]) => {
            const select = selectForFilter(filterKey);
            (payload.options || []).forEach(option => addSelectValue(select, option.value, option.label));
        });
    }

    function renderDetectedColumns(columns, matchedColumns = {}) {
        const container = document.getElementById('audienceImportColumns');
        const badges = (columns || []).map(column => `<span class="badge bg-light text-dark border me-1 mb-1">${escapeHtml(column)}</span>`).join('');
        const matched = Object.entries(matchedColumns || {}).map(([filter, cols]) => `<div class="small mt-1"><strong>${escapeHtml(filter)}:</strong> ${cols.map(escapeHtml).join(', ')}</div>`).join('');
        container.innerHTML = badges || '<span class="text-muted">No columns detected.</span>';
        if (matched) container.innerHTML += `<div class="mt-2">${matched}</div>`;
    }

    document.getElementById('importAudienceBtn').addEventListener('click', () => {
        resetAudienceImportModal();
        bootstrap.Modal.getOrCreateInstance(document.getElementById('audienceImportModal')).show();
    });

    document.getElementById('audienceImportSubmit').addEventListener('click', async () => {
        const fileInput = document.getElementById('audienceImportFile');
        if (!fileInput.files.length) {
            showAudienceImportAlert('Please choose a CSV, XLSX, or XLS file to import.', 'warning');
            return;
        }

        const button = document.getElementById('audienceImportSubmit');
        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('audience_type', audienceType.value);

        button.disabled = true;
        button.textContent = 'Importing...';
        try {
            const response = await fetch('{{ route('admin.campaigns.import-audience') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: formData
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                showAudienceImportAlert(data.message || 'Audience import failed.', 'danger');
                return;
            }

            renderDetectedColumns(data.data.columns || [], data.data.matched_columns || {});
            fillImportedFilters(data.data.filters || { [data.data.filter]: { options: (data.data.values || []).map(value => ({ value, label: (data.data.labels || {})[value] || value })) } });
            showAudienceImportAlert(data.message || `${data.data.count || 0} values imported successfully.`, 'success');
        } catch (error) {
            showAudienceImportAlert('Audience import failed. Please check the file and try again.', 'danger');
        } finally {
            button.disabled = false;
            button.textContent = 'Import';
        }
    });

    function pamphletImageHtml(url) {
        return url ? `<p><img src="${url}" style="max-width:100%;height:auto;"></p>` : '';
    }

    function applyPamphlet(pamphlet, target = 'both') {
        const type = campaignType.value;
        document.getElementById('pamphletId').value = pamphlet.id;
        if ((target === 'email' || target === 'both') && (type === 'email_only' || type === 'email_and_notification')) {
            currentPamphletImageUrl = pamphlet.image_url || '';
            const template = selectedEmailTemplate();
            const appendImageToBody = !template || ['blank-template', 'simple-text'].includes(template.slug);
            document.getElementById('campaignEmailBody').value = `${pamphlet.content || ''}${appendImageToBody ? pamphletImageHtml(pamphlet.image_url || '') : ''}`;
            renderEmailTemplatePreview();
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
                <div class="card h-100 border shadow-sm">
                    ${item.image_url ? `<img src="${escapeHtml(item.image_url)}" class="card-img-top" style="height:140px;object-fit:cover;" alt="${escapeHtml(item.title)}">` : ''}
                    <div class="card-body">
                        <h6 class="card-title fw-bold text-dark mb-1">${escapeHtml(item.title)}</h6>
                        <p class="small text-muted mb-3" style="line-height: 1.3;">${escapeHtml(item.short_message || '')}</p>
                        <button type="button" class="btn btn-sm btn-primary pamphlet-choose w-100 fw-semibold" data-id="${item.id}">Select</button>
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

    // Custom Interactive Radio Buttons Listener
    document.querySelectorAll('.schedule-type-card').forEach(card => {
        const selectRadio = () => {
            const val = card.dataset.value;
            const radio = document.querySelector(`input[name="schedule[schedule_type]"][value="${val}"]`);
            if (radio) {
                radio.checked = true;
                radio.dispatchEvent(new Event('change'));
            }
        };
        card.addEventListener('click', selectRadio);
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectRadio();
            }
        });
    });

    document.querySelectorAll('.recurrence-pattern-card').forEach(card => {
        const selectPattern = () => {
            const val = card.dataset.value;
            const select = document.getElementById('recurrenceType');
            if (select) {
                select.value = val;
                select.dispatchEvent(new Event('change'));
            }
        };
        card.addEventListener('click', selectPattern);
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                selectPattern();
            }
        });
    });

    function syncScheduleFields() {
        const selectedRadio = document.querySelector('input[name="schedule[schedule_type]"]:checked');
        const selectedType = selectedRadio?.value || 'immediately';
        
        // Visual highlights update
        document.querySelectorAll('.schedule-type-card').forEach(card => {
            const isActive = card.dataset.value === selectedType;
            card.classList.toggle('active', isActive);
        });

        const onceFields = document.getElementById('scheduleOnceFields');
        const recurringFields = document.getElementById('scheduleRecurringFields');
        const recurrenceCard = document.getElementById('recurrenceCard');
        
        onceFields.classList.toggle('d-none', selectedType !== 'once');
        recurringFields.classList.toggle('d-none', selectedType !== 'recurring');
        
        if (recurrenceCard) {
            recurrenceCard.classList.toggle('d-none', selectedType !== 'recurring');
        }
        
        onceFields.querySelectorAll('input, select').forEach(el => el.disabled = selectedType !== 'once');
        recurringFields.querySelectorAll('input, select, textarea').forEach(el => el.disabled = selectedType !== 'recurring');
        
        if (selectedType === 'recurring') {
            syncRecurrencePatternFields();
            syncEndTypeFields();
        }
    }
    
    function syncRecurrencePatternFields() {
        const pattern = document.getElementById('recurrenceType').value;

        // Visual highlights update
        document.querySelectorAll('.recurrence-pattern-card').forEach(card => {
            const isActive = card.dataset.value === pattern;
            card.classList.toggle('active', isActive);
        });
        
        document.querySelectorAll('.pattern-fields').forEach(el => {
            el.classList.add('d-none');
            el.querySelectorAll('input, select').forEach(input => input.disabled = true);
        });
        
        let activePatternId = '';
        switch (pattern) {
            case 'daily': activePatternId = 'patternDaily'; break;
            case 'weekly': activePatternId = 'patternWeekly'; break;
            case 'monthly': activePatternId = 'patternMonthly'; break;
            case 'yearly': activePatternId = 'patternYearly'; break;
            case 'custom': activePatternId = 'patternCustom'; break;
            case 'cycle': activePatternId = 'patternCycle'; break;
        }
        
        if (activePatternId) {
            const activeDiv = document.getElementById(activePatternId);
            activeDiv.classList.remove('d-none');
            activeDiv.querySelectorAll('input, select').forEach(input => input.disabled = false);
            
            if (pattern === 'daily') {
                syncDailyPattern();
            } else if (pattern === 'monthly') {
                syncMonthlyPattern();
            }
        }
    }
    
    function syncDailyPattern() {
        const mode = document.querySelector('input[name="daily_freq_mode"]:checked')?.value || '1';
        const inputDiv = document.querySelector('.daily-interval-input');
        const intervalInput = document.getElementById('daily_interval');
        
        if (mode === 'custom') {
            inputDiv.classList.remove('d-none');
            intervalInput.disabled = false;
        } else {
            inputDiv.classList.add('d-none');
            intervalInput.disabled = true;
            intervalInput.value = '1';
        }
    }
    
    function syncMonthlyPattern() {
        const basis = document.getElementById('monthlyBasis').value;
        const dateDiv = document.getElementById('monthlyDateFields');
        const posDiv = document.getElementById('monthlyPositionFields');
        
        dateDiv.classList.toggle('d-none', basis !== 'date');
        dateDiv.querySelectorAll('input, select').forEach(el => el.disabled = basis !== 'date');
        
        posDiv.classList.toggle('d-none', basis !== 'position');
        posDiv.querySelectorAll('input, select').forEach(el => el.disabled = basis !== 'position');
    }
    
    function syncEndTypeFields() {
        const endType = document.getElementById('endType').value;
        const endDateBlock = document.querySelector('.id-end-date-block');
        const endDateInput = document.getElementById('recurring_end_date');
        
        endDateBlock.classList.toggle('d-none', endType !== 'date');
        endDateInput.disabled = (endType !== 'date');
    }

    document.querySelectorAll('input[name="schedule[schedule_type]"]').forEach(radio => {
        radio.addEventListener('change', syncScheduleFields);
    });
    
    document.getElementById('recurrenceType').addEventListener('change', syncScheduleFields);
    
    document.querySelectorAll('.daily-freq-radio').forEach(radio => {
        radio.addEventListener('change', syncDailyPattern);
    });
    
    document.getElementById('monthlyBasis').addEventListener('change', syncMonthlyPattern);
    document.getElementById('endType').addEventListener('change', syncEndTypeFields);
    
    syncScheduleFields();

    campaignType.addEventListener('change', syncTypeFields);
    audienceType.addEventListener('change', syncFilterFields);
    syncTypeFields(); syncFilterFields(); renderEmailTemplatePreview();

    const previewRecipientsBtn = document.getElementById('previewRecipientsBtn');
    if (previewRecipientsBtn) {
        previewRecipientsBtn.addEventListener('click', async () => {
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
    }
})();
</script>
@endpush
