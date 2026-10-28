# Speed Networking Admin Panel API Specification (Doc 2 of 2)
## Real-Time Live Monitor, Live Tables & Connects View

This document provides the specification for the **Real-Time Live Monitor** and the **Connects View** (Matches & Connections) for the Node.js Admin Panel team.

---

## 1. Overview of Monitoring & Connections Features

During and after a Speed Networking session, administrators have access to:
1. **Live Tables Monitor**:
   - Visual bird's-eye view of all virtual 1-on-1 tables.
   - Shows active pairs in call with remaining round countdown timer.
   - Shows odd-man-out break / lounge tables.
2. **Real-Time Participant Presence Pool**:
   - Status indicators: `registered`, `searching`, `in_call`, `break`, `left`.
   - Heartbeat status: Online (green indicator if active in last 60s) or Offline.
3. **Connects View (Matches & Interactions History)**:
   - Full ledger of all pairings generated across all rounds.
   - View User A and User B profiles (Name, Company, Avatar, Title, Contact Info).
   - Call duration in seconds (`started_at`, `ended_at`).
   - Match status (`active`, `completed`, `skipped`).
   - Summary statistics: Unique peers connected, total matches, completed calls.

---

## 2. API Endpoints

All endpoints require Admin Bearer token authorization:
`Authorization: Bearer <ADMIN_AUTH_TOKEN>`

| Method | Endpoint | Description |
|---|---|---|
| `GET` | `/api/v1/admin/speed-networking/events/{id}/live-tables` | Real-time live tables monitor with table pairings & countdown |
| `GET` | `/api/v1/admin/speed-networking/events/{id}/participants` | List all participants with live presence & online status |
| `GET` | `/api/v1/admin/speed-networking/events/{id}/matches` | **Connects View**: Complete match history & interaction ledger |
| `GET` | `/api/v1/admin/speed-networking/events/{id}/connections` | Alias for matches/connects view |

---

## 3. Endpoint Specifications

### 3.1 Live Tables Monitor
* **`GET /api/v1/admin/speed-networking/events/{id}/live-tables`**
* **Usage**: Poll every 3–5 seconds on the admin live monitor dashboard while the event is `in_progress`.
* **Response (200 OK)**:
```json
{
  "success": true,
  "data": {
    "event": {
      "id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
      "title": "Entrepreneurs Speed Networking ⚡",
      "description": "Fast-paced 1-on-1 networking for tech innovators.",
      "status": "in_progress",
      "round_duration_seconds": 180,
      "break_duration_seconds": 30,
      "total_rounds": 8,
      "current_round": 2,
      "scheduled_start_time": "2026-11-05T18:30:00.000000Z",
      "actual_start_time": "2026-11-05T18:30:15.000000Z",
      "total_participants_count": 52,
      "active_tables_count": 26
    },
    "active_participants_count": 52,
    "total_participants_count": 52,
    "remaining_seconds": 124,
    "is_paused": false,
    "tables": [
      {
        "match_id": "9a38f4e2-762b-42ea-a411-92b451c098ef",
        "table_number": 1,
        "channel_id": "sn_c86f78e4_r2_t1_x8d9a2",
        "status": "active",
        "is_break_round": false,
        "user_a": {
          "id": "11111111-1111-1111-1111-111111111111",
          "name": "Amit Singhal",
          "company": "Krypton Labs",
          "industry": "Information Technology",
          "avatar_url": "https://cdn.peersunity.com/avatars/user1.jpg"
        },
        "user_b": {
          "id": "22222222-2222-2222-2222-222222222222",
          "name": "Priya Sharma",
          "company": "Apex Ventures",
          "industry": "Venture Capital",
          "avatar_url": "https://cdn.peersunity.com/avatars/user2.jpg"
        },
        "started_at": "2026-11-05T18:35:10.000000Z"
      },
      {
        "match_id": "8b27d3c1-512a-43dc-b320-81a340b087de",
        "table_number": 2,
        "channel_id": "sn_c86f78e4_r2_t2_k2m8e1",
        "status": "active",
        "is_break_round": true,
        "user_a": {
          "id": "33333333-3333-3333-3333-333333333333",
          "name": "Rajesh Varma",
          "company": "Varma Infotech",
          "industry": "Cloud Computing",
          "avatar_url": "https://cdn.peersunity.com/avatars/user3.jpg"
        },
        "user_b": null,
        "started_at": "2026-11-05T18:35:10.000000Z"
      }
    ]
  }
}
```

---

### 3.2 Participants List & Presence Monitoring
* **`GET /api/v1/admin/speed-networking/events/{id}/participants`**
* **Query Parameters**:
  - `page`: Page number (default: 1)
  - `per_page`: Items per page (default: 25)
