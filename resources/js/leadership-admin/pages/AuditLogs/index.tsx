import React, { useEffect, useState } from 'react';
import { auditApi } from '../../api/services';
import { AuditLog } from '../../types';
import { ShieldCheck, Search, RefreshCw, Eye, History, User } from 'lucide-react';

export const AuditLogsPage: React.FC = () => {
    const [logs, setLogs] = useState<AuditLog[]>([]);
    const [actionFilter, setActionFilter] = useState<string>('');
    const [entityFilter, setEntityFilter] = useState<string>('');
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);

    // Detail Modal for Diff Inspection
    const [selectedLog, setSelectedLog] = useState<AuditLog | null>(null);

    const loadLogs = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const params: any = {};
            if (actionFilter) params.action = actionFilter;
            if (entityFilter) params.entity_type = entityFilter;

            const res = await auditApi.getLogs(params);
            setLogs(res.data.data || []);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to fetch audit log trail.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadLogs();
    }, [actionFilter, entityFilter]);

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Audit Trail & Security Logs</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Immutable, tamper-resistant record of every administrative action, nomination status change, and election event
                    </p>
                </div>
                <button
                    onClick={loadLogs}
                    disabled={isLoading}
                    className="btn btn-light border btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs"
                >
                    <RefreshCw size={14} className={isLoading ? 'spinner-border spinner-border-sm' : ''} /> Refresh Trail
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

            {/* Filters Bar */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-3 mb-4">
                <div className="row g-2 align-items-center">
                    <div className="col-12 col-md-4">
                        <select
                            className="form-select form-select-sm"
                            value={entityFilter}
                            onChange={(e) => setEntityFilter(e.target.value)}
                        >
                            <option value="">All Entities (Campaigns, Nominations, Juries, Decisions)</option>
                            <option value="campaign">Campaigns</option>
                            <option value="nomination">Nominations</option>
                            <option value="vote">Votes</option>
                            <option value="jury_assignment">Jury Assignments</option>
                            <option value="final_decision">Final Decisions</option>
                        </select>
                    </div>

                    <div className="col-12 col-md-4">
                        <input
                            type="text"
                            className="form-control form-control-sm"
                            placeholder="Filter by action (e.g. approve, publish)..."
                            value={actionFilter}
                            onChange={(e) => setActionFilter(e.target.value)}
                        />
                    </div>

                    <div className="col-12 col-md-4 text-md-end">
                        <span className="text-muted small">
                            Recorded Audits: <strong>{logs.length}</strong>
                        </span>
                    </div>
                </div>
            </div>

            {/* Audit Logs Table */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th>Timestamp</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Entity</th>
                                <th>Entity ID</th>
                                <th>IP Address</th>
                                <th className="text-end">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            {isLoading ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-5 text-muted">
                                        <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Fetching security audit trail...
                                    </td>
                                </tr>
                            ) : logs.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-5 text-muted">
                                        No audit trail logs recorded yet.
                                    </td>
                                </tr>
                            ) : (
                                logs.map((log) => (
                                    <tr key={log.id}>
                                        <td className="small text-muted">{new Date(log.created_at).toLocaleString()}</td>
                                        <td>
                                            <div className="fw-semibold text-dark">{log.actor_name || 'System Admin'}</div>
                                            <div className="text-muted small font-monospace">{log.actor_id || '-'}</div>
                                        </td>
                                        <td>
                                            <span className="badge bg-light text-dark border font-monospace text-uppercase">
                                                {log.action}
                                            </span>
                                        </td>
                                        <td>
                                            <span className="badge bg-indigo-subtle text-indigo">{log.entity_type}</span>
                                        </td>
                                        <td className="font-monospace small text-muted">{log.entity_id}</td>
                                        <td className="small text-muted">{log.ip_address || 'Internal'}</td>
                                        <td className="text-end">
                                            <button
                                                onClick={() => setSelectedLog(log)}
                                                className="btn btn-outline-secondary btn-sm rounded-2 py-0.5 px-2.5 d-inline-flex align-items-center gap-1"
                                            >
                                                <Eye size={12} /> Inspect
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Diff Inspection Modal */}
            {selectedLog && (
                <div className="modal fade show d-block" tabIndex={-1} style={{ backgroundColor: 'rgba(0,0,0,0.5)', zIndex: 1055 }}>
                    <div className="modal-dialog modal-lg modal-dialog-centered">
                        <div className="modal-content rounded-4 border-0 shadow">
                            <div className="modal-header border-bottom-0 pb-0">
                                <h5 className="modal-title fw-bold text-dark">Audit Event Payload Details</h5>
                                <button type="button" className="btn-close" onClick={() => setSelectedLog(null)}></button>
                            </div>
                            <div className="modal-body py-3">
                                <div className="p-3 bg-light rounded-3 border mb-3">
                                    <div className="row g-2 small">
                                        <div className="col-6">
                                            <strong>Action:</strong> {selectedLog.action}
                                        </div>
                                        <div className="col-6">
                                            <strong>Entity:</strong> {selectedLog.entity_type} ({selectedLog.entity_id})
                                        </div>
                                        <div className="col-6">
                                            <strong>Actor:</strong> {selectedLog.actor_name || selectedLog.actor_id || 'System'}
                                        </div>
                                        <div className="col-6">
                                            <strong>Timestamp:</strong> {new Date(selectedLog.created_at).toLocaleString()}
                                        </div>
                                    </div>
                                </div>

                                <div className="row g-3">
                                    <div className="col-12 col-md-6">
                                        <h6 className="fw-semibold text-muted small text-uppercase">Before State:</h6>
                                        <pre className="p-3 bg-light rounded-3 border small" style={{ maxHeight: '200px', overflowY: 'auto' }}>
                                            {selectedLog.before_state ? JSON.stringify(selectedLog.before_state, null, 2) : 'null'}
                                        </pre>
                                    </div>
                                    <div className="col-12 col-md-6">
                                        <h6 className="fw-semibold text-muted small text-uppercase">After State:</h6>
                                        <pre className="p-3 bg-light rounded-3 border small" style={{ maxHeight: '200px', overflowY: 'auto' }}>
                                            {selectedLog.after_state ? JSON.stringify(selectedLog.after_state, null, 2) : 'null'}
                                        </pre>
                                    </div>
                                </div>
                            </div>
                            <div className="modal-footer border-top-0 pt-0">
                                <button type="button" className="btn btn-secondary btn-sm" onClick={() => setSelectedLog(null)}>
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};
