import React, { useEffect, useState } from 'react';
import { notificationApi } from '../../api/services';
import { NotificationLog } from '../../types';
import { StatusBadge } from '../../components/common/Badge';
import { Bell, RefreshCw, Send, Mail, MessageSquare, Phone, AlertCircle, CheckCircle } from 'lucide-react';

export const NotificationsPage: React.FC = () => {
    const [logs, setLogs] = useState<NotificationLog[]>([]);
    const [channelFilter, setChannelFilter] = useState<string>('');
    const [statusFilter, setStatusFilter] = useState<string>('');
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [error, setError] = useState<string | null>(null);
    const [isRetrying, setIsRetrying] = useState<string | null>(null);

    const loadLogs = async () => {
        setIsLoading(true);
        setError(null);
        try {
            const params: any = {};
            if (channelFilter) params.channel = channelFilter;
            if (statusFilter) params.status = statusFilter;

            const res = await notificationApi.getLogs(params);
            setLogs(res.data.data || []);
        } catch (err: any) {
            setError(err?.response?.data?.message || 'Failed to fetch notification dispatch logs.');
        } finally {
            setIsLoading(false);
        }
    };

    useEffect(() => {
        loadLogs();
    }, [channelFilter, statusFilter]);

    const handleRetry = async (id: string) => {
        setIsRetrying(id);
        try {
            await notificationApi.resend(id);
            alert('Notification dispatch retried successfully.');
            loadLogs();
        } catch (err: any) {
            alert(err?.response?.data?.message || 'Failed to resend notification.');
        } finally {
            setIsRetrying(null);
        }
    };

    return (
        <div className="container-fluid px-0">
            {/* Header Toolbar */}
            <div className="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 p-4 bg-white rounded-4 border shadow-xs mb-4">
                <div>
                    <h1 className="h4 fw-bold text-dark mb-0 tracking-tight">Notification Logs & Dispatch</h1>
                    <p className="text-muted small mb-0 mt-0.5">
                        Audit trail of all email, SMS, and WhatsApp messages generated across the leadership lifecycle
                    </p>
                </div>
                <button
                    onClick={loadLogs}
                    disabled={isLoading}
                    className="btn btn-light border btn-sm rounded-3 px-3 py-2 d-flex align-items-center gap-1.5 shadow-xs"
                >
                    <RefreshCw size={14} className={isLoading ? 'spinner-border spinner-border-sm' : ''} /> Refresh Logs
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
                            value={channelFilter}
                            onChange={(e) => setChannelFilter(e.target.value)}
                        >
                            <option value="">All Channels (Email, SMS, WhatsApp)</option>
                            <option value="email">Email Gateway</option>
                            <option value="sms">SMS Gateway</option>
                            <option value="whatsapp">WhatsApp FlexiMSG</option>
                        </select>
                    </div>

                    <div className="col-12 col-md-4">
                        <select
                            className="form-select form-select-sm"
                            value={statusFilter}
                            onChange={(e) => setStatusFilter(e.target.value)}
                        >
                            <option value="">All Delivery Statuses</option>
                            <option value="sent">Delivered / Sent</option>
                            <option value="queued">Queued in Background</option>
                            <option value="failed">Failed Delivery</option>
                        </select>
                    </div>

                    <div className="col-12 col-md-4 text-md-end">
                        <span className="text-muted small">Total Messages: <strong>{logs.length}</strong></span>
                    </div>
                </div>
            </div>

            {/* Notification Table */}
            <div className="card border-0 shadow-xs rounded-4 bg-white p-4">
                <div className="table-responsive">
                    <table className="table table-hover align-middle mb-0">
                        <thead className="table-light">
                            <tr>
                                <th>Channel</th>
                                <th>Recipient</th>
                                <th>Template</th>
                                <th>Delivery Status</th>
                                <th>Timestamp</th>
                                <th>Failure Reason</th>
                                <th className="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {isLoading ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-5 text-muted">
                                        <div className="spinner-border spinner-border-sm text-primary me-2"></div>
                                        Fetching notification delivery records...
                                    </td>
                                </tr>
                            ) : logs.length === 0 ? (
                                <tr>
                                    <td colSpan={7} className="text-center py-5 text-muted">
                                        No notification dispatch records found.
                                    </td>
                                </tr>
                            ) : (
                                logs.map((log) => (
                                    <tr key={log.id}>
                                        <td>
                                            <span className="badge bg-light text-dark border text-uppercase d-inline-flex align-items-center gap-1">
                                                {log.channel === 'email' ? (
                                                    <Mail size={12} className="text-primary" />
                                                ) : log.channel === 'whatsapp' ? (
                                                    <MessageSquare size={12} className="text-success" />
                                                ) : (
                                                    <Phone size={12} className="text-info" />
                                                )}
                                                {log.channel}
                                            </span>
                                        </td>
                                        <td>
                                            <div className="fw-semibold text-dark">{log.recipient_email || log.recipient_phone || 'User'}</div>
                                        </td>
                                        <td className="small font-monospace text-muted">{log.template_name}</td>
                                        <td>
                                            <StatusBadge status={log.status} />
                                        </td>
                                        <td className="small text-muted">{new Date(log.created_at).toLocaleString()}</td>
                                        <td className="small text-danger">{log.error_message || '-'}</td>
                                        <td className="text-end">
                                            {log.status === 'failed' ? (
                                                <button
                                                    onClick={() => handleRetry(log.id)}
                                                    disabled={isRetrying === log.id}
                                                    className="btn btn-outline-warning btn-sm rounded-2 py-0.5 px-2.5 d-inline-flex align-items-center gap-1"
                                                >
                                                    <RefreshCw size={12} /> {isRetrying === log.id ? 'Retrying...' : 'Retry'}
                                                </button>
                                            ) : (
                                                <span className="text-muted small">-</span>
                                            )}
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
