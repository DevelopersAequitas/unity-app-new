# Peers Global — Existing Leadership App Deep Audit, Role-Wise Activity Governance & Mobile Enhancement Documentation

> **Central Documentation Index (`00 — Main Index`)**  
> **Target Package Directory:** `docs/peers-global-leadership-audit/` (87 Interlinked Markdown Specifications)  
> **Mode:** Strict Read-Only Investigation & Evidence-Based Architecture Documentation (`Enhance, Never Duplicate`)  
> **Repositories & Primary Reference Audited:**
> - **Laravel 12 Backend Monolith:** `c:\Users\malic\OneDrive\Documents\GitHub\unity-app-new` (`develop` branch)
> - **Flutter Leadership Mobile App:** `D:\office project\LeaderApp-Fornt-end-` (`api-integration` branch — 423 Dart files, 8 prior audit docs, 44 screenshots preserved untouched)
> - **Flutter Member Mobile App:** `D:\office project\peers-unity\unity_app` (1,956 Dart files across 26 Clean Architecture modules)
> - **React + TypeScript Admin Web Panel:** `D:\office project\unity-admin-web` (`peersglobal-admin-redesign` branch — 488 `.ts`/`.tsx` files)
> - **Master Business Activity Workbook:** `PEERS UNITY — MASTER ACTIVITY & ARCHITECTURE DIRECTORY.xlsx` (All 3 worksheets: `Peers Activity (Side-by-Side)`, `Non-Tech Activity Guide`, `Technical Architecture Specs` — 35 Platform Activities)

---

## 1. Project Overview & Purpose

Peers Global already operates a substantial production ecosystem: a **PHP 8.2 / Laravel 12 + PostgreSQL** modular backend, a **Flutter Member App**, a **React + TypeScript Admin Panel**, a **Website Leadership Election Portal**, and a **423-file Flutter Leadership Mobile Application** (`LeaderApp-Fornt-end-`).

The purpose of this documentation system is **not** to propose a greenfield rebuild or duplicate working screens, APIs, BLoCs, or database tables. Instead, it establishes a **single, code-verified source of truth** answering four founder-critical questions:
1. **What actually exists today** across the Flutter Leadership App, Member App, React Admin Panel, and Laravel 12 backend—versus what is mocked, synthetic, unrouted, or only described in the Excel workbook?
2. **Who can see and do what**: how every organizational role (`Super Admin / Global Founder`, `Country Director / EED`, `District Executive Director`, `Industry Director`, `Circle Founder`, `Circle Director`, `Committee Chairs / Chair`, `Vice Chair`, `Secretary`, `Committee Leader`, etc.) is resolved, scoped, and enforced server-side.
3. **How all 35 platform activities operate end-to-end**, and which belong in the Leadership App for oversight or approval versus remaining strictly member-only or desktop-admin-only.
4. **What minimum-change, high-impact enhancements** are required to harden security, guarantee authoritative metrics, complete disconnected integrations, and deliver a world-class role-aware Leadership App.

---

## 2. Audit Methodology, Evidence Labels & Classification Taxonomy

Every technical claim in this 87-document package was verified directly against repository source code, route registrations, Eloquent models, SQL migrations, Flutter BLoCs/datasources/views, and all three sheets of `PEERS UNITY — MASTER ACTIVITY & ARCHITECTURE DIRECTORY.xlsx`.

### 2.1 Mandatory Evidence Labels
- **`FACT:`** Directly verified in existing PHP, Dart, TypeScript, or SQL source files (cited with clickable `file:///` links and line numbers).
- **`WORKBOOK REQUIREMENT:`** Explicitly specified in one or more of the three sheets of `PEERS UNITY — MASTER ACTIVITY & ARCHITECTURE DIRECTORY.xlsx`.
- **`INFERENCE:`** Logical architectural conclusion where live production PostgreSQL state predates tracked Laravel migrations (e.g., baseline tables checked via `Schema::hasColumn`).
- **`RECOMMENDATION:`** Minimum-change proposed enhancement reusing existing services, tables, widgets, and APIs.
- **`BUSINESS DECISION REQUIRED:`** Policy or product rule requiring explicit Founder / Product Owner sign-off (consolidated in [51-open-business-decisions.md](./51-open-business-decisions.md)).
- **`NOT IMPLEMENTED:`** Confirmed absent across all repositories after exhaustive search.

