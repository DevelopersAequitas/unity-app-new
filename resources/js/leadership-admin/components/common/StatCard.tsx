import React from 'react';

interface StatCardProps {
    title: string;
    value: number | string;
    subtitle?: string;
    icon: React.ReactNode;
    color?: 'indigo' | 'emerald' | 'amber' | 'rose' | 'sky' | 'purple';
    badge?: string;
}

const colorMap = {
    indigo: {
        bg: 'rgba(99, 102, 241, 0.1)',
        text: '#6366f1',
        gradient: 'linear-gradient(90deg, #6366f1, #818cf8)',
    },
    emerald: {
        bg: 'rgba(16, 185, 129, 0.1)',
        text: '#10b981',
        gradient: 'linear-gradient(90deg, #10b981, #34d399)',
    },
    amber: {
        bg: 'rgba(245, 158, 11, 0.1)',
        text: '#f59e0b',
        gradient: 'linear-gradient(90deg, #f59e0b, #fbbf24)',
    },
    rose: {
        bg: 'rgba(244, 63, 94, 0.1)',
        text: '#f43f5e',
        gradient: 'linear-gradient(90deg, #f43f5e, #fb7185)',
    },
    sky: {
        bg: 'rgba(14, 165, 233, 0.1)',
        text: '#0ea5e9',
        gradient: 'linear-gradient(90deg, #0ea5e9, #38bdf8)',
    },
    purple: {
        bg: 'rgba(168, 85, 247, 0.1)',
        text: '#a855f7',
        gradient: 'linear-gradient(90deg, #a855f7, #c084fc)',
    },
};

export const StatCard: React.FC<StatCardProps> = ({
    title,
    value,
    subtitle,
    icon,
    color = 'indigo',
    badge,
}) => {
    const scheme = colorMap[color];

    return (
        <div className="card border-0 shadow-xs rounded-4 p-4 bg-white position-relative overflow-hidden h-100">
            <div className="d-flex align-items-center justify-content-between mb-2">
                <span className="text-uppercase tracking-wider text-muted fw-bold" style={{ fontSize: '0.7rem' }}>
                    {title}
                </span>
                <div className="p-2 rounded-3 d-flex align-items-center justify-content-center" style={{ background: scheme.bg, color: scheme.text }}>
                    {icon}
                </div>
            </div>
            <div className="my-1">
                <div className="h2 fw-bold text-dark mb-0">{value}</div>
            </div>
            {(subtitle || badge) && (
                <div className="d-flex align-items-center justify-content-between pt-3 mt-2 border-top">
                    <span className="text-muted small">{subtitle}</span>
                    {badge && (
                        <span className="badge rounded-pill px-2 py-1" style={{ background: scheme.bg, color: scheme.text, fontWeight: 600 }}>
                            {badge}
                        </span>
                    )}
                </div>
            )}
            <div className="position-absolute bottom-0 start-0 end-0" style={{ height: '4px', background: scheme.gradient }}></div>
        </div>
    );
};
