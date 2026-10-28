# Speed Networking Admin Panel API Specification (Doc 1 of 2)
## Event Configuration, Customization & Session Lifecycle

This document provides the complete API specification for the **Node.js Admin Panel** team to build the Speed Networking administration portal. Every setting (timers, pricing, rounds, circle gating, banners, start dates) is fully customizable via REST APIs.

---

## 1. Core Architecture & Settings

Every Speed Networking event can be customized with:
1. **Title & Description**: Custom session branding (e.g. *"Entrepreneurs Speed Networking ⚡"*).
2. **Banner / Thumbnail**: Header image URL for home feeds and lobby screens.
3. **Round Duration (`round_duration_seconds`)**: Duration for each 1-on-1 video call round (e.g. 180s, 300s, 600s). Default: `180`.
4. **Break Duration (`break_duration_seconds`)**: Intermission duration between rounds (e.g. 20s, 60s). Default: `20`.
5. **Total Rounds (`total_rounds`)**: Number of rotation rounds planned (e.g. 5, 10, 15).
6. **Scheduled Start Time (`scheduled_start_time`)**: ISO 8601 timestamp. The mobile app automatically displays a real-time countdown ticker until this start time.
7. **Monetization & Pricing**:
   - `is_free`: `true` for free events, `false` for paid events.
   - `ticket_price`: Entry price in INR (e.g. `299.00`).
   - `currency`: Default `'INR'`.
   - When paid, pre-registration and live join enforce native Razorpay checkout.
8. **Circle Gating / Exclusivity**:
   - `circle_id`: UUID of a circle to make the event exclusive to members of that circle.
   - `circle_restricted`: `true` to restrict participation only to active circle members.
9. **Event Status Lifecycle**:
   - `'draft'`: Unpublished, under preparation.
   - `'scheduled'`: Published, visible in app home feed with countdown ticker and pre-registration active.
   - `'lobby_open'`: Lobby unlocked before the live session starts.
   - `'in_progress'`: Live video rounds running.
   - `'paused'`: Temporarily paused by admin (timers held).
   - `'completed'`: Session ended, P2P meetings and creatives processed.

---

## 2. API Endpoints Overview

