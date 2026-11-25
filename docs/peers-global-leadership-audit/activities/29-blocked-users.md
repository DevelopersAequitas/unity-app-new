# Activity 29: Blocked Users

| Attribute | Verified Audit Classification |
|---|---|
| **Workbook Activity #** | `29` |
| **Exact Workbook Name** | `Blocked Users` |
| **Domain Category** | `Privacy & Safety` *(Sheet 3: `Privacy & Safety`)* |
| **Activity Governance Tier** | `Not Needed in Leader App` / `Member-Only` *(Strictly Personal Consumer Safety & Privacy Boundary)* |
| **Overall Implementation Status** | `Not Required` *(in Leadership App)* · `Fully Implemented` *(in Member App & Laravel Backend via `peer_blocks` + `speed_networking_blocked_peers`)* |
| **Master Index & Cross-Links** | [12-master-35-activities-index.md](../12-master-35-activities-index.md) · [19-activity-to-role-access-matrix.md](../19-activity-to-role-access-matrix.md) · [25-activity-workflow-reference.md](../25-activity-workflow-reference.md) · [43-sensitive-data-and-finance-access.md](../43-sensitive-data-and-finance-access.md) |

---

## 1. Business Purpose (§9.1)

- **WORKBOOK REQUIREMENT:** Sheet 1 & Sheet 2 state Activity 29 exists to *"Prevent unwanted interactions, messages, or profile visibility from selected accounts"* via a *"Block User"* action on peer profiles or chat, with a settings screen (`BlockedUsersScreen`) showing `blockedUsersCount` and a one-tap `"Unblock"` action.
- **FACT:** In the Laravel 12 + Flutter ecosystem, Activity 29 is implemented across **two strictly member-owned safety tables**:
  1. **Platform-Wide Peer Block (`peer_blocks`):** Managed by [PeerBlockController.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/V1/PeerBlockController.php#L17-L92) and `App\Services\Blocks\PeerBlockService`. Enforces bidirectional interaction and visibility suppression (`isBlockedEitherWay`) across peer profiles, direct messaging, connection requests, profile view logs, and badge lookups.
  2. **Speed Networking Match Exclusion (`speed_networking_blocked_peers`):** Managed by `SpeedNetworkingBlockController`, allowing members to privately opt out of being paired with specific peers during live 1-on-1 Speed Networking rounds without necessarily blocking them across the entire app.
- **STRICT PRIVACY GOVERNANCE RULE (Why Neither Circle Leaders Nor Super Admin Should Browse Personal Peer Block Lists in the Leadership App):**
  - **Personal Psychological Safety:** A member blocking another peer inside a business chapter is an interpersonal boundary decision (often involving unwanted sales solicitation, personal discomfort, or harassment prevention). Exposing *"Peer A blocked Peer B"* to Circle Chairs, Circle Directors, Circle Founders, DEDs, or even Super Admin in a mobile Leadership directory creates severe retaliation, chapter politics, and gossip risks.
  - **Separation of Personal Block vs Formal Misconduct Report:** If a peer violates chapter ethics and requires leadership intervention, the member explicitly files a **Post Report** ([25-deactivated-hidden-posts.md](./25-deactivated-hidden-posts.md)) or **Support Ticket** ([31-help-and-support-tickets.md](./31-help-and-support-tickets.md)). Silent personal blocks (`peer_blocks`) are private self-defense controls — **NOT** leadership surveillance signals.
  - **Hard Audit Rule:** Per Master Governance Rule #8, **neither Circle Leaders nor Super Admin** are granted visibility into personal `peer_blocks` or `speed_networking_blocked_peers` lists in the Leadership App (`Not Required`). Platform-level account suspension is a completely separate administrative action (`PATCH /api/v1/admin/peers/{id}/status` on `users.status`).

---

## 2. Sub-Activities Catalogue (§9.2)

| # | Sub-Activity / Atomic Operation | Member App (`unity_app`) | Leadership App (`LeaderApp`) | React Admin Web (`unity-admin-web`) / Blade | Laravel 12 Backend (`unity-app-new`) | Classification |
|---|---|---|---|---|---|---|
| **29.1** | **Block a Peer Platform-Wide (`peer_blocks`)** | `peer_profile_screen.dart` / `direct_chat_screen.dart` (`POST /api/v1/peers/{user}/block`) | `Not Required` | `Not Required` (Privacy boundary) | `PeerBlockController@store` → `PeerBlockService::block()` | `Fully Implemented` |
| **29.2** | **Unblock a Previously Blocked Peer** | `blocked_users_screen.dart` (`DELETE /api/v1/peers/{user}/block`) | `Not Required` | `Not Required` | `PeerBlockController@destroy` → `PeerBlockService::unblock()` | `Fully Implemented` |
| **29.3** | **View Own Blocked Users List & Count** | `blocked_users_screen.dart` (`GET /api/v1/blocked-peers`) | `Not Required` | `Not Required` | `PeerBlockController@index` (`BlockedPeerResource`) | `Fully Implemented` |
| **29.4** | **Check Bidirectional Block Status (`cannot_interact`)** | Peer Profile & Chat guards (`GET /api/v1/peers/{user}/block-status`) | `Not Required` | `Not Required` | `PeerBlockController@status` (`is_blocked_by_me`, `has_blocked_me`, `cannot_interact`) | `Fully Implemented` |
| **29.5** | **Exclude Peers from Speed Networking Pairing (`speed_networking_blocked_peers`)** | `speed_networking_lobby_screen.dart` / `history_screen.dart` | `Not Required` | `Not Required` | `SpeedNetworkingBlockController` (`GET\|POST /speed-networking/blocked-peers`, `DELETE .../{peerId}`) | `Fully Implemented` |
| **29.6** | **Enforce Mutual Visibility Filter Across All Feeds** | Enforced in Profile, Connections, Chat, Badges | N/A | N/A | Enforced in `PeerBadgeService`, `ProfileController`, Chat; **Gap:** Missing in `IntroVideoController@index` | `Requires Enhancement` *(Add `PeerBlock` filter to `IntroVideoController`)* |

---

## 3. Input Form & Field Validation Mapping (§9.3)

| Field Name | Data Type | Required/Optional | Validation | Default | Input Widget | Data Source | Visibility | Who Enters | Who Edits | Who Views | Sensitive? | Backend Request Key |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| **Target Peer User ID** | `uuid` | Required | Valid UUID in `users.id`; `!= $authUser->id` (`422 "You cannot block yourself."`) | Viewed peer | Overflow menu `"Block User"` + Confirmation Dialog | `users.id` | Private to Blocker | Blocking Peer Member | Deletable via Unblock | **Blocker Only** *(Blocked peer sees generic unavailable/restricted state, not block reason)* | **Yes** *(Confidential privacy boundary)* | `{user}` *(route)* / `peer_id` |
| **Block Reason** | `string` | Optional | `nullable\|string\|max:500` (`BlockPeerRequest`) | `null` | Optional reason input / preset | User input | Stored in `peer_blocks.reason` | Blocking Peer Member | Immutable (delete & re-create) | **Blocker Only** *(Never exposed to Leaders or Blocked Peer)* | **Yes** *(Highly sensitive)* | `reason` |
| **Speed Networking Event ID** | `uuid` | Optional | `nullable\|uuid\|exists:speed_networking_events,id` | `null` *(global SN exclusion)* | Speed Networking post-call / lobby block button | `speed_networking_events.id` | Private to Blocker | Peer Participant | Deletable via Unblock | **Blocker Only** (consumed internally by pairing engine) | **Yes** | `event_id` |

---

## 4. Workflow, Lifecycle & Mermaid Diagram (§9.4)

### Workbook vs Actual PostgreSQL Lifecycle Comparison
- **WORKBOOK REQUIREMENT:** `User Blocked -> Visibility Revoked -> Chat Restricted -> Unblocked`.
- **FACT (Actual Database Persistence):**
  - `peer_blocks` has **no** status enum column; row presence represents an active block (`UNIQUE(blocker_user_id, blocked_user_id)`), and unblocking hard-deletes the `peer_blocks` row (`PeerBlockService::unblock()`).
  - Duplicate block attempts are idempotent (`PeerBlockController@store` checks `hasBlocked()` and returns `200 "Peer is already blocked."`).
  - **Endpoint Alias Note in Member App:** [api_endpoints.dart](file:///D:/office%20project/peers-unity/unity_app/lib/core/constants/api_endpoints.dart#L118-L121) defines both `blockedPeers = '/blocked-peers'` (canonical v1 route) and legacy menu constants `blockedUsers = '/blocked-users'` (`L307-308`).

```mermaid
stateDiagram-v2
    [*] --> Unblocked: "Default Peer Relationship (cannot_interact = false)"
    Unblocked --> Blocked: "POST /api/v1/peers/{user}/block (Insert peer_blocks row)"
    Blocked --> Blocked: "Repeat Block Call (Idempotent 200 OK)"
    Blocked --> MutualSuppression: "PeerBlockService::isBlockedEitherWay() == true (Profile, Chat, Badges, Connections Hidden)"
    MutualSuppression --> Unblocked: "DELETE /api/v1/peers/{user}/block (Delete peer_blocks row)"
```

---

## 5. Outputs & Deliverables (§9.5)

| Deliverable / Output | Workbook Spec | Actual Codebase Reality | Verification Label |
|---|---|---|---|
| **Blocked Users Settings List** | `BlockedUsersScreen` with one-tap Unblock action | Implemented in [blocked_users_screen.dart](file:///D:/office%20project/peers-unity/unity_app/lib/features/menu/presentation/screens/blocked_users_screen.dart) rendering `count` + `BlockedPeerResource` cards (`display_name`, `company_name`, `designation`, `profile_photo_url`, `Unblock` button) | **FACT** (`Fully Implemented`) |
| **Bidirectional Interaction Guard Payload** | Chat & profile restriction | `GET /api/v1/peers/{user}/block-status` returns `{ is_blocked_by_me, has_blocked_me, cannot_interact }` | **FACT** (`Fully Implemented`) |
| **Silent Action (Zero Push Notification)** | Not specified | Blocking or unblocking intentionally sends **zero** notifications to the blocked user to prevent retaliation | **FACT** *(Privacy Best Practice)* |
| **Leadership / Admin Block Surveillance Table** | None | Intentionally **absent** across `LeaderApp` and `unity-admin-web` | **FACT** (`Not Required` — Privacy Boundary Enforced) |

---

## 6. Database & Metrics Mapping (§9.6)

### Eloquent Models & PostgreSQL Tables
1. **`App\Models\PeerBlock`** (`app/Models/PeerBlock.php` → `peer_blocks`):
   - **Columns:** `id` (`uuid`), `blocker_user_id` (`uuid` FK → `users.id`), `blocked_user_id` (`uuid` FK → `users.id`), `reason` (`text`, nullable), `created_at`, `updated_at`.
   - **Constraint:** Unique composite index `(blocker_user_id, blocked_user_id)`.
2. **`App\Models\SpeedNetworking\SpeedNetworkingBlockedPeer`** (`app/Models/SpeedNetworking/SpeedNetworkingBlockedPeer.php` → `speed_networking_blocked_peers`):
   - **Columns:** `id` (`uuid`), `blocker_id` (`uuid` FK → `users.id`), `blocked_id` (`uuid` FK → `users.id`), `event_id` (`uuid`, nullable), `reason` (`string`, nullable), `created_at`, `updated_at`.

### Ownership, Organizational Scope & Privacy Isolation
- **Strict Self-Only Scope (`Own`):** `PeerBlockController@index` hardcodes `->where('blocker_user_id', (string) $authUser->id)` ([PeerBlockController.php](file:///c:/Users/malic/OneDrive/Documents/GitHub/unity-app-new/app/Http/Controllers/Api/V1/PeerBlockController.php#L23)). No query parameter (`?user_id=...`) is accepted, eliminating horizontal IDOR risk.
- **Circle Attendance Exception:** Blocking a peer in `peer_blocks` suppresses 1-on-1 DMs and personal social views, while chapter rosters (`circle_members`) and official event attendance logs remain accurate for Circle Leaders without revealing that a block exists between two members.

### Sheet 3 Workbook Metric Keys vs Actual Database Mapping
| Workbook Metric Key (Sheet 1 & Sheet 3) | Actual Backend / SQL Equivalent | Status |
|---|---|---|
| `blockedUsersCount` | `data.count` (`PeerBlockService::blockedCountFor($authUser->id)`) | `Fully Implemented` (`FACT`) |
| `blockedUserIdsList` | `data.items` (`BlockedPeerResource::collection($blocks)`) | `Fully Implemented` (`FACT`) |

---

## 7. API & Backend Service Mapping (§9.7)

| Surface | Method & Endpoint | Controller & Action | Service Layer | Auth Middleware | Scope Enforcement & Security Audit | Flutter / Web Integration |
|---|---|---|---|---|---|---|
| **Member API** | `GET /api/v1/blocked-peers` | `Api\V1\PeerBlockController@index` | `PeerBlockService::blockedCountFor()` | `auth:sanctum`, `unity.user` | Strictly self-scoped (`blocker_user_id = auth()->id()`) | Wired in `unity_app` (`BlockedUsersScreen`) |
| **Member API** | `POST /api/v1/peers/{user}/block` | `Api\V1\PeerBlockController@store` | `PeerBlockService::block()` | `auth:sanctum`, `unity.user` | Self-block rejected (`422`); idempotent | Wired in `unity_app` (`ApiEndpoints.blockPeer`) |
| **Member API** | `DELETE /api/v1/peers/{user}/block` | `Api\V1\PeerBlockController@destroy` | `PeerBlockService::unblock()` | `auth:sanctum`, `unity.user` | Only deletes row where `blocker_user_id = auth()->id()` | Wired in `unity_app` (`ApiEndpoints.unblockPeer`) |
| **Member API** | `GET /api/v1/peers/{user}/block-status` | `Api\V1\PeerBlockController@status` | `PeerBlockService::hasBlocked()` | `auth:sanctum`, `unity.user` | Returns pair interaction flags only for authenticated caller | Wired in `unity_app` (`ApiEndpoints.peerBlockStatus`) |
| **Member API** | `GET\|POST /api/v1/speed-networking/blocked-peers`, `DELETE .../{peerId}` | `SpeedNetworkingBlockController` | Speed Networking match filter | `auth:sanctum`, `unity.user` | Strictly self-scoped (`blocker_id = auth()->id()`) | Wired in `unity_app` (`speed_networking` module) |
| **Leader / DED / Admin APIs** | *None* | *None* | *None* | N/A | **Properly Absent:** No Leader, DED, or Admin endpoint exposes `peer_blocks` | `Not Required` (Do NOT create) |

### Workbook Cloud Services vs Actual Laravel 12 Services
- **WORKBOOK REQUIREMENT:** `AccessControlService` and `MutualVisibilityFilter`.
- **FACT:** Implemented via `App\Services\Blocks\PeerBlockService` and `App\Services\ProfileVisibilityService`.

---

## 8. Role-Wise Access Governance (§9.8)

> **Mandatory Privacy Enforcement:** Across **all** leadership tiers — including `Super Admin` — browsing another member's personal `peer_blocks` list is **DENIED** by design. Every leader is also a `User` in the Member App and may manage *only their own* personal blocks when using the Member App.

| Role (`UserRoleResolverService` / `roles.key`) | View activity *(Others' Block Lists)* | Create record *(Block Peer)* | Edit own | Edit others | Submit for review | Verify | Approve | Reject | Assign | Manage *(Global Account Suspension — Separate from `peer_blocks`)* | Delete *(Unblock)* | View aggregated reports |
|---|---|---|---|---|---|---|---|---|---|---|---|---|
| **Super Admin** (`superAdmin` / `global_admin`) | DENIED — No permission under the verified backend authorization model. *(Strict Member Privacy Boundary — cannot browse personal `peer_blocks`)* | Allowed (**Own** personal blocks in Member App only) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed *only* for platform account status (`PATCH /admin/peers/{id}/status`), **NEVER** personal `peer_blocks` | Allowed (**Own** blocks only) | DENIED — No permission under the verified backend authorization model. |
| **Country Director** (`countryDirector` / `eed`) | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | DENIED — No permission under the verified backend authorization model. |
| **DED** (`districtExecDirector` / `ded`) | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | DENIED — No permission under the verified backend authorization model. |
| **Industry Director** (`industryDirector` / `industry_director`) | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | DENIED — No permission under the verified backend authorization model. |
| **Circle Founder** (`circleFounder` / `circle_founder`) | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | DENIED — No permission under the verified backend authorization model. |
| **Circle Director** (`circleDirector` / `circle_director`) | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | DENIED — No permission under the verified backend authorization model. |
| **Chair / Committee Chairs** (`chairBusinessGrowth`, `chairMembership`, `chairEventsPrograms`, `circleChair`) | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | DENIED — No permission under the verified backend authorization model. |
| **Vice Chair** (`viceChair` / `vice_chair`) | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | DENIED — No permission under the verified backend authorization model. |
| **Secretary** (`secretary`) | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | DENIED — No permission under the verified backend authorization model. |
| **Committee Leader** (`committee_leader`) | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed (Own Member App only) | DENIED — No permission under the verified backend authorization model. |
| **Peer Member** (`member`) | Allowed (**Strictly Own** `GET /api/v1/blocked-peers` only) | Allowed (`POST /api/v1/peers/{user}/block`) | N/A | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | DENIED — No permission under the verified backend authorization model. | Allowed (`DELETE /api/v1/peers/{user}/block` on own blocks) | DENIED — No permission under the verified backend authorization model. |

---

## 9. Existing Implementation Summary, Gaps & Minimum-Change Enhancement Recommendations

| # | Audit Dimension | Verified Assessment |
|---|---|---|
| **1** | **Member App Status (`unity_app`)** | `Fully Implemented` (`BlockedUsersScreen`, profile/chat block actions, and Speed Networking peer exclusions). |
| **2** | **Leadership App Status (`LeaderApp`)** | `Not Required` — Correctly excluded from `LeaderApp`. |
| **3** | **Admin Web & Blade Status** | `Not Required` — Correctly excluded from `unity-admin-web` (Admins manage global user account `status` in `UsersPage.tsx`, not private peer-to-peer blocks). |
| **4** | **Backend & Database Status** | `PeerBlockController`, `PeerBlockService`, `peer_blocks`, and `speed_networking_blocked_peers` are clean, UUID-keyed, and self-scoped. |
| **5** | **Workbook vs Codebase Discrepancies** | Codebase exceeds workbook by adding `speed_networking_blocked_peers` so peers can discreetly avoid 1-on-1 video pairing without full platform blocking. |
| **6** | **Security, RBAC & Scope Vulnerabilities** | **1. (`P2` Privacy Gap)** `PeerBlockController@status` (`GET /api/v1/peers/{user}/block-status`, `L87-91`) explicitly returns `'has_blocked_me' => $hasBlockedMe`, allowing a curious user to query the API and confirm that another peer blocked them (consider returning only `cannot_interact` + `is_blocked_by_me`). **2. (`P2`)** `IntroVideoController@index` and `ProfileController@getViews` should apply `PeerBlockService` mutual exclusion consistently. |
| **7** | **Notification & Audit Trail Gaps** | Intentionally silent (zero notifications on block/unblock — preserve this behavior!). |
| **8** | **Leadership App Disposition** | **`Not Required` (Strict Prohibition)**: Do **NOT** add Blocked Users screens, counters, or Member 360° tabs to the Leadership App for any role, including Super Admin. |
| **9** | **Minimum-Change Enhancement Plan** | 1. Keep Activity 29 100% out of `LeaderApp` and `unity-admin-web`.<br>2. Apply `PeerBlockService` filtering to `IntroVideoController@index` and `ProfileController@getViews` (`ProfileController.php:726`) so blocked peers never appear in Shorts or Profile Views.<br>3. Remove dead legacy constants `blockedUsers = '/blocked-users'` in `unity_app/lib/core/constants/api_endpoints.dart:307` in favor of `/blocked-peers`. |
| **10** | **Open Business Decisions** | None required — privacy isolation of `peer_blocks` is an established platform safety boundary. |
