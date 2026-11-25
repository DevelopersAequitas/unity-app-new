# 08 — Master Role & Permission Matrix (21 Actions × 9 Domains × 13 Capabilities)

> **Audit Scope:** Exhaustive Action, Domain, Capability, and Boolean-Flag Permission Matrix across all Peers Global Roles  
> **Overall Status:** `Implemented but Incorrect` (21 UI flags exist, `CheckLeaderCapability` (`leader.can`) is applied to `0` routes, and `DynamicRbacSeeder` leaks `coin_payouts` & `manage_finance`)  
> **Related Deliverables:** [06-complete-role-hierarchy.md](./06-complete-role-hierarchy.md) · [07-role-wise-responsibilities.md](./07-role-wise-responsibilities.md) · [09-organizational-data-scope-matrix.md](./09-organizational-data-scope-matrix.md) · [10-role-wise-screen-access.md](./10-role-wise-screen-access.md) · [11-approval-and-delegation-authority.md](./11-approval-and-delegation-authority.md)

---

## 1. Strict Classification Vocabulary & Enforcement Legend

Every cell in every permission matrix in this document uses **only** the 6 mandatory permission states — **never** generic checkmarks where scope or workflow conditions apply:

| Classification Label | Exact Governance Meaning |
|---|---|
| `Allowed` | Permitted across the platform (Global Scope) without secondary scope conditions, subject to standard audit logging and privacy boundaries. |
| `Conditional` | Permitted **strictly when** specific organizational scope (`Assigned Country`, `Assigned District`, `Assigned Industry`, `Own Circle`, or `Own Record`), status-stage, or Maker-Checker conditions are met (exact conditions documented in Section 5). |
| `Requires Higher Approval` | Role may initiate, draft, recommend, or pre-screen, **but** final state transition requires a higher-ranked authority (e.g., CD Stage-1 -> ID/DED Stage-2, or Maker -> Checker). |
| `Denied` | Explicitly prohibited for this role by business governance (exact rationale documented in Section 6). |
| `Not Applicable` | Action has no meaningful semantics for this role's domain (e.g., mobile Circle Report submission for Functional HQ Read-Only staff). |
| `Not Yet Supported` | Intended governance action where backend/frontend infrastructure does not yet exist (`Missing` or `Implemented but Not Integrated`). |

### Backend Enforcement Reality Tags (Appended to Matrix Rows/Notes)
- **`[ENFORCED: FACT]`** — Server-side controller/service/middleware actively validates role + scope and returns `401`/`403`/`422`.
- **`[UI-ONLY: BUG]`** — Gated in Flutter UI (`LeaderPermissions`) or React UI (`roleAccess.ts`), **but** backend route lacks `leader.can` / `admin.role` / scope validation (`Implemented but Incorrect`).
- **`[UNROUTED]`** — Controller/service code exists in `app/Leader/` without a registered route in `routes/leader.php` (`Implemented but Not Integrated`).

---

## 2. Master 21-Action × Role Governance Matrix

**FACT:** Role column abbreviations: **SA** = Super Admin / Global Founder · **CDir** = Country Director (`eed`) · **DED** = District Exec Director · **ID** = Industry Director · **CF** = Circle Founder · **CD** = Circle Director · **CBG** = Chair Business Growth · **CMG** = Chair Membership · **CEI** = Chair Events & Impacts · **CHR** = Generic Circle Chair · **VC** = Vice Chair · **SEC** = Secretary · **CML** = Committee Leader · **FUNC** = Functional Admin Teams · **AMB** = Ambassador · **MBR** = Peer Member.