### 2.2 Mandatory 8-State Capability Classification
Every audited screen, API, workflow, and activity sub-operation is classified into one of eight states:
1. **`Fully Implemented`** — Works end-to-end with verified auth, scope, and real data.
2. **`Partially Implemented`** — Core structure exists, but sub-flows or edge cases are incomplete.
3. **`Implemented but Incorrect`** — Exists in code, but contains logic, calculation, enum, or security/IDOR defects.
4. **`Implemented but Not Integrated`** — Built on backend or frontend without end-to-end wiring (e.g., unrouted `LeaderPeersController::update`, unrouted `LeaderCircularsController::publish`, unused `CheckLeaderCapability` middleware, unintegrated `/api/v1/ded/leadership/*` & `/api/v1/leadership/*` APIs).
5. **`Missing`** — Genuinely absent and required by leadership governance (e.g., scoped Circle Chair assignment endpoint in `app/Leader/`, SLA escalation jobs).
6. **`Requires Enhancement`** — Functional baseline that needs UX, performance, or operational refinement.
7. **`Requires Business Confirmation`** — Ambiguous or conflicting rules across code, docs, and workbook.
8. **`Not Required`** — Consumer-only or desktop-ERP capability that must **not** be duplicated in the mobile Leadership App.

---

## 3. Executive Summary of Key Audit Findings

