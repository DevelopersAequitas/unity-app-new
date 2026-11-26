# Peers Global — Speed Collaboration Flutter Frontend Change Specification

**Scope:** Required & optional updates to the Flutter app (`D:\office project\peers-unity\unity_app`) to align with the updated Speed Collaboration backend flow, dynamic admin skip limit (`max_skip_limit`), and preview timeout auto-connect.

---

## 1. Production Backward Compatibility (Old App Builds)

Existing mobile users who have **not** updated their Flutter app will **not crash or fail to join** live Speed Collaboration events after backend deployment:

| Flow / Event | Old Un-Updated Flutter Behavior | Impact & Safety |
| :--- | :--- | :--- |
| **Joining / Registration / Payment / Agora Video** | Calls `/active-event`, `/join`, `/next-match`, `/heartbeat`, `/complete-p2p`, `/summary`. | **Zero breakage.** All legacy JSON keys (`status`, `match_status`, `is_waiting`, `channel_id`, `agora_token`, `peer`, `active_attendees_count`, etc.) remain unchanged; new fields (`is_recycled_skip`, `skip_count`, `max_skip_limit`, `auto_connect_on_preview_timeout`, `is_paused`) are purely additive. |
| **Manual Two-Sided Connect & Skip -> Find Next Peer** | Both tap Connect -> `in_call`. Skip -> `SpeedNetworkingNextPeerRequired`. | **Works identically.** |
| **Next Peer (>2m) during Live Call** | `_onNextPeerDuringCall` finalizes duration and calls `_fetchNextMatch(emit, fromManualFindNext: false)` directly. | **Works identically.** |
| **Preview 15s Timer Timeout Auto-Connect** | In un-updated builds, `_onMatchPreviewTimerTick` locally exits preview at 15s (`suggestionExpired`) and `_onHeartbeat` blocks auto-entering call unless `latest.isAcceptedByMe == true`. | Does not crash. However, **until the Flutter update below ships**, old clients will go to "Find Next Peer" at 15s unless they tapped Connect, and local `_sessionSkippedPeerIds` will block backend-recycled skipped peers. |
| **Block -> Immediate Search** | In un-updated builds, `_onBlockAndSkipPeer` navigates to `SpeedNetworkingNextPeerRequired(reason: blocked)`. Server sets blocker status to `STATUS_SEARCHING` so tapping "Find Next Peer" works normally. | Safe; update below makes Block go straight to Finding/Waiting screen. |

---

## 2. Exact Flutter Changes Required (`D:\office project\peers-unity\unity_app`)

### Change 1 — Auto-Connect When Preview 15s Timer Ends (Unless Skipped or Blocked)
**File:** `lib/features/speed_networking/presentation/bloc/speed_networking_bloc.dart`

1. **`_onMatchPreviewTimerTick` (lines 933–963):**
   - **Current:** When `cur.secondsRemaining <= 1`, it stops timers, adds `cur.match.channelId` to `_lockedExcludedChannelIds`, leaves Agora, and emits `SpeedNetworkingNextPeerRequired(reason: NextPeerRequiredReason.suggestionExpired)`.
   - **Required Change:** When the 15s preview countdown finishes without the user pressing Skip or Block:
     - Do **not** add `channelId` to `_lockedExcludedChannelIds` and do **not** emit `SpeedNetworkingNextPeerRequired`.
     - Instead, trigger server handshake reconciliation: call `submitMatchActionUseCase(eventId: _eventId, action: 'connect', peerId: cur.peer.id, channelId: cur.match.channelId, matchId: cur.match.matchId)` (or poll `getMatchStateUseCase` / `heartbeat`) so the client receives the server-authoritative `match_status == 'in_call'` payload and invokes `_connectToCall(...)`.
     - Only emit `SpeedNetworkingNextPeerRequired` if the server explicitly reports `match_status` is `'skipped'`, `'blocked'`, or `'expired'`.

2. **`_onHeartbeat` in `SpeedNetworkingMatchPreview` (lines 1202–1220):**
   - **Current:**
     ```dart
     if (latest.isAcceptedByMe &&
         (m.bothAccepted || m.matchStatus == 'in_call' || m.matchStatus == 'connected'))
     ```
   - **Required Change:** Remove the `latest.isAcceptedByMe` prerequisite when transitioning to call:
     ```dart
     if (m.matchStatus == 'in_call' ||
         m.matchStatus == 'connected' ||
         m.bothAccepted) {
       _timers.stopPreviewCountdown();
       await _connectToCall(m, emit, remoteUid: _currentRemoteUid);
       return;
     }
     ```
     *Why:* When Peer B presses Connect and User A does nothing until the 15s preview timer expires, the backend automatically sets `match_status = 'in_call'` and `both_accepted = true`. Without removing `latest.isAcceptedByMe`, User A's client would ignore the backend's auto-connected call.

---

### Change 2 — Block Navigates Directly to Finding/Waiting Screen
**File:** `lib/features/speed_networking/presentation/bloc/speed_networking_bloc.dart`

