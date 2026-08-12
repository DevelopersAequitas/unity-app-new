-- ============================================================
-- DYNAMIC RBAC SYSTEM — SEED DATA (DML)
-- Unity App - PeersUnity
-- Run this AFTER 01_create_tables.sql
-- ============================================================


-- ────────────────────────────────────────────────────────────
-- 1. SEED PERMISSIONS (10 default action types)
-- ────────────────────────────────────────────────────────────
INSERT INTO permissions (id, `key`, name, description, sort_order, created_at, updated_at)
VALUES
    (UUID(), 'view',    'View',    'View records and pages',    1, NOW(), NOW()),
    (UUID(), 'create',  'Create',  'Create new records',        2, NOW(), NOW()),
    (UUID(), 'edit',    'Edit',    'Edit existing records',     3, NOW(), NOW()),
    (UUID(), 'delete',  'Delete',  'Delete records',            4, NOW(), NOW()),
    (UUID(), 'approve', 'Approve', 'Approve pending items',     5, NOW(), NOW()),
    (UUID(), 'reject',  'Reject',  'Reject pending items',      6, NOW(), NOW()),
    (UUID(), 'export',  'Export',  'Export data to CSV/Excel',  7, NOW(), NOW()),
    (UUID(), 'import',  'Import',  'Import data from files',    8, NOW(), NOW()),
    (UUID(), 'print',   'Print',   'Print reports',             9, NOW(), NOW()),
    (UUID(), 'restore', 'Restore', 'Restore deleted records',  10, NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);


-- ────────────────────────────────────────────────────────────
-- 2. SEED MODULES (16 sidebar sections)
-- ────────────────────────────────────────────────────────────

-- Module 1: Dashboard
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Dashboard', 'dashboard', 'bi-speedometer2', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'dashboard');

-- Module 2: Peers (Members)
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Peers', 'members', 'bi-people', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'members');

-- Module 3: Activities
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Activities', 'activities', 'bi-activity', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'activities');

-- Module 4: Circles
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Circles', 'circles', 'bi-diagram-3', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'circles');

-- Module 5: Events
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Events', 'events', 'bi-calendar-check', 5, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'events');

-- Module 6: Coins
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Coins', 'coins', 'bi-coin', 6, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'coins');

-- Module 7: Life Impact
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Life Impact', 'life-impact', 'bi-heart-pulse', 7, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'life-impact');

-- Module 8: Notifications & Email
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Notifications & Email', 'notifications', 'bi-bell', 8, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'notifications');

-- Module 9: Pending Requests
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Pending Requests', 'pending-requests', 'bi-hourglass-split', 9, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'pending-requests');

-- Module 10: Referral Report
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Referral Report', 'referral-report', 'bi-person-lines-fill', 10, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'referral-report');

-- Module 11: Content & Posts
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Content & Posts', 'content', 'bi-file-post', 11, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'content');

-- Module 12: Lead Submissions
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Lead Submissions', 'leads', 'bi-clipboard-data', 12, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'leads');

-- Module 13: Industries
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Industries', 'industries', 'bi-diagram-2', 13, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'industries');

-- Module 14: Settings
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Settings', 'settings', 'bi-gear', 14, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'settings');

-- Module 15: Role Management
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Role Management', 'role-management', 'bi-shield-lock', 15, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'role-management');

-- Module 16: Brand Partners
INSERT INTO admin_modules (id, name, slug, icon, sort_order, is_active, created_at, updated_at)
SELECT UUID(), 'Brand Partners', 'brand-partners', 'bi-shop', 16, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_modules WHERE slug = 'brand-partners');


-- ────────────────────────────────────────────────────────────
-- 3. SEED PAGES (all pages per module)
-- ────────────────────────────────────────────────────────────

-- === Dashboard Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'dashboard'), 'Main Dashboard', 'admin.dashboard', 'main-dashboard', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.dashboard');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'dashboard'), 'Circle Dashboard', 'admin.circle-member.dashboard', 'circle-dashboard', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.circle-member.dashboard');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'dashboard'), 'DED Dashboard', 'admin.ded.dashboard', 'ded-dashboard', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.ded.dashboard');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'dashboard'), 'Industry Director Dashboard', 'admin.industry-director.dashboard', 'id-dashboard', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.industry-director.dashboard');

-- === Peers (Members) Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'members'), 'All Members', 'admin.users.index', 'all-members', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.users.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'members'), 'Member Introducers', 'admin.member-introducers.index', 'member-introducers', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.member-introducers.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'members'), 'Sponsored Milestones', 'admin.sponsored-milestones.index', 'sponsored-milestones', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.sponsored-milestones.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'members'), 'Login History', 'admin.login-history.index', 'login-history', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.login-history.index');

