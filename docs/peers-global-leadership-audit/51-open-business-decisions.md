# 51 — Open Business & Product Governance Decisions Register

> **Audit Group:** 08 — Enhancement Planning & Roadmap  
> **Audience:** Peers Global Founder, Product Owner & Engineering Leadership  
> **Purpose:** Consolidate every `**BUSINESS DECISION REQUIRED:**` item discovered across the 4-repository code audit, the 35-activity workbook (`PEERS UNITY — MASTER ACTIVITY & ARCHITECTURE DIRECTORY.xlsx`), and the public leadership framework (`peersglobal.com/leadership`) so engineering never guesses or hardcodes unapproved business rules.  
> **Related Deliverables:** [06-complete-role-hierarchy.md](./06-complete-role-hierarchy.md) · [08-master-role-permission-matrix.md](./08-master-role-permission-matrix.md) · [43-sensitive-data-and-finance-access.md](./43-sensitive-data-and-finance-access.md) · [45-existing-vs-required-feature-gap-analysis.md](./45-existing-vs-required-feature-gap-analysis.md) · [46-mobile-app-enhancement-recommendations.md](./46-mobile-app-enhancement-recommendations.md) · [47-api-enhancement-recommendations.md](./47-api-enhancement-recommendations.md) · [50-final-implementation-roadmap.md](./50-final-implementation-roadmap.md)

---

## 1. Decision Register Overview

**FACT:** Note that **Phase 0 (`P0` Security Lockdown)** and **Phase 1 (IDOR & Scope Fixes)** in [50-final-implementation-roadmap.md](./50-final-implementation-roadmap.md) do **NOT** block on any open business decision — security vulnerabilities (`'123456'` OTP, `@gmail.com` bypasses, unauthenticated `/rbac/*`, cross-circle IDORs) must be fixed unconditionally. The **14 business decisions (`BD-01` to `BD-14`)** below govern Phase 2–4 product behavior, role boundaries, financial definitions, and SLA policies.

| ID | Domain | Decision Question (Short) | Blocking Phase | Recommended Default |
|---|---|---|---|---|
| `BD-01` | Roles & Hierarchy | Is `Ambassador` a Growth Milestone Honour only, or an operational Leadership App role? | Phase 2 | **Milestone Honour only** (no Leader App login/dashboard yet) |
| `BD-02` | Roles & Hierarchy | Should `Committee Leader` (`committee_leader`) & `Project Chair` log into the Leadership App? | Phase 2 | **Allow `committee_leader`** (read-only Own Circle scope); **defer `Project Chair`** until DB role exists |
| `BD-03` | Organizational Scope | Should `Executive Director` unify `Area / District / State / Country` via assignment scope while keeping `ded` & `eed` role IDs? | Phase 2 | **Yes** — preserve `districtExecDirector` & `countryDirector` IDs; drive geography from `assignments[]` |
| `BD-04` | Privacy & PII | When a Peer enables `hide_phone` / `hide_email`, can their **own** Circle Director / Founder still view contact info for governance? | Phase 1–2 | **Mask in lists for all; allow own-Circle `CD`/`CF` on 360° profile with audit indicator** |
| `BD-05` | Approvals & Circles | Should `Build Your Own Circle` (`user_circle_requests`) and `Ad Bookings` (`ad_bookings`) be moderated in the mobile Action Center? | Phase 3 | **Web Admin only** for `user_circle_requests` & `ad_bookings` in v1 (keep mobile focused on high-frequency circle ops) |
| `BD-06` | 35 Activities UX | Should Leaders log 1-on-1 Coaching P2Ps & Referrals from `PeerProfileBottomActions`, while keeping Business Deals & Impacts Member-App-only? | Phase 3 | **Mount `Log P2P` + `Send Referral` on Peer 360°; remove orphaned `CreateBusinessDeal` / `CreateImpact` sheets** |
| `BD-07` | Content Moderation | When an authorized Leader deletes a Peer's timeline post (`DELETE /leader/members/{id}/posts/{post_id}`), should it run `PostDeactivationService` (reversing Coins & Life Impact)? | Phase 2 | **Yes — delegate to `PostDeactivationService`** with required `deactivation_remarks` |
| `BD-08` | Task Management | Should Peers Global build a new standalone `tasks` CRUD engine, or use the **Approval & Compliance Action Center**? | Phase 3 | **Use Action Center** (Pending Approvals + Overdue Reports + Event Checklists); do NOT build generic `tasks` CRUD yet |
| `BD-09` | Approvals & SLAs | What are the exact SLA reminder & escalation thresholds (`T+48h`, `T+96h`, `T+7d`) and who receives escalations? | Phase 4 | **48h reminder to `CD`/`CF` -> 96h escalate to `ID`/`DED` -> 7d digest to `Country Director`/`Super Admin`** |
| `BD-10` | Authoritative Finance | What is the exact definition, timeframe, and currency representation of `overall_revenue` (Dashboard) vs `total_revenue` & `circle_revenue` (Finance)? | Phase 1–2 | **Collected GST-exclusive (or gross — confirm!) `payments` + `circle_subscriptions` attributed strictly by `circle_id` within selected `timeframe`** |
| `BD-11` | Finance Operations | Should `POST /finance/transactions/record-offline` require Web Admin maker-checker (`pending_verification`) and which roles may submit? | Phase 1–2 | **`DED`, `Country Director` & `Super Admin` only -> creates `pending_verification` row settled on Web Admin** |
| `BD-12` | Finance Privacy | Should the full cross-role `commission_structure` table (`CF 15%`, `CD 5%`, `DED 10%`, `ID 10%`) be visible to Circle Leaders or restricted? | Phase 1–2 | **Show each Leader only their own role's commission tier; full cross-role table for `Super Admin` only** |
| `BD-13` | Reporting Rules | Which `circle_members.status` & `users.membership_status` values qualify as **Eligible Peers** in Circle & All-Circles Reports? | Phase 2–4 | **`circle_members.status IN ('approved', 'needs_attention', 'under_review')` AND `left_at IS NULL`, excluding `visitor` registrations** |
| `BD-14` | Brand Vocabulary | Should the 34 remaining `"member(s)"` and 5 `"network"` strings in legacy docs/API messages be rewritten, or strictly enforced on mobile UI literals only? | Phase 2 | **Enforce strictly on user-facing UI copy (`Peer` / `Circle Ecosystem`); never rename backend DB/JSON keys** |

