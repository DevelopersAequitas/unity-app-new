import React from 'react';

interface BadgeProps {
    status: string;
    label?: string;
}

export const StatusBadge: React.FC<BadgeProps> = ({ status, label }) => {
    const formattedLabel = label || status.replace(/_/g, ' ').toUpperCase();

    let bgClass = 'bg-secondary-subtle text-secondary';
    let dotColor = '#64748b';

    switch (status.toLowerCase()) {
        case 'published':
        case 'approved':
        case 'selected':
        case 'completed':
        case 'verified':
        case 'active':
        case 'sent':
            bgClass = 'bg-success-subtle text-success';
            dotColor = '#10b981';
            break;
        case 'voting_open':
        case 'shortlisted':
        case 'processing':
            bgClass = 'bg-primary-subtle text-primary';
            dotColor = '#3b82f6';
            break;
        case 'draft':
        case 'pending':
        case 'under_review':
        case 'assigned':
        case 'in_progress':
        case 'queued':
            bgClass = 'bg-warning-subtle text-warning';
            dotColor = '#f59e0b';
            break;
        case 'correction_requested':
        case 'deferred':
        case 'standby':
            bgClass = 'bg-info-subtle text-info';
            dotColor = '#06b6d4';
            break;
        case 'rejected':
        case 'failed':
        case 'closed':
        case 'voting_closed':
        case 'nomination_closed':
            bgClass = 'bg-danger-subtle text-danger';
            dotColor = '#ef4444';
            break;
    }

    return (
        <span className={`badge rounded-pill px-2.5 py-1.5 d-inline-flex align-items-center gap-1.5 ${bgClass}`} style={{ fontWeight: 600, fontSize: '0.72rem' }}>
            <span style={{ width: '6px', height: '6px', borderRadius: '50%', backgroundColor: dotColor }}></span>
            <span>{formattedLabel}</span>
        </span>
    );
};