### A. Critical Security & RBAC Findings (`P0` — Immediate Backend Remediation Required)
1. **Unconditional Production OTP Backdoor (`123456`):** **FACT:** [LeaderAuthService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderAuthService.php#L156-L159) accepts `'123456'` in **all environments including production** (alongside 3 other subsystems: `StoreOtpService.php:65`, `JuryWebController.php:180`, `VerificationService.php:380`), and `AuthController.php:1497` + `AdminAuthService.php:196-245` contain hardcoded `@gmail.com` zero-OTP `global_admin` bypasses.
2. **Unprotected RBAC & Admin Route Groups:** **FACT:** `/api/v1/rbac/*` and `/api/v1/admin/rbac/*` ([routes/api.php:312-380](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/routes/api.php#L312-L380)) sit outside authentication middleware, `/api/v1/admin/*` (`L701-1305`) uses `unity.user` with **no admin role guard**, and `DynamicAccess` (`L2319-2450`) uses `auth:sanctum,admin`, allowing any regular mobile `User` token to invoke admin endpoints or self-assign `global_admin`.
3. **Unused `leader.can` Middleware:** **FACT:** [CheckLeaderCapability.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Middleware/CheckLeaderCapability.php) is registered as `'leader.can'` in [bootstrap/app.php:84](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/bootstrap/app.php#L84) **but applied to 0 routes**. Any lowest-rank leader (`Secretary` or `Vice Chair`) can call `PUT /api/v1/leader/roles/matrix` to grant themselves all capabilities, `POST /api/v1/leader/admin/notifications/send` to broadcast push alerts to 500 leaders, or `POST /api/v1/leader/finance/transactions/record-offline` to fabricate `'Paid'` records.
4. **Horizontal Cross-Circle / Cross-District IDORs & Leaks:** **FACT:** Passing `?scope=global` or `?circle_id=all` to `GET /api/v1/leader/peers` bypasses non-admin circle scoping and dumps **all platform users** (`LeaderPeersService.php:288-330`); direct UUID routes (`/peers/{id}`, `/teams/circles/{id}`, `/activities/{type}/{id}`, `/reports/{id}/file`) lack scope checks; and `GET /teams/contacts` (`LeaderTeamsService.php:1942-1947`) leaks global `contact_posts` phonebooks whenever a leader has zero personal contacts.

### B. Data Integrity, Finance & Dashboard Findings
1. **Synthetic Revenue & Dashboard Numbers:** **FACT:** Despite Round 4 fixing the Flutter `DashboardHeroCard` UI, backend [LeaderDashboardService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderDashboardService.php#L733) still calculates `overall_revenue` as a **fake `5%` of `business_deals.deal_amount`** (`$dealsSum * 0.05`), fabricates `60% / 25% / 15%` Ask status splits (`L784-786`), fabricates `70% / 30%` Certification splits (`L789-790`), hardcodes `circulars = 5` (`L798`), and returns 5 fictional names for `top_impacters` when empty (`L945-970`).
2. **Cross-Circle Payment Over-Attribution & Formula Flaws:** **FACT:** [LeaderFinanceService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderFinanceService.php#L77-L79) uses `orWhereHas('user.circleMembers')`, attributing 100% of a multi-circle Peer's lifetime payments to every Circle they belong to; `circle_budget` (`L339`) sums all wallets globally when `circle_id` is omitted; `projected_annual_revenue` (`L181`) divides multi-year all-time revenue by the current calendar month `1..12` × `12`; and `LeaderTeamsService.php:478` estimates Circle revenue as `(price ?? 120000) * approved_peers`.
3. **Report Submission Fake Fallbacks:** **FACT:** [LeaderReportsService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderReportsService.php#L118-L179) assigns the random first Circle in PostgreSQL if `circle_id` is omitted and injects fake metrics (`94.5%` attendance, `'₹18.5L'` deals, `'₹24.0L'` revenue) whenever optional fields are omitted.

### C. Mobile ↔ Backend Integration & Role Taxonomy Drift
1. **Silent Peer Edit Failure:** [edit_peer_bottom_sheet.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/features/peer_profile/view/widgets/edit_peer_bottom_sheet.dart#L123-L136) calls unrouted `PUT /api/v1/leader/peers/{id}` (`405`), swallows the error in `catch (_) {}`, and fakes local UI success.
2. **Unrouted Circular Broadcast:** `LeaderCircularsController::publish` is unrouted in `routes/leader.php`, and `PublishCircularBottomSheet` hardcodes `'National Directorate'` / `'Super Admin'`.
3. **Missing Chair Assignment Endpoint in `app/Leader/`:** `PeerRoleManagementSection` routes all 4 assignment buttons to the capability matrix screen (`RoleManagementView`) rather than a user-to-circle assignment flow.
4. **Role Enum Drift Across 4 Surfaces:** Backend `UserRoleResolverService` ranks 13 keys (`viceChair`, `secretary`, `chairEventsPrograms`), Dynamic RBAC seeds 19 keys (`committee_leader`, `circle_leader`, `eed`, `global_admin`), React Admin defines 19 groups (including `ambessader`), while Flutter `UserRole` only defines **10 values** (omitting `viceChair`, `secretary`, `committeeLeader`).
5. **Unintegrated Backend Gems:** All 7 `/api/v1/ded/leadership/*` role drilldowns (`DistrictAnalyticsService`) and Scoped Leadership APIs 42–50 (`/api/v1/leadership/*`) already exist in Laravel and can be reused with minimal bridging.

### D. 35 Platform Activities & Workbook Reconciliation
1. **Workbook Cloud Names vs. Laravel Reality:** Workbook references to `PdfGeneratorLambda`, `ElasticSearchIndexer`, `CollabRoomSocketService`, `HlsVideoTranscoder`, and Cloud Firestore are conceptual target labels; production runs **100% on Laravel 12 + PostgreSQL + DomPDF + Reverb + FCM HTTP v1** (**zero Firestore**).
2. **Clear Governance Partitioning Across All 35 Activities:**
   - **Verification / Multi-Stage Approval Required in Leadership App (9):** #9 Entrepreneur Certification, #10 Leadership Certification, #13 Leadership Role Application, #16 Recommend a Peer, #17 Register a Visitor, #20 Circle Membership Requests & Build Circle, #21 Cross-Circle Event Joining Requests, #22 Pending Life Impacts, #23 Coin Claims.
   - **Scoped Leadership Oversight & Coaching Only (15):** #1 Business Deals, #2 P2P Meetings, #3 Normal Referrals, #4 I Can Help Tags, #5 Collaboration Asks, #6 Get Help Asks, #7 Referral Asks, #8 Testimonials, #19 Joined Circles, #24 Timeline Posts, #27 Introduced Peers, #28 Badges Earned, #32 Membership & Subscriptions (authoritative finance scope), #34 Profile Last Updated, #35 Last Month Activity Summary.
   - **Global / Desktop Web Administration Only (6):** #11 Partner With Us, #12 Brand Partner Form & Ads, #14 Become a Speaker, #15 Become a Mentor, #18 Vyapaar Jagat Story Moderation, #25 Post Deactivation & Reversal Studio (#30 Feedback & #31 Support Tickets also managed primarily by central support/admin, with leader support submission).
   - **Strictly Member-Only / Excluded from Leadership App (3+):** #26 User Shorts Feed (except viewing intro video on Peer Profile), #29 Blocked Users (`peer_blocks` — private), #33 Who Viewed My Profile (`profile_views` — private).

---

## 4. Complete 87-Document Navigation Directory

### 00 — Main Index (1 Document)
| File | Description |
|---|---|
| [README.md](./README.md) | Executive overview, methodology, key findings, reading order, and master navigation index. |

### 01 — Existing System Audit (5 Documents)
| File | Description |
|---|---|
| [01-existing-system-architecture.md](./01-existing-system-architecture.md) | Multi-repo topology (`unity-app-new`, `LeaderApp-Fornt-end-`, `unity_app`, `unity-admin-web`), dual `User`/`AdminUser` model, 3 API generations. |
| [02-existing-leadership-app-audit.md](./02-existing-leadership-app-audit.md) | Verification of prior 8 audit docs & Rounds 1–6 fixes + 12 newly uncovered frontend↔backend defects. |
| [03-existing-backend-api-inventory.md](./03-existing-backend-api-inventory.md) | Verification of all Prompt §6 candidate endpoints + complete `routes/leader.php`, `/ded/*`, `/leadership/*`, `/admin/*`, and unrouted code inventory. |
| [04-existing-feature-and-screen-inventory.md](./04-existing-feature-and-screen-inventory.md) | Complete 28-row screen & modal inventory, Role × Screen visibility matrix, and React Admin reuse assessment. |
| [05-existing-infrastructure-and-integration.md](./05-existing-infrastructure-and-integration.md) | Firebase/FCM (**zero Firestore** proof), Sanctum, Dio, Hive, WhatsApp, 42 Mailables, DomPDF, QR, Razorpay/Zoho, 25 cron jobs, 4 audit tables. |

### 02 — Roles and Permissions (6 Documents)
| File | Description |
|---|---|
| [06-complete-role-hierarchy.md](./06-complete-role-hierarchy.md) | Complete reconciliation of all roles across `UserRoleResolverService`, `roles` table, `circle_members` enum, Flutter `UserRole`, and React Admin (`Global Founder`, `Committee Leader`, `Ambassador`, etc.). |
| [07-role-wise-responsibilities.md](./07-role-wise-responsibilities.md) | 17-point governance profile for every leadership, committee, functional, and member role. |
| [08-master-role-permission-matrix.md](./08-master-role-permission-matrix.md) | Mandatory 21-action permission matrix + 9 specialized sub-matrices + 13-capability × 21-flag analysis with explicit `Denied` & `Conditional` rules. |
| [09-organizational-data-scope-matrix.md](./09-organizational-data-scope-matrix.md) | Global, Country, State, District, Area, Industry, Circle, Multi-Circle, and Self scope mechanics, storage tables, and deduplication rules. |
| [10-role-wise-screen-access.md](./10-role-wise-screen-access.md) | Post-login mobile experience (Dashboard, Navigation, Actions, Restrictions, Scope) for every discovered role in one shared Flutter app. |
| [11-approval-and-delegation-authority.md](./11-approval-and-delegation-authority.md) | Multi-stage Circle Join (`CD -> ID/DED -> Fee`), Build Circle, 10 Pending Request queues, Election jury/decisions, Maker-Checker, and `WorkflowApprovalRule` audit. |

### 03 — Activity Documentation: Cross-Cutting Catalogues (8 Documents)
| File | Description |
|---|---|
| [12-master-35-activities-index.md](./12-master-35-activities-index.md) | Master directory and governance classification of all 35 workbook activities. |
| [13-activity-input-forms-and-validation.md](./13-activity-input-forms-and-validation.md) | Field-by-field input specification and 13-column validation mapping across all 35 activities. |
| [14-activity-sub-activity-catalogue.md](./14-activity-sub-activity-catalogue.md) | Atomic sub-activity decomposition and implementation status across Member App, Leader App, Admin Web, and Backend. |
| [15-activity-database-and-metrics-mapping.md](./15-activity-database-and-metrics-mapping.md) | Actual PostgreSQL tables, Eloquent models, columns, metric counters vs. Workbook Sheet 3 schemas. |
| [16-activity-api-and-backend-mapping.md](./16-activity-api-and-backend-mapping.md) | Member, Leader, DED, and Admin API mappings + Workbook cloud service vs. Laravel 12 service comparison. |
| [17-activity-output-and-deliverables.md](./17-activity-output-and-deliverables.md) | Screens, history lists, creatives, PDFs, certificates, badges, QR passes, notifications, and shareable assets. |
| [18-activity-lifecycle-and-status-reference.md](./18-activity-lifecycle-and-status-reference.md) | Workbook lifecycle states vs. actual PostgreSQL status columns/enums and transition mismatches. |
| [19-activity-to-role-access-matrix.md](./19-activity-to-role-access-matrix.md) | Master 35 Activities × All Roles access matrix + approval intervention & personal privacy protection matrices. |

### 03B — Individual Activity Deep-Dive Specifications (35 Documents in `activities/`)
| # | File | Exact Workbook Activity Title |
|---:|---|---|
| 01 | [activities/01-business-deals.md](./activities/01-business-deals.md) | Business Deals (Given & Received) |
| 02 | [activities/02-p2p-meetings.md](./activities/02-p2p-meetings.md) | P2P Meetings (1-on-1s) |
| 03 | [activities/03-normal-referrals.md](./activities/03-normal-referrals.md) | Normal Referrals (Given & Received Leads) |
| 04 | [activities/04-i-can-help-profiles-and-tags.md](./activities/04-i-can-help-profiles-and-tags.md) | I Can Help Profiles & Tags |
| 05 | [activities/05-collaboration-asks.md](./activities/05-collaboration-asks.md) | Collaboration Asks |
| 06 | [activities/06-get-help-asks.md](./activities/06-get-help-asks.md) | Get Help Asks |
| 07 | [activities/07-referral-asks.md](./activities/07-referral-asks.md) | Referral Asks (Help Me Connect) |
| 08 | [activities/08-testimonials.md](./activities/08-testimonials.md) | Testimonials (Given & Received) |
| 09 | [activities/09-entrepreneur-certification-form.md](./activities/09-entrepreneur-certification-form.md) | Entrepreneur Certification Form |
| 10 | [activities/10-leadership-certification-form.md](./activities/10-leadership-certification-form.md) | Leadership Certification Form |
| 11 | [activities/11-partner-with-us-form.md](./activities/11-partner-with-us-form.md) | Partner With Us Form |
| 12 | [activities/12-brand-partner-form-and-ads.md](./activities/12-brand-partner-form-and-ads.md) | Brand Partner Form & Ads |
| 13 | [activities/13-leadership-role-application-form.md](./activities/13-leadership-role-application-form.md) | Leadership Role Application Form |
| 14 | [activities/14-become-a-speaker-form.md](./activities/14-become-a-speaker-form.md) | Become a Speaker Form |
| 15 | [activities/15-become-a-mentor-form.md](./activities/15-become-a-mentor-form.md) | Become a Mentor Form |
| 16 | [activities/16-recommend-a-peer-form.md](./activities/16-recommend-a-peer-form.md) | Recommend a Peer Form |
| 17 | [activities/17-register-a-visitor-form.md](./activities/17-register-a-visitor-form.md) | Register a Visitor Form |
| 18 | [activities/18-vyapaar-jagat-story-form.md](./activities/18-vyapaar-jagat-story-form.md) | Vyapaar Jagat Story Form |
| 19 | [activities/19-joined-circles-and-memberships.md](./activities/19-joined-circles-and-memberships.md) | Joined Circles & Memberships |
| 20 | [activities/20-circle-membership-requests-and-build-circle.md](./activities/20-circle-membership-requests-and-build-circle.md) | Circle Membership Requests & Build Circle |
| 21 | [activities/21-attended-events-and-registrations.md](./activities/21-attended-events-and-registrations.md) | Attended Events & Registrations |
| 22 | [activities/22-impact-activity-and-life-impact-score.md](./activities/22-impact-activity-and-life-impact-score.md) | Impact Activity & Life Impact Score |
| 23 | [activities/23-coins-activity-wallet-and-history.md](./activities/23-coins-activity-wallet-and-history.md) | Coins Activity, Wallet & History |
| 24 | [activities/24-timeline-posts-created.md](./activities/24-timeline-posts-created.md) | Timeline Posts Created |
| 25 | [activities/25-deactivated-hidden-posts.md](./activities/25-deactivated-hidden-posts.md) | Deactivated / Hidden Posts |
| 26 | [activities/26-user-shorts-videos.md](./activities/26-user-shorts-videos.md) | User Shorts / Videos |
| 27 | [activities/27-invited-friends-introduced-peers.md](./activities/27-invited-friends-introduced-peers.md) | Invited Friends / Introduced Peers |
| 28 | [activities/28-badges-earned.md](./activities/28-badges-earned.md) | Badges Earned |
| 29 | [activities/29-blocked-users.md](./activities/29-blocked-users.md) | Blocked Users |
| 30 | [activities/30-submitted-feedback.md](./activities/30-submitted-feedback.md) | Submitted Feedback |
| 31 | [activities/31-help-and-support-tickets.md](./activities/31-help-and-support-tickets.md) | Help & Support Tickets |
| 32 | [activities/32-membership-plan-and-subscriptions.md](./activities/32-membership-plan-and-subscriptions.md) | Membership Plan & Subscriptions |
| 33 | [activities/33-who-viewed-my-profile.md](./activities/33-who-viewed-my-profile.md) | Who Viewed My Profile |
| 34 | [activities/34-profile-edit-history-and-last-updated.md](./activities/34-profile-edit-history-and-last-updated.md) | Profile Edit History & Last Updated |
| 35 | [activities/35-last-month-activity-summary.md](./activities/35-last-month-activity-summary.md) | Last Month Activity Summary |

### 04 — Workflow Documentation (10 Documents)
| File | Description |
|---|---|
| [20-authentication-and-role-resolution-flow.md](./20-authentication-and-role-resolution-flow.md) | Splash gate, OTP send/verify, Sanctum token, 8-step role resolution, permission matrix load, and sign-out flows. |
| [21-leadership-application-and-approval-flow.md](./21-leadership-application-and-approval-flow.md) | In-app Leader Interest flow + full Website Leadership Election (campaign, nomination, voting, jury, winner creative) flows. |
| [22-leadership-assignment-and-management-flow.md](./22-leadership-assignment-and-management-flow.md) | Circle Founder/Director/Chair/Committee assignment, DED & Industry Director assignment, role removal, and tenure tracking. |
| [23-organizational-structure-and-circle-flow.md](./23-organizational-structure-and-circle-flow.md) | District/Industry taxonomy, Build Your Own Circle, Circle chartering, category seat locking, and committee setup. |
| [24-member-management-flow.md](./24-member-management-flow.md) | Peer onboarding, verification, plan activation, multi-stage Circle Join, profile editing, status flagging, and 360° monitoring. |
| [25-activity-workflow-reference.md](./25-activity-workflow-reference.md) | Individual Mermaid sequence/flow diagrams and 11-step operational walkthroughs for **all 35 activities**. |
| [26-events-and-attendance-flow.md](./26-events-and-attendance-flow.md) | Event creation, paid/free registration, QR pass & scanner check-in, cross-circle clearance, meeting attendance, and Speed Networking. |
| [27-approval-and-escalation-flow.md](./27-approval-and-escalation-flow.md) | Unified 10-queue moderation flow, stage gating, Store Maker-Checker, `WorkflowApprovalRule` gap, and SLA escalation design. |
| [28-notification-generation-and-delivery-flow.md](./28-notification-generation-and-delivery-flow.md) | Event -> recipient/scope filter -> dual inbox -> FCM HTTP v1 / WhatsApp / Email -> `NotificationNavigator` deep link. |
| [29-reporting-and-analytics-flow.md](./29-reporting-and-analytics-flow.md) | Circle roster validation, weekly report submission, DomPDF generation, CSV exports, and hierarchical rollups. |

### 05 — Notifications and Operations (5 Documents)
| File | Description |
|---|---|
| [30-existing-notification-system-audit.md](./30-existing-notification-system-audit.md) | Audit of `app_notifications`, `notifications`, `user_push_tokens`, FCM v1, unread badge drift, and unguarded broadcast endpoints. |
| [31-master-actionable-notification-catalogue.md](./31-master-actionable-notification-catalogue.md) | 11-column specification of every actionable leadership, membership, circle, district, industry, and activity notification. |
| [32-role-wise-notification-matrix.md](./32-role-wise-notification-matrix.md) | Role-by-role notification routing & zero-data-leakage scope rules. |
| [33-action-center-and-task-management.md](./33-action-center-and-task-management.md) | Notification vs. Task vs. Approval separation and minimal-change Role-Aware Action Center specification. |
| [34-escalation-rules-and-ownership.md](./34-escalation-rules-and-ownership.md) | SLA windows, ownership chains, idempotent reminder/escalation schedules, and audit logging. |

### 06 — Analytics & Role-Wise Dashboards (7 Documents)
| File | Description |
|---|---|
| [35-global-admin-dashboard.md](./35-global-admin-dashboard.md) | Super Admin & Country Director (country-scoped) executive KPIs, health rankings, and escalations. |
| [36-district-executive-dashboard.md](./36-district-executive-dashboard.md) | DED dashboard & district role/circle drilldowns reusing `DistrictAnalyticsService`. |
| [37-industry-director-dashboard.md](./37-industry-director-dashboard.md) | Industry Director cross-circle vertical growth, category occupancy, and collaboration metrics. |
| [38-circle-founder-dashboard.md](./38-circle-founder-dashboard.md) | Circle Founder charter growth, milestones/tenures, authoritative circle revenue, and leadership health. |
| [39-circle-director-dashboard.md](./39-circle-director-dashboard.md) | Circle Director Stage-1 approvals, attendance, `At Risk` Peers, events, and operational compliance. |
| [40-chair-dashboard.md](./40-chair-dashboard.md) | Circle Chair, 3 Committee Chairs, Vice Chair, and Secretary activity/meeting/report dashboards (zero finance exposure). |
| [41-master-reporting-and-metrics-reference.md](./41-master-reporting-and-metrics-reference.md) | Authoritative SQL formulas, deduplicated All-Circles rules, `—` revenue rules, exclusions, and scope checks for every metric. |

### 07 — Security & Data Access Audit (3 Documents)
| File | Description |
|---|---|
| [42-rbac-security-audit.md](./42-rbac-security-audit.md) | Severity-ranked RBAC & auth vulnerabilities (`123456` OTP, `@gmail.com` bypasses, unguarded `/rbac/*` & `/admin/*`, unused `leader.can`, token/logout flaws). |
| [43-sensitive-data-and-finance-access.md](./43-sensitive-data-and-finance-access.md) | PII masking (`hide_phone`/`hide_email`), `contact_posts` phonebook leak, public PDF file leak, personal privacy boundaries, and financial access controls. |
| [44-api-and-organizational-scope-security.md](./44-api-and-organizational-scope-security.md) | Endpoint-by-endpoint horizontal IDOR & scope bypass audit (`?scope=global`, `?circle_id=<uuid>`, direct UUID routes, `GET circles` collision). |

### 08 — Enhancement Planning & Implementation Roadmap (7 Documents)
| File | Description |
|---|---|
| [45-existing-vs-required-feature-gap-analysis.md](./45-existing-vs-required-feature-gap-analysis.md) | Master capability gap matrix across all modules classified into the 8 mandatory states. |
| [46-mobile-app-enhancement-recommendations.md](./46-mobile-app-enhancement-recommendations.md) | 10-field enhancement specifications for `LeaderApp-Fornt-end-` reusing existing screens, BLoCs, and widgets. |
| [47-api-enhancement-recommendations.md](./47-api-enhancement-recommendations.md) | Backend API reuse, fix, wire-up, and minimal-addition specifications respecting `app/Leader/` isolation. |
| [48-database-enhancement-recommendations.md](./48-database-enhancement-recommendations.md) | Strictly additive PostgreSQL migration plan, enum checks, `web_` prefix compliance, and shared-table impact review. |
| [49-notification-enhancement-recommendations.md](./49-notification-enhancement-recommendations.md) | Actionable notification triggers, unread-count unification, broadcast lockdown, and SLA reminder jobs. |
| [50-final-implementation-roadmap.md](./50-final-implementation-roadmap.md) | Dependency-ordered Phase 0 → Phase 4 execution roadmap with effort, regression risk, and test gates. |
| [51-open-business-decisions.md](./51-open-business-decisions.md) | Consolidated Founder / Product Owner decision register for every policy ambiguity uncovered during the audit. |

---

## 5. Recommended Reading Order by Stakeholder Role

1. **Founder / CEO / Product Owner:**  
   [README.md](./README.md) → [06-complete-role-hierarchy.md](./06-complete-role-hierarchy.md) → [10-role-wise-screen-access.md](./10-role-wise-screen-access.md) → [12-master-35-activities-index.md](./12-master-35-activities-index.md) → [19-activity-to-role-access-matrix.md](./19-activity-to-role-access-matrix.md) → [33-action-center-and-task-management.md](./33-action-center-and-task-management.md) → [51-open-business-decisions.md](./51-open-business-decisions.md) → [50-final-implementation-roadmap.md](./50-final-implementation-roadmap.md).
2. **Backend & Security Engineering Lead (`unity-app-new`):**  
   [42-rbac-security-audit.md](./42-rbac-security-audit.md) → [44-api-and-organizational-scope-security.md](./44-api-and-organizational-scope-security.md) → [43-sensitive-data-and-finance-access.md](./43-sensitive-data-and-finance-access.md) → [03-existing-backend-api-inventory.md](./03-existing-backend-api-inventory.md) → [08-master-role-permission-matrix.md](./08-master-role-permission-matrix.md) → [41-master-reporting-and-metrics-reference.md](./41-master-reporting-and-metrics-reference.md) → [47-api-enhancement-recommendations.md](./47-api-enhancement-recommendations.md) → [48-database-enhancement-recommendations.md](./48-database-enhancement-recommendations.md).
3. **Senior Flutter Architect (`LeaderApp-Fornt-end-`):**  
   [02-existing-leadership-app-audit.md](./02-existing-leadership-app-audit.md) → [04-existing-feature-and-screen-inventory.md](./04-existing-feature-and-screen-inventory.md) → [10-role-wise-screen-access.md](./10-role-wise-screen-access.md) → [30-existing-notification-system-audit.md](./30-existing-notification-system-audit.md) → [35-global-admin-dashboard.md](./35-global-admin-dashboard.md)..[40-chair-dashboard.md](./40-chair-dashboard.md) → [46-mobile-app-enhancement-recommendations.md](./46-mobile-app-enhancement-recommendations.md).
4. **QA Lead & Domain Analysts:**  
   [13-activity-input-forms-and-validation.md](./13-activity-input-forms-and-validation.md) → [18-activity-lifecycle-and-status-reference.md](./18-activity-lifecycle-and-status-reference.md) → [20-authentication-and-role-resolution-flow.md](./20-authentication-and-role-resolution-flow.md)..[29-reporting-and-analytics-flow.md](./29-reporting-and-analytics-flow.md) → Individual specs in `activities/01-business-deals.md`..`activities/35-last-month-activity-summary.md`.
