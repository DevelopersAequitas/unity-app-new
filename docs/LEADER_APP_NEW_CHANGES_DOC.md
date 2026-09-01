# Leader App — New Changes & Added Endpoints Document

> **Document Version:** 2.0.0 (Delta Release)  
> **Target Audience:** Frontend Team (Flutter), QA & Backend Engineers  
> **Base URLs:**
> - Dev: `https://dev.peersunity.com/api/v1` (or `http://localhost:8000/api/v1`)
> - Production: `https://peersunity.com/api/v1`
> - Universal Dev OTP: `123456`

---

## 📌 Executive Summary of New Changes

This document details the **11 newly implemented REST API endpoints**, updated authentication behaviors (live WhatsApp + Email OTP dispatch), database schema fixes (PostgreSQL enums & column alignments), and manual SQL queries.

---

## 📋 Summary Table of Newly Added Endpoints

| # | Section / Screen | Endpoint | Method | Request Payload | Response Key / Status |
|---|---|---|:---:|---|---|
| 1 | **Circle Sub-Industries** | `/teams/circles/{id}/sub-industries` | `GET` | None | `active_sub_industries`, `open_sub_industries` |
| 2 | **Circle Events** | `/teams/circles/{id}/events` | `GET` | `?filter=all\|upcoming\|completed` | List of assemblies & summits |
| 3 | **Peer Meetings History** | `/peers/{id}/meetings` | `GET` | None | List of P2P & Circle meetings |
| 4 | **Peer Activities Audit** | `/peers/{id}/activities` | `GET` | `?page=1&limit=20` | Chronological activity feed |
| 5 | **Quick P2P Meeting** | `/peers/p2p-meetings` | `POST` | `peer_id`, `meeting_date`, `meeting_place` | `201 Created` (`meeting_id`) |
| 6 | **Commission Rates** | `/finance/commission-rates` | `PUT` | `commission_rates: [...]` | `200 OK` (Super Admin only) |
| 7 | **Record Offline Fee** | `/finance/transactions/record-offline` | `POST` | `peer_id`, `amount`, `payment_mode`, `ref` | `201 Created` (`transaction_id`) |
| 8 | **Edit Profile / Bio** | `/auth/profile` | `PUT` | `name`, `phone`, `bio`, `company_name` | `200 OK` (Updated User Object) |
| 9 | **Upload Avatar Image** | `/auth/profile/avatar` | `POST` | Multipart `avatar` (Image file) | `200 OK` (`avatar_url`) |
| 10| **Create Referral Lead** | `/referrals` | `POST` | `to_peer_id`, `prospect_name`, `estimated_deal_value` | `201 Created` (`referral_id`) |
| 11| **Download Report Link** | `/reports/{id}/download` | `GET` | None | `download_url`, `file_name`, `expires_in` |

---

## 🔍 Detailed Specifications for New Endpoints

---

### 1. Circle Details — Sub-Industries Breakdown
* **Target Screen:** `CircleDetailsView` ➔ Sub-Industries Tab
* **Endpoint:** `GET /api/v1/teams/circles/{circle_id}/sub-industries`
* **Headers:** `Authorization: Bearer <TOKEN>`

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "circle_id": "d06173c0-368c-4bfd-b682-e07e67fdb320",
    "active_sub_industries": [
      {
        "id": "19",
        "name": "Renewable Energy & CleanTech",
        "peer_count": 4,
        "is_open": false
      },
      {
        "id": "20",
        "name": "ESG & Sustainability Consulting",
        "peer_count": 3,
        "is_open": false
      },
      {
        "id": "21",
        "name": "Waste Management & Circular Economy",
        "peer_count": 2,
        "is_open": false
      }
    ],
    "open_sub_industries": [
      {
        "id": "22",
        "name": "Green Manufacturing & Industry",
        "peer_count": 0,
        "is_open": true
      },
      {
        "id": "23",
        "name": "Water & Environmental Solutions",
        "peer_count": 0,
        "is_open": true
      },
      {
        "id": "24",
        "name": "Sustainable Agriculture & Food",
        "peer_count": 0,
        "is_open": true
      }
    ]
  }
}
```

---

### 2. Circle Details — Circle Events & Assemblies
* **Target Screen:** `CircleDetailsView` ➔ Events Tab
* **Endpoint:** `GET /api/v1/teams/circles/{circle_id}/events`
* **Query Parameters:** `filter` = `all` | `upcoming` | `completed` (Optional)

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": "37c1fe0d-4e79-40bb-bbbe-8cfe5e2f92e2",
      "title": "Tech Growth Summit 2026",
      "date": "2026-09-01",
      "time": "10:00 AM",
      "location": "The Grand Ballroom, Mumbai",
      "mode": "In-Person",
      "status": "Upcoming",
      "attendees_count": 48
    },
    {
      "id": "53a9d9d3-f7cf-4c69-9736-d862b4e49d8e",
      "title": "AI & ML Peer Workshop",
      "date": "2026-08-10",
      "time": "03:00 PM",
      "location": "Zoom Online",
      "mode": "Online",
      "status": "Completed",
      "attendees_count": 52
    }
  ]
}
```

