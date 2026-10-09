import { apiClient } from './client';
import {
    ApiResponse,
    Campaign,
    Scope,
    FormTemplate,
    Nomination,
    JuryAssignment,
    EvaluationCriterion,
    FinalDecision,
    WinnerCreative,
    NotificationLog,
    AuditLog,
    DashboardOverview,
} from '../types';

export const campaignApi = {
    getAll: (params?: any) => apiClient.get<ApiResponse<Campaign[]>>('/admin/campaigns', { params }),
    getById: (id: string) => apiClient.get<ApiResponse<Campaign>>(`/admin/campaigns/${id}`),
    create: (data: any) => apiClient.post<ApiResponse<Campaign>>('/admin/campaigns', data),
    update: (id: string, data: any) => apiClient.put<ApiResponse<Campaign>>(`/admin/campaigns/${id}`, data),
    publish: (id: string) => apiClient.post<ApiResponse<Campaign>>(`/admin/campaigns/${id}/publish`),
    pause: (id: string) => apiClient.post<ApiResponse<Campaign>>(`/admin/campaigns/${id}/pause`),
    close: (id: string) => apiClient.post<ApiResponse<Campaign>>(`/admin/campaigns/${id}/close`),
    resume: (id: string) => apiClient.post<ApiResponse<Campaign>>(`/admin/campaigns/${id}/resume`),
    toggleStatus: (id: string) => apiClient.post<ApiResponse<Campaign>>(`/admin/campaigns/${id}/toggle-status`),
    delete: (id: string) => apiClient.delete<ApiResponse<any>>(`/admin/campaigns/${id}`),
    getScopes: (campaignId: string) => apiClient.get<ApiResponse<Scope[]>>(`/admin/campaigns/${campaignId}/scopes`),
    addScope: (campaignId: string, data: any) => apiClient.post<ApiResponse<Scope>>(`/admin/campaigns/${campaignId}/scopes`, data),
    deleteScope: (campaignId: string, scopeId: string) => apiClient.delete<ApiResponse<any>>(`/admin/campaigns/${campaignId}/scopes/${scopeId}`),
};

export const formApi = {
    getTemplates: (params?: any) => apiClient.get<ApiResponse<FormTemplate[]>>('/admin/forms/templates', { params }),
    getTemplate: (id: string) => apiClient.get<ApiResponse<FormTemplate>>(`/admin/forms/templates/${id}`),
    createTemplate: (data: any) => apiClient.post<ApiResponse<FormTemplate>>('/admin/forms/templates', data),
    updateTemplate: (id: string, data: any) => apiClient.put<ApiResponse<FormTemplate>>(`/admin/forms/templates/${id}`, data),
    publishTemplate: (id: string) => apiClient.post<ApiResponse<FormTemplate>>(`/admin/forms/templates/${id}/publish`),
    createSection: (data: any) => apiClient.post<ApiResponse<any>>('/admin/forms/sections', data),
    updateSection: (id: string, data: any) => apiClient.put<ApiResponse<any>>(`/admin/forms/sections/${id}`, data),
    deleteSection: (id: string) => apiClient.delete<ApiResponse<any>>(`/admin/forms/sections/${id}`),
    createQuestion: (data: any) => apiClient.post<ApiResponse<any>>('/admin/forms/questions', data),
    updateQuestion: (id: string, data: any) => apiClient.put<ApiResponse<any>>(`/admin/forms/questions/${id}`, data),
    deleteQuestion: (id: string) => apiClient.delete<ApiResponse<any>>(`/admin/forms/questions/${id}`),
    createOption: (data: any) => apiClient.post<ApiResponse<any>>('/admin/forms/options', data),
    updateOption: (id: string, data: any) => apiClient.put<ApiResponse<any>>(`/admin/forms/options/${id}`, data),
    deleteOption: (id: string) => apiClient.delete<ApiResponse<any>>(`/admin/forms/options/${id}`),
};

