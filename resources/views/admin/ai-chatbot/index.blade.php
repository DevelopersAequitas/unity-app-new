@extends('admin.layouts.app')

@section('title', 'Peers AI Chatbot & Knowledge Base')

@section('content')
@php
    $enabledTools = is_array($settings->enabled_tools_json) ? $settings->enabled_tools_json : \App\Models\AiChatbotSetting::DEFAULT_ENABLED_TOOLS;
    $availableTools = [
        'search_docs' => 'Search Knowledge Docs (RAG Policies & Rules)',
        'find_peers' => 'Find Peers (Live Users Table)',
        'get_events' => 'Get Upcoming Events (Live Events Table)',
        'get_speed_networking' => 'Get Speed Networking Sessions',
        'get_my_stats' => 'Get Peer Referrals, Coins & Impact Summary',
    ];
    $innerOptionsJson = json_encode($settings->inner_options_json ?: \App\Models\AiChatbotSetting::DEFAULT_INNER_OPTIONS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $starterChipsJson = json_encode($settings->starter_chips_json ?: \App\Models\AiChatbotSetting::DEFAULT_STARTER_CHIPS, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
@endphp

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h4 mb-1 fw-bold text-dark">
                <i class="bi bi-chat-dots-fill me-2 text-primary"></i>Peers AI Chatbot &amp; Knowledge Base
            </h2>
            <p class="text-muted small mb-0">
                Manage mobile app chatbot visibility, dynamic greetings, Gemini model, live DB tools, and RAG knowledge documents.
            </p>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadDocModal">
            <i class="bi bi-file-earmark-plus me-1"></i> Add Knowledge Document (RAG)
        </button>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0 small">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row g-4">
        <!-- Left Column: Chatbot Settings Form -->
        <div class="col-lg-7">
            <form action="{{ route('admin.ai-chatbot.settings.update') }}" method="POST">
                @csrf
                @method('PUT')

                <!-- Visibility & Toggles -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-toggles me-2 text-primary"></i>App Visibility &amp; Entry Points</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_enabled" id="is_enabled" value="1" {{ $settings->is_enabled ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="is_enabled">Master Enable AI Chatbot</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_floating_button" id="show_floating_button" value="1" {{ $settings->show_floating_button ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_floating_button">Show Floating Pill Button</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_app_bar_icon" id="show_app_bar_icon" value="1" {{ $settings->show_app_bar_icon ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_app_bar_icon">Show Floating Button in Profile Screen</label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="show_home_banner" id="show_home_banner" value="1" {{ $settings->show_home_banner ? 'checked' : '' }}>
                                    <label class="form-check-label" for="show_home_banner">Show Inline Home Feed Banner</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- UI Texts & Branding -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-card-text me-2 text-primary"></i>Dynamic UI Titles &amp; Texts</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Button / Banner Title</label>
                                <input type="text" name="button_title" class="form-control form-control-sm" value="{{ old('button_title', $settings->button_title) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Banner Subtitle</label>
                                <input type="text" name="button_subtitle" class="form-control form-control-sm" value="{{ old('button_subtitle', $settings->button_subtitle) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Bot Header Name</label>
                                <input type="text" name="bot_name" class="form-control form-control-sm" value="{{ old('bot_name', $settings->bot_name) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Bot Header Subtitle</label>
                                <input type="text" name="bot_subtitle" class="form-control form-control-sm" value="{{ old('bot_subtitle', $settings->bot_subtitle) }}" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Custom Bot Avatar URL (Optional)</label>
                                <input type="text" name="bot_avatar_url" class="form-control form-control-sm" value="{{ old('bot_avatar_url', $settings->bot_avatar_url) }}" placeholder="https://...">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Welcome Greeting Title</label>
                                <input type="text" name="welcome_title" class="form-control form-control-sm" value="{{ old('welcome_title', $settings->welcome_title) }}" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Welcome Greeting Subtitle</label>
                                <textarea name="welcome_subtitle" rows="2" class="form-control form-control-sm">{{ old('welcome_subtitle', $settings->welcome_subtitle) }}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Empty History Title</label>
                                <input type="text" name="empty_history_title" class="form-control form-control-sm" value="{{ old('empty_history_title', $settings->empty_history_title) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Empty History Subtitle</label>
                                <input type="text" name="empty_history_subtitle" class="form-control form-control-sm" value="{{ old('empty_history_subtitle', $settings->empty_history_subtitle) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Input Placeholder</label>
                                <input type="text" name="input_placeholder" class="form-control form-control-sm" value="{{ old('input_placeholder', $settings->input_placeholder) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-semibold">Thinking Indicator Text</label>
                                <input type="text" name="thinking_text" class="form-control form-control-sm" value="{{ old('thinking_text', $settings->thinking_text) }}" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Gemini Model, Master Prompt & Tools -->
                <div class="card border-0 shadow-sm rounded-3 mb-4">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-cpu me-2 text-primary"></i>Gemini Model, Master Prompt &amp; Live DB Tools</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-3">
                            <div class="col-md-8">
                                <label class="form-label small fw-semibold">Gemini Model</label>
                                <input type="text" name="model_name" class="form-control form-control-sm font-monospace" value="{{ old('model_name', $settings->model_name ?: 'gemini-2.0-flash') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-semibold">Temperature (0.0 - 1.0)</label>
                                <input type="number" step="0.05" min="0" max="1" name="temperature" class="form-control form-control-sm" value="{{ old('temperature', $settings->temperature ?? 0.30) }}" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Enabled Live Database Tools (Function Calling)</label>
                            <div class="row g-2">
                                @foreach($availableTools as $toolKey => $toolLabel)
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="enabled_tools[]" value="{{ $toolKey }}" id="tool_{{ $toolKey }}" {{ in_array($toolKey, $enabledTools, true) ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="tool_{{ $toolKey }}">{{ $toolLabel }}</label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Master System Prompt</label>
                            <textarea name="master_system_prompt" rows="7" class="form-control form-control-sm font-monospace" required>{{ old('master_system_prompt', $settings->master_system_prompt) }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Inner Options Cards (JSON)</label>
                            <textarea name="inner_options_json" rows="6" class="form-control form-control-sm font-monospace">{{ old('inner_options_json', $innerOptionsJson) }}</textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Starter Question Chips (JSON)</label>
                            <textarea name="starter_chips_json" rows="4" class="form-control form-control-sm font-monospace">{{ old('starter_chips_json', $starterChipsJson) }}</textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i> Save Chatbot Settings
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Right Column: RAG Knowledge Base Documents -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold"><i class="bi bi-journal-bookmark-fill me-2 text-primary"></i>RAG Knowledge Base Documents</h6>
                    <span class="badge bg-light text-dark border">{{ $documents->count() }} Docs</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-muted small text-uppercase">
                                <tr>
                                    <th class="ps-3 py-2">Document</th>
                                    <th>Chunks</th>
                                    <th>Status</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($documents as $doc)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold text-dark small">{{ $doc->title }}</div>
                                            <span class="badge bg-light text-secondary border small">{{ $doc->category }}</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-info text-dark">{{ $doc->chunks_count ?? 0 }}</span>
                                        </td>
                                        <td>
                                            @if($doc->is_active)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-secondary">Disabled</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <form action="{{ route('admin.ai-chatbot.documents.toggle', $doc->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Toggle Active">
                                                    <i class="bi bi-power"></i>
                                                </button>
                                            </form>
                                            <form action="{{ route('admin.ai-chatbot.documents.destroy', $doc->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this document and its vector chunks?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted small">
                                            No knowledge documents uploaded yet. Click <strong>Add Knowledge Document</strong> to add official Peers Global policies and FAQs.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Add Knowledge Document -->
<div class="modal fade" id="uploadDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <form action="{{ route('admin.ai-chatbot.documents.store') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus text-primary me-2"></i>Add Knowledge Base Document (RAG)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Document Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Peers Global Membership Handbook" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="general">General</option>
                                <option value="speed_networking">Speed Networking</option>
                                <option value="referrals">Referrals &amp; Coins</option>
                                <option value="circles">Unity Circles</option>
                                <option value="faq">FAQ</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Document Content (Markdown / Plain Text)</label>
                        <textarea name="raw_content" rows="10" class="form-control font-monospace small" placeholder="Paste official rules, guidelines, FAQs, or extracted handbook text here. It will automatically be split into 300-word chunks and embedded via Gemini text-embedding-004..." required></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_active" id="doc_is_active" value="1" checked>
                        <label class="form-check-label small" for="doc_is_active">Active immediately for AI Chatbot RAG</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-cloud-upload me-1"></i> Save &amp; Generate Chunks
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