---

## 2. Detailed Decision Cards (Context, Options, Trade-Offs & Affected Code)

### `BD-01` — Status of `Ambassador` Role
- **Evidence (`FACT`):** On `peersglobal.com/leadership/ambassadors`, Ambassador is described as *"not a sales role"* outside the 6-stage ladder and 5-role table. In `unity-app-new`, `"Ambassador"` is a **10-peer-introductions Growth Milestone Honour** (`ContributionMilestoneResolver.php:30`, `Track1GrowthHonoursSeeder.php:39`), plus one custom DB role typo `'ambassador' => 'ambassader'` in `RolePermissionMatrixController.php:76`. It does not exist in `UserRoleResolverService` or Flutter `UserRole`.
- **Options:**
  - **Option A (Recommended):** Keep `Ambassador` strictly as a **Growth Milestone Honour Badge** in Member/Leader 360° profiles (`user_milestone_badges`) and clean up the `'ambassader'` typo alias — **no separate Leadership App login or dashboard**.
  - **Option B:** Promote `Ambassador` to an active Leadership App role (`ambassador` key) with outreach-only KPIs (`Introduced Peers`, `Visitor Registrations`).
- **Affected Files/Tables:** `UserRoleResolverService.php`, `RolePermissionMatrixController.php`, `user_role.dart`, `user_milestone_badges`.

---

### `BD-02` — Leadership App Access for `Committee Leader` & `Project Chair`
- **Evidence (`FACT`):** `committee_leader` is seeded at Level 8 in `RoleHierarchySeeder.php:59` and `circle_member_role_enum`, and `OrganizationalAssignmentSyncService.php:293` writes `circle_members.role = 'committee_leader'` — yet `UserRoleResolverService` omits it, causing `403 NOT_A_LEADER` on Leader App login! Meanwhile, `Project Chair` (`peersglobal.com/circle-roles`) does not exist anywhere in PostgreSQL or code.
- **Options:**
  - **Option A (Recommended):** Add `committeeLeader` (`committee_leader`, rank `15`) to `UserRoleResolverService` and Flutter `UserRole` with default read/collaboration capabilities (`access_dashboard`, `view_peers`, `request_actions`, `view_reports` within Own Circle); defer `Project Chair` until product defines concrete workflows.
  - **Option B:** Keep `committee_leader` in the Member App only (`is_leader = false`) and fix `OrganizationalAssignmentSyncService` so the 3 standing Committee Chairs map to `'chair'` instead of `'committee_leader'`. *(Note: Even under Option A, fixing `OrganizationalAssignmentSyncService` so Committee Chairs map properly is recommended!)*
- **Affected Files/Tables:** `UserRoleResolverService.php`, `LeaderPermissionService.php`, `OrganizationalAssignmentSyncService.php`, `user_role.dart`.

