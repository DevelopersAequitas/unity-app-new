import React, { Component, ErrorInfo, ReactNode } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { DashboardPage } from './pages/Dashboard';
import { CampaignsPage } from './pages/Campaigns';
import { FormBuilderPage } from './pages/FormBuilder';
import { NominationsPage } from './pages/Nominations';
import { VotingPage } from './pages/Voting';
import { JuryPage } from './pages/Jury';
import { JuryEvaluationsPage } from './pages/JuryEvaluations';
import { FinalDecisionsPage } from './pages/FinalDecisions';
import { WinnersPage } from './pages/Winners';
import { CreativesPage } from './pages/Creatives';
import { NotificationsPage } from './pages/Notifications';
import { ReportsPage } from './pages/Reports';
import { AuditLogsPage } from './pages/AuditLogs';

interface ErrorBoundaryProps {
    children: ReactNode;
}

interface ErrorBoundaryState {
    hasError: boolean;
    error: Error | null;
}

class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
    public state: ErrorBoundaryState = {
        hasError: false,
        error: null,
    };

    public static getDerivedStateFromError(error: Error): ErrorBoundaryState {
        return { hasError: true, error };
    }

    public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        console.error('Leadership Selection Admin Runtime Error:', error, errorInfo);
    }

    public render() {
        if (this.state.hasError) {
            return (
                <div className="container-fluid py-4">
                    <div className="alert alert-danger rounded-4 p-4 shadow-sm">
                        <h5 className="fw-bold text-danger mb-2">Something went wrong while rendering this section</h5>
                        <p className="mb-3 small text-secondary">{this.state.error?.message || 'An unexpected rendering error occurred.'}</p>
                        <button
                            className="btn btn-sm btn-outline-danger fw-semibold rounded-3 px-3"
                            onClick={() => {
                                this.setState({ hasError: false, error: null });
                                window.location.reload();
                            }}
                        >
                            Reload Section
                        </button>
                    </div>
                </div>
            );
        }

        return this.props.children;
    }
}

export const App: React.FC = () => {
    return (
        <ErrorBoundary>
            <BrowserRouter basename="/admin/web/leadership">
                <Routes>
                    <Route path="/" element={<Navigate to="/dashboard" replace />} />
                    <Route path="/dashboard" element={<DashboardPage />} />
                    <Route path="/campaigns" element={<CampaignsPage />} />
                    <Route path="/forms" element={<FormBuilderPage />} />
                    <Route path="/nominations" element={<NominationsPage />} />
                    <Route path="/nominations/main" element={<NominationsPage />} />
                    <Route path="/nominations/main-form-1" element={<NominationsPage />} />
                    <Route path="/nominations/sub-form-2" element={<NominationsPage />} />
                    <Route path="/nominations/sub" element={<NominationsPage />} />
                    <Route path="/voting" element={<VotingPage />} />
                    <Route path="/jury" element={<JuryPage />} />
                    <Route path="/jury-evaluations" element={<JuryEvaluationsPage />} />
                    <Route path="/decisions" element={<FinalDecisionsPage />} />
                    <Route path="/winners" element={<WinnersPage />} />
                    <Route path="/creatives" element={<CreativesPage />} />
                    <Route path="/notifications" element={<NotificationsPage />} />
                    <Route path="/reports" element={<ReportsPage />} />
                    <Route path="/audit-logs" element={<AuditLogsPage />} />
                    <Route path="*" element={<Navigate to="/dashboard" replace />} />
                </Routes>
            </BrowserRouter>
        </ErrorBoundary>
    );
};