-- === Activities Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Activity Summary', 'admin.activities.index', 'activity-summary', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activities.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Testimonials', 'admin.activities.testimonials.index', 'testimonials', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activities.testimonials.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Requirements', 'admin.activities.requirements.index', 'requirements', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activities.requirements.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Referrals', 'admin.activities.referrals.index', 'referrals', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activities.referrals.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'P2P Meetings', 'admin.activities.p2p-meetings.index', 'p2p-meetings', 5, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activities.p2p-meetings.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Business Deals', 'admin.activities.business-deals.index', 'business-deals', 6, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activities.business-deals.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Connections', 'admin.activities.connections.index', 'connections', 7, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activities.connections.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Leadership Requests', 'admin.activities.become-a-leader.index', 'leadership-requests', 8, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activities.become-a-leader.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Recommended Peers', 'admin.activities.recommend-peer.index', 'recommended-peers', 9, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activities.recommend-peer.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Collaborations', 'admin.collaborations.index', 'collaborations', 10, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.collaborations.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Registered Visitor', 'admin.activities.register-visitor.index', 'registered-visitor', 11, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activities.register-visitor.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'activities'), 'Activity Creatives', 'admin.activity-creatives.index', 'activity-creatives', 12, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.activity-creatives.index');

-- === Circles Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'circles'), 'All Circles', 'admin.circles.index', 'all-circles', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.circles.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'circles'), 'Circle Join Requests', 'admin.circle-joining-requests.index', 'circle-join-requests', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.circle-joining-requests.index');

-- === Events Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'events'), 'All Events', 'admin.events.index', 'all-events', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.events.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'events'), 'Total Attendance', 'admin.events.total-attendance', 'total-attendance', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.events.total-attendance');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'events'), 'Total Registered', 'admin.events.total-registered', 'total-registered', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.events.total-registered');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'events'), 'Event Joining Requests', 'admin.event-joining-requests.index', 'event-joining-requests', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.event-joining-requests.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'events'), 'Event Scan Credentials', 'admin.event-scan-credentials.index', 'event-scan-credentials', 5, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.event-scan-credentials.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'events'), 'Event Gallery', 'admin.event-gallery.index', 'event-gallery', 6, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.event-gallery.index');

-- === Coins Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'coins'), 'Coins Overview', 'admin.coins.index', 'coins-overview', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.coins.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'coins'), 'Coin Claims', 'admin.coin-claims.index', 'coin-claims', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.coin-claims.index');

-- === Life Impact Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'life-impact'), 'Life Impact Overview', 'admin.life-impact.index', 'life-impact-overview', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.life-impact.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'life-impact'), 'Pending Impacts', 'admin.impacts.pending', 'pending-impacts', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.impacts.pending');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'life-impact'), 'Impact Options', 'admin.impacts.index', 'impact-options', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.impacts.index');

-- === Notifications Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'notifications'), 'Campaign Dashboard', 'admin.campaigns.index', 'campaign-dashboard', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.campaigns.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'notifications'), 'Email Templates', 'admin.campaign-email-templates.index', 'email-templates', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.campaign-email-templates.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'notifications'), 'Pamphlets', 'admin.campaign-pamphlets.index', 'pamphlets', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.campaign-pamphlets.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'notifications'), 'Email Logs', 'admin.email-logs.index', 'email-logs', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.email-logs.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'notifications'), 'Daily Notifications', 'admin.daily-notifications.index', 'daily-notifications', 5, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.daily-notifications.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'notifications'), 'Notification Dashboard', 'admin.notifications.dashboard', 'notification-dashboard', 6, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.notifications.dashboard');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'notifications'), 'Push Tokens', 'admin.notifications.push-tokens', 'push-tokens', 7, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.notifications.push-tokens');

-- === Pending Requests Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'pending-requests'), 'Visitor Registrations', 'admin.visitor-registrations.index', 'visitor-registrations', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.visitor-registrations.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'pending-requests'), 'Pending Registrations', 'admin.pending-registrations.index', 'pending-registrations', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.pending-registrations.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'pending-requests'), 'Certifications', 'admin.certifications.index', 'certifications', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.certifications.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'pending-requests'), 'Account Deletion Requests', 'admin.account-deletion.index', 'account-deletion', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.account-deletion.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'pending-requests'), 'Introduction Requests', 'admin.introduction-requests.index', 'introduction-requests', 5, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.introduction-requests.index');

