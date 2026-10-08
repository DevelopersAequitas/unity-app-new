import React, { useEffect, useState } from 'react';
import { reportApi, campaignApi } from '../../api/services';
import { Campaign } from '../../types';
import { BarChart3, Download, FileText, Vote, Scale, Users, Award } from 'lucide-react';

export const ReportsPage: React.FC = () => {
    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [selectedCampaignId, setSelectedCampaignId] = useState<string>('');
    const [isLoading, setIsLoading] = useState<boolean>(true);

    const loadCampaigns = async () => {
        try {
            const res = await campaignApi.getAll();
            const list = res.data.data || [];
            setCampaigns(list);
            if (list.length > 0 && !selectedCampaignId) {
                setSelectedCampaignId(list[0].id);
            }
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadCampaigns();
    }, []);

    const selectedCampaign = campaigns.find((c) => c.id === selectedCampaignId);

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Analytics & Reports</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Download verified CSV datasets, election summaries, voter audit trails, and jury scorecards
                    </p>
                </div>
            </div>

            {/* Campaign Selector Bar */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-3 mb-4">
                <div className="row g-2 align-items-center">
                    <div className="col-12 col-md-6">
                        <label className="form-label text-muted small fw-semibold text-uppercase tracking-wider mb-1">
                            Select Campaign to Export:
                        </label>
                        <select
                            className="form-select form-select-sm"
                            value={selectedCampaignId}
                            onChange={(e) => setSelectedCampaignId(e.target.value)}
                        >
                            {campaigns.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name} ({c.year}) - {c.status.toUpperCase()}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            </div>

            {/* Export Cards */}
            <div className="row g-4">
                <div className="col-12 col-md-4">
                    <div className="card border-0 shadow-xs rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div className="p-3 rounded-3 bg-light d-inline-flex mb-3 text-primary">
                                <Users size={24} />
                            </div>
                            <h5 className="fw-bold text-dark mb-1">Nomination Report</h5>
                            <p className="text-muted small mb-4">
                                Complete export of all candidate applications, submitted profile fields, verification status, and review outcomes.
                            </p>
                        </div>
                        {selectedCampaignId ? (
                            <a
                                href={reportApi.exportNominationsUrl(selectedCampaignId)}
                                download
                                className="btn btn-outline-primary btn-sm rounded-3 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2"
                            >
                                <Download size={16} /> Download Nominations CSV
                            </a>
                        ) : (
                            <button disabled className="btn btn-light btn-sm rounded-3 py-2">
                                Select a campaign
                            </button>
                        )}
                    </div>
                </div>

                <div className="col-12 col-md-4">
                    <div className="card border-0 shadow-xs rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div className="p-3 rounded-3 bg-light d-inline-flex mb-3 text-purple">
                                <Vote size={24} />
                            </div>
                            <h5 className="fw-bold text-dark mb-1">Verified Votes Audit</h5>
                            <p className="text-muted small mb-4">
                                Tamper-resistant voting ledger with anonymized voter token hashes, timestamps, candidate tallies, and IP logs.
                            </p>
                        </div>
                        {selectedCampaignId ? (
                            <a
                                href={reportApi.exportVotesUrl(selectedCampaignId)}
                                download
                                className="btn btn-outline-purple btn-sm rounded-3 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 text-primary border-primary"
                            >
                                <Download size={16} /> Download Voting Ledger CSV
                            </a>
                        ) : (
                            <button disabled className="btn btn-light btn-sm rounded-3 py-2">
                                Select a campaign
                            </button>
                        )}
                    </div>
                </div>

                <div className="col-12 col-md-4">
                    <div className="card border-0 shadow-xs rounded-4 p-4 bg-white h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div className="p-3 rounded-3 bg-light d-inline-flex mb-3 text-warning">
                                <Scale size={24} />
                            </div>
                            <h5 className="fw-bold text-dark mb-1">Jury Scoring Ledger</h5>
                            <p className="text-muted small mb-4">
                                Detailed breakdown of each juror's multi-criterion scores, weights, candidate remarks, and recommendations.
                            </p>
                        </div>
                        {selectedCampaignId ? (
                            <a
                                href={reportApi.exportJuryScoresUrl(selectedCampaignId)}
                                download
                                className="btn btn-outline-warning btn-sm rounded-3 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2"
                            >
                                <Download size={16} /> Download Jury Scorecard CSV
                            </a>
                        ) : (
                            <button disabled className="btn btn-light btn-sm rounded-3 py-2">
                                Select a campaign
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
};
