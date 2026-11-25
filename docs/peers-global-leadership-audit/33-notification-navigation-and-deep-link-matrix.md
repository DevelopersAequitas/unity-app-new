# 33 — Notification Navigation, Deep-Link & Auto-Highlight Matrix

**Document Group:** 05 — Notifications and Operations  
**Schema Version:** `2.0` (`LeaderNotificationDispatcherService` <-> `NotificationPayload`)

---

## 1. Canonical Payload Contract (`schema_version: 2.0`)

Every Leadership App notification persisted by `LeaderNotificationDispatcherService` and parsed by `NotificationPayload.fromMap` carries:
- `schema_version`: `'2.0'`
- `notification_id`, `notification_type`, `audience` (`'leadership'`), `category`, `priority`, `action_required`
- `entity_type`, `entity_id`, `target_record_id`, `target_list_id`, `target_status`, `action_identifier`
- `destination`, `destination_tab`, `destination_filter`, `org_scope` (`circle_id`, `district_id`, `industry_id`, `country_id`)

---

## 2. Reusable Exact-Item Focus & Three-Pulse Highlight

- **Controller:** `NotificationListFocusController` (`lib/core/notifications/notification_list_focus.dart`)
  - Locates `targetId` across loaded items; invokes `loadMore()` up to `maxPaginationPages` when not on page 1.
  - Clears restrictive filters (e.g. Circulars priority filter) if the target exists outside the active filter.
  - Scrolls the mounted item into view via `Scrollable.ensureVisible(..., alignment: 0.25)`.
  - Sets `notFound = true` without highlighting any unrelated item if absent or out of scope.
- **Highlight Widget:** `NotificationHighlightWrapper` (`lib/core/notifications/notification_highlight_wrapper.dart`)
  - Pulses **exactly 3 times** (`AnimationController` 420ms forward/reverse x3), clears styling on completion, never repeats on rebuild, and falls back to a static focus border when `MediaQuery.disableAnimations` is enabled.
- **Integrated Screens (Complete with Auto-Scroll + 3-Pulse Highlight):**
  - `LeaderActivitiesView` (`/leader-activities`): `COMPLETE` (paginated `LoadMoreLeaderActivities`, `Scrollable.ensureVisible`, 3-pulse highlight)
  - `CircularsView` (`/circulars`): `COMPLETE` (auto-resets priority filter to `All`, `Scrollable.ensureVisible`, 3-pulse highlight)
  - `BusinessDealsView` (`/business-deals`): `COMPLETE` (`Scrollable.ensureVisible`, 3-pulse highlight)
  - `FinanceView` -> `FinanceTransactionsList` (`/home` `tab: 3` & `/finance`): `COMPLETE` (`canAccessFinanceTab` enforced, `Scrollable.ensureVisible`, 3-pulse highlight, missing-record snackbar)
  - `CircleEventsScreen` -> `CircleEventsSection` (`/circle-events`): `COMPLETE` (auto-resets event filter to `All`, `Scrollable.ensureVisible`, 3-pulse highlight, missing-record snackbar)
  - `ReportDetailsView` (`/report-details`), `CircleDetailsView` (`/circle-details`), `PeerProfileView` (`/peer-profile`): `COMPLETE` (direct entity-by-ID detail screens)
  - `RoleManagementView` (`/role-management`): `DESTINATION_ONLY` (policy/matrix screen)
  - `A-09` Support ticket SLA desk: `UNSUPPORTED` on mobile (falls back to `/notifications`, desk is in `unity-admin-web`)

---

## 3. Complete 41-Event & `NotificationType` Navigation Matrix