-- === Referral Report Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'referral-report'), 'Referral Report', 'admin.referral-report.index', 'referral-report-index', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.referral-report.index');

-- === Content & Posts Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'content'), 'All Posts', 'admin.posts.index', 'all-posts', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.posts.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'content'), 'Post Reports', 'admin.post-reports.index', 'post-reports', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.post-reports.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'content'), 'Circulars', 'admin.circulars.index', 'circulars', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.circulars.index');

-- === Lead Submissions Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'leads'), 'Entrepreneur Certification', 'admin.leads.entrepreneur-certification.index', 'entrepreneur-cert', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.leads.entrepreneur-certification.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'leads'), 'Leadership Certification', 'admin.leads.leadership-certification.index', 'leadership-cert', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.leads.leadership-certification.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'leads'), 'Partner With Us', 'admin.leads.partner-with-us.index', 'partner-with-us', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.leads.partner-with-us.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'leads'), 'Become Speaker', 'admin.leads.become-speaker.index', 'become-speaker', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.leads.become-speaker.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'leads'), 'Become Mentor', 'admin.leads.become-mentor.index', 'become-mentor', 5, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.leads.become-mentor.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'leads'), 'Story Submissions', 'admin.stories.index', 'story-submissions', 6, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.stories.index');

-- === Industries Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'industries'), 'Industries Overview', 'admin.execution.industries', 'industries-overview', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.execution.industries');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'industries'), 'DED Industries', 'admin.ded.dashboard.industries', 'ded-industries', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.ded.dashboard.industries');

-- === Settings Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'settings'), 'App Config', 'admin.app-config.index', 'app-config', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.app-config.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'settings'), 'App Updates', 'admin.app-updates.index', 'app-updates', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.app-updates.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'settings'), 'Birthday Creative', 'admin.birthday-creative.index', 'birthday-creative', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.birthday-creative.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'settings'), 'Anniversary Creatives', 'admin.anniversary-creatives.index', 'anniversary-creatives', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.anniversary-creatives.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'settings'), 'Tutorials', 'admin.tutorials.index', 'tutorials', 5, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.tutorials.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'settings'), 'Categories', 'admin.categories.index', 'categories', 6, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.categories.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'settings'), 'Unity Peers Plans', 'admin.unity-peers-plans.index', 'unity-peers-plans', 7, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.unity-peers-plans.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'settings'), 'Unity Contacts', 'admin.contacts.index', 'unity-contacts', 8, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.contacts.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'settings'), 'Support Tickets', 'admin.support-tickets.index', 'support-tickets', 9, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.support-tickets.index');

-- === Role Management Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'role-management'), 'Role Hierarchy', 'admin.rbac.hierarchy', 'role-hierarchy', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.rbac.hierarchy');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'role-management'), 'RBAC Modules', 'admin.rbac.modules.index', 'rbac-modules', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.rbac.modules.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'role-management'), 'RBAC Pages', 'admin.rbac.pages.index', 'rbac-pages', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.rbac.pages.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'role-management'), 'Permission Matrix', 'admin.rbac.permission-matrix.index', 'permission-matrix', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.rbac.permission-matrix.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'role-management'), 'Module Access', 'admin.rbac.module-access.index', 'module-access', 5, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.rbac.module-access.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'role-management'), 'Page Groups', 'admin.rbac.page-groups.index', 'page-groups', 6, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.rbac.page-groups.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'role-management'), 'Data Scope', 'admin.rbac.data-scope.index', 'data-scope', 7, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.rbac.data-scope.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'role-management'), 'Workflow Rules', 'admin.rbac.workflow-rules.index', 'workflow-rules', 8, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.rbac.workflow-rules.index');

-- === Brand Partners Pages ===
INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'brand-partners'), 'All Brand Partners', 'admin.brand-partners.index', 'all-brand-partners', 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.brand-partners.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'brand-partners'), 'Brand Partner Categories', 'admin.brand-partner-categories.index', 'brand-partner-categories', 2, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.brand-partner-categories.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'brand-partners'), 'Brand Partner Analytics', 'admin.brand-partner-analytics.index', 'brand-partner-analytics', 3, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.brand-partner-analytics.index');

INSERT INTO admin_pages (id, module_id, name, route_name, slug, sort_order, is_active, created_at, updated_at)
SELECT UUID(), (SELECT id FROM admin_modules WHERE slug = 'brand-partners'), 'Ads', 'admin.ads.index', 'ads', 4, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM admin_pages WHERE route_name = 'admin.ads.index');
