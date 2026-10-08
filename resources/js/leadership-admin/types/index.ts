export interface ApiResponse<T = any> {
    success: boolean;
    message?: string;
    data: T;
    meta?: {
        total?: number;
        per_page?: number;
        current_page?: number;
        last_page?: number;
    };
    errors?: Record<string, string[]>;
}

export interface AdminUserContext {
    id: string;
    name: string;
    email: string;
    roles: string[];
    is_super: boolean;
}

export interface Campaign {
    id: string;
    role_id: string;
    role_name?: string;
    name: string;
    code: string;
    year: number;
    description?: string;
    banner_url?: string;
    status: 'draft' | 'published' | 'nomination_closed' | 'voting_open' | 'voting_closed' | 'completed' | 'archived';
    nomination_start_at: string;
    nomination_end_at: string;
    voting_start_at?: string;
    voting_end_at?: string;
    jury_start_at?: string;
    jury_end_at?: string;
    rules?: Record<string, any>;
    nominations_count?: number;
    votes_count?: number;
    shortlisted_count?: number;
    created_at: string;
    updated_at: string;
}

export interface Scope {
    id: string;
    campaign_id: string;
    scope_type: 'territory' | 'state' | 'district' | 'city' | 'industry' | 'circle';
    scope_value: string;
    name?: string;
}

export interface FormTemplate {
    id: string;
    campaign_id?: string;
    form_type: 'nomination' | 'jury_evaluation';
    title: string;
    description?: string;
    version: number;
    is_active: boolean;
    sections?: FormSection[];
    created_at: string;
    updated_at: string;
}

export interface FormSection {
    id: string;
    template_id: string;
    title: string;
    description?: string;
    sort_order: number;
    questions?: FormQuestion[];
}

export interface FormQuestion {
    id: string;
    section_id: string;
    field_key: string;
    label: string;
    field_type: 'text' | 'textarea' | 'number' | 'email' | 'phone' | 'url' | 'date' | 'select' | 'radio' | 'checkbox' | 'file' | 'declaration' | 'repeater';
    placeholder?: string;
    help_text?: string;
    is_required: boolean;
    validation_rules?: Record<string, any>;
    conditional_rules?: Record<string, any>;
    sort_order: number;
    is_active: boolean;
    options?: FormOption[];
}

export interface FormOption {
    id: string;
    question_id: string;
    label: string;
    value: string;
    sort_order: number;
}

export interface Nomination {
    id: string;
    campaign_id: string;
    campaign_name?: string;
    user_id?: string;
    application_number: string;
    candidate_name: string;
    email: string;
    mobile: string;
    applied_role_name?: string;
    profile_photo_url?: string;
    status: 'draft' | 'submitted' | 'under_review' | 'correction_requested' | 'approved' | 'rejected' | 'shortlisted';
    admin_remarks?: string;
    rejection_reason?: string;
    correction_notes?: string;
    profile_diff?: Record<string, { original: any; submitted: any }>;
    answers?: NominationAnswer[];
    documents?: NominationDocument[];
    history?: NominationHistory[];
    created_at: string;
    submitted_at?: string;
}

export interface NominationAnswer {
    id: string;
    question_id: string;
    question_label?: string;
    field_type?: string;
    answer_text?: string;
    answer_json?: any;
}

export interface NominationDocument {
    id: string;
    document_type: string;
    file_name: string;
    file_path: string;
    file_url?: string;
    status: 'pending' | 'verified' | 'rejected';
    remarks?: string;
    created_at: string;
}

export interface NominationHistory {
    id: string;
    action: string;
    previous_status?: string;
    new_status?: string;
    remarks?: string;
    created_at: string;
}

export interface JuryAssignment {
    id: string;
    campaign_id: string;
    nomination_id: string;
    jury_user_id: string;
    jury_name?: string;
    candidate_name?: string;
    status: 'assigned' | 'in_progress' | 'completed';
    deadline?: string;
    created_at: string;
}

export interface EvaluationCriterion {
    id: string;
    name: string;
    category?: string;
    description?: string;
    max_score: number;
    weightage: number;
    sort_order: number;
}

export interface JuryScore {
    id: string;
    assignment_id: string;
    criterion_id: string;
    criterion_name?: string;
    score: number;
    max_score: number;
    remarks?: string;
}

export interface FinalDecision {
    id: string;
    campaign_id: string;
    nomination_id: string;
    candidate_name?: string;
    decision: 'selected' | 'runner_up' | 'standby' | 'rejected' | 'deferred';
    rank?: number;
    remarks?: string;
    is_published: boolean;
    decided_at?: string;
}

export interface WinnerCreative {
    id: string;
    campaign_id: string;
    decision_id: string;
    candidate_name?: string;
    template_name?: string;
    image_url: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    created_at: string;
}

export interface NotificationLog {
    id: string;
    campaign_id?: string;
    recipient_email?: string;
    recipient_phone?: string;
    channel: 'email' | 'sms' | 'whatsapp';
    template_name: string;
    status: 'queued' | 'sent' | 'failed';
    error_message?: string;
    created_at: string;
}

export interface AuditLog {
    id: string;
    actor_id?: string;
    actor_name?: string;
    action: string;
    entity_type: string;
    entity_id: string;
    before_state?: any;
    after_state?: any;
    ip_address?: string;
    created_at: string;
}

export interface DashboardOverview {
    total_campaigns: number;
    active_campaigns: number;
    total_nominations: number;
    pending_nominations: number;
    approved_nominations: number;
    rejected_nominations: number;
    shortlisted_candidates: number;
    total_votes: number;
    pending_juries: number;
    completed_juries: number;
    pending_decisions: number;
    total_winners: number;
}