export const nominationApi = {
    getAll: (params?: any) => apiClient.get<ApiResponse<Nomination[]>>('/admin/nominations', { params }),
    getById: (id: string) => apiClient.get<ApiResponse<Nomination>>(`/admin/nominations/${id}`),
    requestCorrection: (id: string, correction_notes: string) =>
        apiClient.post<ApiResponse<Nomination>>(`/admin/nominations/${id}/request-correction`, { correction_notes }),
    approve: (id: string, admin_remarks?: string) =>
        apiClient.post<ApiResponse<Nomination>>(`/admin/nominations/${id}/approve`, { admin_remarks }),
    reject: (id: string, rejection_reason: string) =>
        apiClient.post<ApiResponse<Nomination>>(`/admin/nominations/${id}/reject`, { rejection_reason }),
    shortlist: (id: string) => apiClient.post<ApiResponse<Nomination>>(`/admin/nominations/${id}/shortlist`),
};

export const votingApi = {
    getTally: (campaignId: string) => apiClient.get<ApiResponse<any>>(`/admin/voting/campaigns/${campaignId}/tally`),
    getSummary: (campaignId: string) => apiClient.get<ApiResponse<any>>(`/voting/campaigns/${campaignId}/summary`),
    generateResultToken: (nominationId: string, expiresInDays?: number) =>
        apiClient.post<ApiResponse<any>>(`/admin/voting/nominations/${nominationId}/result-token`, { expires_in_days: expiresInDays }),
    revokeResultToken: (tokenId: string) =>
        apiClient.delete<ApiResponse<any>>(`/admin/voting/result-tokens/${tokenId}`),
};

export const juryApi = {
    getAssignments: (params?: any) => apiClient.get<ApiResponse<JuryAssignment[]>>('/admin/jury/assignments', { params }),
    assign: (data: any) => apiClient.post<ApiResponse<JuryAssignment>>('/admin/jury/assign', data),
    deleteAssignment: (id: string) => apiClient.delete<ApiResponse<any>>(`/admin/jury/assignments/${id}`),
    getCriteria: (params?: any) => apiClient.get<ApiResponse<EvaluationCriterion[]>>('/admin/jury/criteria', { params }),
    createCriterion: (data: any) => apiClient.post<ApiResponse<EvaluationCriterion>>('/admin/jury/criteria', data),
    updateCriterion: (id: string, data: any) => apiClient.put<ApiResponse<EvaluationCriterion>>(`/admin/jury/criteria/${id}`, data),
    deleteCriterion: (id: string) => apiClient.delete<ApiResponse<any>>(`/admin/jury/criteria/${id}`),
    getReport: (campaignId: string) => apiClient.get<ApiResponse<any>>(`/admin/jury/reports/${campaignId}`),
};

export const decisionApi = {
    getByCampaign: (campaignId: string) => apiClient.get<ApiResponse<FinalDecision[]>>(`/admin/decisions/campaigns/${campaignId}`),
    submit: (data: any) => apiClient.post<ApiResponse<FinalDecision>>('/admin/decisions', data),
    publishWinners: (campaignId: string) => apiClient.post<ApiResponse<any>>(`/admin/decisions/campaigns/${campaignId}/publish-winners`),
};

export const creativeApi = {
    getByCampaign: (campaignId: string) => apiClient.get<ApiResponse<WinnerCreative[]>>(`/admin/creatives/campaigns/${campaignId}`),
    generate: (data: any) => apiClient.post<ApiResponse<WinnerCreative>>('/admin/creatives/generate', data),
    getDownloadUrl: (id: string) => `/api/v1/leadership/creatives/${id}/download`,
};

export const notificationApi = {
    getLogs: (params?: any) => apiClient.get<ApiResponse<NotificationLog[]>>('/admin/notifications/logs', { params }),
    resend: (id: string) => apiClient.post<ApiResponse<any>>(`/admin/notifications/resend/${id}`),
};

export const reportApi = {
    getDashboardOverview: (params?: any) => apiClient.get<ApiResponse<DashboardOverview>>('/admin/reports/dashboard-overview', { params }),
    getCampaignDetailed: (campaignId: string) => apiClient.get<ApiResponse<any>>(`/admin/reports/campaigns/${campaignId}/detailed`),
    exportNominationsUrl: (campaignId: string) => `/api/v1/leadership/admin/reports/export/nominations/${campaignId}`,
    exportVotesUrl: (campaignId: string) => `/api/v1/leadership/admin/reports/export/votes/${campaignId}`,
    exportJuryScoresUrl: (campaignId: string) => `/api/v1/leadership/admin/reports/export/jury-scores/${campaignId}`,
};

export const auditApi = {
    getLogs: (params?: any) => apiClient.get<ApiResponse<AuditLog[]>>('/admin/audit-logs', { params }),
};
