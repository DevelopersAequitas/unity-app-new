import React from 'react';
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

export const App: React.FC = () => {
    return (
        <BrowserRouter basename="/admin/web/leadership">
            <Routes>
                <Route path="/" element={<Navigate to="/dashboard" replace />} />
                <Route path="/dashboard" element={<DashboardPage />} />
                <Route path="/campaigns" element={<CampaignsPage />} />
                <Route path="/forms" element={<FormBuilderPage />} />
                <Route path="/nominations" element={<NominationsPage />} />
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
    );
};
