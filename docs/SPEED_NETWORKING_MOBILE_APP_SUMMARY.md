# Speed Networking Mobile App (Peer App) Architecture & Frontend Summary

This document summarizes the complete client-side architecture, UI flows, state machine, and API integration contracts for the **Peer App (Flutter)** Speed Networking feature.

---

## 1. Feature Overview & User Journey

The Speed Networking feature allows peers to connect 1-on-1 via high-quality video rotations during scheduled or instant networking events.

### Key Capabilities:
1. **Pre-Event Countdown & Attendance Scaling**:
   - Upcoming sessions display a live countdown timer (`Starts in 01h 24m 15s`) on the Home Feed banner and Lobby screen.
   - Live registered attendee counter (`👥 48 Registered Peers`) to show momentum and pool size.
2. **Pre-Registration (Free & Paid)**:
   - Free events: One-tap reservation.
   - Paid events: Native Razorpay pre-payment gateway checkout. Once paid, the ticket is stored, unlocking direct 1-tap join when the event goes live without re-payment.
3. **Dynamic Action Button Engine**:
   - The backend delivers a single source of truth for the primary action button (`action_type`, `label`, `is_enabled`, `helper_text`). The mobile client cleanly maps this state to its UI without hardcoded conditional sprawl.
4. **Late Joiner Support**:
   - Attendees who arrive after the session starts can join on the fly and immediately enter the matchmaking rotation pool.
5. **Post-Round Dynamic Options Screen**:
   - At the conclusion of each 1-on-1 round, instead of forcing users directly into the next call, the app provides 3 choices:
     - ⚡ **Find Next Peer Now**: Immediate entry into the queue.
     - ☕ **Take a Break**: Option for 5-Minute or 10-Minute rest.
     - 🚪 **Leave Session & View Summary**: Exit and view all peers met.
6. **Break Mode with Real-Time Incoming Match Invitations**:
   - During break mode, the background heartbeat continues.
   - If an unmatched peer in the active queue is seeking a connection, an interactive banner appears showing their photo, name, title, and company.
   - The resting user can tap **`Accept & Connect`** (ends break immediately) or **`Stay on Break`**.
7. **Auto-Start & Live Push Notifications**:
   - When the scheduled start time arrives (or when an admin triggers manual start early), the event automatically shifts to live.
   - All registered peers receive a high-priority push notification (`⚡ Speed Networking is Live! {title} has started! Join now to meet your first peer 1-on-1.`).
   - Tapping the notification opens the Speed Networking lobby directly with the `⚡ Join Live Session Now` button unlocked.

---

## 2. Participant State Machine

```
                      ┌──────────────────────┐
                      │      SCHEDULED       │
                      └──────────┬───────────┘
                                 │
                     [Pre-Register / Pre-Pay]
                                 │
                      ┌──────────▼───────────┐
                      │      REGISTERED      │
                      └──────────┬───────────┘
                                 │
                      [Event Goes Live / Join]
                                 │
                      ┌──────────▼───────────┐
         ┌───────────►│  SEARCHING / LOBBY   │◄──────────┐
         │            └──────────┬───────────┘           │
         │                       │                       │
         │                [Match Found]                  │
         │                       │                       │
         │            ┌──────────▼───────────┐           │
         │            │       IN_CALL        │           │
         │            │  (1-on-1 Agora Video)│           │
         │            └──────────┬───────────┘           │
         │                       │                       │
         │                 [Round Ends]                  │
         │                       │                       │
         │            ┌──────────▼───────────┐           │
         │            │   ROUND_FINISHED     │           │
         │            │  (Choices Screen)    │           │
         │            └────┬────────────┬────┘           │
         │                 │            │                │
[⚡ Find Next Peer]        │     [☕ Take a Break]       │
         │                 │            │                │
         └─────────────────┘  ┌─────────▼──────────┐     │
                              │     ON_BREAK       │     │
                              │ (Break View Ticker)│     │
                              └─────────┬──────────┘     │
                                        │                │
                          [Timer Ends / Accept Peer]     │
                                        │                │
                                        └────────────────┘
```

---

## 3. Dynamic Action Button Engine

The `active-event` endpoint returns an `action_button` object that dictates the button state:

| `action_type` | Button Label | Button Enabled? | User State / Event State | Helper Text |
|---|---|---|---|---|
| `REGISTER` | Register for Free | ✅ Enabled | Scheduled + Free Event + Not Registered | Free registration reserves your spot |
| `PAY_AND_REGISTER` | Register & Pre-Pay ₹299 | ✅ Enabled | Scheduled + Paid Event + Not Registered | Pre-pay now for instant 1-tap join when live |
| `REGISTERED` | ✓ Registered | ❌ Disabled | Scheduled + Registered / Paid | Direct join will unlock when event goes live at {time} |
| `DIRECT_JOIN` | ⚡ Join Live Session Now | ✅ Enabled | Live + Pre-Registered / Paid | Tap to enter matchmaking rotation |
| `JOIN_SPEED_NETWORKING`| ⚡ Join Live Session | ✅ Enabled | Live + Free Event + Not Pre-Registered | Tap to start meeting peers right away |
| `PAY_AND_JOIN` | Pay ₹299 & Join Live | ✅ Enabled | Live + Paid Event + Not Pre-Registered | Complete quick payment to enter live session |
| `COMPLETED` | Event Completed | ❌ Disabled | Event Completed | This speed networking session has ended |

---

## 4. Mobile Screen & Widget Implementation

### 4.1 Home Feed & Lobby
* **`SpeedNetworkingBannerCard`** (`lib/features/events/presentation/widgets/speed_networking_banner_card.dart`):
  * Appears on the home feed.
  * Shows `SCHEDULED` badge with live countdown when upcoming.
  * Shows `LIVE NOW` pulsing badge with direct join when session is running.
  * Displays attendee scaling counter (`👥 48 Registered Peers`).
* **`SpeedNetworkingLobbyCard`** (`lib/features/speed_networking/presentation/widgets/speed_networking_lobby_card.dart`):
  * Displays event details, timer countdown, rounds planned, circle restrictions, and registered peer count.
* **`SpeedNetworkingLobbyScreen`** (`lib/features/speed_networking/presentation/screens/speed_networking_lobby_screen.dart`):
  * Hosts pre-registration logic and payment handlers.
  * On payment success, automatically refreshes event status to flip state to `REGISTERED`.

### 4.2 Post-Round Options View
* **`SpeedNetworkingRoundFinishedView`** (`lib/features/speed_networking/presentation/widgets/speed_networking_round_finished_view.dart`):
  * Rendered when a video call terminates (timer expires or peer disconnects).
  * Shows the peer just met (Avatar, Name, Company) with quick options:
    1. **⚡ Find Next Peer Now**: Dispatches `SpeedNetworkingNextMatchRequested` event to BLoC.
    2. **☕ Take a 5-Minute Break**: Dispatches `SpeedNetworkingTakeBreakRequested(breakDurationSeconds: 300)`.
    3. **☕ Take a 10-Minute Break**: Dispatches `SpeedNetworkingTakeBreakRequested(breakDurationSeconds: 600)`.
    4. **🚪 Leave & View Session Summary**: Navigates to summary screen.

### 4.3 Break View with Real-Time Incoming Match
* **`SpeedNetworkingBreakView`** (`lib/features/speed_networking/presentation/widgets/speed_networking_break_view.dart`):
  * Circular animated countdown showing remaining break time (`04:32 remaining`).
  * "Ready early? Find Next Peer" button to cancel break anytime.
  * **Incoming Match Banner**: If `incoming_match_request` is received via heartbeat:
    * Displays peer card (Photo, Name, Title, Company).
    * `Accept & Connect`: Connects directly to the Agora channel.
    * `Stay on Break`: Rejects the match and continues resting.

---

## 5. API Contracts for Mobile App

