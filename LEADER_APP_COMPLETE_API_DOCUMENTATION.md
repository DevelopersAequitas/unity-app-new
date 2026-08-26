# 📱 Leader App — Complete REST API & Access Control Documentation

> **Document Version:** 3.0.0 (Complete Master Release)  
> **Target Audience:** Flutter Frontend Team, Mobile Developers, QA, Backend Integrators  
> **Date:** August 2026  
> **Base URLs:**  
> - **Local Development:** `http://localhost:8000/api/v1` (or your local IP `http://192.168.x.x:8000/api/v1`)  
> - **Dev Staging:** `https://dev.peersunity.com/api/v1`  
> - **Production:** `https://peersunity.com/api/v1`  
> - **Universal Dev Bypass OTP:** `123456`  
> - **Default Headers:**  
>   `Accept: application/json`  
>   `Content-Type: application/json`  
>   `Authorization: Bearer <AUTH_TOKEN>` (for protected endpoints)  

---

## 📑 Table of Contents

1. [Dynamic RBAC & 21-Flag Permission Matrix](#1-dynamic-rbac--21-flag-permission-matrix)
2. [Section 1: Authentication & User Profile](#section-1-authentication--user-profile)
3. [Section 2: Tab 0 — Dashboard](#section-2-tab-0--dashboard)
4. [Section 3: Tab 1 — Peers & Celebrations](#section-3-tab-1--peers--celebrations)
5. [Section 4: Tab 2 — Teams & Circles](#section-4-tab-2--teams--circles)
6. [Section 5: Tab 3 — Finance & Accounts](#section-5-tab-3--finance--accounts)
7. [Section 6: Tab 4 — Reports & Analytics](#section-6-tab-4--reports--analytics)
8. [Section 7: Referrals, Testimonials & Coins Leaderboard](#section-7-referrals-testimonials--coins-leaderboard)
9. [Section 8: Notifications](#section-8-notifications)
10. [Section 9: Tab 5 — Role & Permission Management](#section-9-tab-5--role--permission-management)
11. [Section 10: PostgreSQL Database Setup Queries](#section-10-postgresql-database-setup-queries)

---

## 1. Dynamic RBAC & 21-Flag Permission Matrix

When a user logs in, the backend computes their role and returns their customized **21-Flag Permission Matrix**:

| Feature / Action Permission Flag | Circle Chair (`circleChair`) | Circle Founder (`circleFounder`) | Circle Director (`circleDirector`) | Industry Director (`industryDirector`) | District Exec Director (`districtExecDirector`) | Country Director (`countryDirector`) | Super Admin (`superAdmin`) |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `can_access_dashboard` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_view_overall_revenue` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_review_pending_peers` | `true` | `true` | `true` | `false` | `true` | `false` | `false` |
| `can_access_peers_tab` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_add_edit_peer` | `false` | `true` | `true` | `false` | `true` | `true` | `true` |
| `can_send_wishes` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_view_peer_profile` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_view_peer_contact_info` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_access_teams_tab` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_manage_circles` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_assign_circle_chair` | `false` | `true` | `true` | `false` | `true` | `true` | `true` |
| `can_access_finance_tab` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_modify_finance_settings` | `false` | `false` | `false` | `false` | `true` | `true` | `true` |
| `can_issue_coins` | `false` | `false` | `false` | `false` | `false` | `true` | `true` |
| `can_access_reports_tab` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_submit_reports` | `true` | `true` | `true` | `false` | `false` | `false` | `false` |
| `can_export_peer_data` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_export_financial_data` | `false` | `false` | `false` | `false` | `true` | `true` | `true` |
| `can_export_global_data` | `false` | `false` | `false` | `false` | `false` | `false` | `true` |
| `can_access_role_management` | `false` | `false` | `false` | `false` | `false` | `false` | `true` |
| `can_view_regional_scope` | `false` | `false` | `false` | `true` | `true` | `true` | `true` |

---

## Section 1: Authentication & User Profile

### 1.1 Request Login OTP
* **Endpoint:** `POST /api/v1/auth/send-otp`
* **Auth Required:** No

#### Request Body:
```json
{
  "email_or_phone": "arjun@peersglobal.in"
}
```

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "OTP has been sent successfully to your registered email/phone.",
  "data": {
    "is_registered": true,
    "otp_expiry_seconds": 300
  }
}
```

---

### 1.2 Verify Login OTP
* **Endpoint:** `POST /api/v1/auth/verify-otp`
* **Auth Required:** No
* **Dev Bypass OTP:** `123456`

#### Request Body:
```json
{
  "email_or_phone": "arjun@peersglobal.in",
  "otp": "123456"
}
```

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "Authentication successful",
  "data": {
    "auth_token": "12|U2xS861foFTcTiiZnlXv7R3fWPcUplrURaV3k7CZ0df0e787",
    "refresh_token": "X1ysPTdpaMriwwrjumwZahursAAVpGCajuwbm6k9",
    "token_type": "Bearer",
    "expires_in": 86400,
    "user": {
      "id": "1133aa8e-575c-4abc-8986-c4880fd260ff",
      "name": "Arjun Patel",
      "email": "arjun@peersglobal.in",
      "phone": "+919876543209",
      "role": "circleChair",
      "custom_role_label": null,
      "regional_scope": "Own Circle",
      "member_since": "Jul 2026",
      "avatar_url": "https://cdn.peersglobal.in/avatars/default.png",
      "managed_circles": [
        {
          "id": "d06173c0-368c-4bfd-b682-e07e67fdb320",
          "name": "Mumbai Tech Sunrise",
          "location": "Mumbai",
          "category": "Technology"
        }
      ]
    },
    "permissions": {
      "can_access_dashboard": true,
      "can_view_overall_revenue": false,
      "can_review_pending_peers": true,
      "can_access_peers_tab": true,
      "can_add_edit_peer": false,
      "can_send_wishes": true,
      "can_view_peer_profile": true,
      "can_view_peer_contact_info": true,
      "can_access_teams_tab": false,
      "can_manage_circles": false,
      "can_assign_circle_chair": false,
      "can_access_finance_tab": false,
      "can_modify_finance_settings": false,
      "can_issue_coins": false,
      "can_access_reports_tab": true,
      "can_submit_reports": true,
      "can_export_peer_data": false,
      "can_export_financial_data": false,
      "can_export_global_data": false,
      "can_access_role_management": false,
      "can_view_regional_scope": false
    }
  }
}
```

---

### 1.3 Edit Profile & Bio
* **Endpoint:** `PUT /api/v1/auth/profile`
* **Auth Required:** `Bearer <TOKEN>`

#### Request Body:
```json
{
  "name": "Arjun Patel",
  "phone": "+919876543210",
  "bio": "Circle Chair for Mumbai Tech Sunrise.",
  "company_name": "Apex Dynamics Pvt Ltd"
}
```

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "Profile updated successfully.",
  "data": {
    "id": "1133aa8e-575c-4abc-8986-c4880fd260ff",
    "name": "Arjun Patel",
    "phone": "+919876543210",
    "bio": "Circle Chair for Mumbai Tech Sunrise.",
    "company_name": "Apex Dynamics Pvt Ltd",
    "avatar_url": "https://peersunity.com/storage/avatars/76265b49.png"
  }
}
```

---

### 1.4 Upload Avatar Photo
* **Endpoint:** `POST /api/v1/auth/profile/avatar`
* **Auth Required:** `Bearer <TOKEN>`
* **Content-Type:** `multipart/form-data`
* **Payload:** `avatar`: `[Image binary - JPG/PNG/WEBP up to 10MB]`

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "Avatar updated successfully.",
  "data": {
    "id": "1133aa8e-575c-4abc-8986-c4880fd260ff",
    "name": "Arjun Patel",
    "phone": "+919876543210",
    "avatar_url": "https://peersunity.com/storage/avatars/avatar_uploaded.png"
  }
}
```

---

## Section 2: Tab 0 — Dashboard

### 2.1 Get Dashboard Metrics
* **Endpoint:** `GET /api/v1/dashboard/metrics`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `circle_id` (optional), `district_id` (optional)

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "circle_id": "d06173c0-368c-4bfd-b682-e07e67fdb320",
    "circle_name": "Mumbai Tech Sunrise",
    "overall_revenue": "₹1.48Cr",
    "overall_deals_closed": "₹1.20Cr",
    "impact": 142,
    "deals": "₹86.4L",
    "p2p_meetings": 38,
    "total_peers": 56,
    "total_peers_growth": 4,
    "referrals": 28,
    "testimonials": 42,
    "coins": 3840,
    "pending_peers_count": 4
  }
}
```

---

### 2.2 Get Top 5 Impacters
* **Endpoint:** `GET /api/v1/dashboard/top-impacters`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `circle_id` (optional), `district_id` (optional)

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "rank": 1,
      "name": "Siddharth Verma",
      "company": "Apex Dynamics Pvt Ltd",
      "location": "Mumbai",
      "lives": 48,
      "coins": 1240
    },
    {
      "rank": 2,
      "name": "Ananya Roy",
      "company": "Veritas Health Tech",
      "location": "Mumbai",
      "lives": 36,
      "coins": 980
    },
    {
      "rank": 3,
      "name": "Rohan Deshmukh",
      "company": "Elevate Logistics",
      "location": "Mumbai",
      "lives": 29,
      "coins": 750
    },
    {
      "rank": 4,
      "name": "Pooja Hegde",
      "company": "Solace Architecture",
      "location": "Mumbai",
      "lives": 18,
      "coins": 520
    },
    {
      "rank": 5,
      "name": "Karan Singhal",
      "company": "Nexus FinServ",
      "location": "Mumbai",
      "lives": 11,
      "coins": 350
    }
  ]
}
```

---

## Section 3: Tab 1 — Peers & Celebrations

### 3.1 List Peers Directory
* **Endpoint:** `GET /api/v1/peers`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:**  
  - `circle_id` (UUID)  
  - `district_id` (UUID)  
  - `status` (`All`, `Active`, `Needs Attention`, `At Risk`, `Pending`)  
  - `sort` (`Impact`, `Deals`, `Coins`, `Attendance`)  
  - `search` (string)  

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": "76265b49-4e41-406e-bb8c-7182d5f6536c",
      "name": "Siddharth Verma",
      "company": "Apex Dynamics Pvt Ltd",
      "circle": "Mumbai Tech Sunrise",
      "location": "Mumbai",
      "tags": "FinTech · Series A · B2B SaaS",
      "status": "Active",
      "impact_count": 48,
      "deals_formatted": "₹32.5L",
      "coins": 1240,
      "attendance": "94%"
    }
  ]
}
```

---

### 3.2 Get Peer Detailed Profile
* **Endpoint:** `GET /api/v1/peers/{id}`
* **Auth Required:** `Bearer <TOKEN>`

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "id": "76265b49-4e41-406e-bb8c-7182d5f6536c",
    "name": "Siddharth Verma",
    "company": "Apex Dynamics Pvt Ltd",
    "designation": "Founder & CEO",
    "phone": "+919876543201",
    "email": "siddharth@apexdynamics.com",
    "circle": "Mumbai Tech Sunrise",
    "location": "Mumbai, India",
    "intro_video_url": "https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4",
    "attendance": "94%",
    "deals_closed": "₹32.5L",
    "coins_balance": 1240,
    "testimonials": [
      {
        "id": "tst_1",
        "endorser_name": "Kavitha Rao",
        "endorser_company": "Zenith AI",
        "content": "Outstanding leadership and integrity. Delivered exceptional value on our cross-circle tech partnership."
      }
    ],
    "referrals": [
      {
        "id": "ref_1",
        "client_name": "Tata Digital",
        "value": "₹12.0L",
        "status": "Closed"
      }
    ]
  }
}
```

---

### 3.3 Get Celebrations (Birthdays & Anniversaries)
* **Endpoint:** `GET /api/v1/peers/celebrations`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `circle_id` (optional), `district_id` (optional)

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "birthdays": [
      {
        "id": "cel_01",
        "peer_id": "76265b49-4e41-406e-bb8c-7182d5f6536c",
        "name": "Siddharth Verma",
        "company": "Apex Dynamics",
        "date_formatted": "Today, 25 Aug",
        "is_today": true
      }
    ],
    "anniversaries": [
      {
        "id": "cel_02",
        "peer_id": "8931bfa2-3112-4212-9901-817290182390",
        "name": "Rohan Deshmukh",
        "company": "Elevate Logistics",
        "milestone": "3 Years in Circle",
        "date_formatted": "28 Aug",
        "is_today": false
      }
    ]
  }
}
```

---

### 3.4 Send Celebration Wish
* **Endpoint:** `POST /api/v1/peers/{id}/send-wish`
* **Auth Required:** `Bearer <TOKEN>`

#### Request Body:
```json
{
  "type": "birthday",
  "message": "Wishing you a very happy birthday! Have a wonderful year ahead."
}
```

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "Wish sent to Siddharth Verma successfully!"
}
```

---

### 3.5 Peer Profile — Historical & Scheduled Meetings
* **Endpoint:** `GET /api/v1/peers/{id}/meetings`
* **Auth Required:** `Bearer <TOKEN>`

#### Response (`200 OK`):
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

### 3.6 Peer Profile — Activity Audit Trail
* **Endpoint:** `GET /api/v1/peers/{id}/activities`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `page=1&limit=20`

#### Response (`200 OK`):
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

### 3.7 Quick Action — Log 1-on-1 P2P Meeting
* **Endpoint:** `POST /api/v1/peers/p2p-meetings`
* **Auth Required:** `Bearer <TOKEN>`

#### Request Body:
```json
{
  "peer_id": "76265b49-4e41-406e-bb8c-7182d5f6536c",
  "meeting_date": "2026-09-01",
  "meeting_place": "Starbucks BKC, Mumbai",
  "remarks": "Strategic partnership on enterprise AI consulting."
}
```

#### Response (`201 Created`):
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

## Section 4: Tab 2 — Teams & Circles

### 4.1 Teams Overview Summary
* **Endpoint:** `GET /api/v1/teams/summary`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `district_id` (optional)

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "total_circles": 12,
    "avg_health": 88,
    "total_peers": 420,
    "total_revenue": "₹4.8Cr"
  }
}
```

---

### 4.2 Master List of 18 Industries
* **Endpoint:** `GET /api/v1/teams/industries` (or `GET /api/v1/industries`)
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `district_id` (optional), `status` (optional)

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "Industries fetched successfully.",
  "data": [
    {
      "id": "1",
      "name": "Technology & IT",
      "circles_count": 4,
      "peers_count": 142
    },
    {
      "id": "2",
      "name": "Healthcare & Pharma",
      "circles_count": 3,
      "peers_count": 89
    }
  ]
}
```

---

### 4.3 List Circles Directory
* **Endpoint:** `GET /api/v1/teams/circles`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `industry`, `status`, `search`, `district_id`

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": "d06173c0-368c-4bfd-b682-e07e67fdb320",
      "name": "Mumbai Tech Sunrise",
      "category": "Technology",
      "location": "Mumbai",
      "health_percentage": 94,
      "peers_count": 56,
      "revenue": "₹1.48Cr",
      "chair_name": "Arjun Patel",
      "founders_count": 2,
      "status": "Active"
    }
  ]
}
```

---

### 4.4 Get Circle Detailed View
* **Endpoint:** `GET /api/v1/teams/circles/{id}`
* **Auth Required:** `Bearer <TOKEN>`

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "id": "d06173c0-368c-4bfd-b682-e07e67fdb320",
    "name": "Mumbai Tech Sunrise",
    "category": "Technology",
    "location": "Mumbai",
    "launch_date": "Jan 2022",
    "health_percentage": 94,
    "chair": {
      "id": "usr_987214",
      "name": "Arjun Patel",
      "email": "arjun@peersglobal.in",
      "phone": "+919876543209"
    },
    "founders": [
      {
        "id": "usr_110",
        "name": "Sanjana Mehta",
        "email": "sanjana@peersglobal.in"
      }
    ],
    "metrics": {
      "total_peers": 56,
      "attendance_rate": "92%",
      "monthly_revenue": "₹12.4L",
      "annual_revenue": "₹1.48Cr"
    },
    "members": [
      {
        "id": "peer_001",
        "name": "Siddharth Verma",
        "company": "Apex Dynamics Pvt Ltd",
        "status": "Active"
      }
    ]
  }
}
```

---

### 4.5 Circle Sub-Industries Breakdown
* **Endpoint:** `GET /api/v1/teams/circles/{id}/sub-industries`
* **Auth Required:** `Bearer <TOKEN>`

#### Response (`200 OK`):
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
      }
    ]
  }
}
```

