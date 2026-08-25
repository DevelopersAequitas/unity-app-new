# Leader App – Backend API & Access Control Specification

This document serves as the comprehensive technical contract between the Flutter Frontend and Backend Engineering teams for the **Leader App**. It details all authentication flows, role-based permission flags, screen & tab hierarchies, and REST API endpoint specifications required to replace frontend mock data with production services.

---

## 1. Authentication & Session Architecture

### 1.1 Login & OTP Flow Overview
```
[Flutter Mobile App] ---> POST /api/v1/auth/send-otp ---> [Backend / SMS & Email Gateway]
[Flutter Mobile App] ---> POST /api/v1/auth/verify-otp ---> [Backend Service]
                                                                  |
                                                                  +---> Validates OTP
                                                                  +---> Returns JWT Access & Refresh Tokens
                                                                  +---> Returns User Profile & Active Circle(s)
                                                                  +---> Returns Dynamic Permission Flags Matrix
```

---

### 1.2 Endpoint: Request Login OTP
- **Route:** `POST /api/v1/auth/send-otp`
- **Auth Required:** No

#### Request Payload
```json
{
  "email_or_phone": "arjun@peersglobal.in"
}
```

#### Response (200 OK)
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

### 1.3 Endpoint: Verify Login OTP
- **Route:** `POST /api/v1/auth/verify-otp`
- **Auth Required:** No

#### Request Payload
```json
{
  "email_or_phone": "arjun@peersglobal.in",
  "otp": "123456"
}
```