### 5.1 Get Active Event & Action State
* **`GET /api/v1/speed-networking/active-event`**
* **Headers**: `Authorization: Bearer <TOKEN>`
* **Response (200 OK)**:
```json
{
  "status": "success",
  "data": {
    "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
    "title": "Entrepreneurs Speed Networking ⚡",
    "description": "Fast-paced 1-on-1 networking for tech innovators.",
    "status": "scheduled",
    "scheduled_start_time": "2026-11-05T18:30:00.000000Z",
    "actual_start_time": null,
    "round_duration_seconds": 180,
    "break_duration_seconds": 30,
    "total_rounds": 8,
    "current_round": 1,
    "participants_count": 48,
    "is_registered": false,
    "is_paid": true,
    "is_free": false,
    "ticket_price": 299.0,
    "currency": "INR",
    "user_has_paid": false,
    "can_join": true,
    "circle_restricted": false,
    "circle_name": null,
    "action_button": {
      "action_type": "PAY_AND_REGISTER",
      "label": "Register & Pre-Pay ₹299",
      "is_enabled": true,
      "helper_text": "Pre-pay now for instant 1-tap join when the event goes live"
    }
  }
}
```

---

### 5.2 Pre-Registration
* **`POST /api/v1/speed-networking/events/{id}/register`**
* **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
* **Response for Free Event (200 OK)**:
```json
{
  "status": "success",
  "message": "Successfully registered for the event.",
  "data": {
    "registered": true,
    "requires_payment": false
  }
}
```
* **Response for Paid Event (200 OK - Triggers Razorpay Checkout)**:
```json
{
  "status": "success",
  "message": "Payment order generated for pre-registration.",
  "data": {
    "registered": false,
    "requires_payment": true,
    "order_id": "order_M5vX8L9k2P1aB",
    "amount": 29900,
    "currency": "INR",
    "key_id": "rzp_live_xxxxxxxx"
  }
}
```

---

### 5.3 Join Live Session
* **`POST /api/v1/speed-networking/events/{id}/join`**
* **Headers**: `Authorization: Bearer <TOKEN>`
* **Response (Immediate Match)**:
```json
{
  "status": "success",
  "is_waiting": false,
  "match": {
    "channel_id": "sn_channel_c86f78e4_1",
    "agora_token": "006xxxxxxxxxxxxxxxxxxxxxxxxxxxx",
    "peer": {
      "id": "893c5d6e-1122-4455-8899-aabbccddeeff",
      "name": "Sarah Jenkins",
      "designation": "Head of Product",
      "company": "ScaleUp Labs",
      "avatar_url": "https://cdn.peers.com/avatars/sarah.jpg",
      "city": "Bengaluru",
      "bio": "Building AI workflow products."
    }
  }
}
```
* **Response (Queueing / Waiting for Next Peer)**:
```json
{
  "status": "success",
  "is_waiting": true,
  "message": "Waiting for next available peer..."
}
```

---

### 5.4 Heartbeat with Break Mode & Incoming Peer Pairing
* **`POST /api/v1/speed-networking/events/{id}/heartbeat`**
* **Headers**: `Authorization: Bearer <TOKEN>`, `Content-Type: application/json`
* **Request Body**:
```json
{
  "user_status": "on_break",
  "break_remaining_seconds": 240
}
```
* **Response when an Available Peer Wants to Connect**:
```json
{
  "status": "success",
  "active_attendees_count": 52,
  "has_active_match": false,
  "incoming_match_request": {
    "channel_id": "sn_channel_9988_2",
    "agora_token": "006yyyyyyyyyyyyyyyyyyyyyyyyyyyy",
    "peer": {
      "id": "11223344-5566-7788-9900-aabbccddeeff",
      "name": "Vikram Patel",
      "designation": "Angel Investor & Partner",
      "company": "Venture Horizon",
      "avatar_url": "https://cdn.peers.com/avatars/vikram.jpg",
      "city": "Mumbai",
      "bio": "Investing in early-stage B2B SaaS and consumer tech."
    }
  }
}
```

---

### 5.5 Next Match Request
* **`POST /api/v1/speed-networking/events/{id}/next-match`**
* **Headers**: `Authorization: Bearer <TOKEN>`
* Called when user taps **⚡ Find Next Peer Now** on the post-round screen or when breaking early.
* Returns match payload or queueing status.

---

### 5.6 Leave Session & Session Summary
* **`POST /api/v1/speed-networking/events/{id}/leave`**
* **Headers**: `Authorization: Bearer <TOKEN>`
* Marks participant as `left`, terminates any active call, and removes user from the matchmaking queue.
* **`GET /api/v1/speed-networking/events/{id}/summary`**
* Returns full list of all peers met during this session with their complete profiles for easy post-session follow-up.