---

### 4.6 Circle Events & Assemblies
* **Endpoint:** `GET /api/v1/teams/circles/{id}/events`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `filter` = `all` | `upcoming` | `completed`

#### Response (`200 OK`):
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

## Section 5: Tab 3 — Finance & Accounts

### 5.1 Get Financial Metrics
* **Endpoint:** `GET /api/v1/finance/metrics`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `circle_id` (optional), `district_id` (optional)

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "total_collections": "₹84.5L",
    "total_dues": "₹12.2L",
    "projected_annual_revenue": "₹1.20Cr",
    "coin_issuances_total": 14500
  }
}
```

---

### 5.2 Get Transactions & Dues Ledger
* **Endpoint:** `GET /api/v1/finance/transactions`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `circle_id`, `status` (`Paid`, `Pending`, `Overdue`), `district_id`

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": "txn_8921",
      "peer_name": "Siddharth Verma",
      "circle_name": "Mumbai Tech Sunrise",
      "amount": "₹45,000",
      "type": "Annual Membership Fee",
      "status": "Paid",
      "date": "2026-08-15"
    }
  ]
}
```

---

### 5.3 Update Commission Rates
* **Endpoint:** `PUT /api/v1/finance/commission-rates`
* **Auth Required:** `Bearer <TOKEN>` (Super Admin / DED)

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
      "role_id": "districtExecDirector",
      "direct_referral_cut_percentage": 10.0,
      "app_join_cut_percentage": 4.0
    }
  ]
}
```

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "Commission rates updated successfully."
}
```