- **`_onBlockAndSkipPeer` (lines 965–1039):**
  - **Current:** Ends with `emit(SpeedNetworkingNextPeerRequired(eventId: _eventId, reason: NextPeerRequiredReason.blocked, previousPeerName: e.peer.name));` (lines 1032–1038).
  - **Required Change:** Replace that final `emit(SpeedNetworkingNextPeerRequired(...))` with immediate transition to the Finding/Waiting flow:
    ```dart
    await _fetchNextMatch(emit, fromManualFindNext: false);
    ```
    (Keep `_blockedPeerIds.add(e.peer.id)` and Agora cleanup intact; if `_fetchNextMatch` returns `isWaiting == true`, the BLoC automatically emits `SpeedNetworkingWaitingRoom`, and if a new match is immediately available, shows the next peer preview.)

---

### Change 3 — Allow End-of-Pool Recycled Skipped Peers (Backend Enforces `max_skip_limit`)
**Files:**
- `lib/features/speed_networking/data/models/speed_networking_match_model.dart`
- `lib/features/speed_networking/domain/entities/speed_networking_match_entity.dart` (if mapped there)
- `lib/features/speed_networking/presentation/bloc/speed_networking_bloc.dart`
- `lib/features/speed_networking/data/datasources/speed_networking_remote_datasource.dart`

1. **Parse New Backend Match Fields (`speed_networking_match_model.dart`):**
   - Add fields to `SpeedNetworkingMatchModel.fromJson`:
     - `isRecycledSkip`: `bool` (`data['is_recycled_skip'] == true`)
     - `skipCount`: `int` (`_parseInt(data['skip_count']) ?? 0`)
     - `maxSkipLimit`: `int` (`_parseInt(data['max_skip_limit']) ?? 2`)
     - `nextStep`: `String?` (`(data['next_step'] ?? json['next_step'])?.toString()`)

2. **Bypass Local Skip Suppression When Backend Recycles a Peer (`speed_networking_bloc.dart` lines 159–182):**
   - **Current (`_isMatchExcluded`):**
     ```dart
     if (_blockedPeerIds.contains(peerId) ||
         _sessionSkippedPeerIds.contains(peerId)) {
       return true;
     }
     ```
   - **Required Change:** Keep `_blockedPeerIds` permanently rejected on the client, **but allow skipped peers when backend explicitly sends them back (`m.isRecycledSkip == true`)** — or remove permanent client-side blocking in `_sessionSkippedPeerIds` and let the backend authoritatively enforce the dynamic `max_skip_limit`:
     ```dart
     if (_blockedPeerIds.contains(peerId)) {
       return true;
     }
     if (_sessionSkippedPeerIds.contains(peerId) && !m.isRecycledSkip) {
       return true;
     }
     ```
   - Also in `_onDeclineMatchedPeer` / `_onAcceptMatchedPeer` / `_handleMatch`: when connecting to a match with `m.isRecycledSkip == true`, remove `peerId` from `_sessionSkippedPeerIds` so `_finalizeActiveCallDuration` and `_isMatchExcluded` do not drop the call.

3. **Do Not Filter Recycled Peers in Datasource Summary Cache (`speed_networking_remote_datasource.dart` lines 420–476):**
   - **Current:** `_markPeerSkippedForEvent()` persists skipped IDs into SharedPreferences (`_skippedPeersByEventCacheKey`), and `_isPeerMapSkippedOrInvalid()` hides skipped IDs from `interacted_peers` unless they are in `_connectedPeersByEventCacheKey`.
   - Once a recycled skipped peer connects into a live call, ensure `_markPeerConnectedForEvent(eventId, peerId)` is invoked so they appear cleanly in session summary / P2P if talked to.

---

### Change 4 — Optional UX Polish on Preview Card
**File:** `lib/features/speed_networking/presentation/widgets/speed_networking_match_preview_view.dart`

- Update helper label near the Connect / Skip / Block actions to reflect auto-connect semantics (e.g., *"Auto-connects in Xs unless skipped"* when `!widget.isAcceptedByMe` and *"Waiting for peer... auto-connects in Xs"* when waiting).
- Handle `is_paused == true` on Waiting Room (`heartbeat` / `join` / `next-match`) by displaying a subtle `"Session paused by host — matchmaking will resume shortly"` status banner.

---

## 3. Flows Already Working in Current Flutter Code (No Changes Needed)
- **Skip during Preview -> "Find Next Peer" Screen:** `_onDeclineMatchedPeer` (`speed_networking_bloc.dart` L863–931) submits `action: 'skip'` and emits `SpeedNetworkingNextPeerRequired(reason: NextPeerRequiredReason.skipped)`.
- **Early Next Peer (> 2 mins talked) during Call -> Direct Search:** `_onNextPeerDuringCall` (`speed_networking_bloc.dart` L1292–1312) records call duration, leaves Agora, and immediately calls `_fetchNextMatch(emit, fromManualFindNext: false)` without stopping on the "Find Next Peer" button screen.
- **2-Minute (`120s`) P2P & Rewards Threshold:** `PeerProfileEntity.minQualifyingCallSeconds` (`120s`) matches backend `SpeedNetworkingMatch::MIN_QUALIFYING_DURATION_SECONDS`.