#### Response (200 OK)
```json
{
  "success": true,
  "message": "Authentication successful",
  "data": {
    "auth_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "def50200873491...",
    "token_type": "Bearer",
    "expires_in": 86400,
    "user": {
      "id": "usr_987214",
      "name": "Arjun Patel",
      "email": "arjun@peersglobal.in",
      "phone": "+919876543209",
      "role": "circleChair",
      "custom_role_label": null,
      "regional_scope": "Own Circle",
      "member_since": "Jan 2023",
      "avatar_url": "https://cdn.peersglobal.in/avatars/arjun.png",
      "managed_circles": [
        {
          "id": "cir_101",
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

## 2. Granular Role & Permission Access Matrix

| Feature / Action Permission Flag | Circle Chair | Circle Founder | Circle Director | Industry Director | District Exec Director | Country Director | Super Admin |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| `can_access_dashboard` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_view_overall_revenue` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_review_pending_peers` | `true` | `true` | `true` | `false` | `true` | `false` | `false` |
| `can_access_peers_tab` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_add_edit_peer` | `false` | `true` | `true` | `false` | `true` | `true` | `true` |
| `can_send_wishes` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_access_teams_tab` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_manage_circles` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_access_finance_tab` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_modify_finance_settings` | `false` | `false` | `false` | `false` | `true` | `true` | `true` |
| `can_issue_coins` | `false` | `false` | `false` | `false` | `false` | `true` | `true` |
| `can_access_reports_tab` | `true` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_submit_reports` | `true` | `true` | `true` | `false` | `false` | `false` | `false` |
| `can_export_peer_data` | `false` | `true` | `true` | `true` | `true` | `true` | `true` |
| `can_export_financial_data` | `false` | `false` | `false` | `false` | `true` | `true` | `true` |
| `can_export_global_data` | `false` | `false` | `false` | `false` | `false` | `false` | `true` |
| `can_access_role_management` | `false` | `false` | `false` | `false` | `false` | `false` | `true` |

---

## 3. Screen & Tab Breakdown with Permission Flags

### 🏠 TAB 0: Dashboard Screen (`/home?tab=0`)

#### Sections & Visibility Flags:
1. **Top App Bar**:
   - `show_circle_selector`: `true` (if user manages > 1 circle or role is Industry Director/Country Director/Super Admin)
   - `show_notification_badge`: `true`
   - `show_role_badge`: `true`
2. **Overall Revenue Banner**:
   - `is_visible`: `permissions.can_view_overall_revenue`
   - Metrics: `overall_revenue`, `overall_deals_closed`
3. **Hero Overview Card**:
   - `is_visible`: `true`
   - Metrics: `impact_count`, `deals_amount`, `p2p_meetings_count`
4. **Key Metrics 2x2 Grid**:
   - `is_visible`: `true`
   - Tiles:
     - `total_peers` (Tap navigates to Peers Tab)
     - `referrals_count` (Tap navigates to Referrals View `/referrals`)
     - `testimonials_count` (Tap navigates to Testimonials View `/testimonials`)
     - `coins_count` (Tap navigates to Peers By Coins View `/peers-by-coins`)
5. **Top 5 Impacters List**:
   - `is_visible`: `true`
   - Fields: Rank (1 to 5), Name, Company, Location, Lives count, Coins count
6. **Pending Peers Alert Card**:
   - `is_visible`: `permissions.can_review_pending_peers`
   - Action: "Review" button redirects to Peers Tab with Pending filter active.

---

### 👥 TAB 1: Peers Screen (`/home?tab=1`)

#### Sub-tab 1.1: Peers Directory
- **Search & Status Filters**: `All`, `Active`, `Needs Attention`, `At Risk`, `Pending`
- **Sort Options**: `Impact`, `Deals`, `Coins`, `Attendance`
- **Peer List Item**:
  - Avatar / Initials
  - Peer Name & Status badge
  - Company & Designation
  - Circle & Location
  - Dynamic Sort Metric badge (Lives / Deals in ₹ / Coins / Attendance %)
  - `can_view_peer_profile`: `true` (Tapping item opens `/peer-profile`)

#### Sub-tab 1.2: Celebrations
- **Birthdays This Week List**: Peer Name, Company, Date (`is_today: boolean`), "Wish" button
- **Anniversaries This Week List**: Peer Name, Company, Years Completed, "Wish" button
- **Permission Flag**: `can_send_wishes: true/false`

#### Screen: Peer Detailed Profile (`/peer-profile`)
- **Sections**:
  - `intro_video_url`: Video player / URL
  - `contact_details`: Phone, Email, Location (`can_view_peer_contact_info`)
  - `attendance_percentage`: Progress bar (e.g. 92%)
  - `deals_closed`: Total formatted currency
  - `coin_balance`: Number of coins
  - `received_testimonials`: List of peer endorsement cards
  - `referrals_history`: List of referrals given & received

---

### 🏢 TAB 2: Teams & Circles Screen (`/home?tab=2`)

- **Permission Lock Check**:
  - If `permissions.can_access_teams_tab == false`: Displays locked access screen with missing capability notice.
- **When Unlocked (`true`)**:
  - **Summary Metrics**: `total_circles`, `avg_health_percentage`, `total_peers`, `total_revenue`
  - **Industry Filter**: `All Industries`, `Manufacturing`, `Real Estate`, `Technology`, `Healthcare`, `Startups`
  - **Circles List**:
    - Circle Name, Category, Location, Peer Count
    - Health % Indicator
    - Revenue Generated
    - Circle Chair Name
    - Tapping opens Circle Details (`/circle-details`)

#### Screen: Circle Details (`/circle-details`)
- Circle Overview (Chair info, Founder details, Launch date)
- KPI Metrics (Attendance avg, Revenue, Deals count)
- Member Directory of that circle
- `can_assign_circle_chair`: `true/false`

---

### 💳 TAB 3: Finance & Accounts Screen (`/home?tab=3`)

- **Permission Lock Check**:
  - If `permissions.can_access_finance_tab == false`: Displays locked access card.
- **When Unlocked (`true`)**:
  - **KPI Cards**: `total_collections`, `total_dues_pending`, `projected_annual_revenue`, `coin_issuances_total`
  - **Circle-wise Financial Ledger Breakdown**
  - **Recent Transactions & Dues Table**:
    - Peer Name, Circle, Amount, Status (`Paid`, `Overdue`, `Pending`), Due Date
  - **Actions**:
    - `can_modify_finance_settings`: Edit fee tiers / dues rules
    - `can_issue_coins`: Send platform coins directly

---

### 📊 TAB 4: Reports & Analytics Screen (`/home?tab=4`)

- **Sub-tab 4.1: Submit Report** (`can_submit_reports: true`):
  - Form: Report Type (`Weekly` / `Monthly`), Meeting Date, Attendance Count, Deals Done, Discussion Notes, Action Items.
- **Sub-tab 4.2: Report History**:
  - List of past submitted reports with status (`Approved`, `Under Review`, `Pending`).
- **Sub-tab 4.3: Performance Analytics**:
  - Attendance % Spline Graph (6-month trend data points: `[{ "month": "Feb", "value": 72.0 }, ...]`)
  - Revenue & Deal Growth charts
- **Sub-tab 4.4: Export Center**:
  - `Export Peers` (`can_export_peer_data`)
  - `Export Financials` (`can_export_financial_data`)
  - `Global Export` (`can_export_global_data`)

---

### ⚙️ TAB 5: Role & Permission Management Screen (`/role-management`)
*(Accessible only if `permissions.can_access_role_management == true`)*
- View all system & dynamic custom roles.
- Add Custom Role (`POST /api/v1/roles`)
- Rename Role (`PUT /api/v1/roles/:id`)
- Delete Custom Role (`DELETE /api/v1/roles/:id`)
- Toggle Matrix for the **12 Granular Capabilities**:
  1. `access_dashboard`
  2. `access_teams`
  3. `access_finance`
  4. `regional_data`
  5. `view_peers`
  6. `manage_peers`
  7. `request_actions`
  8. `view_reports`
  9. `manage_finance`
  10. `coin_payouts`
  11. `manage_roles`
  12. `system_configs`

---

## 4. REST API Endpoints Specification

### 4.1 Dashboard Endpoints

#### GET `/api/v1/dashboard/metrics`
- **Query Params:** `circle_id` (optional, string)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "circle_id": "cir_101",
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

#### GET `/api/v1/dashboard/top-impacters`
- **Query Params:** `circle_id` (optional, string)
- **Response (200 OK):**
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

### 4.2 Peers & Celebrations Endpoints

#### GET `/api/v1/peers`
- **Query Params:** `circle_id` (string), `status` (string), `sort` (string), `search` (string)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "peer_001",
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

#### GET `/api/v1/peers/:id`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": "peer_001",
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

#### GET `/api/v1/peers/celebrations`
- **Query Params:** `circle_id` (string)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "birthdays": [
      {
        "id": "cel_01",
        "peer_id": "peer_001",
        "name": "Siddharth Verma",
        "company": "Apex Dynamics",
        "date_formatted": "Today, 25 Aug",
        "is_today": true
      }
    ],
    "anniversaries": [
      {
        "id": "cel_02",
        "peer_id": "peer_003",
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

#### POST `/api/v1/peers/:id/send-wish`
- **Request Payload:**
```json
{
  "type": "birthday",
  "message": "Wishing you a very happy birthday! Have a wonderful year ahead."
}
```
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "Wish sent to Siddharth Verma successfully!"
}
```