---

### 5.4 Record Offline / Manual Fee Payment
* **Endpoint:** `POST /api/v1/finance/transactions/record-offline`
* **Auth Required:** `Bearer <TOKEN>`

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

#### Response (`201 Created`):
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

## Section 6: Tab 4 — Reports & Analytics

### 6.1 List Reports History
* **Endpoint:** `GET /api/v1/reports`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `circle_id`, `type` (`weekly`, `monthly`), `district_id`

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": "rep_101",
      "circle_name": "Mumbai Tech Sunrise",
      "report_type": "Monthly",
      "period": "July 2026",
      "submitted_by": "Arjun Patel",
      "submitted_at": "2026-08-01T10:00:00Z",
      "status": "Approved",
      "attendance_percentage": 92,
      "deals_closed_value": "₹14.2L",
      "summary_text": "Strong monthly participation with 4 new peer referrals closed."
    }
  ]
}
```

---

### 6.2 Submit Performance Report
* **Endpoint:** `POST /api/v1/reports`
* **Auth Required:** `Bearer <TOKEN>`

#### Request Body:
```json
{
  "circle_id": "d06173c0-368c-4bfd-b682-e07e67fdb320",
  "report_type": "Monthly",
  "period": "August 2026",
  "attendance_percentage": 94,
  "deals_closed_value": "₹18.5L",
  "content": "August monthly meeting conducted successfully with 52 peers present.",
  "action_items": "Follow up on pending renewals for Q3."
}
```

#### Response (`201 Created`):
```json
{
  "success": true,
  "message": "Report submitted successfully!",
  "data": {
    "report_id": "rep_102"
  }
}
```

---

### 6.3 Get 6-Month Attendance Trend Spline
* **Endpoint:** `GET /api/v1/reports/attendance-trend`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `circle_id` (optional)

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    { "month": "Feb", "value": 72.0 },
    { "month": "Mar", "value": 78.0 },
    { "month": "Apr", "value": 74.0 },
    { "month": "May", "value": 82.0 },
    { "month": "Jun", "value": 87.0 },
    { "month": "Jul", "value": 90.0 }
  ]
}
```

