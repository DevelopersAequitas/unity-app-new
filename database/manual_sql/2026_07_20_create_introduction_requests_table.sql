-- Manual PostgreSQL Schema updates for Introduction Requests Workflow
-- Run these queries directly on your PostgreSQL database.

-- 1. Create introduction_requests table
CREATE TABLE IF NOT EXISTS introduction_requests (
    id UUID PRIMARY KEY,
    requester_id UUID NOT NULL,
    introducer_id UUID NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    admin_note TEXT,
    reviewed_by UUID,
    requested_at TIMESTAMP WITH TIME ZONE NOT NULL,
    reviewed_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE,
    updated_at TIMESTAMP WITH TIME ZONE,
    
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (introducer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES admin_users(id) ON DELETE SET NULL
);

-- 2. Create indexes for performance
CREATE INDEX IF NOT EXISTS idx_introduction_requests_status ON introduction_requests(status);
CREATE INDEX IF NOT EXISTS idx_introduction_requests_requester ON introduction_requests(requester_id);
CREATE INDEX IF NOT EXISTS idx_introduction_requests_introducer ON introduction_requests(introducer_id);