---

### 3. Peer Profile — Historical & Scheduled Meetings
* **Target Screen:** `PeerProfileView` ➔ Meetings Tab
* **Endpoint:** `GET /api/v1/peers/{peer_id}/meetings`

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": "meet_301",
      "day": "01",
      "month": "Sep",
      "title": "Monthly Circle Meeting",
      "time_location": "7:30 AM - Grand Ballroom, Mumbai",
      "status": "Confirmed",
      "type": "Circle Meeting"
    },
    {
      "id": "meet_302",
      "day": "12",
      "month": "Sep",
      "title": "P2P 1-on-1 Alignment",
      "time_location": "4:00 PM - Starbucks BKC",
      "status": "Open",
      "type": "P2P Meeting"
    }
  ]
}
```

---

### 4. Peer Profile — Activity Audit Trail
* **Target Screen:** `PeerProfileView` ➔ Activity Tab
* **Endpoint:** `GET /api/v1/peers/{peer_id}/activities`
* **Query Parameters:** `page=1&limit=20`

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": "act_401",
      "icon_type": "arrows",
      "title": "Completed P2P meeting with Ananya Roy",
      "subtitle": "Discussed healthcare AI integration pipeline",
      "created_at": "2 hours ago"
    },
    {
      "id": "act_402",
      "icon_type": "speaker",
      "title": "Gave 2 referrals to CloudSoft",
      "subtitle": "Enterprise Cloud Migration leads",
      "created_at": "3 days ago"
    },
    {
      "id": "act_403",
      "icon_type": "trophy",
      "title": "Closed ₹14.2L deal with Veritas Tech",
      "subtitle": "Transaction confirmed by Circle Director",
      "created_at": "1 week ago"
    }
  ]
}
```

---

### 5. Quick Actions — Log 1-on-1 P2P Meeting
* **Target Screen:** `QuickActions` ➔ Log P2P Session Modal
* **Endpoint:** `POST /api/v1/peers/p2p-meetings`

#### Request Body:
```json
{
  "peer_id": "76265b49-4e41-406e-bb8c-7182d5f6536c",
  "meeting_date": "2026-09-01",
  "meeting_place": "Starbucks BKC, Mumbai",
  "remarks": "Strategic partnership on enterprise AI consulting."
}
```

#### Success Response (`201 Created`):
```json
{
  "success": true,
  "message": "P2P meeting logged successfully.",
  "data": {
    "meeting_id": "bfc1e7d6-effc-48e9-ac62-e494f83390f8",
    "status": "Confirmed"
  }
}
```

---

### 6. Finance — Update Commission Structure
* **Target Screen:** `FinanceView` ➔ Commission Settings Modal
* **Endpoint:** `PUT /api/v1/finance/commission-rates`
* **Permission Required:** Super Admin (`can_modify_finance_settings`)

#### Request Body:
```json
{
  "commission_rates": [
    {
      "role_id": "circleFounder",
      "direct_referral_cut_percentage": 5.0,
      "app_join_cut_percentage": 2.5
    },
    {
      "role_id": "circleDirector",
      "direct_referral_cut_percentage": 7.5,
      "app_join_cut_percentage": 3.0
    },
    {
      "role_id": "industryDirector",
      "direct_referral_cut_percentage": 10.0,
      "app_join_cut_percentage": 4.0
    }
  ]
}
```

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Commission rates updated successfully."
}
```

---

### 7. Finance — Record Offline / Manual Fee Payment
* **Target Screen:** `FinanceView` ➔ Record Dues Modal
* **Endpoint:** `POST /api/v1/finance/transactions/record-offline`

#### Request Body:
```json
{
  "peer_id": "76265b49-4e41-406e-bb8c-7182d5f6536c",
  "circle_id": "d06173c0-368c-4bfd-b682-e07e67fdb320",
  "amount": 45000,
  "payment_mode": "Cheque",
  "reference_number": "CHQ-890211",
  "payment_date": "2026-08-25",
  "type": "Annual Membership Fee"
}
```

#### Success Response (`201 Created`):
```json
{
  "success": true,
  "message": "Payment recorded successfully.",
  "data": {
    "transaction_id": "defb736a-b5f9-4769-ba12-a334c5702407",
    "status": "Paid"
  }
}
```

---

### 8. User Profile — Edit Profile & Bio
* **Target Screen:** `ProfileView` ➔ Edit Profile
* **Endpoint:** `PUT /api/v1/auth/profile`

#### Request Body:
```json
{
  "name": "Arjun Patel",
  "phone": "+919876543210",
  "bio": "Circle Chair for Mumbai Tech Sunrise.",
  "company_name": "Apex Dynamics Pvt Ltd"
}
```

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Profile updated successfully.",
  "data": {
    "id": "76265b49-4e41-406e-bb8c-7182d5f6536c",
    "name": "Arjun Patel",
    "phone": "+919876543210",
    "bio": "Circle Chair for Mumbai Tech Sunrise.",
    "company_name": "Apex Dynamics Pvt Ltd",
    "avatar_url": "https://peersunity.com/storage/avatars/76265b49.png"
  }
}
```