---

### 6.4 Dynamic Report Download Link
* **Endpoint:** `GET /api/v1/reports/{id}/download`
* **Auth Required:** `Bearer <TOKEN>`

#### Response (`200 OK`):
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

## Section 7: Referrals, Testimonials & Coins Leaderboard

### 7.1 List Referrals
* **Endpoint:** `GET /api/v1/referrals`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `circle_id`, `status`

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": "ref_501",
      "rank": 1,
      "peer_name": "Siddharth Verma",
      "company": "Apex Dynamics Pvt Ltd",
      "referrals_count": 14,
      "value_formatted": "₹18.4L",
      "status": "Active",
      "source": "Direct"
    }
  ]
}
```

---

### 7.2 Submit a Business Referral Lead
* **Endpoint:** `POST /api/v1/referrals`
* **Auth Required:** `Bearer <TOKEN>`

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

#### Response (`201 Created`):
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

### 7.3 List Testimonials
* **Endpoint:** `GET /api/v1/testimonials`
* **Auth Required:** `Bearer <TOKEN>`
* **Query Parameters:** `circle_id`

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": "tst_901",
      "author_name": "Kavitha Rao",
      "author_role": "Industry Director",
      "target_peer_name": "Siddharth Verma",
      "circle_name": "Mumbai Tech Sunrise",
      "content": "Siddharth's team delivered a state-of-the-art solution that increased efficiency by 40%.",
      "date": "2026-08-10"
    }
  ]
}
```

