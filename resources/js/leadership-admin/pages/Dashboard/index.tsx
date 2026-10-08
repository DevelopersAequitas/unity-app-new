import React, { useEffect, useState } from 'react';
import { reportApi, campaignApi } from '../../api/services';
import { DashboardOverview, Campaign } from '../../types';
import { StatCard } from '../../components/common/StatCard';
import { StatusBadge } from '../../components/common/Badge';
import {
    Award,
    Users,
    Vote,
    FileCheck2,
    Clock,
    CheckCircle2,
    XCircle,
    UserCheck,
    Scale,
    Trophy,
    RefreshCw,
    Calendar,
} from 'lucide-react';
import { Link } from 'react-router-dom';

export const DashboardPage: React.FC = () => {
    const [overview, setOverview] = useState<DashboardOverview | null>(null);
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [selectedCampaignId, setSelectedCampaignId] = useState<string>('');
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    const loadData = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const [overviewRes, campaignsRes] = await Promise.all([
                reportApi.getDashboardOverview(selectedCampaignId ? { campaign_id: selectedCampaignId } : {}),
                campaignApi.getAll(),
            ]);
            setOverview(overviewRes.data.data);
            setCampaigns(campaignsRes.data.data || []);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to load dashboard metrics from backend.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadData();
    }, [selectedCampaignId]);

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <div className="d-flex align-items-center gap-2 mb-1">
                        <span className="badge rounded-pill px-2.5 py-1" style={{ background: 'rgba(99, 102, 241, 0.12)', color: '#6366f1', fontWeight: 600, fontSize: '0.75rem' }}>
                            <Award className="me-1 d-inline" size={14} /> Leadership Selection System
                        </span>
                        <span className="text-muted small">● Real-time Centralized Hub</span>
                    </div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Leadership Dashboard Overview</h1>
                    <p className="text-muted small mb-0 mt-0.5">Comprehensive monitoring across campaigns, nominations, public voting, and jury scoring</p>
                </div>

                <div className="d-flex align-items-center gap-2 flex-wrap">
                    {/* Campaign Filter */}
                    <select
                        className="form-select form-select-sm rounded-3 shadow-xs"
                        style={{ minWidth: '220px' }}
                        value={selectedCampaignId}
                        onChange={(e) => setSelectedCampaignId(e.target.value)}
                    >
                        <option value="">All Campaigns (Aggregated)</option>
                        {campaigns.map((c) => (
                            <option key={c.id} value={c.id}>
                                {c.name} ({c.year})
                            </option>
                        ))}
                    </select>

                    <button
                        onClick={loadData}
                        disabled={isLoading}
                        className="btn btn-light border btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs"
                        title="Refresh metrics from backend"
                    >
                        <RefreshCw size={14} className={isLoading ? 'spinner-border spinner-border-sm' : ''} />
                        <span className="fw-semibold">Refresh</span>
                    </button>
                </div>
            </div>

            {error && (
                <div className="alert alert-danger rounded-4 shadow-xs border-0 p-3 mb-4 d-flex align-items-center justify-content-between">
                    <div>
                        <strong>Connection Error:</strong> {error}
                    </div>
                    <button className="btn btn-sm btn-outline-danger" onClick={loadData}>
                        Retry
                    </button>
                </div>
            )}

            {/* KPI Cards Grid */}
            <div className="row g-3 mb-4">
                <div className="col-12 col-sm-6 col-lg-3">
                    <StatCard
                        title="Active Campaigns"
                        value={overview?.active_campaigns ?? (isLoading ? '...' : 0)}
                        subtitle={`Total: ${overview?.total_campaigns ?? 0} campaigns`}
                        icon={<Award size={20} />}
                        color="indigo"
                    />
                </div>
                <div className="col-12 col-sm-6 col-lg-3">
                    <StatCard
                        title="Total Nominations"
                        value={overview?.total_nominations ?? (isLoading ? '...' : 0)}
                        subtitle={`${overview?.pending_nominations ?? 0} pending review`}
                        icon={<FileCheck2 size={20} />}
                        color="sky"
                    />
                </div>
                <div className="col-12 col-sm-6 col-lg-3">
                    <StatCard
                        title="Approved Nominations"
                        value={overview?.approved_nominations ?? (isLoading ? '...' : 0)}
                        subtitle={`${overview?.shortlisted_candidates ?? 0} shortlisted`}
                        icon={<UserCheck size={20} />}
                        color="emerald"
                    />
                </div>
                <div className="col-12 col-sm-6 col-lg-3">
                    <StatCard
                        title="Total Public Votes"
                        value={overview?.total_votes ?? (isLoading ? '...' : 0)}
                        subtitle="Verified duplicate-resistant"
                        icon={<Vote size={20} />}
                        color="purple"
                    />
                </div>
            </div>

            <div className="row g-3 mb-4">
                <div className="col-12 col-sm-6 col-lg-3">
                    <StatCard
                        title="Jury Progress"
                        value={`${overview?.completed_juries ?? 0} Done`}
                        subtitle={`${overview?.pending_juries ?? 0} assignments pending`}
                        icon={<Scale size={20} />}
                        color="amber"
                    />
                </div>
                <div className="col-12 col-sm-6 col-lg-3">
                    <StatCard
                        title="Pending Decisions"
                        value={overview?.pending_decisions ?? (isLoading ? '...' : 0)}
                        subtitle="Awaiting final selection"
                        icon={<Clock size={20} />}
                        color="rose"
                    />
                </div>
                <div className="col-12 col-sm-6 col-lg-3">
                    <StatCard
                        title="Declared Winners"
                        value={overview?.total_winners ?? (isLoading ? '...' : 0)}
                        subtitle="Official selections"
                        icon={<Trophy size={20} />}
                        color="emerald"
                    />
                </div>
                <div className="col-12 col-sm-6 col-lg-3">
                    <StatCard
                        title="Rejected Nominations"
                        value={overview?.rejected_nominations ?? (isLoading ? '...' : 0)}
                        subtitle="Ineligible or incomplete"
                        icon={<XCircle size={20} />}
                        color="rose"
                    />
                </div>
            </div>

            {/* Campaigns Table & Quick Actions */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-4 mb-4">
                <div className="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h5 className="fw-bold text-dark mb-0">Active Leadership Campaigns</h5>
                        <p className="text-muted small mb-0">Overview of configured campaigns and their current lifecycle stage</p>
                    </div>
                    <Link to="/campaigns" className="btn btn-primary btn-sm rounded-3 px-3 fw-semibold">
                        Manage Campaigns
                    </Link>
                </div>

                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th>Campaign Name</th>
                                <th>Role</th>
                                <th>Year</th>
                                <th>Status</th>
                                <th>Nomination Window</th>
                                <th>Voting Window</th>
                                <th className="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {isLoading ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-4 text-muted">
                                        <div className="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                        Loading campaigns from server...
                                    </td>
                                </tr>
                            ) : campaigns.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-4 text-muted">
                                        No leadership campaigns found. Create your first campaign to get started.
                                    </td>
                                </tr>
                            ) : (
                                campaigns.slice(0, 5).map((camp) => (
                                    <tr key={camp.id}>
                                        <td>
                                            <div className="fw-semibold text-dark">{camp.name}</div>
                                            <div className="text-muted small font-monospace">{camp.code}</div>
                                        </td>
                                        <td>
                                            <span className="badge bg-light text-dark border">{camp.role_name || 'Dynamic Role'}</span>
                                        </td>
                                        <td>{camp.year}</td>
                                        <td>
                                            <StatusBadge status={camp.status} />
                                        </td>
                                        <td className="small text-muted">
                                            {camp.nomination_start_at ? new Date(camp.nomination_start_at).toLocaleDateString() : 'N/A'} -{' '}
                                            {camp.nomination_end_at ? new Date(camp.nomination_end_at).toLocaleDateString() : 'N/A'}
                                        </td>
                                        <td className="small text-muted">
                                            {camp.voting_start_at ? new Date(camp.voting_start_at).toLocaleDateString() : 'Not set'} -{' '}
                                            {camp.voting_end_at ? new Date(camp.voting_end_at).toLocaleDateString() : 'Not set'}
                                        </td>
                                        <td className="text-end">
                                            <Link to={`/campaigns`} className="btn btn-outline-secondary btn-sm rounded-2 py-1 px-2.5">
                                                View
                                            </Link>
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