---

### 9. User Profile — Upload Avatar Image
* **Target Screen:** `ProfileView` ➔ Change Avatar Photo
* **Endpoint:** `POST /api/v1/auth/profile/avatar`
* **Content-Type:** `multipart/form-data`
* **Payload:** `avatar`: [Binary Image File - JPG/PNG/WEBP up to 10MB]

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "message": "Avatar updated successfully.",
  "data": {
    "id": "76265b49-4e41-406e-bb8c-7182d5f6536c",
    "name": "Arjun Patel",
    "phone": "+919876543210",
    "avatar_url": "https://peersunity.com/storage/avatars/avatar_uploaded.png"
  }
}
```

---

### 10. Quick Actions — Submit a Referral Lead
* **Target Screen:** `QuickActions` ➔ Create Referral Modal
* **Endpoint:** `POST /api/v1/referrals`

#### Request Body:
```json
{
  "to_peer_id": "76265b49-4e41-406e-bb8c-7182d5f6536c",
  "prospect_name": "Rajesh Singhania",
  "prospect_company": "Reliance Retail Tech",
  "prospect_phone": "+919822019283",
  "prospect_email": "rajesh@relianceretail.com",
  "estimated_deal_value": "₹15.0L",
  "notes": "Interested in AI automation platform consulting."
}
```

#### Success Response (`201 Created`):
```json
{
  "success": true,
  "message": "Referral created and forwarded to peer.",
  "data": {
    "referral_id": "e201b10a-3199-4c12-9843-820194820129",
    "status": "Pending"
  }
}
```

---

### 11. Reports & Analytics — Dynamic Download Link
* **Target Screen:** `ReportsView` ➔ Download PDF / Excel Button
* **Endpoint:** `GET /api/v1/reports/{id}/download`

#### Success Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "report_id": "9a01f822-1082-4112-aa01-d82049182390",
    "file_name": "Report-Monthly-August-2026.pdf",
    "file_format": "PDF",
    "file_size": "2.4 MB",
    "download_url": "https://peersunity.com/api/v1/files/9a01f822-1082-4112-aa01-d82049182390/download?type=pdf",
    "expires_in_seconds": 3600
  }
}
```

---

## 🛠️ PostgreSQL Database Setup Queries

```sql
-- 1. Commission Rates Table (Super Admin Configs)
CREATE TABLE IF NOT EXISTS leader_commission_rates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role_id VARCHAR(100) NOT NULL,
    direct_referral_cut_percentage NUMERIC(5, 2) DEFAULT 0.00,
    app_join_cut_percentage NUMERIC(5, 2) DEFAULT 0.00,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_leader_comm_role UNIQUE (role_id)
);

-- 2. Role Capabilities Mapping (Two-Way Dynamic RBAC Sync)
CREATE TABLE IF NOT EXISTS leader_role_capabilities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role_id VARCHAR(100) NOT NULL,
    capability_key VARCHAR(100) NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_role_capability UNIQUE (role_id, capability_key)
);

-- 3. Leader Reports Storage
CREATE TABLE IF NOT EXISTS leader_reports (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    circle_id UUID NOT NULL,
    submitted_by_user_id UUID NOT NULL,
    report_type VARCHAR(50) NOT NULL DEFAULT 'Monthly',
    period VARCHAR(50) NOT NULL,
    attendance_percentage NUMERIC(5, 2) DEFAULT 0.00,
    deals_closed_value VARCHAR(100) NULL,
    content TEXT NULL,
    summary_text TEXT NULL,
    action_items TEXT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'Under Review',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP WITH TIME ZONE NULL
);

-- 4. Celebration Wishes Table
CREATE TABLE IF NOT EXISTS leader_wishes (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    sender_user_id UUID NOT NULL,
    receiver_user_id UUID NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- 5. Mobile Search Indexes
CREATE INDEX IF NOT EXISTS idx_users_phone ON users (phone);
CREATE INDEX IF NOT EXISTS idx_users_secondary_mobile ON users (secondary_mobile);
CREATE INDEX IF NOT EXISTS idx_leader_reports_circle ON leader_reports (circle_id);
CREATE INDEX IF NOT EXISTS idx_p2p_meetings_initiator ON p2p_meetings (initiator_user_id);
CREATE INDEX IF NOT EXISTS idx_p2p_meetings_peer ON p2p_meetings (peer_user_id);
CREATE INDEX IF NOT EXISTS idx_referrals_from_user ON referrals (from_user_id);
CREATE INDEX IF NOT EXISTS idx_referrals_to_user ON referrals (to_user_id);

-- 6. Circle Member Status Enum Extensions (Optional)
ALTER TYPE circle_member_status_enum ADD VALUE IF NOT EXISTS 'needs_attention';
ALTER TYPE circle_member_status_enum ADD VALUE IF NOT EXISTS 'under_review';
```