---

### `BD-03` — Executive Director Multi-Level Territory Scope (`Area / District / State / Country`)
- **Evidence (`FACT`):** Website Stage 005 defines one *Executive Director* spanning `Area -> District -> State -> Country`. Backend `UserRoleResolverService` defines `districtExecDirector` (`ded`) and `countryDirector` (`eed`), while `OrganizationalScopeService` (`app/Shared/OrganizationalScope/OrganizationalScopeService.php`) already supports `area` (`city_ids`), `district` (`district_ids`), `state` (`state_names`), and `country` (`country_id`)!
- **Options:**
  - **Option A (Recommended):** Preserve backend role keys `districtExecDirector` (`ded`) and `countryDirector` (`eed`) untouched (per **Preserve Backend Role IDs** rule), store exact `scope_type` (`area | district | state | country`) in `user_organizational_assignments`, enforce via `OrganizationalScopeService`, and display `"Executive Director · <Scope Label>"` in UI.
  - **Option B:** Create separate `area_director` and `state_director` rows in `roles`. *(Trade-off: adds role-matrix sprawl without adding new capabilities.)*
- **Affected Files/Tables:** `OrganizationalScopeService.php`, `LeaderPermissionService.php`, `user_organizational_assignments`, `ProfileAssignedCirclesCard`.

---

### `BD-04` — Peer Contact Privacy (`hide_phone` / `hide_email`) vs Circle Governance
- **Evidence (`FACT`):** Peers can set `users.hide_phone = true` and `users.hide_email = true` in the Member App. Currently `LeaderPeersService::formatPeerCard` (`L616`) leaks raw phone/email everywhere, while `getPeer` (`L727`) exposes them to anyone with `manage_peers` across all circles (`DATA-SEC-04`).
- **Options:**
  - **Option A (Strict Privacy):** Always mask `phone` and `email` in the Leadership App whenever `hide_phone` / `hide_email` is `true` (Leaders reach out via in-app `send-reminder` / `sendWish` push notifications).
  - **Option B (Recommended Balanced Governance):** Always mask in directory list payloads (`formatPeerCard`) and for out-of-circle leaders, **but** allow the Peer's **own approved Circle Director, Circle Founder, DED, and Super Admin** to view contact info on the single Peer 360° screen (`GET /leader/members/{id}/profile`) with a visible `"Private (Governance Access Only)"` badge for attendance/renewal follow-up.
- **Affected Files/Tables:** `LeaderPeersService.php`, `LeaderMember360Service.php`, `peer_card.dart`, `peer_profile_overview_section.dart`.

---

### `BD-05` — Mobile Moderation Scope for `user_circle_requests` (*Build Circle*) & `ad_bookings`
- **Evidence (`FACT`):** `PendingRequestAdminController` exposes `ad_booking_requests` in `/leader/pending-requests`, while `user_circle_requests` (*Build Your Own Circle*, which provisions a new `Circle` via `BuildYourOwnCircleApprovalService`) is Admin-only (`/api/v1/admin/user-circle-requests`).
- **Options:**
  - **Option A (Recommended):** Keep both `user_circle_requests` (new Circle provisioning + Zoho plan setup) and `ad_booking_requests` (creative/placement review) **on Web Admin only** (filtering `ad_booking_requests` out of non-Super-Admin `/leader/pending-requests`), keeping the mobile Action Center focused on Circle Joins, Visitor Passes, Pending Impacts, Certifications, Coin Claims, Event Joins, and Peer Referrals.
  - **Option B:** Add read/recommend step for `DED` / `Country Director` on `user_circle_requests` in mobile Action Center.
- **Affected Files/Tables:** `PendingRequestAdminController.php`, `UserCircleRequestAdminController.php`, `leader_activities_view.dart`.

---

### `BD-06` — Orphaned Activity Creation Bottom Sheets in Flutter Leadership App
- **Evidence (`FACT`):** `PeerProfileBottomActions` (`LogP2pBottomSheet` + `SendReferralBottomSheet`), `CreateBusinessDealBottomSheet` (raw UUID input), `CreateImpactBottomSheet`, and `CreateP2pMeetingBottomSheet` are currently unmounted (`MOB-ENH-07`).
- **Options:**
  - **Option A (Recommended):** Mount `PeerProfileBottomActions` (`Log P2P` + `Send Referral`) on `PeerProfileView` so Leaders can log 1-on-1 coaching check-ins and send Peer introductions; **delete/archive** `CreateBusinessDealBottomSheet` and `CreateImpactBottomSheet` (commercial deals and personal impact claims must originate from Peers in the Member App).
  - **Option B:** Keep the Leadership App 100% read/moderation-only for activities (delete all 5 creation sheets; Leaders use the Member App when acting as Peers).
