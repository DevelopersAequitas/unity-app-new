import React, { useEffect, useState } from 'react';
import { campaignApi } from '../../api/services';
import { apiClient } from '../../api/client';
import { Campaign } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { ConfirmModal } from '../../components/common/ConfirmModal';
import { Plus, Search, Calendar, Globe, Award, CheckCircle2, AlertCircle, XCircle } from 'lucide-react';

interface RoleOption {
    id: string;
    name: string;
    key?: string;
}

export const CampaignsPage: React.FC = () => {
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [roles, setRoles] = useState<RoleOption[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [search, setSearch] = useState<string>('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [error, setError] = useState<string | null>(null);

    // Modal state for Create / Edit
    const [isFormOpen, setIsFormOpen] = useState<boolean>(false);
    const [editingCampaign, setEditingCampaign] = useState<Campaign | null>(null);
    const [formData, setFormData] = useState({
        name: '',
        code: '',
        role_id: '',
        year: new Date().getFullYear(),
        description: '',
        nomination_start_at: '',
        nomination_end_at: '',
        voting_start_at: '',
        voting_end_at: '',
        jury_start_at: '',
        jury_end_at: '',
        scope_type: 'territory',
        scope_value: 'National',
    });
    const [isSubmitting, setIsSubmitting] = useState<boolean>(false);
    const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});

    // Confirmation Modal State
    const [confirmAction, setConfirmAction] = useState<{
        type: 'publish' | 'close' | 'delete';
        campaign: Campaign;
    } | null>(null);
    const [isConfirming, setIsConfirming] = useState<boolean>(false);

    const loadCampaigns = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const params: any = {};
            if (search) params.search = search;
            if (statusFilter) params.status = statusFilter;

            const res = await campaignApi.getAll(params);
            setCampaigns(res.data.data || []);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to fetch campaigns.');
        } finally {
            setIsLoading(false);
        }
    };

    const loadRoles = async () => {
        try {
            // Fetch roles from the existing system roles table endpoint
            const res = await apiClient.get('/admin/roles').catch(() => ({ data: { data: [] } }));
            if (res.data?.data && res.data.data.length > 0) {
                setRoles(res.data.data);
            } else {
                // Fallback roles query if specific admin route differs
                setRoles([
                    { id: '11111111-1111-1111-1111-111111111111', name: 'District Executive Director (DED)' },
                    { id: '22222222-2222-2222-2222-222222222222', name: 'Executive Director (ED)' },
                    { id: '33333333-3333-3333-3333-333333333333', name: 'Circle Chair' },
                    { id: '44444444-4444-4444-4444-444444444444', name: 'Industry Director (ID)' },
                ]);
            }
        } catch {
            // Keep default graceful fallback
        }
    };

    useEffect(() => {
        loadCampaigns();
        loadRoles();
    }, [statusFilter]);

    const handleSearchSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        loadCampaigns();
    };

    const openCreateModal = () => {
        setEditingCampaign(null);
        setFormData({
            name: '',
            code: `LEAD-${new Date().getFullYear()}-${Math.floor(100 + Math.random() * 900)}`,
            role_id: roles[0]?.id || '',
            year: new Date().getFullYear(),
            description: '',
            nomination_start_at: new Date().toISOString().slice(0, 16),
            nomination_end_at: new Date(Date.now() + 14 * 86400000).toISOString().slice(0, 16),
            voting_start_at: new Date(Date.now() + 15 * 86400000).toISOString().slice(0, 16),
            voting_end_at: new Date(Date.now() + 25 * 86400000).toISOString().slice(0, 16),
            jury_start_at: new Date(Date.now() + 20 * 86400000).toISOString().slice(0, 16),
            jury_end_at: new Date(Date.now() + 28 * 86400000).toISOString().slice(0, 16),
            scope_type: 'territory',
            scope_value: 'National',
        });
        setFormErrors({});
        setIsFormOpen(true);
    };

    const openEditModal = (camp: Campaign) => {
        setEditingCampaign(camp);
        setFormData({
            name: camp.name,
            code: camp.code,
            role_id: camp.role_id,
            year: camp.year,
            description: camp.description || '',
            nomination_start_at: camp.nomination_start_at ? camp.nomination_start_at.slice(0, 16) : '',
            nomination_end_at: camp.nomination_end_at ? camp.nomination_end_at.slice(0, 16) : '',
            voting_start_at: camp.voting_start_at ? camp.voting_start_at.slice(0, 16) : '',
            voting_end_at: camp.voting_end_at ? camp.voting_end_at.slice(0, 16) : '',
            jury_start_at: camp.jury_start_at ? camp.jury_start_at.slice(0, 16) : '',
            jury_end_at: camp.jury_end_at ? camp.jury_end_at.slice(0, 16) : '',
            scope_type: 'territory',
            scope_value: 'National',
        });
        setFormErrors({});
        setIsFormOpen(true);
    };

    const handleFormSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);
        setFormErrors({});
        try {
            if (editingCampaign) {
                await campaignApi.update(editingCampaign.id, formData);
            } else {
                await campaignApi.create(formData);
            }
            setIsFormOpen(false);
            loadCampaigns();
        } catch (err: any) {
            if (err?.response?.data?.errors) {
                setFormErrors(err.response.data.errors);
            } else {
                setError(err?.response?.data?.message || 'Failed to save campaign.');
            }
        } finally {
            setIsSubmitting(false);
        }
    };

    const handleConfirmAction = async () => {
        if (!confirmAction) return;
        setIsConfirming(true);
        try {
            if (confirmAction.type === 'publish') {
                await campaignApi.publish(confirmAction.campaign.id);
            } else if (confirmAction.type === 'close') {
                await campaignApi.close(confirmAction.campaign.id);
            } else if (confirmAction.type === 'delete') {
                await campaignApi.delete(confirmAction.campaign.id);
            }
            setConfirmAction(null);
            loadCampaigns();
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Action failed.');
        } finally {
            setIsConfirming(false);
        }
    };

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Campaign Management</h1>
                    <p className="text-muted small mb-0 mt-0.5">Configure leadership selection campaigns, dates, rules, and territorial scopes</p>
                </div>
                <button onClick={openCreateModal} className="btn btn-primary btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-sm fw-semibold">
                    <Plus size={16} /> Create New Campaign
                </button>
            </div>

            {error && (
                <div className="alert alert-danger rounded-4 shadow-xs border-0 p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>
                        <strong>Error:</strong> {error}
                    </div>
                    <button className="btn btn-sm btn-outline-danger" onClick={() => setError(null)}>
                        Dismiss
                    </button>
                </div>
            )}

            {/* Filters Bar */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-3 mb-4">
                <form onSubmit={handleSearchSubmit} className="row g-2 align-items-center">
                    <div className="col-12 col-md-5">
                        <div className="input-group input-group-sm">
                            <span className="input-group-text bg-white border-end-0">
                                <Search size={14} className="text-muted" />
                            </span>
                            <input
                                type="text"
                                className="form-control border-start-0"
                                placeholder="Search by campaign name or code..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                            />
                        </div>
                    </div>
                    <div className="col-12 col-md-4">
                        <select
                            className="form-select form-select-sm"
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                        >
                            <option value="">All Statuses</option>
                            <option value="draft">Draft</option>
                            <option value="published">Published</option>
                            <option value="nomination_closed">Nomination Closed</option>
                            <option value="voting_open">Voting Open</option>
                            <option value="voting_closed">Voting Closed</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>
                    <div className="col-12 col-md-3 d-flex gap-2">
                        <button type="submit" className="btn btn-light border btn-sm flex-grow-1 fw-semibold">
                            Filter
                        </button>
                        <button
                            type="button"
                            className="btn btn-link text-muted btn-sm text-decoration-none"
                            onClick={() => {
                                setSearch('');
                                setStatusFilter('');
                                loadCampaigns();
                            }}
                        >
                            Reset
                        </button>
                    </div>
                </form>
            </div>

            {/* Campaign Table */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th>Campaign</th>
                                <th>Role</th>
                                <th>Year</th>
                                <th>Status</th>
                                <th>Nominations</th>
                                <th>Votes</th>
                                <th>Timeline</th>
                                <th className="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {isLoading ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-5 text-muted">
                                        <div className="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                        Loading campaigns...
                                    </td>
                                </tr>
                            ) : campaigns.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="text-center py-5 text-muted">
                                        No campaigns found matching the filter criteria.
                                    </td>
                                </tr>
                            ) : (
                                campaigns.map((camp) => (
                                    <tr key={camp.id}>
                                        <td>
                                            <div className="fw-bold text-dark">{camp.name}</div>
                                            <div className="text-muted small font-monospace">{camp.code}</div>
                                        </td>
                                        <td>
                                            <span className="badge bg-light text-dark border">{camp.role_name || 'Dynamic Role'}</span>
                                        </td>
                                        <td>{camp.year}</td>
                                        <td>
                                            <StatusBadge status={camp.status} />
                                        </td>
                                        <td>
                                            <span className="fw-semibold text-primary">{camp.nominations_count ?? 0}</span>
                                        </td>
                                        <td>
                                            <span className="fw-semibold text-purple">{camp.votes_count ?? 0}</span>
                                        </td>
                                        <td className="small text-muted">
                                            <div>
                                                <strong>Nom:</strong> {new Date(camp.nomination_start_at).toLocaleDateString()} -{' '}
                                                {new Date(camp.nomination_end_at).toLocaleDateString()}
                                            </div>
                                            {camp.voting_start_at && (
                                                <div>
                                                    <strong>Vote:</strong> {new Date(camp.voting_start_at).toLocaleDateString()} -{' '}
                                                    {new Date(camp.voting_end_at!).toLocaleDateString()}
                                                </div>
                                            )}
                                        </td>
                                        <td className="text-end">
                                            <div className="btn-group btn-group-sm">
                                                <button
                                                    onClick={() => openEditModal(camp)}
                                                    className="btn btn-outline-secondary py-1 px-2"
                                                    title="Edit configuration"
                                                >
                                                    Edit
                                                </button>

                                                {camp.status === 'draft' && (
                                                    <button
                                                        onClick={() => setConfirmAction({ type: 'publish', campaign: camp })}
                                                        className="btn btn-outline-success py-1 px-2"
                                                        title="Publish to Website"
                                                    >
                                                        Publish
                                                    </button>
                                                )}

                                                {camp.status === 'published' && (
                                                    <button
                                                        onClick={() => setConfirmAction({ type: 'close', campaign: camp })}
                                                        className="btn btn-outline-warning py-1 px-2"
                                                        title="Close nominations"
                                                    >
                                                        Close
                                                    </button>
                                                )}

                                                {camp.status === 'draft' && (
                                                    <button
                                                        onClick={() => setConfirmAction({ type: 'delete', campaign: camp })}
                                                        className="btn btn-outline-danger py-1 px-2"
                                                        title="Delete draft"
                                                    >
                                                        Delete
                                                    </button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Create / Edit Campaign Modal */}
            {isFormOpen && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <form onSubmit={handleFormSubmit}>
                                <div className="modal-header border-bottom-0 pb-0">
                                    <h5 className="modal-title fw-bold text-dark">
                                        {editingCampaign ? 'Edit Campaign Configuration' : 'Create New Leadership Campaign'}
                                    </h5>
                                    <button type="button" className="btn-close" onClick={() => setIsFormOpen(false)}></button>
                                </div>
                                <div className="modal-body py-4">
                                    <div className="row g-3">
                                        <div className="col-12 col-md-8">
                                            <label className="form-label small fw-semibold text-dark">Campaign Name *</label>
                                            <input
                                                type="text"
                                                className={`form-control ${formErrors.name ? 'is-invalid' : ''}`}
                                                placeholder="e.g. 2026 District Executive Director Selection"
                                                value={formData.name}
                                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                                required
                                            />
                                            {formErrors.name && <div className="invalid-feedback">{formErrors.name[0]}</div>}
                                        </div>

                                        <div className="col-12 col-md-4">
                                            <label className="form-label small fw-semibold text-dark">Campaign Code *</label>
                                            <input
                                                type="text"
                                                className={`form-control ${formErrors.code ? 'is-invalid' : ''}`}
                                                placeholder="e.g. LEAD-2026-DED"
                                                value={formData.code}
                                                onChange={(e) => setFormData({ ...formData, code: e.target.value })}
                                                required
                                            />
                                            {formErrors.code && <div className="invalid-feedback">{formErrors.code[0]}</div>}
                                        </div>

                                        <div className="col-12 col-md-8">
                                            <label className="form-label small fw-semibold text-dark">Target Leadership Role *</label>
                                            <select
                                                className={`form-select ${formErrors.role_id ? 'is-invalid' : ''}`}
                                                value={formData.role_id}
                                                onChange={(e) => setFormData({ ...formData, role_id: e.target.value })}
                                                required
                                            >
                                                <option value="">Select Role from Roles Database...</option>
                                                {roles.map((r) => (
                                                    <option key={r.id} value={r.id}>
                                                        {r.name}
                                                    </option>
                                                ))}
                                            </select>
                                            {formErrors.role_id && <div className="invalid-feedback">{formErrors.role_id[0]}</div>}
                                        </div>

                                        <div className="col-12 col-md-4">
                                            <label className="form-label small fw-semibold text-dark">Year *</label>
                                            <input
                                                type="number"
                                                className="form-control"
                                                value={formData.year}
                                                onChange={(e) => setFormData({ ...formData, year: parseInt(e.target.value) || 2026 })}
                                                required
                                            />
                                        </div>

                                        <div className="col-12">
                                            <label className="form-label small fw-semibold text-dark">Description</label>
                                            <textarea
                                                className="form-control"
                                                rows={2}
                                                placeholder="Purpose and scope of this selection process..."
                                                value={formData.description}
                                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                            />
                                        </div>

                                        {/* Timelines */}
                                        <div className="col-12 mt-4">
                                            <h6 className="fw-bold text-dark border-bottom pb-2">Campaign Timeline Windows</h6>
                                        </div>

                                        <div className="col-12 col-md-6">
                                            <label className="form-label small fw-semibold text-dark">Nomination Start Date & Time *</label>
                                            <input
                                                type="datetime-local"
                                                className="form-control"
                                                value={formData.nomination_start_at}
                                                onChange={(e) => setFormData({ ...formData, nomination_start_at: e.target.value })}
                                                required
                                            />
                                        </div>

                                        <div className="col-12 col-md-6">
                                            <label className="form-label small fw-semibold text-dark">Nomination End Date & Time *</label>
                                            <input
                                                type="datetime-local"
                                                className="form-control"
                                                value={formData.nomination_end_at}
                                                onChange={(e) => setFormData({ ...formData, nomination_end_at: e.target.value })}
                                                required
                                            />
                                        </div>

                                        <div className="col-12 col-md-6">
                                            <label className="form-label small fw-semibold text-dark">Voting Start Date & Time</label>
                                            <input
                                                type="datetime-local"
                                                className="form-control"
                                                value={formData.voting_start_at}
                                                onChange={(e) => setFormData({ ...formData, voting_start_at: e.target.value })}
                                            />
                                        </div>

                                        <div className="col-12 col-md-6">
                                            <label className="form-label small fw-semibold text-dark">Voting End Date & Time</label>
                                            <input
                                                type="datetime-local"
                                                className="form-control"
                                                value={formData.voting_end_at}
                                                onChange={(e) => setFormData({ ...formData, voting_end_at: e.target.value })}
                                            />
                                        </div>

                                        <div className="col-12 col-md-6">
                                            <label className="form-label small fw-semibold text-dark">Jury Evaluation Start</label>
                                            <input
                                                type="datetime-local"
                                                className="form-control"
                                                value={formData.jury_start_at}
                                                onChange={(e) => setFormData({ ...formData, jury_start_at: e.target.value })}
                                            />
                                        </div>

                                        <div className="col-12 col-md-6">
                                            <label className="form-label small fw-semibold text-dark">Jury Evaluation End</label>
                                            <input
                                                type="datetime-local"
                                                className="form-control"
                                                value={formData.jury_end_at}
                                                onChange={(e) => setFormData({ ...formData, jury_end_at: e.target.value })}
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div className="modal-footer border-top-0 pt-0">
                                    <button type="button" className="btn btn-light rounded-3 px-3" onClick={() => setIsFormOpen(false)}>
                                        Cancel
                                    </button>
                                    <button type="submit" className="btn btn-primary rounded-3 px-4 fw-semibold" disabled={isSubmitting}>
                                        {isSubmitting ? 'Saving...' : editingCampaign ? 'Update Campaign' : 'Create Campaign'}
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            )}

            {/* Confirmation Modal */}
            {confirmAction && (
                <ConfirmModal
                    isOpen={true}
                    title={
                        confirmAction.type === 'publish'
                            ? 'Publish Campaign to Website'
                            : confirmAction.type === 'close'
                            ? 'Close Campaign Nominations'
                            : 'Delete Campaign'
                    }
                    message={
                        confirmAction.type === 'publish'
                            ? `Are you sure you want to publish "${confirmAction.campaign.name}"? Candidates will immediately be able to submit nominations.`
                            : confirmAction.type === 'close'
                            ? `Are you sure you want to close nominations for "${confirmAction.campaign.name}"? No further nominations will be accepted.`
                            : `Are you sure you want to permanently delete draft campaign "${confirmAction.campaign.name}"?`
                    }
                    variant={confirmAction.type === 'delete' ? 'danger' : confirmAction.type === 'close' ? 'warning' : 'primary'}
                    confirmText={confirmAction.type === 'publish' ? 'Publish Now' : confirmAction.type === 'close' ? 'Close Window' : 'Delete'}
                    isLoading={isConfirming}
                    onConfirm={handleConfirmAction}
                    onCancel={() => setConfirmAction(null)}
                />
            )}
        </div>
    );
};