* **Response (200 OK)**:
```json
{
  "success": true,
  "data": [
    {
      "participant_id": "f4561234-5678-90ab-cdef-1234567890ab",
      "user_id": "11111111-1111-1111-1111-111111111111",
      "name": "Amit Singhal",
      "company": "Krypton Labs",
      "designation": "Founder & CEO",
      "city": "Mumbai",
      "avatar_url": "https://cdn.peersunity.com/avatars/user1.jpg",
      "status": "in_call",
      "is_online": true,
      "joined_at": "2026-11-05T18:25:00.000000Z",
      "last_heartbeat_at": "2026-11-05T18:36:12.000000Z"
    },
    {
      "participant_id": "a1239876-4321-09ba-fedc-ba0987654321",
      "user_id": "44444444-4444-4444-4444-444444444444",
      "name": "Rohit Roy",
      "company": "Solaris Energy",
      "designation": "Managing Director",
      "city": "Delhi",
      "avatar_url": "https://cdn.peersunity.com/avatars/user4.jpg",
      "status": "break",
      "is_online": true,
      "joined_at": "2026-11-05T18:20:00.000000Z",
      "last_heartbeat_at": "2026-11-05T18:36:05.000000Z"
    }
  ],
  "pagination": {
    "total": 52,
    "per_page": 25,
    "current_page": 1,
    "last_page": 3
  }
}
```

---

### 3.3 Connects View (Matches & Interactions Ledger)
* **`GET /api/v1/admin/speed-networking/events/{id}/matches`**
* **Query Parameters**:
  - `round_number` (optional): Filter matches by a specific round (e.g. `?round_number=1`)
  - `status` (optional): Filter by match status (`active`, `completed`, `skipped`)
  - `search` (optional): Search by user name or company name
  - `page` (optional): Page number (default: 1)
  - `per_page` (optional): Items per page (default: 50)

* **Response (200 OK)**:
```json
{
  "success": true,
  "summary": {
    "total_matches": 96,
    "completed_matches": 72,
    "active_matches": 24,
    "unique_pairs_count": 92,
    "current_round": 3,
    "total_rounds": 8
  },
  "data": [
    {
      "id": "9a38f4e2-762b-42ea-a411-92b451c098ef",
      "match_id": "9a38f4e2-762b-42ea-a411-92b451c098ef",
      "event_id": "c86f78e4-8a4b-4b2a-89a3-5e9a4f6d8920",
      "round_number": 2,
      "table_number": 1,
      "channel_id": "sn_c86f78e4_r2_t1_x8d9a2",
      "status": "completed",
      "is_break_round": false,
      "started_at": "2026-11-05T18:35:10.000000Z",
      "ended_at": "2026-11-05T18:38:10.000000Z",
      "duration_seconds": 180,
      "user_a": {
        "id": "11111111-1111-1111-1111-111111111111",
        "name": "Amit Singhal",
        "company": "Krypton Labs",
        "designation": "Founder & CEO",
        "city": "Mumbai",
        "avatar_url": "https://cdn.peersunity.com/avatars/user1.jpg",
        "phone": "+919876543210",
        "email": "amit@kryptonlabs.com"
      },
      "user_b": {
        "id": "22222222-2222-2222-2222-222222222222",
        "name": "Priya Sharma",
        "company": "Apex Ventures",
        "designation": "Partner",
        "city": "Bengaluru",
        "avatar_url": "https://cdn.peersunity.com/avatars/user2.jpg",
        "phone": "+919812345678",
        "email": "priya@apexvc.in"
      }
    }
  ],
  "pagination": {
    "total": 96,
    "per_page": 50,
    "current_page": 1,
    "last_page": 2
  }
}
```

---

## 4. UI Suggestions for Node.js Admin Panel

1. **Dashboard Top Metric Cards**:
   - `Total Participants`: `summary.total_participants`
   - `Active Tables Now`: `stats.active_tables_count`
   - `Completed Calls`: `summary.completed_matches`
   - `Current Round`: `event.current_round` of `event.total_rounds`
2. **Live Controls Bar**:
   - Big Action Button: `Start Event` (if scheduled), `Next Round` (if live), `End Event` (to wrap up).
   - Utility Buttons: `Pause / Resume`, `Rematch Waiting Peers`.
3. **Tabs Layout**:
   - Tab 1: **Live Tables Grid**: Shows cards for each table with peer photos and Agora channel status.
   - Tab 2: **Participants Table**: List with online indicator badges, last seen, and current status.
   - Tab 3: **Connects View (Match History)**: Searchable, filterable table of all 1-on-1 pairs formed, duration, contact details, and P2P generation status.
   - Tab 4: **Settings / Configuration**: Editable form to change round duration, break seconds, price, or circle exclusivity.
