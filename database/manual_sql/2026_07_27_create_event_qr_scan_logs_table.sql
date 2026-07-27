CREATE TABLE IF NOT EXISTS event_qr_scan_logs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    event_id UUID NOT NULL REFERENCES events(id) ON DELETE CASCADE,
    occurrence_id UUID REFERENCES event_occurrences(id) ON DELETE CASCADE,
    registration_id UUID REFERENCES event_registrations(id) ON DELETE CASCADE,
    user_id UUID REFERENCES users(id) ON DELETE CASCADE,
    scanned_by_user_id UUID REFERENCES users(id) ON DELETE SET NULL,
    scan_result VARCHAR(50) NOT NULL,
    scan_status VARCHAR(50),
    failure_reason TEXT,
    scan_device_info TEXT,
    scanner_app_version VARCHAR(50),
    ip_address VARCHAR(50),
    scanned_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_event_qr_scan_logs_event_id ON event_qr_scan_logs(event_id);
CREATE INDEX IF NOT EXISTS idx_event_qr_scan_logs_scanned_at ON event_qr_scan_logs(scanned_at);