---

### 4.3 Teams & Circles Endpoints

#### GET `/api/v1/teams/summary`
- **Response (200 OK):**
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

#### GET `/api/v1/teams/circles`
- **Query Params:** `industry` (string), `status` (string), `search` (string)
- **Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "cir_101",
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

#### GET `/api/v1/teams/circles/:id`
- **Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": "cir_101",
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

### 4.4 Finance Endpoints

#### GET `/api/v1/finance/metrics`
- **Query Params:** `circle_id` (optional, string)
- **Response (200 OK):**
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

#### GET `/api/v1/finance/transactions`
- **Query Params:** `circle_id` (string), `status` (string)
- **Response (200 OK):**
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

### 4.5 Reports & Analytics Endpoints

#### GET `/api/v1/reports`
- **Query Params:** `circle_id` (string), `type` (weekly | monthly)
- **Response (200 OK):**
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

#### POST `/api/v1/reports`
- **Request Payload:**
```json
{
  "circle_id": "cir_101",
  "report_type": "Monthly",
  "period": "August 2026",
  "attendance_percentage": 94,
  "deals_closed_value": "₹18.5L",
  "content": "August monthly meeting conducted successfully with 52 peers present.",
  "action_items": "Follow up on pending renewals for Q3."
}
```
- **Response (201 Created):**
```json
{
  "success": true,
  "message": "Report submitted successfully!",
  "data": {
    "report_id": "rep_102"
  }
}
```

#### GET `/api/v1/reports/attendance-trend`
- **Query Params:** `circle_id` (string)
- **Response (200 OK):**
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

### 4.6 Referrals, Testimonials & Coins Endpoints

#### GET `/api/v1/referrals`
- **Query Params:** `circle_id` (string), `status` (string)
- **Response (200 OK):**
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

#### GET `/api/v1/testimonials`
- **Query Params:** `circle_id` (string)
- **Response (200 OK):**
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

#### GET `/api/v1/peers-by-coins`
- **Response (200 OK):**
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
      }
    ]
  }
}
```

---

### 4.7 Notifications & Role Management Endpoints

#### GET `/api/v1/notifications`
- **Response (200 OK):**
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

#### POST `/api/v1/notifications/mark-read`
- **Request Payload:**
```json
{
  "notification_ids": ["notif_01"] // or ["all"]
}
```

#### GET `/api/v1/roles/matrix`
- **Response (200 OK):**
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

#### PUT `/api/v1/roles/matrix`
- **Request Payload:**
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

#### POST `/api/v1/roles`
- **Request Payload:**
```json
{
  "label": "Regional Manager",
  "enabled_capabilities": ["access_dashboard", "view_peers", "regional_data"]
}
```

#### DELETE `/api/v1/roles/:id`
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "Custom role deleted successfully."
}
```

---

## 5. Standard Error Handling Envelope

For all failure cases (HTTP 400, 401, 403, 404, 500), backend must return a consistent JSON response:

```json
{
  "success": false,
  "error_code": "UNAUTHORIZED_ACCESS",
  "message": "You do not have permission to access the Finance tab.",
  "details": null
}
```