| Event ID / Enum | Destination Screen | Tab / Filter | Entity Type | Stable ID | Required Capabilities / Scope | Focus Behavior | Status |
|---|---|---|---|---|---|---|---|
| **L-01..L-03** (`leader_interest`, `nomination`, `jury`) | `/leader-activities` | `leads` / `certifications` | `approval` | `entity_id` | `canReviewPendingPeers` / Country / Global | List-item focus + 3-pulse | Partial (Admin Web primary) |
| **L-04** (`leadership_tenure_appointed`) | `/role-management` | — | `role_assignment` | `entity_id` | `canAccessRoleManagement` / Circle | Screen navigation | Verified (Unit + Flutter) |
| **L-05** (`leadership_tenure_expiring`) | `/role-management` | — | `role_assignment` | `entity_id` | `canAccessRoleManagement` | Screen navigation | Deferred cron |
| **L-06, L-07, M-04** (Finance / Offline Payment) | `/home` (`tab: 3`) | Finance ledger | `finance` / `offline_payment` | `transaction_id` | `canAccessFinanceTab` (`access_finance`) | Tab 3 + masked push | Verified (Unit + Flutter) |
| **L-08** (`role_matrix_modified`) | `/role-management` | Role Matrix | `security` | `role_id` | `canAccessRoleManagement` (`superAdmin`) | Screen navigation | Verified (Unit + Flutter) |
| **L-09** (`official_circle_circular`) | `/circulars` | Priority = `All` | `circular` | `circular_id` | `canAccessDashboard` + scoped `target_roles` | Auto-scroll + 3-pulse | Verified (Flutter + Unit) |
| **M-01, M-02, I-01** (`circle_join_*`) | `/leader-activities` | `circle-join-requests` | `approval` | `request_id` | `canReviewPendingPeers` + Circle/ID/DED scope | Paginated focus + 3-pulse | Verified (Flutter + Unit) |
| **M-03, M-05, M-06, M-07** (Peer status) | `/peer-profile` | — | `peer` | `user_id` | `canViewPeerProfile` + Circle scope | Detail screen by ID | Verified (`M-03, M-05, M-06`) |
| **M-08** (`birthday_anniversary`) | Excluded | — | — | — | Member App only | Excluded from Leader App | Verified Excluded |
| **M-09, M-10** (`intro_claim`, `account_deletion`) | `/leader-activities` | `approval` / `security` | `approval` / `security` | `entity_id` | Country Dir / Super Admin | Paginated focus + 3-pulse | Partial |
| **C-01, C-03** (`circle_proposal`, `open_category`) | `/leader-activities` | `approval` | `approval` | `entity_id` | `canReviewPendingPeers` | Paginated focus + 3-pulse | Not implemented |
| **C-02, C-06, D-02, D-03** (Circle governance) | `/circle-details` | — | `circle` | `circle_id` | `canAccessTeamsTab` + Circle/DED scope | Detail screen by ID | Verified destination |
| **C-04, C-05, C-07** (Reports & compliance) | `/report-details` / `/home` (`tab: 4`) | Reports | `report` | `report_id` | `canAccessReportsTab` | Detail screen by ID (`C-05` wired) | Verified (`C-05`) |
| **D-01** (`regional_sla_escalation`) | `/leader-activities` | `approval` | `approval` | `entity_id` | DED / ID / Country / SA | Paginated focus + 3-pulse | Deferred cron |
| **I-02** (`cross_circle_deal`) | `/business-deals` | — | `industry_op` | `deal_id` | `canAccessPeersTab` + Industry/Circle | Auto-scroll + 3-pulse | Verified destination |
| **I-03** (`unmatched_industry_ask`) | `/peer-collaboration` | — | `industry_op` | `ask_id` | `canAccessPeersTab` + Industry/Circle | Screen navigation | Deferred cron |
| **A-01** (`visitor_registration_pending`) | `/leader-activities` | `visitor-registrations` | `approval` | `registration_id` | `canReviewPendingPeers` + Circle | Paginated focus + 3-pulse | Verified (Flutter + Unit) |
| **A-02** (`event_join_request_pending`) | `/circle-events` | Circle events | `event` | `event_id` | `canAccessTeamsTab` + Circle | Event focus by `circle_id` | Verified destination |
| **A-03** (`life_impact_pending`) | `/leader-activities` | `impacts` | `approval` | `impact_id` | `canReviewPendingPeers` + Circle | Paginated focus + 3-pulse | Verified (Flutter + Unit) |
| **A-04** (`coin_claim_pending`) | `/leader-activities` | `coin-claims` | `approval` | `claim_id` | `canReviewPendingPeers` / `coin_payouts` | Paginated focus + 3-pulse | Verified (Flutter + Unit) |
| **A-05, A-06** (`certification`, `lead_or_ad`) | `/leader-activities` | `certifications` / `leads` | `approval` | `entity_id` | Country Dir / Super Admin | Paginated focus + 3-pulse | Partial |
| **A-07** (`post_reported`) | `/peer-profile` | — | `peer` | `author_id` | `manage_peers` / SA | Detail screen by ID | Not implemented |
| **A-08** (Ordinary P2P/Referral/Wish) | Excluded | — | — | — | Member App only | Excluded from Leader App | Verified Excluded |
| **A-09** (`support_ticket_sla_breach`) | `/notifications` | — | `support` | `ticket_id` | Super Admin / Assigned | Feed fallback (Admin web desk) | Deferred cron |
| **Consumer `NotificationType` cases** (`new_post`, `chat_message`, `badge_unlocked`, `profile_viewed`, `daily_reminder`, `streak_reminder`, etc.) | Excluded | — | — | — | Member App only | Rejected by `isLeadershipAudiencePayload` & `isLeaderNotification` | Verified Excluded |