---

### 7.4 Platform Peers Leaderboard By Coins
* **Endpoint:** `GET /api/v1/peers-by-coins`
* **Auth Required:** `Bearer <TOKEN>`

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "total_platform_coins": 3840,
    "leaderboard": [
      {
        "rank": 1,
        "peer_name": "Siddharth Verma",
        "circle_name": "Mumbai Tech Sunrise",
        "coins": 1240
      },
      {
        "rank": 2,
        "peer_name": "Ananya Roy",
        "circle_name": "Mumbai Tech Sunrise",
        "coins": 980
      }
    ]
  }
}
```

---

## Section 8: Notifications

### 8.1 List Notifications
* **Endpoint:** `GET /api/v1/notifications`
* **Auth Required:** `Bearer <TOKEN>`

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": [
    {
      "id": "notif_01",
      "title": "New Referral Received",
      "message": "You received a new lead from Ananya Roy.",
      "category": "referral",
      "is_unread": true,
      "created_at": "2026-08-25T08:30:00Z"
    }
  ]
}
```

---

### 8.2 Mark Notifications As Read
* **Endpoint:** `POST /api/v1/notifications/mark-read`
* **Auth Required:** `Bearer <TOKEN>`

#### Request Body:
```json
{
  "notification_ids": ["notif_01"]
}
```
*(Pass `["all"]` to mark all notifications as read)*

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "Notifications marked as read successfully."
}
```

---

## Section 9: Tab 5 — Role & Permission Management

### 9.1 Get Capability Definitions & Role Matrix
* **Endpoint:** `GET /api/v1/roles/matrix`
* **Auth Required:** `Bearer <TOKEN>`

#### Response (`200 OK`):
```json
{
  "success": true,
  "data": {
    "capabilities": [
      {
        "id": "access_dashboard",
        "name": "Access Dashboard",
        "category": "Navigation & Access",
        "description": "Allows access to the primary metrics and impacter list dashboard."
      },
      {
        "id": "access_teams",
        "name": "Access Circles & Teams",
        "category": "Navigation & Access",
        "description": "Allows viewing circles, directors, and chairs directories."
      },
      {
        "id": "access_finance",
        "name": "Access Financial Analytics",
        "category": "Navigation & Access",
        "description": "Allows viewing fee collections, dues, and transaction histories."
      },
      {
        "id": "regional_data",
        "name": "View Regional Scope Data",
        "category": "Navigation & Access",
        "description": "Access and filter data beyond own local circle (District/Country level)."
      },
      {
        "id": "view_peers",
        "name": "View Peer Profiles",
        "category": "Core Operations",
        "description": "Allows viewing and browsing peer profile details and attendance stats."
      },
      {
        "id": "manage_peers",
        "name": "Add/Edit Peer Information",
        "category": "Core Operations",
        "description": "Allows coordinators to add new peers or edit biographical fields."
      },
      {
        "id": "request_actions",
        "name": "Endorse Testimonials & Referrals",
        "category": "Core Operations",
        "description": "Allows creating and endorsing peer testimonials and registering new referrals."
      },
      {
        "id": "view_reports",
        "name": "View Performance Reports",
        "category": "Core Operations",
        "description": "Allows accessing downloadable PDFs and spreadsheets of peer activities."
      },
      {
        "id": "manage_finance",
        "name": "Modify Financial Settings",
        "category": "Financial Control",
        "description": "Allows modifying annual fees, approval of dues, and updating ledger settings."
      },
      {
        "id": "coin_payouts",
        "name": "Issue Coin Payouts",
        "category": "Financial Control",
        "description": "Allows awarding platform coins directly to peers for special achievements."
      },
      {
        "id": "manage_roles",
        "name": "Manage App Roles (Matrix)",
        "category": "Administration",
        "description": "Allows altering permission rules and toggling capabilities per role."
      },
      {
        "id": "system_configs",
        "name": "System Global Settings",
        "category": "Administration",
        "description": "Allows modifying global server variables, maintenance modes, and metadata keys."
      }
    ],
    "roles": [
      {
        "id": "circleChair",
        "label": "Circle Chair",
        "is_system_role": true,
        "enabled_capabilities": [
          "access_dashboard",
          "view_peers",
          "request_actions",
          "view_reports"
        ]
      },
      {
        "id": "superAdmin",
        "label": "Super Admin",
        "is_system_role": true,
        "enabled_capabilities": [
          "access_dashboard",
          "access_teams",
          "access_finance",
          "regional_data",
          "view_peers",
          "manage_peers",
          "request_actions",
          "view_reports",
          "manage_finance",
          "coin_payouts",
          "manage_roles",
          "system_configs"
        ]
      }
    ]
  }
}
```

---

### 9.2 Update Role Capability Assignments
* **Endpoint:** `PUT /api/v1/roles/matrix`
* **Auth Required:** `Bearer <TOKEN>`

#### Request Body:
```json
{
  "role_id": "circleChair",
  "enabled_capabilities": [
    "access_dashboard",
    "view_peers",
    "request_actions",
    "view_reports"
  ]
}
```

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "Role capabilities updated successfully."
}
```