- **Affected Files/Tables:** `peer_profile_view.dart`, `peer_profile_bottom_actions.dart`, `create_business_deal_bottom_sheet.dart`, `create_impact_bottom_sheet.dart`.

---

### `BD-07` — Coin & Life Impact Reversal on Leader Post Deletion (`Activity #24 / #25`)
- **Evidence (`FACT`):** In Web Admin, deactivating a timeline post runs `App\Services\Posts\PostDeactivationService`, which idempotently reverses Coins (`coins_ledger`) and Life Impact (`life_impact_histories`) earned from that post and notifies the Peer. In contrast, `DELETE /api/v1/leader/members/{member_id}/posts/{post_id}` in `LeaderMember360Service.php:1973` performs a raw soft-delete without calling `PostDeactivationService`!
- **Options:**
  - **Option A (Recommended):** Refactor `LeaderMember360Service::deletePost` (gated by `leader.can:manage_peers`) to delegate to `PostDeactivationService::deactivate()` with mandatory `deactivation_remarks`, ensuring Coin/Impact ledgers stay consistent across Admin and Leader App!
  - **Option B:** Restrict post removal/deactivation strictly to Web Admin (`PostModerationController`) and replace `DELETE` on mobile with `"Report Post for Admin Review"` (`post_reports`).
- **Affected Files/Tables:** `LeaderMember360Service.php`, `PostDeactivationService.php`, `posts`, `coins_ledger`, `life_impact_histories`.

---

### `BD-08` — Generic `tasks` Engine vs Approval-Centric Action Center
- **Evidence (`FACT`):** Zero `Task` model or `tasks` table exists in `unity-app-new` (`subagent_3_activities_notifications.md` §2.8).
- **Options:**
  - **Option A (Recommended):** Satisfy operational leadership responsibilities through the **Unified Action Center** (`Pending Approvals` across 8–10 queues + `Due Circle Reports` + `Upcoming Circle Meeting Checklists`) — **zero duplicate task-tracker tables**.
  - **Option B:** Build a new `leader_tasks` table and assignment CRUD in Phase 5 for ad-hoc committee action items (`Committee Chair -> Committee Leader`).
- **Affected Files/Tables:** `leader_activities_view.dart`, `DashboardPendingPeersCard`.

---

### `BD-09` — Pending Approval SLA Timers & Escalation Hierarchy
- **Evidence (`FACT`):** Currently `circle_join_requests`, `impacts`, and `visitor_registrations` can sit in `pending` indefinitely with zero automated nudges (`NOTIF-ENH-06`).
- **Options:**
  - **Option A (Recommended):**
    - `T + 48h` -> Reminder push/in-app to Circle Approver (`Circle Director` / `Circle Founder`).
    - `T + 96h` -> Escalate actionable alert to `Industry Director` & `DED` (allowing regional override approval).
    - `T + 7d` -> Weekly overdue summary to `Country Director` / `Super Admin`.
    - *(Never auto-approve memberships or impacts without human review — keep `workflow_approval_rules.auto_approve_after_hours` disabled for governance queues!)*
  - **Option B:** Custom per-queue SLA hours (e.g., `24h` for Visitor Registrations before event date, `72h` for Circle Joins).
- **Affected Files/Tables:** `SendLeaderSlaRemindersCommand.php`, `circle_join_requests`, `impacts`, `visitor_registrations`.

---

### `BD-10` — Authoritative Revenue Definition & GST Treatment (`Dashboard` vs `Finance`)
- **Evidence (`FACT`):** `LeaderDashboardService` currently fakes `overall_revenue = deals * 0.05`, while `LeaderFinanceService` takes `max(rawPaymentSum, ledgerSubscriptionGrossSum, circleCreditedTotal)` mixing `total_amount` (inclusive of GST) and `base_amount` (exclusive of GST) across all time (`DATA-SEC-09`).
- **Options:**
  - **Option A (Recommended):** Define **Authoritative Circle Revenue** across both Dashboard and Finance as **net collected `base_amount` (ex-GST)** from verified paid `payments` (`status IN ('paid','success','captured')` AND `circle_id IN $scopedCircleIds`) within the selected `timeframe` (`this_month`, `this_quarter`, `this_fy`, `all_time`), returning `{amount_minor, currency: "INR", formatted}` and `null` (`"Unavailable"`) when no authoritative ledger exists. Keep **Business Deals Closed** strictly separate as peer-to-peer GMV.
  - **Option B:** Report gross collected `total_amount` (inclusive of 18% GST) as `Total Collections` and `base_amount` as `Net Circle Revenue`.