All admin routes require standard Admin Bearer token authorization:
`Authorization: Bearer <ADMIN_AUTH_TOKEN>`

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/admin/speed-networking/events` | List all speed networking events with pagination & filters |
| `POST` | `/api/v1/admin/speed-networking/events` | Create a new customizable speed networking event |
| `GET` | `/api/v1/admin/speed-networking/events/{id}` | Get event details, configurations, pricing & live statistics |
| `PUT` | `/api/v1/admin/speed-networking/events/{id}` | Update any event parameters, pricing, circle, or status |
| `DELETE` | `/api/v1/admin/speed-networking/events/{id}` | Soft-delete an event and clean up related records |
| `POST` | `/api/v1/admin/speed-networking/events/{id}/start` | Start live event & trigger Round 1 matchmaking pairings |
| `POST` | `/api/v1/admin/speed-networking/events/{id}/next-round` | Advance to next round & trigger pairings |
| `POST` | `/api/v1/admin/speed-networking/events/{id}/pause` | Pause active round timers |
| `POST` | `/api/v1/admin/speed-networking/events/{id}/resume` | Resume paused session |
| `POST` | `/api/v1/admin/speed-networking/events/{id}/rematch` | Force rematch/pair waiting attendees for current round |
| `POST` | `/api/v1/admin/speed-networking/events/{id}/end` | End event, complete all active calls, trigger P2P generation |

---

## 3. Detailed Endpoint Contracts

### 3.1 List Events
* **`GET /api/v1/admin/speed-networking/events`**
* **Query Parameters**:
  - `status` (optional): `draft` | `scheduled` | `lobby_open` | `in_progress` | `paused` | `completed`
  - `search` (optional): Case-insensitive search on title
  - `page` (optional): Page number (default: `1`)
  - `per_page` (optional): Items per page (default: `15`)

* **Response (200 OK)**:
```json
{
  "success": true,
  "data": [
    {
      "id": "e8a9364b-a7eb-44c3-b461-13dafcce5ff4",
      "title": "Entrepreneurs Speed Networking ⚡",
      "description": "Connect 1-on-1 with founders and business owners.",
      "banner_url": "https://cdn.peersunity.com/banners/speed_net_1.jpg",
      "status": "scheduled",
      "round_duration_seconds": 180,
      "break_duration_seconds": 20,
      "total_rounds": 5,
      "current_round": 0,
      "scheduled_start_time": "2026-10-30T18:00:00.000000Z",
      "actual_start_time": null,
      "ticket_price": 299.0,
      "is_paid": true,
      "currency": "INR",
      "circle_id": null,
      "circle_restricted": false,
      "participants_count": 48,
      "active_participants_count": 48,
      "matches_count": 0,
      "created_at": "2026-10-28T14:30:00.000000Z"
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

### 3.2 Create Speed Networking Event
* **`POST /api/v1/admin/speed-networking/events`**
* **Request Body**:
```json
{
  "title": "Fintech & SaaS Founders Networking ⚡",
  "description": "Fast-paced 1-on-1 networking for tech innovators.",
  "banner_url": "https://cdn.peersunity.com/banners/fintech_speed.jpg",
  "scheduled_start_time": "2026-11-05T18:30:00Z",
  "round_duration_seconds": 180,
  "break_duration_seconds": 30,
  "total_rounds": 8,
  "ticket_price": 199.00,
  "is_paid": true,
  "currency": "INR",
  "circle_id": null,
  "circle_restricted": false,
  "status": "scheduled"
}
```

* **Validation Rules**:
  - `title`: required, string, max 255
  - `description`: nullable, string
  - `banner_url`: nullable, string, max 500
  - `scheduled_start_time`: nullable, valid date/time
  - `round_duration_seconds`: nullable, integer (min: 10, max: 3600)
  - `break_duration_seconds`: nullable, integer (min: 5, max: 1800)
  - `total_rounds`: nullable, integer (min: 1, max: 100)
  - `ticket_price`: nullable, numeric (min: 0)
  - `is_paid`: nullable, boolean
  - `is_free`: nullable, boolean (sets price to 0 and is_paid to false)
  - `circle_id`: nullable, UUID of an existing Circle
  - `circle_restricted`: nullable, boolean
  - `status`: nullable, one of `draft`, `scheduled`, `lobby_open`, `in_progress`, `paused`, `completed`

* **Response (201 Created)**:
```json
{
  "success": true,
  "message": "Speed networking event created successfully.",
  "data": {
    "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
    "title": "Fintech & SaaS Founders Networking ⚡",
    "description": "Fast-paced 1-on-1 networking for tech innovators.",
    "banner_url": "https://cdn.peersunity.com/banners/fintech_speed.jpg",
    "status": "scheduled",
    "round_duration_seconds": 180,
    "break_duration_seconds": 30,
    "total_rounds": 8,
    "current_round": 0,
    "scheduled_start_time": "2026-11-05T18:30:00.000000Z",
    "ticket_price": 199.0,
    "is_paid": true,
    "currency": "INR",
    "circle_id": null,
    "circle_restricted": false
  }
}
```

---

### 3.3 Get Single Event Details & Live Statistics
* **`GET /api/v1/admin/speed-networking/events/{id}`**
* **Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "event": {
      "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
      "title": "Fintech & SaaS Founders Networking ⚡",
      "description": "Fast-paced 1-on-1 networking for tech innovators.",
      "banner_url": "https://cdn.peersunity.com/banners/fintech_speed.jpg",
      "status": "in_progress",
      "round_duration_seconds": 180,
      "break_duration_seconds": 30,
      "total_rounds": 8,
      "current_round": 2,
      "scheduled_start_time": "2026-11-05T18:30:00.000000Z",
      "actual_start_time": "2026-11-05T18:30:15.000000Z",
      "ticket_price": 199.0,
      "is_paid": true,
      "currency": "INR",
      "circle_id": null,
      "circle_restricted": false,
      "circle": null
    },
    "stats": {
      "total_participants": 54,
      "active_participants": 52,
      "total_matches": 50,
      "active_matches": 24,
      "active_tables_count": 24,
      "participants_by_status": {
        "in_call": 48,
        "break": 4,
        "left": 2
      }
    }
  }
}
```

---

### 3.4 Update Event Settings
* **`PUT /api/v1/admin/speed-networking/events/{id}`**
* **Request Body** (all fields optional for partial/full updates):
```json
{
  "title": "Updated Title",
  "round_duration_seconds": 240,
  "break_duration_seconds": 45,
  "total_rounds": 10,
  "ticket_price": 249.00,
  "scheduled_start_time": "2026-11-05T19:00:00Z"
}
```
* **Response (200 OK)**:
```json
{
  "success": true,
  "message": "Speed networking event updated successfully.",
  "data": { ... }
}
```

---

### 3.5 Delete Event
* **`DELETE /api/v1/admin/speed-networking/events/{id}`**
* **Response (200 OK)**:
```json
{
  "success": true,
  "message": "Speed networking event deleted successfully."
}
```

---

## 4. Live Session Execution Controls

Administrators can control the live flow using real-time action endpoints:

### 4.1 Start Event (Manual Start Button & Auto-Start Engine)
* **Manual Start Button**:
  * In the Node Admin Panel, provide a prominent **⚡ Start Event Now** button on the event dashboard.
  * If the organizer wants to start earlier than scheduled, tapping this button calls:
    * **`POST /api/v1/admin/speed-networking/events/{id}/start`**
* **Automatic Scheduled Start**:
  * When `scheduled_start_time` is reached (`scheduled_start_time <= now()`), the system **automatically starts the event** via a 1-minute background scheduler (`speed-networking:auto-start`) or when the active session is queried.
* **Behaviors for Both Manual & Auto-Start**:
  1. Sets `status` to `in_progress`
  2. Sets `actual_start_time` to `now()`
  3. Sets `current_round = 1`
  4. Runs matchmaking algorithm to pair waiting participants into Agora tables
  5. **Dispatches Push & In-App Live Notifications to All Registered Attendees**:
     * Title: `⚡ Speed Networking is Live!`
     * Body: `"{title} has started! Join now to meet your first peer 1-on-1."`
     * Payload: `{"type": "speed_networking_live", "event_id": "{id}", "action": "open_speed_networking"}`
* **Response**:
```json
{
  "success": true,
  "message": "Speed networking event started and Round 1 generated.",
  "data": {
    "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
    "status": "in_progress",
    "current_round": 1
  }
}
```

---

### 4.2 Next Round
* **`POST /api/v1/admin/speed-networking/events/{id}/next-round`**
* **Behavior**:
  - Closes active calls for previous round (`ended_at = now()`)
  - Increments `current_round` by 1
  - If `current_round > total_rounds`, automatically completes event and triggers P2P meetings
  - Otherwise, runs matchmaking for the new round ensuring strict non-repeating pairs
* **Response**:
```json
{
  "success": true,
  "message": "Round advanced successfully.",
  "data": {
    "current_round": 2,
    "status": "in_progress"
  }
}
```

---

### 4.3 Pause Event
* **`POST /api/v1/admin/speed-networking/events/{id}/pause`**
* **Behavior**:
  - Sets `status` to `paused`
  - Client apps hold their remaining round countdown timers
* **Response**:
```json
{
  "success": true,
  "message": "Speed networking event paused.",
  "data": {
    "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
    "status": "paused"
  }
}
```

---

### 4.4 Resume Event
* **`POST /api/v1/admin/speed-networking/events/{id}/resume`**
* **Behavior**:
  - Sets `status` back to `in_progress`
* **Response**:
```json
{
  "success": true,
  "message": "Speed networking event resumed.",
  "data": {
    "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
    "status": "in_progress"
  }
}
```

---

### 4.5 Rematch Waiting Attendees
* **`POST /api/v1/admin/speed-networking/events/{id}/rematch`**
* **Behavior**:
  - Triggers rematching for the current round without advancing round number
  - Pairs any late joiners or attendees who became free from break
* **Response**:
```json
{
  "success": true,
  "message": "Rematch generated for active round.",
  "data": { ...live tables data... }
}
```

---

### 4.6 End Event
* **`POST /api/v1/admin/speed-networking/events/{id}/end`**
* **Behavior**:
  - Sets status to `completed`
  - Completes all active matches
  - Automatically transforms completed 1-on-1 calls into P2P meeting records
  - Generates custom creative meeting posters with user photos and avatars
  - Distributes Life Impact and Coins rewards to participants
* **Response**:
```json
{
  "success": true,
  "message": "Speed networking event ended successfully.",
  "data": {
    "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
    "status": "completed",
    "actual_end_time": "2026-11-05T19:45:00.000000Z"
  }
}
```

---

## 5. Event History & Administrative Summary

### 5.1 Completed Events History
* **`GET /api/v1/admin/speed-networking/history`**
* **Query Parameters**:
  - `search` (optional): Search by title/description
  - `page` (optional): Page number (default: `1`)
  - `per_page` (optional): Items per page (default: `15`)
* **Response (200 OK)**:
```json
{
  "success": true,
  "message": "Speed networking history retrieved successfully.",
  "data": [
    {
      "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
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
      "rounds_conducted": 8,
      "total_joined": 48,
      "participants_count": 48,
      "total_matches": 184,
      "completed_matches": 179,
      "ticket_price": 299.0,
      "is_paid": true,
      "currency": "INR"
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

### 5.2 Event Summary & Overall Analytics
* **`GET /api/v1/admin/speed-networking/events/{id}/summary`**
* **Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "event": {
      "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
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
      "current_round": 8
    },
    "statistics": {
      "total_joined": 48,
      "participants_count": 48,
      "total_matches": 184,
      "completed_matches": 179,
      "average_talk_time_seconds": 172
    }
  }
}
```

---

## 6. Manual Database SQL Scripts (No Migrations)

Run these queries directly in your database management tool (pgAdmin, DBeaver, MySQL Workbench, etc.).

### Option A: PostgreSQL (Production Unity App Database)

```sql
-- 1. Create speed_networking_events table (if does not exist)
CREATE TABLE IF NOT EXISTS speed_networking_events (
    id VARCHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    banner_url VARCHAR(500) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    round_duration_seconds INT NOT NULL DEFAULT 180,
    break_duration_seconds INT NOT NULL DEFAULT 20,
    total_rounds INT NOT NULL DEFAULT 5,
    current_round INT NOT NULL DEFAULT 0,
    scheduled_start_time TIMESTAMP NULL,
    scheduled_end_time TIMESTAMP NULL,
    actual_start_time TIMESTAMP NULL,
    actual_end_time TIMESTAMP NULL,
    ticket_price NUMERIC(10, 2) NOT NULL DEFAULT 0.00,
    is_paid BOOLEAN NOT NULL DEFAULT FALSE,
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    circle_id VARCHAR(36) NULL,
    circle_restricted BOOLEAN NOT NULL DEFAULT FALSE,
    metadata JSONB NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);

-- Ensure newly added columns exist if the table already existed earlier:
ALTER TABLE speed_networking_events ADD COLUMN IF NOT EXISTS scheduled_start_time TIMESTAMP;
ALTER TABLE speed_networking_events ADD COLUMN IF NOT EXISTS scheduled_end_time TIMESTAMP;
ALTER TABLE speed_networking_events ADD COLUMN IF NOT EXISTS actual_start_time TIMESTAMP;
ALTER TABLE speed_networking_events ADD COLUMN IF NOT EXISTS actual_end_time TIMESTAMP;
ALTER TABLE speed_networking_events ADD COLUMN IF NOT EXISTS ticket_price NUMERIC(10, 2) DEFAULT 0.00;
ALTER TABLE speed_networking_events ADD COLUMN IF NOT EXISTS is_paid BOOLEAN DEFAULT FALSE;
ALTER TABLE speed_networking_events ADD COLUMN IF NOT EXISTS currency VARCHAR(10) DEFAULT 'INR';
ALTER TABLE speed_networking_events ADD COLUMN IF NOT EXISTS circle_id VARCHAR(36);
ALTER TABLE speed_networking_events ADD COLUMN IF NOT EXISTS circle_restricted BOOLEAN DEFAULT FALSE;
ALTER TABLE speed_networking_events ADD COLUMN IF NOT EXISTS metadata JSONB;

-- Now create indexes safely:
CREATE INDEX IF NOT EXISTS idx_sn_events_status ON speed_networking_events (status);
CREATE INDEX IF NOT EXISTS idx_sn_events_start ON speed_networking_events (scheduled_start_time);
CREATE INDEX IF NOT EXISTS idx_sn_events_end ON speed_networking_events (scheduled_end_time);

-- 2. Create speed_networking_participants table
CREATE TABLE IF NOT EXISTS speed_networking_participants (
    id VARCHAR(36) PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'registered',
    rounds_completed INT NOT NULL DEFAULT 0,
    total_talk_time_seconds INT NOT NULL DEFAULT 0,
    last_heartbeat_at TIMESTAMP NULL,
    break_started_at TIMESTAMP NULL,
    break_remaining_seconds INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    CONSTRAINT uq_sn_participant UNIQUE (event_id, user_id)
);

ALTER TABLE speed_networking_participants ADD COLUMN IF NOT EXISTS break_started_at TIMESTAMP;
ALTER TABLE speed_networking_participants ADD COLUMN IF NOT EXISTS break_remaining_seconds INT DEFAULT 0;

CREATE INDEX IF NOT EXISTS idx_sn_part_event_status ON speed_networking_participants (event_id, status);
CREATE INDEX IF NOT EXISTS idx_sn_part_user ON speed_networking_participants (user_id);

-- 3. Create speed_networking_matches table
CREATE TABLE IF NOT EXISTS speed_networking_matches (
    id VARCHAR(36) PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL,
    round_number INT NOT NULL,
    user_a_id VARCHAR(36) NOT NULL,
    user_b_id VARCHAR(36) NULL,
    channel_id VARCHAR(255) NULL,
    agora_token TEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    duration_seconds INT NOT NULL DEFAULT 0,
    started_at TIMESTAMP NULL,
    ended_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE INDEX IF NOT EXISTS idx_sn_matches_event_round ON speed_networking_matches (event_id, round_number);
CREATE INDEX IF NOT EXISTS idx_sn_matches_users ON speed_networking_matches (user_a_id, user_b_id);
```

---

### Option B: MySQL / MariaDB

```sql
-- 1. Create speed_networking_events table
CREATE TABLE IF NOT EXISTS speed_networking_events (
    id VARCHAR(36) PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    banner_url VARCHAR(500) NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    round_duration_seconds INT NOT NULL DEFAULT 180,
    break_duration_seconds INT NOT NULL DEFAULT 20,
    total_rounds INT NOT NULL DEFAULT 5,
    current_round INT NOT NULL DEFAULT 0,
    scheduled_start_time DATETIME NULL,
    scheduled_end_time DATETIME NULL,
    actual_start_time DATETIME NULL,
    actual_end_time DATETIME NULL,
    ticket_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    is_paid TINYINT(1) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'INR',
    circle_id VARCHAR(36) NULL,
    circle_restricted TINYINT(1) NOT NULL DEFAULT 0,
    metadata JSON NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_sn_events_status (status),
    INDEX idx_sn_events_start (scheduled_start_time),
    INDEX idx_sn_events_end (scheduled_end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create speed_networking_participants table
CREATE TABLE IF NOT EXISTS speed_networking_participants (
    id VARCHAR(36) PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL,
    user_id VARCHAR(36) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'registered',
    rounds_completed INT NOT NULL DEFAULT 0,
    total_talk_time_seconds INT NOT NULL DEFAULT 0,
    last_heartbeat_at DATETIME NULL,
    break_started_at DATETIME NULL,
    break_remaining_seconds INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY uq_sn_participant (event_id, user_id),
    INDEX idx_sn_part_event_status (event_id, status),
    INDEX idx_sn_part_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create speed_networking_matches table
CREATE TABLE IF NOT EXISTS speed_networking_matches (
    id VARCHAR(36) PRIMARY KEY,
    event_id VARCHAR(36) NOT NULL,
    round_number INT NOT NULL,
    user_a_id VARCHAR(36) NOT NULL,
    user_b_id VARCHAR(36) NULL,
    channel_id VARCHAR(255) NULL,
    agora_token TEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    duration_seconds INT NOT NULL DEFAULT 0,
    started_at DATETIME NULL,
    ended_at DATETIME NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    INDEX idx_sn_matches_event_round (event_id, round_number),
    INDEX idx_sn_matches_users (user_a_id, user_b_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```
