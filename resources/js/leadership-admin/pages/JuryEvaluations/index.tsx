import React, { useEffect, useState } from 'react';
import { juryApi, campaignApi } from '../../api/services';
import { Campaign } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { Scale, Users, Award, FileText, CheckCircle2, AlertCircle, RefreshCw } from 'lucide-react';

export const JuryEvaluationsPage: React.FC = () => {
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [selectedCampaignId, setSelectedCampaignId] = useState<string>('');
    const [reportData, setReportData] = useState<any[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    const loadCampaigns = async () => {
        try {
            const res = await campaignApi.getAll();
            const list = res.data.data || [];
            setCampaigns(list);
            if (list.length > 0 && !selectedCampaignId) {
                setSelectedCampaignId(list[0].id);
            }
        } catch {
            setError('Failed to load campaigns.');
        }
    };

    const loadReport = async (campaignId: string) => {
        if (!campaignId) return;
        setIsLoading(true);
        setError(null);
        try {
            const res = await juryApi.getReport(campaignId);
            setReportData(res.data.data?.evaluations || res.data.data || []);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to load jury evaluation reports.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadCampaigns();
    }, []);

    useEffect(() => {
        if (selectedCampaignId) {
            loadReport(selectedCampaignId);
        }
    }, [selectedCampaignId]);

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Jury Evaluation Reviews</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Consolidated Form 2 evaluation responses, multi-criterion scorecards, weighted scores, and juror recommendations
                    </p>
                </div>
                <button
                    onClick={() => loadReport(selectedCampaignId)}
                    disabled={isLoading}
                    className="btn btn-light border btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs"
                >
                    <RefreshCw size={14} className={isLoading ? 'spinner-border spinner-border-sm' : ''} /> Refresh Report
                </button>
            </div>

            {error && (
                <div className="alert alert-danger rounded-4 border-0 shadow-xs p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>{error}</div>
                    <button className="btn btn-sm btn-outline-danger" onClick={() => setError(null)}>
                        Dismiss
                    </button>
                </div>
            )}

            {/* Campaign Filter Bar */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-3 mb-4">
                <div className="row g-2 align-items-center">
                    <div className="col-12 col-md-6">
                        <label className="form-label text-muted small fw-semibold text-uppercase tracking-wider mb-1">
                            Campaign:
                        </label>
                        <select
                            className="form-select form-select-sm"
                            value={selectedCampaignId}
                            onChange={(e) => setSelectedCampaignId(e.target.value)}
                        >
                            {campaigns.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name} ({c.year})
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {/* Evaluations Report Table */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th>Candidate</th>
                                <th>Juror</th>
                                <th>Overall Score</th>
                                <th>Completion</th>
                                <th>Juror Recommendation</th>
                                <th>Submitted At</th>
                            </tr>
                        </thead>
                        <tbody>
                            {isLoading ? (
                                <tr>
                                    <td colSpan={6} className="text-center py-5 text-muted">
                                        <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Fetching evaluation scorecards...
                                    </td>
                                </tr>
                            ) : reportData.length === 0 ? (
                                <tr>
                                    <td colSpan={6} className="text-center py-5 text-muted">
                                        No submitted evaluations recorded for this campaign yet.
                                    </td>
                                </tr>
                            ) : (
                                reportData.map((ev, idx) => (
                                    <tr key={idx}>
                                        <td className="fw-semibold text-dark">{ev.candidate_name || 'Candidate'}</td>
                                        <td className="text-muted small">{ev.jury_name || ev.jury_user_id || 'Juror'}</td>
                                        <td>
                                            <span className="h6 fw-bold text-primary mb-0">{ev.total_score ?? 'N/A'}</span>
                                            {ev.max_score && <span className="text-muted small"> / {ev.max_score} pts</span>}
                                        </td>
                                        <td>
                                            <StatusBadge status={ev.status || 'completed'} />
                                        </td>
                                        <td>
                                            <span className="badge bg-light text-dark border">
                                                {ev.recommendation || 'Recommended for Leadership'}
                                            </span>
                                        </td>
                                        <td className="small text-muted">
                                            {ev.submitted_at ? new Date(ev.submitted_at).toLocaleDateString() : 'Pending'}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
};