| # | Mandatory Action | SA | CDir | DED | ID | CF | CD | CBG / CMG / CEI / CHR | VC / SEC | CML | FUNC | AMB | MBR | Backend Enforcement Reality |
|---:|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| 1 | **View** (Scoped Records & Dashboards) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Not Yet Supported` | `Conditional` | `Conditional` | `Conditional` | **`[UI-ONLY: BUG]`** Country unfiltered; IDORs on `/peers/{id}`, `/circles/{id}`, `/ask-flows` |
| 2 | **List** (Directories, Feeds, Queues) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Not Yet Supported` | `Conditional` | `Conditional` | `Conditional` | **`[UI-ONLY: BUG]`** `?scope=global` & `?circle_id=<uuid>` bypass scoping in `LeaderPeersService` |
| 3 | **Search** (Peers, Circles, Activities, Reports) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Not Yet Supported` | `Conditional` | `Conditional` | `Conditional` | **`[UI-ONLY: BUG]`** Inherits list scope bypasses in `LeaderPeersService` & `LeaderReportsService` |
| 4 | **Create** (Activities, Events, Circles, Roles) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | **`[UI-ONLY: BUG]`** `POST /roles` lacks `superAdmin` check; `POST /activities/*` lacks circle check |
| 5 | **Draft** (Asks, Nominations, Campaigns) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | **`[ENFORCED: FACT]`** `POST /api/v1/asks` & `POST /leadership/nominations/save-draft` |
| 6 | **Submit** (Weekly Reports, Forms, Claims) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Not Applicable` | `Conditional` | `Conditional` | **`[UI-ONLY: BUG]`** `POST /reports/submit` lacks `leader.can:view_reports`, `can_submit_reports`, & circle check |
| 7 | **Edit Own** (Profile, Avatar, Own Draft Asks) | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | **`[ENFORCED: FACT]`** `PUT /leader/auth/profile` & `PUT /api/v1/profile` bound to `$request->user()` |
| 8 | **Edit Others** (Peer Info, Status Flag, Notes) | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Conditional` | `Denied` | `Denied` | **`[UNROUTED]`** `LeaderPeersController::update` unrouted; `flagStatus` lacks `manage_peers` check |
| 9 | **Delete Own** (Own Posts, Drafts, Intro Video) | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Not Applicable` | `Allowed` | `Allowed` | **`[ENFORCED: FACT]`** `DELETE /api/v1/posts/{id}` & `DELETE /api/v1/intro-videos` |
| 10 | **Delete Others** (Member Posts/Creatives, Roles) | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Conditional` | `Denied` | `Denied` | **`[UI-ONLY: BUG]`** `DELETE /leader/members/{id}/posts/{post_id}` & `DELETE /roles/{id}` miss capability checks! |
| 11 | **Approve** (Join Requests, Impacts, Claims, Certs) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Requires Higher Approval` | `Requires Higher Approval` | `Denied` | `Denied` | `Denied` | `Conditional` | `Denied` | `Denied` | **`[ENFORCED: FACT]`** Stage-enforced in `CircleJoinRequestService` & `PendingRequestAdminController` (`403` for Chairs/VC/SEC) |
| 12 | **Reject** (Join Requests, Impacts, Claims, Certs) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Conditional` | `Denied` | `Denied` | **`[ENFORCED: FACT]`** Stage-enforced in `PendingRequestAdminController::rejectCircleJoinRequestViaService` |
| 13 | **Verify** (Activities, Payments, QR Check-In) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Conditional` | `Denied` | `Conditional` | **`[PARTIAL]`** Receiver peer confirms deals/P2P; QR check-in in `EventCheckinService`; offline payment unguarded |
| 14 | **Assign** (Chairs, Founders, Directors, Districts, Jury) | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Not Yet Supported` | `Not Yet Supported` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | **`[UI-ONLY: BUG]`** No Leader chair-assign API; `/api/v1/admin/circles/{id}/assign-*` lacks `admin.role` guard! |
| 15 | **Reassign** (Tickets, Approval Items, Circles) | `Conditional` | `Not Yet Supported` | `Not Yet Supported` | `Not Yet Supported` | `Not Yet Supported` | `Not Yet Supported` | `Denied` | `Denied` | `Denied` | `Conditional` | `Denied` | `Denied` | **`[NOT SUPPORTED]`** Only `support_tickets.assigned_to` exists; zero reassignment on approval queues |
| 16 | **Escalate** (SLA Breaches, Bottlenecks, Disputes) | `Not Applicable` | `Not Yet Supported` | `Not Yet Supported` | `Not Yet Supported` | `Not Yet Supported` | `Not Yet Supported` | `Not Yet Supported` | `Not Yet Supported` | `Not Yet Supported` | `Not Yet Supported` | `Not Applicable` | `Not Applicable` | **`[NOT SUPPORTED]`** Zero escalation tables, endpoints, or cron jobs exist |
| 17 | **Suspend** (Deactivate Posts, Freeze Wallets, Suspend Peer) | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Conditional` | `Denied` | `Denied` | **`[PARTIAL]`** `PostDeactivationService` & `AdminStoreWalletController::freeze` on Admin; `flagStatus` on Leader |
| 18 | **Activate** (Restore Posts, Unfreeze Wallets, Activate Circle) | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Conditional` | `Denied` | `Denied` | **`[PARTIAL]`** `PostController::restore`, `unfreeze`, `UserManagementController` (missing `admin.role` on `/v1/admin/*`!) |
| 19 | **Export** (Activities CSV, Report PDF, Finance/Global) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Conditional` | `Denied` | `Denied` | **`[UI-ONLY: BUG]`** `GET /activities/export` & `GET /reports/{id}/file` lack capability/user scope checks! |
| 20 | **Manage** (Circles, Finance Offline Entries, Campaigns) | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Conditional` | `Denied` | `Denied` | **`[UI-ONLY: BUG]`** `POST /finance/transactions/record-offline` has zero `manage_finance` or scope check! |
| 21 | **Configure** (RBAC Matrix, Commissions, System, Push Config) | `Allowed` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | **`[PARTIAL]`** `PUT /finance/commission-rates` checks `isSuperAdmin` (`FACT`); `/roles/matrix` & `/admin/notifications/config` are **unguarded** (`BUG`)! |

---

## 3. Nine Detailed Domain Permission Matrices

### 3.1 Matrix 1 — Data Visibility & Privacy Boundaries

| Data Asset / Stream | SA | CDir | DED | ID | CF / CD | Chairs (4) | VC / SEC | CML | FUNC |MBR / AMB | Backend Enforcement Status |
|---|---|---|---|---|---|---|---|---|---|---|
| **Scoped Peer Directory (`users`)** | `Allowed` | `Conditional` (Country) | `Conditional` (District) | `Conditional` (Industry) | `Conditional` (Circle) | `Conditional` (Circle) | `Conditional` (Circle) | `Not Yet Supported` | `Conditional` (Module) | `Conditional` (Member App) | `Implemented but Incorrect` (`?scope=global` & `?circle_id` IDOR in [LeaderPeersService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPeersService.php#L288-L330)) |
| **Unmasked Peer Phone & Email (`hide_phone`, `hide_email`)** | `Allowed` | `Conditional` | `Conditional` | `Denied` (Masked if hidden) | `Conditional` (Own Circle + `manage_peers`) | `Denied` (Must respect flags) | `Denied` (Must respect flags) | `Denied` | `Conditional` | `Denied` | `Implemented but Incorrect` (`formatPeerCard()` [L616](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPeersService.php#L616) leaks raw PII unconditionally!) |
| **Deduplicated All-Circles KPIs** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Not Yet Supported` | `Conditional` | `Not Applicable` | `Partially Implemented` (`LeaderDashboardService` deduplicates `$scopedMemberUserIds`; `circleMembers->first()` collapses multi-circle display) |
| **Asks Discovery Feed (`asks`)** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Not Yet Supported` | `Conditional` | `Conditional` (`visibility_type`) | `Implemented but Incorrect` (`LeaderAskFlowService` [L73](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderAskFlowService.php#L73) leaks all platform asks globally!) |
| **Uploaded Phonebook (`contact_posts`)** | `Conditional` (Audit only) | `Denied` | `Denied` | `Denied` | `Conditional` (Own rows) | `Conditional` (Own rows) | `Conditional` (Own rows) | `Denied` | `Denied` | `Conditional` (Own rows) | `Implemented but Incorrect` (`LeaderTeamsService::getUserContacts` [L1942](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderTeamsService.php#L1942) leaks 500 global contacts when leader has `0`!) |
| **Private `peer_blocks` & `profile_views` & Draft Asks** | `Denied` (Privacy Rule) | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Conditional` (Self Only) | `Fully Implemented` (`ProfileController@getViews` & `PeerBlockController` bound to `auth()->id()`) |

### 3.2 Matrix 2 — CRUD Permissions on Operational Entities

| Entity | SA | CDir | DED | ID | CF / CD | Chairs (4) | VC / SEC | MBR | Backend Enforcement Status |
|---|---|---|---|---|---|---|---|---|---|
| **Peer Profile (`users`) — Edit Others** | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Conditional` (Circle) | `Denied` | `Denied` | `Denied` | `Implemented but Not Integrated` (`LeaderPeersController::update` unrouted) |
| **Member 360° Flag Status & Coaching Notes** | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Conditional` (Circle) | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (`LeaderMemberController` checks circle scope, misses `manage_peers` guard) |
| **Member Timeline Posts & Creatives — Delete Others** | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Conditional` (Circle) | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (`DELETE /leader/members/{id}/posts/{post_id}` allows Chairs/VC/SEC in circle to delete!) |
| **P2P Meetings & Referrals (`POST /activities/*`)** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Partially Implemented` (Routed, no circle check on target `peer_id`) |
| **Open-Category Candidate Nomination (`refer-category`)** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Denied` | `Conditional` (`recommend-peer`) | `Implemented but Incorrect` (`LeaderTeamsService::referCategory` [L1806](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderTeamsService.php#L1806) lacks `$circleId` scope check) |
| **Official Circulars (`publish`)** | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Not Integrated` (`LeaderCircularsController::publish` is 100% unrouted!) |

### 3.3 Matrix 3 — Approval & Verification Authority

| Workflow / Queue | SA | CDir | DED | ID | CF / CD | Chairs (4) | VC / SEC | Backend Enforcement Status |
|---|---|---|---|---|---|---|---|---|
| **Circle Join Request — Stage 1 (`pending_cd_approval`)** | `Allowed` (Full/Stage) | `Conditional` (Country) | `Denied` (Stage 2) | `Denied` (Stage 2) | `Requires Higher Approval` (`-> pending_id_approval`) | `Denied` | `Denied` | `Fully Implemented` (`CircleJoinRequestService::approveByCd` & `PendingRequestAdminController` [L1624](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/Admin/PendingRequestAdminController.php#L1624)) |
| **Circle Join Request — Stage 2 (`pending_id_approval`)** | `Allowed` | `Conditional` | `Conditional` (District) | `Conditional` (Industry -> `pending_circle_fee`) | `Denied` | `Denied` | `Denied` | `Fully Implemented` (`CircleJoinRequestService::approveById` & `DedPendingRequestsController`) |
| **Circle Join Request — Fee Override (`mark-paid`)** | `Allowed` | `Conditional` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (`/api/v1/admin/circle-join-requests/{id}/mark-paid` lacks `admin.role` middleware!) |
| **Build Your Own Circle (`user_circle_requests`)** | `Allowed` | `Conditional` | `Requires Higher Approval` | `Requires Higher Approval` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (`BuildYourOwnCircleApprovalService` works; `/api/v1/admin/*` lacks `admin.role`!) |
| **Scoped Pending Impacts / Visitors / Event Joins / Referrals** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` (Circle) | `Denied` | `Denied` | `Fully Implemented` (`PendingRequestAdminController::resolveLeaderScope` returns `403` for Chairs/VC/SEC) |
| **Certifications (`entrepreneur` / `leadership` PDF)** | `Allowed` | `Conditional` | `Denied` | `Denied` | `Conditional` (Circle Peers via Pending Queue) | `Denied` | `Denied` | `Partially Implemented` (`PendingRequestAdminController::approveCertificationSubmission`) |
| **Ad Bookings & Account Deletion Requests** | `Allowed` | `Denied` | `Denied` | `Denied` | `Denied` (Should be HQ Admin only!) | `Denied` | `Denied` | `Implemented but Incorrect` (Exposed in `PendingRequestAdminController` to scoped CDs/CFs if applicant email matches circle!) |
| **Store Wallet Adjustment Maker-Checker** | `Conditional` (`requested_by != auth_id`) | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Fully Implemented` (`MAKER_CHECKER_VIOLATION` enforced in [AdminStoreWalletAdjustmentController.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/V1/Admin/Store/AdminStoreWalletAdjustmentController.php#L80)) |

### 3.4 Matrix 4 — Administrative Operations

| Operation | SA | CDir | DED | ID | CF / CD | Chairs / VC / SEC | FUNC | Backend Enforcement Status |
|---|---|---|---|---|---|---|---|
| **RBAC Role Matrix View & Edit (`/roles/matrix`)** | `Allowed` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (**CRITICAL:** `LeaderRoleManagementController` has **zero** `superAdmin` check!) |
| **Create / Update / Delete Custom Roles (`/roles`)** | `Allowed` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (**CRITICAL:** Any leader can delete system roles via `DELETE /roles/{id}`!) |
| **Deactivate / Restore Timeline Posts (with Coin/Impact Reversal)** | `Allowed` | `Conditional` | `Denied` | `Denied` | `Denied` | `Denied` | `Conditional` (`content_team`) | `Partially Implemented` (`PostDeactivationService` on Admin API/Web) |
| **User Tags CRUD & Assignment (`user_tags`)** | `Allowed` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Conditional` | `Implemented but Incorrect` (`/api/v1/admin/user-tags` missing `admin.role` guard) |
| **Maintenance Mode Bypass & App Config** | `Allowed` | `Conditional` (`allowed_bypass_roles`) | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Fully Implemented` (`AppConfigService` / `SplashView`) |

### 3.5 Matrix 5 — Reports & Analytics

| Report / Analytics Capability | SA | CDir | DED | ID | CF / CD | Chairs (4) | VC / SEC | Backend Enforcement Status |
|---|---|---|---|---|---|---|---|---|
| **View Weekly/Monthly Circle Reports (`GET /reports`)** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Implemented but Incorrect` (`?circle_id=<uuid>` bypasses scope at `LeaderReportsService.php:48`) |
| **Submit Circle Report (`POST /reports/submit`)** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Denied` | `Implemented but Incorrect` (No `can_submit_reports` or circle ownership check; injects fake `94.5%` / `₹18.5L` defaults!) |
| **Download Report PDF (`GET /reports/{id}/file`)** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Implemented but Incorrect` (`renderReportPdf` omits `$user`, plus `/api/v1/files/{id}/download` is public!) |
| **Export Peer / Activity CSV (`GET /activities/export`)** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Implemented but Incorrect` (Missing `can_export_peer_data` route guard) |
| **Export Financial Data** | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Denied` | `Not Yet Supported` (Flag `can_export_financial_data` exists; no backend endpoint) |
| **Export Global Platform Data** | `Allowed` | `Conditional` (Country only) | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Requires Enhancement` (`can_export_global_data` wrongly granted to DED & ID via `regional_data`!) |

### 3.6 Matrix 6 — Notifications & Broadcasts

| Notification Capability | SA | CDir | DED | ID | CF / CD | Chairs (4) | VC / SEC | Backend Enforcement Status |
|---|---|---|---|---|---|---|---|---|
| **Leader Governance Inbox (`GET /notifications`, `mark-read`)** | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Allowed` | `Fully Implemented` (`CONSUMER_EXCLUDED_TYPES` filters out 38 social noise types) |
| **Send Peer Birthday / Anniversary Wish (`POST /peers/{id}/wishes`)** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Implemented but Incorrect` (Missing circle scope check on `{id}`) |
| **Send Member 360° Coaching Reminder (`POST .../send-reminder`)** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Fully Implemented` (Scoped via `LeaderMember360Service::resolveMember`) |
| **Super Admin Notification Broadcast & Trigger Config (`/leader/admin/notifications/*`)** | `Allowed` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (**CRITICAL:** Zero `isSuperAdmin` check on all 5 `/leader/admin/notifications/*` routes!) |

### 3.7 Matrix 7 — Sensitive Financial Information

| Financial Capability / Data | SA | CDir | DED | ID | CF / CD | Chairs (4) | VC / SEC | MBR | Backend Enforcement Status |
|---|---|---|---|---|---|---|---|---|---|
| **View Authoritative Circle/Scoped Revenue & Collections** | `Allowed` | `Conditional` (Country) | `Conditional` (District) | `Conditional` (Industry) | `Conditional` (Circle) | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (`LeaderFinanceController` lacks `leader.can:access_finance`; `orWhereHas('user.circleMembers')` over-attributes revenue; `circle_budget` leaks global sum!) |
| **Record Offline Payment (`POST /finance/transactions/record-offline`)** | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (Zero `manage_finance` check; drops `circle_id` when inserting into `payments`!) |
| **Configure Role Commission Rates (`PUT /finance/commission-rates`)** | `Allowed` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Fully Implemented` (`LeaderFinanceController.php:68` enforces `$this->permissions->isSuperAdmin($user)`) |
| **Track Leadership Milestones & Tenure Bonuses (`track_milestones`)** | `Allowed` | `Conditional` | `Conditional` | `Conditional` | `Conditional` | `Denied` | `Denied` | `Denied` | `Partially Implemented` (`DynamicRbacSeeder` drops `track_milestones`; `superAdmin`, `countryDirector`, & `industryDirector` miss it in defaults!) |
| **Issue / Disburse Coin Payouts (`coin_payouts`)** | `Allowed` | `Conditional` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (`DynamicRbacSeeder` marks `coins` visible for all roles, accidentally granting `coin_payouts` down to `secretary`!) |

### 3.8 Matrix 8 — Leadership Assignment & Tenure Governance

| Assignment Action | SA | CDir | DED | ID | CF / CD | Chairs / VC / SEC | Backend Enforcement Status |
|---|---|---|---|---|---|---|
| **Assign Country Director / DED / Industry Director** | `Allowed` | `Requires Higher Approval` (DED/ID in Country) | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (`/api/v1/admin/access/*` & `/api/v1/rbac/*` lack proper admin guards!) |
| **Assign Circle Founder & Circle Director** | `Allowed` | `Conditional` | `Requires Higher Approval` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (`PATCH /api/v1/admin/circles/{id}/assign-founder|assign-director` lacks `admin.role`!) |
| **Assign Circle Chairs, Vice Chair, Secretary, Committee Leaders** | `Allowed` | `Conditional` | `Conditional` | `Denied` | `Conditional` (Own Circle) | `Denied` | `Implemented but Not Integrated` (`can_assign_circle_chair` flag exists; zero `/api/v1/leader/*` endpoint exists!) |
| **Manage Website Leadership Election Campaigns, Jury & Final Winners** | `Allowed` | `Conditional` | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (`/api/v1/leadership/admin/*` only checks `['web', 'admin.auth']` without role checks!) |

### 3.9 Matrix 9 — Organizational & Platform Configuration

| Configuration Surface | SA | CDir | DED | ID | CF / CD | Chairs / VC / SEC | Backend Enforcement Status |
|---|---|---|---|---|---|---|
| **Create / Archive Circles & Configure Category Seats** | `Allowed` | `Conditional` | `Requires Higher Approval` | `Denied` | `Denied` (Proposes via Build Circle) | `Denied` | `Implemented but Incorrect` (`/api/v1/admin/circles/*` missing `admin.role` middleware) |
| **Configure Membership & Circle Plans (`membership_plans`)** | `Allowed` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Incorrect` (`/api/v1/admin/plans/*` missing `admin.role` middleware) |
| **Configure Ask Flows, Impact Actions & Coin Rules** | `Allowed` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Partially Implemented` (Admin Web guarded; `/api/v1/admin/*` API group under-guarded) |
| **Configure Workflow Approval Rules (`workflow_approval_rules`)** | `Allowed` | `Denied` | `Denied` | `Denied` | `Denied` | `Denied` | `Implemented but Not Integrated` (Unauthenticated `/api/v1/admin/rbac/workflow-rules`; never read at runtime!) |

---

## 4. Complete 13 Capabilities × 21 Backend Boolean Flags (+ 22nd Flutter Flag)

### 4.1 Capability Defaults by Role (`LeaderPermissionService::getDefaultRoleCapabilities()`)

**FACT:** [LeaderPermissionService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L26-L230) defines **13 capabilities** (despite its docblock stating 12):

| # | Capability ID | Category | SA (`12`) | CDir (`10`) | DED (`10`) | ID (`7`) | CF (`8`) | CD (`8`) | Chairs ×4 (`5`) | VC (`4`) | SEC (`4`) | Dynamic RBAC Sync Mapping & Bugs ([L406-L463](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L406-L463)) |
|---:|---|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|---|
| 1 | `access_dashboard` | Navigation & Access | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | `dashboard` module OR page `LIKE '%dashboard%'` |
| 2 | `access_teams` | Navigation & Access | Yes | Yes | Yes | Yes | Yes | Yes | Yes | No | No | `circles` or `teams` module |
| 3 | `access_finance` | Navigation & Access | Yes | Yes | Yes | Yes | Yes | Yes | No | No | No | `finance-analytics` or `finance` module |
| 4 | `regional_data` | Navigation & Access | Yes | Yes | Yes | Yes | No | No | No | No | No | `scope_type != 'circle'` or regional role key |
| 5 | `view_peers` | Core Operations | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | `members` or `peers` module |
| 6 | `manage_peers` | Core Operations | Yes | Yes | Yes | No | Yes | Yes | No | No | No | **BUG:** Granted if `members` visible AND **any** page in **any** module has `create`/`edit`/`delete`! |
| 7 | `request_actions` | Core Operations | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | `activities` module |
| 8 | `view_reports` | Compliance & Growth | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | Yes | `referral-report` or `reports` module |
| 9 | `manage_finance` | Finance Control | Yes | Yes | Yes | No | No | No | No | No | No | **BUG:** Automatically granted whenever `finance-analytics` or `finance` is visible! |
| 10 | `coin_payouts` | Finance Control | Yes | Yes | No | No | No | No | No | No | No | **CRITICAL BUG:** Mapped from `coins` module, which `DynamicRbacSeeder` enables for **ALL** roles down to `secretary`! |
| 11 | `track_milestones` | Finance Control | **No (Bug)** | **No (Bug)** | Yes | **No (Bug)** | Yes | Yes | No | No | No | **BUG:** **Zero mapping** in `resolveCapabilitiesFromDynamicRbac()` — silently stripped when Dynamic RBAC runs! |
| 12 | `manage_roles` | Administration | Yes | No | No | No | No | No | No | No | No | `role-management` module |
| 13 | `system_configs` | Administration | Yes | No | No | No | No | No | No | No | No | `settings` module |

### 4.2 Exact Derivation of the 21 Backend Flags (`resolvePermissionMatrix()`) vs 22 Flutter Flags

**FACT:** Compare [LeaderPermissionService.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Leader/Services/LeaderPermissionService.php#L471-L502) against [leader_permissions.dart](file:///D:/office%20project/LeaderApp-Fornt-end-/lib/core/models/leader_permissions.dart#L77-L155):

| # | Backend JSON Key | Backend Formula (`LeaderPermissionService.php:477-501`) | Flutter `fromCapabilities()` Formula (`leader_permissions.dart:77-103`) | Audit Discrepancy |
|---:|---|---|---|---|
| 1 | `can_access_dashboard` | `true` (hardcoded) | `caps.contains('access_dashboard') \|\| caps.isEmpty` | Aligned |
| 2 | `can_view_overall_revenue` | `$isSuper \|\| $hasCap('access_finance')` | `caps.contains('access_finance')` | Aligned |
| 3 | `can_review_pending_peers` | `$isSuper \|\| $hasCap('access_teams') \|\| in_array($role, ['circleFounder','circleDirector','districtExecDirector','industryDirector','countryDirector'])` | `caps.contains('request_actions') \|\| caps.contains('view_peers')` | **BUG:** Backend grants to Chairs via `access_teams` (who then get `403` on API!); Flutter grants to VC/SEC via `view_peers`! |
| 4 | `can_access_peers_tab` | `$isSuper \|\| $hasCap('view_peers')` | `caps.contains('view_peers')` | Aligned |
| 5 | `can_add_edit_peer` | `$isSuper \|\| $hasCap('manage_peers') \|\| in_array($role, ['circleFounder','circleDirector','districtExecDirector','countryDirector'])` | `caps.contains('manage_peers')` | **BUG:** Hardcoded `in_array` prevents revoking `manage_peers` for CF/CD/DED/CDir in Role Matrix! |
| 6 | `can_send_wishes` | `true` (hardcoded) | `caps.contains('request_actions') \|\| caps.contains('view_peers')` | Minor fallback difference |
| 7 | `can_view_peer_profile` | `$isSuper \|\| $hasCap('view_peers')` | `caps.contains('view_peers')` | Aligned |
| 8 | `can_view_peer_contact_info` | `$isSuper \|\| $hasCap('view_peers')` | `caps.contains('view_peers')` | Needs `hide_phone`/`hide_email` respect |
| 9 | `can_access_teams_tab` | `$isSuper \|\| $hasCap('access_teams')` | `caps.contains('access_teams')` | Aligned |
| 10 | `can_manage_circles` | `$isSuper \|\| $hasCap('access_teams')` | `caps.contains('system_configs') \|\| caps.contains('access_teams')` | Overly broad for Chairs (`access_teams`) |
| 11 | `can_assign_circle_chair` | `$isSuper \|\| ($hasCap('access_teams') && in_array($role, ['circleFounder','circleDirector','districtExecDirector','countryDirector']))` | `caps.contains('system_configs') \|\| caps.contains('manage_roles')` | **Divergent** + **No backend endpoint exists** |
| 12 | `can_access_finance_tab` | `$isSuper \|\| $hasCap('access_finance')` | `caps.contains('access_finance')` | Aligned |
| 13 | `can_modify_finance_settings` | `$isSuper \|\| $hasCap('manage_finance') \|\| in_array($role, ['districtExecDirector','countryDirector'])` | `caps.contains('manage_finance')` | **BUG:** Hardcoded `in_array` prevents revoking for DED/CDir; exposes Commission Rate UI to DED/CDir even though API blocks non-SuperAdmin! |
| 14 | `can_issue_coins` | `$isSuper \|\| $hasCap('coin_payouts') \|\| in_array($role, ['countryDirector'])` | `caps.contains('coin_payouts')` | **BUG:** Leaked to all roles by `DynamicRbacSeeder` `coins` module! |
| 15 | `can_access_reports_tab` | `$isSuper \|\| $hasCap('view_reports')` | `caps.contains('view_reports')` | Aligned |
| 16 | `can_submit_reports` | `$isSuper \|\| ($hasCap('view_reports') && in_array($role, [Chairs×4, CF, CD, ID, DED, CDir]))` | `caps.contains('view_reports')` | **Divergent:** Flutter `fromCapabilities` grants `canSubmitReports` to VC/SEC! |
| 17 | `can_export_peer_data` | `$isSuper \|\| ($hasCap('view_peers') && in_array($role, [CF, CD, ID, DED, CDir]))` | `caps.contains('manage_peers') \|\| caps.contains('system_configs')` | **Divergent:** Flutter drops `canExportPeerData` for ID when parsed from capabilities! |
| 18 | `can_export_financial_data` | `$isSuper \|\| ($hasCap('access_finance') && in_array($role, ['districtExecDirector','countryDirector']))` | `caps.contains('manage_finance') \|\| caps.contains('access_finance')` | **Divergent:** Flutter grants to CF/CD/ID via `access_finance`! |
| 19 | `can_export_global_data` | `$isSuper \|\| $hasCap('regional_data')` | `caps.contains('system_configs')` | **BUG:** Backend grants `can_export_global_data` to DED & ID because they hold `regional_data`! |
| 20 | `can_access_role_management` | `$isSuper \|\| $hasCap('manage_roles')` | `caps.contains('manage_roles')` | Aligned in UI, unguarded on backend! |
| 21 | `can_view_regional_scope` | `$isSuper \|\| $hasCap('regional_data') \|\| in_array($role, ['industryDirector','districtExecDirector','countryDirector'])` | `caps.contains('regional_data')` | Hardcoded `in_array` override |
| 22 | *(Flutter-only)* `canViewIndustryClusters` | *Not returned by backend* (defaults `true` in `fromJson` L153!) | `caps.contains('regional_data') \|\| caps.contains('system_configs') \|\| caps.contains('industry_clusters')` | Frontend-only 22nd flag |

---

## 5. Exact Conditions for Every `Conditional` & `Requires Higher Approval` Classification

1. **Geographic / Sector / Circle Scope Condition:** Every `Conditional` read, list, search, report, export, wish, reminder, or peer-management action requires the target entity (`circle_id`, `member_id`, `report_id`, `request_id`) to intersect the authenticated actor's server-resolved assignment scope (`Assigned Country` for `countryDirector`, `Assigned District(s)` for `districtExecDirector`, `Assigned Industry Categories` for `industryDirector`, `Assigned Circle(s)` for `CF`/`CD`/`Chairs`/`VC`/`SEC`, and `Self` for `member`).
2. **Stage-Gated Circle Join Condition (`Requires Higher Approval` / `Conditional`):**
   - `circleDirector` and `circleFounder` may approve/reject a `CircleJoinRequest` **only when** `status === 'pending_cd_approval'` AND `circle_id` belongs to their managed Circles; approval transitions to `pending_id_approval` (never directly to `circle_member`).
   - `industryDirector` and `districtExecDirector` may approve/reject **only when** `status === 'pending_id_approval'` AND the request's `circle_id` or `level1_category_id` falls inside their assigned Industry/District; approval transitions to `pending_circle_fee` (requiring applicant payment or Admin fee verification before `circle_member`).
3. **Maker-Checker Dual-Control Condition (`Conditional`):**
   - On `wallet_adjustment_requests`, an authorized Admin may approve `status === 'PENDING'` **only when** `$adjustment->requested_by !== $admin->id` ([AdminStoreWalletAdjustmentController.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/V1/Admin/Store/AdminStoreWalletAdjustmentController.php#L80-L84)).
4. **Peer Privacy Masking Condition (`Conditional`):**
   - Viewing unmasked `phone` or `email` when `users.hide_phone = true` or `users.hide_email = true` is permitted **only** for executives holding `manage_peers` **within the target Peer's Circle/District/Country scope** (never cross-circle).
5. **Receiver Confirmation Condition (`Conditional`):**
   - For Peer Members, commercial/collaboration Life Impact and P2P completion verification is valid **only** when confirmed by the counterparty (`to_user_id` / `invitee_id`), never self-certified by the initiator.

---

## 6. Explicit Governance Rationale for Every `Denied` Category

1. **Why Chairs, Vice Chairs, Secretaries & Committee Leaders Are `Denied` Financial Analytics & Offline Payment Recording:** Circle subscription fees, leader commissions, and budget wallets contain sensitive commercial and personal payout data governed under tenure contracts (`CF`, `CD`, `ID`, `DED`); Circle officers focus on peer engagement and meeting operations, not fee collection.
2. **Why Industry Directors Are `Denied` `manage_peers` & Post Deletion:** Industry Directors exercise cross-circle vertical governance and Stage-2 category vetting rather than day-to-day Circle roster discipline or content moderation, which belong to the Circle Director/Founder and DED.
3. **Why Non-Super-Admins Are `Denied` RBAC Matrix, Commission Rate & Global Broadcast Configuration:** Allowing scoped leaders to edit `leader_role_capabilities`, `finance_commission_configs`, or `/leader/admin/notifications/*` enables immediate vertical privilege escalation and platform-wide spam/financial tampering.
4. **Why Super Admin Is `Denied` Casual Access to `peer_blocks`, `profile_views` & Private Drafts:** Personal safety blocks, profile browsing telemetry, and unpublished drafts are private member trust boundaries protected under Hard Rule #8.
5. **Why Circle Officers (Chairs, VC, SEC, CML) Are `Denied` Formal Pending Request Approval:** Multi-stage admission (`CD -> ID/DED -> Fee`) and coin/impact disbursement directly affect category exclusivity, financial ledger balances, and Life Impact rankings, requiring accountable charter/executive authority.