- **Affected Files/Tables:** `LeaderFinanceService.php`, `LeaderDashboardService.php`, `payments`, `finance_ledger_transactions`, `DashboardHeroCard`, `FinanceMetricsGrid`.

---

### `BD-11` — Offline Payment Recording Governance (`POST /finance/transactions/record-offline`)
- **Evidence (`FACT`):** Today any leader can insert `'status' => 'Paid'` directly into `payments` (`DATA-SEC-08`).
- **Options:**
  - **Option A (Recommended Maker-Checker):** Restrict mobile submission to `DED`, `Country Director`, and `Super Admin` (`leader.can:manage_finance`); non-`SuperAdmin` submissions create `'status' => 'pending_verification'` (with `circle_id`, `reference_number`, `recorded_by_user_id`) which only counts toward revenue after Finance Admin approves on Web Admin!
  - **Option B:** Remove `RecordPaymentBottomSheet` from mobile completely and record all offline NEFT/cheque payments exclusively in Web Admin Billing.
- **Affected Files/Tables:** `LeaderFinanceController.php`, `LeaderFinanceService.php`, `payments`, `RecordPaymentBottomSheet`.

---

### `BD-12` — Visibility of Cross-Role Commission Rates (`finance_commission_configs`)
- **Evidence (`FACT`):** `GET /finance/summary` and `GET /finance/commission-rates` return the full percentage split table across all leadership tiers (`CF 15%`, `CD 5%`, `DED 10%`, `ID 10%`) to any caller (`DATA-SEC-10`).
- **Options:**
  - **Option A (Recommended):** Scoped leaders with `access_finance` see **only their own role's applicable commission card** (`commission_rates`); the full cross-role `commission_structure` comparison table is returned **only to `Super Admin`** (and `Country Director` if approved).
  - **Option B:** Keep the cross-role commission structure transparent to all `CF`, `CD`, `ID`, and `DED` role holders as part of the public leadership incentive model.
- **Affected Files/Tables:** `LeaderFinanceService.php`, `FinanceCommissionStructure` widget (`finance_commission_structure.dart`).

---

### `BD-13` — All-Circles & Circle Report Roster Eligibility Rules
- **Evidence (`FACT`):** `LEADER_APP_ARCHITECTURE_PROPOSALS.md` §9.2 proved that membership status eligibility for reports was undocumented across all 13 legacy API specs.
- **Options:**
  - **Option A (Recommended):** A Peer qualifies for a Circle's (and deduplicated All-Circles) report roster if and only if:
    1. `circle_members.status IN ('approved', 'needs_attention', 'under_review')` (excluding `'pending'`, `'rejected'`, `'left'`),
    2. `circle_members.deleted_at IS NULL` AND `circle_members.left_at IS NULL`,
    3. `users.deleted_at IS NULL` AND `users.membership_status NOT IN ('visitor', 'suspended')`.
    In All-Circles mode, count each `user_id` **once** in `unique_peers` while listing all qualifying scoped Circles in `memberships[]`.
  - **Option B:** Strictly `circle_members.status = 'approved'` AND active paid `circle_subscriptions` (`expires_at >= report_period_end`) only.
- **Affected Files/Tables:** `LeaderReportsService.php`, `reports_bloc.dart`, `report_submit_section.dart`.

---

### `BD-14` — Brand Vocabulary Enforcement Scope (`README.md` §9)
- **Evidence (`FACT`):** Flutter `test/content/brand_vocabulary_test.dart` now enforces all three prohibited user-facing terms (`[prohibited industry term]`, `member(s)`, `network`) across `lib/` (46 occurrences cleaned in Round 6). Backend JSON responses and error messages in `unity-app-new` still occasionally return `"member"` in human-readable `message` strings (e.g., `"You are not a member of this circle"`).
- **Options:**
  - **Option A (Recommended):** Standardize user-facing `message` strings inside `app/Leader/` controllers/services to use `"Peer"` and `"Circle Ecosystem"` (matching `README.md` §9), while **strictly preserving** all database table names (`circle_members`), URL slugs (`/leader/members/{id}`), and JSON keys (`member_user_id`) for 100% API compatibility (`DEVELOPER_RULES.md` §8 & §16).
  - **Option B:** Enforce vocabulary transformation strictly in the Flutter presentation layer (`ErrorFormatter`) only.
- **Affected Files/Tables:** `app/Leader/Controllers/*`, `app/Leader/Services/*`, `error_formatter.dart`.