---

### 5.7 Past Sessions History API
* **`GET /api/v1/speed-networking/history`**
* **Headers**: `Authorization: Bearer <TOKEN>`
* **Query Parameters**:
  - `page` (optional): Page number (default: `1`)
  - `per_page` (optional): Items per page (default: `15`)
* **Behavior**:
  - Returns all past Speed Networking events the authenticated user participated in or joined.
  - Each item includes the `total_joined` attendee count and the **exact same `summary`** payload shown at the end of the live meeting (all peers met with full profiles, rounds completed, talk time, life impact, and coins).
* **Response (200 OK)**:
```json
{
  "status": "success",
  "success": true,
  "message": "Speed networking history retrieved successfully.",
  "data": [
    {
      "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
      "event_id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
      "title": "Entrepreneurs Speed Networking ⚡",
      "description": "Fast-paced 1-on-1 networking for tech innovators.",
      "banner_url": "https://cdn.peersunity.com/banners/sn_banner.jpg",
      "status": "completed",
      "scheduled_start_time": "2026-11-05T18:30:00.000000Z",
      "scheduled_end_time": "2026-11-05T19:30:00.000000Z",
      "actual_start_time": "2026-11-05T18:30:12.000000Z",
      "actual_end_time": "2026-11-05T19:28:44.000000Z",
      "round_duration_seconds": 180,
      "break_duration_seconds": 30,
      "total_rounds": 8,
      "current_round": 8,
      "total_joined": 48,
      "participants_count": 48,
      "peers_met_count": 6,
      "ticket_price": 299.0,
      "is_paid": true,
      "currency": "INR",
      "summary": {
        "completed": true,
        "has_met_all_peers": false,
        "message": "All speed networking rounds have concluded. Great conversations made!",
        "total_rounds": 8,
        "rounds_completed": 6,
        "total_interactions_count": 6,
        "life_impact": {
          "earned": 30,
          "total": 125,
          "balance_after": 125
        },
        "life_impact_earned": 30,
        "coins": {
          "earned": 60,
          "balance_after": 340
        },
        "coins_earned": 60,
        "interacted_peers": [
          {
            "id": "11223344-5566-7788-9900-aabbccddeeff",
            "name": "Sarah Jenkins",
            "first_name": "Sarah",
            "last_name": "Jenkins",
            "display_name": "Sarah Jenkins",
            "email": "sarah@scaleup.io",
            "phone": "+91 98765 43210",
            "avatar_url": "https://cdn.peers.com/avatars/sarah.jpg",
            "headline": "Head of Product @ ScaleUp Labs",
            "company": "ScaleUp Labs",
            "designation": "Head of Product",
            "city": "Bengaluru",
            "state": "Karnataka",
            "bio": "Building AI workflow products.",
            "mutual_connections_count": 4,
            "matched_at": "2026-11-05T18:32:00.000000Z",
            "p2p_meeting_id": "p2p_meeting_uuid_1",
            "connection_status": "none"
          }
        ]
      }
    }
  ],
  "pagination": {
    "total": 1,
    "per_page": 15,
    "current_page": 1,
    "last_page": 1
  }
}
```

---

## 6. End Time & Feed Visibility Rules

1. **Auto-Hiding Ended Sessions**:
   - Every speed networking event now supports an explicit `scheduled_end_time` (e.g. `2026-11-05T19:30:00Z`).
   - The active event endpoint (`GET /api/v1/speed-networking/active-event`) automatically filters out any event whose `scheduled_end_time` has elapsed or whose `actual_end_time` has been recorded.
   - When an event ends, `active-event` returns `data: null`, which automatically dismisses the live banner from the Home Feed and Lobby screens.
2. **Revisiting Past Sessions Later**:
   - Attendees can revisit any completed session anytime via the **History Screen** (`GET /api/v1/speed-networking/history`).
   - Clicking on any past event in history loads the exact same meeting conclusion view, displaying all peers met, their profile contact cards, badges earned, and rewards.
   - The standalone summary endpoint (`GET /api/v1/speed-networking/events/{eventId}/summary`) remains permanently accessible for any past event.