---

### 9.3 Create Custom Role
* **Endpoint:** `POST /api/v1/roles`
* **Auth Required:** `Bearer <TOKEN>`

#### Request Body:
```json
{
  "label": "Regional Coordinator",
  "enabled_capabilities": [
    "access_dashboard",
    "view_peers",
    "regional_data"
  ]
}
```

#### Response (`201 Created`):
```json
{
  "success": true,
  "message": "Custom role created successfully.",
  "data": {
    "id": "019488b0-a3df-7b56-8a9d-b4f0e9876543",
    "role_key": "regional_coordinator",
    "label": "Regional Coordinator",
    "is_system_role": false,
    "enabled_capabilities": [
      "access_dashboard",
      "view_peers",
      "regional_data"
    ]
  }
}
```

---

### 9.4 Update Custom Role
* **Endpoint:** `PUT /api/v1/roles/{id}`
* **Auth Required:** `Bearer <TOKEN>`

#### Request Body:
```json
{
  "label": "Senior Regional Coordinator",
  "enabled_capabilities": [
    "access_dashboard",
    "view_peers",
    "regional_data",
    "access_teams"
  ]
}
```

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "Custom role updated successfully."
}
```

---

### 9.5 Delete Custom Role
* **Endpoint:** `DELETE /api/v1/roles/{id}`
* **Auth Required:** `Bearer <TOKEN>`

#### Response (`200 OK`):
```json
{
  "success": true,
  "message": "Custom role deleted successfully."
}
```

---

## Section 10: PostgreSQL Database Setup Queries

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

-- 5. Performance Indexes
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
