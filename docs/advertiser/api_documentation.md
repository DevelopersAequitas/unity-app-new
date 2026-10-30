# PEERS GLOBAL UNITY — ADVERTISER MANAGEMENT API DOCUMENTATION
**Version:** 1.0  
**Environment:** Staging / Production  
**Base URL:** `https://your-domain.com/api/v1`  
**Authentication Scheme:** `Bearer <Sanctum_Token>` (where required)

---

## Table of Contents
1. [Overview & Architecture](#1-overview--architecture)
2. [Response Envelope Standard](#2-response-envelope-standard)
3. [Public Package & Onboarding APIs](#3-public-package--onboarding-apis)
4. [Public Application & Email Verification APIs](#4-public-application--email-verification-apis)
5. [Authenticated Member Advertiser APIs](#5-authenticated-member-advertiser-apis)
6. [Razorpay Payment Lifecycle APIs](#6-razorpay-payment-lifecycle-apis)
7. [Advertiser Campaign Management APIs](#7-advertiser-campaign-management-apis)
8. [Advertiser Performance Analytics APIs](#8-advertiser-performance-analytics-apis)
9. [Admin Panel Advertiser APIs](#9-admin-panel-advertiser-apis)
10. [Admin Web Blade Routes Summary](#10-admin-web-blade-routes-summary)

---

## 1. Overview & Architecture

The Advertiser Management Module allows businesses and entrepreneurs to discover advertising packages, apply, verify contacts, upload creatives, pay through Razorpay, configure campaigns, and view campaign analytics delivered through Peers Global Unity's feed engine.

### Strict Non-Negotiables Enforced
1. **Existing Ads Engine Preservation:** Campaign ads are delivered via the existing `ads` table and feed injection algorithms. View/click tracking is recorded in `ad_views` and `ad_clicks` with 5-second deduplication.
2. **Auditable Relationship:** Linking between campaigns and feed ads is maintained via `advertiser_campaign_ads`.
3. **No In-Place Modification of Legacy Data:** Brand Partner records and legacy Ad Bookings remain isolated and safe.
4. **Idempotent State Transitions:** Webhooks and payment callbacks cannot duplicate payments or subscriptions.

---

## 2. Response Envelope Standard

All JSON responses follow the standard application envelope:

### Success Response
```json
{
  "success": true,
  "data": { ... },
  "message": "Human readable success message."
}
```

### Validation Error Response (HTTP 422)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "destination_url": ["The destination url format is invalid."]
  }
}
```

### Authorization Error Response (HTTP 401 / 403)
```json
{
  "success": false,
  "message": "Unauthorized access or action forbidden."
}
```

---

## 3. Public Package & Onboarding APIs

### 3.1 List Active Packages
* **Endpoint:** `GET /api/v1/advertiser/packages`
* **Auth:** Public
* **Query Parameters:**
  * `sort`: `price_asc` | `price_desc` | `duration`
* **Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "9d8e7f6a-5b4c-3d2e-1f0a-9b8c7d6e5f4a",
      "name": "Standard Advertiser (1 Month)",
      "slug": "standard-1-month",
      "description": "One-month advertising package",
      "duration_months": 1,
      "price": 5000.0,
      "currency": "INR",
      "features": [
        "Brand Directory Listing",
        "Profile Badge",
        "Performance Analytics"
      ],
      "banner_placement": "brand_partner_listing",
      "max_banner_assets": 5,
      "is_active": true
    },
    {
      "id": "8a7b6c5d-4e3f-2a1b-0c9d-8e7f6a5b4c3d",
      "name": "Growth Advertiser (6 Months)",
      "slug": "growth-6-months",
      "description": "Six-month advertising package",
      "duration_months": 6,
      "price": 25000.0,
      "currency": "INR",
      "features": [
        "Brand Directory Listing",
        "Sponsored Placement",
        "Performance Analytics",
        "Priority Support"
      ],
      "banner_placement": "brand_partner_listing",
      "max_banner_assets": 10,
      "is_active": true
    }
  ],
  "message": "Packages retrieved successfully."
}
```

### 3.2 Get Banner Settings
* **Endpoint:** `GET /api/v1/advertiser/banner-config`
* **Auth:** Public
* **Response:**
```json
{
  "success": true,
  "data": {
    "enabled": true,
    "title": "Become a Brand Partner",
    "description": "Grow your business with Peers Global Unity.",
    "cta_text": "Apply Now",
    "destination": "advertiser_packages"
  }
}
```

---

## 4. Public Application & Email Verification APIs

### 4.1 Submit Advertiser Application
* **Endpoint:** `POST /api/v1/advertiser/applications`
* **Auth:** Public / Authenticated (User ID auto-linked if token provided)
* **Headers:** `Content-Type: application/json`
* **Request Body:**
```json
{
  "package_id": "8a7b6c5d-4e3f-2a1b-0c9d-8e7f6a5b4c3d",
  "applicant_name": "Ramesh Patel",
  "applicant_email": "ramesh@example.com",
  "applicant_phone": "+919876543210",
  "company_name": "Patel Technologies Pvt Ltd",
  "company_legal_name": "Patel Technologies Private Limited",
  "company_type": "private_limited",
  "industry": "Information Technology",
  "company_website": "https://pateltech.com",
  "company_email": "contact@pateltech.com",
  "company_phone": "+919876543210",
  "whatsapp_number": "+919876543210",
  "address_line": "101, Titanium City Center, Prahlad Nagar",
  "city": "Ahmedabad",
  "state": "Gujarat",
  "country": "India",
  "postal_code": "380015",
  "contact_person_name": "Ramesh Patel",
  "contact_person_designation": "Managing Director",
  "brand_description": "Leading enterprise cloud and software solutions provider.",
  "target_audience": "Founders, Entrepreneurs, and SMEs",
  "target_locations": ["Gujarat", "Maharashtra"],
  "advertising_objective": "brand_awareness",
  "requested_start_date": "2026-11-01",
  "consent_to_terms": true,
  "consent_to_privacy": true,
  "marketing_consent": true
}
```
* **Response (HTTP 201):**
```json
{
  "success": true,
  "data": {
    "id": "7b8c9d0e-1f2a-3b4c-5d6e-7f8a9b0c1d2e",
    "application_number": "ADV-2026-000001",
    "status": "pending_email_verification",
    "applicant_email_verified": false,
    "next_step": "verify_email"
  },
  "message": "Application submitted. Please verify your email address to proceed."
}
```

### 4.2 Request / Resend Email Verification OTP
* **Endpoint:** `POST /api/v1/advertiser/applications/{id}/email-verification`
* **Endpoint:** `POST /api/v1/advertiser/applications/{id}/email-verification/resend`
* **Response:**
```json
{
  "success": true,
  "data": {
    "application_id": "7b8c9d0e-1f2a-3b4c-5d6e-7f8a9b0c1d2e",
    "email": "r***h@example.com",
    "expires_in_seconds": 600
  },
  "message": "Verification code has been dispatched to your email."
}
```

### 4.3 Confirm Email Verification OTP
* **Endpoint:** `POST /api/v1/advertiser/applications/{id}/email-verification/confirm`
* **Request Body:**
```json
{
  "otp": "482910"
}
```
* **Response:**
```json
{
  "success": true,
  "data": {
    "application_id": "7b8c9d0e-1f2a-3b4c-5d6e-7f8a9b0c1d2e",
    "status": "submitted",
    "verified": true
  },
  "message": "Email verified successfully. Your application is under review."
}
```

### 4.4 Upload Application Asset (Creative/Logo)
* **Endpoint:** `POST /api/v1/advertiser/applications/{id}/assets`
* **Headers:** `Content-Type: multipart/form-data`
* **Form Fields:**
  * `file`: (Binary image file: PNG, JPG, WEBP, max 5MB)
  * `asset_type`: `logo` | `banner` | `campaign_creative` | `cover_image`
  * `alt_text`: "Company Banner Creative"
* **Response:**
```json
{
  "success": true,
  "data": {
    "id": "6c7d8e9f-0a1b-2c3d-4e5f-6a7b8c9d0e1f",
    "asset_type": "banner",
    "original_filename": "patel-tech-banner.png",
    "mime_type": "image/png",
    "file_size_bytes": 245890,
    "review_status": "pending"
  },
  "message": "Asset uploaded and attached to application."
}
```

---

## 5. Authenticated Member Advertiser APIs

### 5.1 My Applications List
* **Endpoint:** `GET /api/v1/advertiser/my-applications`
* **Auth:** Required (`Bearer <token>`)
* **Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "7b8c9d0e-1f2a-3b4c-5d6e-7f8a9b0c1d2e",
        "application_number": "ADV-2026-000001",
        "company_name": "Patel Technologies Pvt Ltd",
        "status": "approved",
        "is_paid": false,
        "payment_status": "pending",
        "payment_eligible": true,
        "package": {
          "name": "Growth Advertiser (6 Months)",
          "price": 25000.0,
          "duration_months": 6
        },
        "created_at": "2026-10-29T10:00:00Z"
      }
    ],
    "total": 1
  }
}
```

### 5.2 Application Details
* **Endpoint:** `GET /api/v1/advertiser/applications/{id}`
* **Auth:** Required

### 5.3 Edit Application Draft / Corrections
* **Endpoint:** `PATCH /api/v1/advertiser/applications/{id}`
* **Auth:** Required
* **Request Body:**
```json
{
  "company_website": "https://pateltechnologies.in",
  "brand_description": "Updated brand copy addressing requested reviewer corrections."
}
```

### 5.4 Withdraw Application
* **Endpoint:** `POST /api/v1/advertiser/applications/{id}/withdraw`
* **Auth:** Required
* **Request Body:**
```json
{
  "reason": "Advertising plans postponed to next quarter."
}
```

### 5.5 Member Dashboard Summary
* **Endpoint:** `GET /api/v1/advertiser/dashboard`
* **Auth:** Required
* **Response:**
```json
{
  "success": true,
  "data": {
    "applications": {
      "total_applications": 2,
      "pending_review": 0,
      "changes_requested": 0,
      "approved": 1,
      "rejected": 0
    },
    "subscriptions": {
      "total": 1,
      "active": 1,
      "pending_payment": 0
    },
    "primary_partner": {
      "brand_partner_id": "5a4b3c2d-1e0f-9a8b-7c6d-5e4f3a2b1c0d",
      "brand_name": "Patel Technologies Pvt Ltd",
      "publication_status": "published",
      "metrics": {
        "total_views": 14250,
        "unique_views": 10200,
        "total_clicks": 480,
        "unique_clicks": 390,
        "ctr_percent": 3.82
      }
    }
  }
}
```

### 5.6 Subscriptions Listing & Details
* **List:** `GET /api/v1/advertiser/subscriptions`
* **Details:** `GET /api/v1/advertiser/subscriptions/{id}`
* **Auth:** Required

---

## 6. Razorpay Payment Lifecycle APIs

### 6.1 Create Razorpay Payment Order
* **Endpoint:** `POST /api/v1/advertiser/applications/{id}/payment-order`
* **Endpoint:** `POST /api/v1/advertiser/subscriptions/{id}/payment-order`
* **Auth:** Required
* **Security:** Amount is strictly determined server-side from the approved package quote snapshot.
* **Request Body:**
```json
{
  "idempotency_key": "ORDER-ADV-20261029-7B8C"
}
```
* **Response (HTTP 200):**
```json
{
  "success": true,
  "data": {
    "payment_id": "4b3c2d1e-0f9a-8b7c-6d5e-4f3a2b1c0d9e",
    "provider_order_id": "order_RN39dkfL92kds1",
    "amount": 25000.0,
    "amount_in_paise": 2500000,
    "currency": "INR",
    "razorpay_key_id": "rzp_test_XXXXXXXXXXXXXX",
    "company_name": "Patel Technologies Pvt Ltd",
    "description": "Advertising Subscription - Growth Advertiser (6 Months)"
  },
  "message": "Payment order initialized successfully."
}
```

### 6.2 Verify Razorpay Checkout Signature
* **Endpoint:** `POST /api/v1/advertiser/payments/verify`
* **Auth:** Required
* **Request Body:**
```json
{
  "payment_id": "4b3c2d1e-0f9a-8b7c-6d5e-4f3a2b1c0d9e",
  "razorpay_order_id": "order_RN39dkfL92kds1",
  "razorpay_payment_id": "pay_RN39zxxkL1092a",
  "razorpay_signature": "e81d7f...a9c8b74"
}
```
* **Response (HTTP 200):**
```json
{
  "success": true,
  "data": {
    "payment_id": "4b3c2d1e-0f9a-8b7c-6d5e-4f3a2b1c0d9e",
    "status": "captured",
    "subscription_id": "3a2b1c0d-9e8f-7a6b-5c4d-3e2f1a0b9c8d",
    "subscription_status": "active",
    "starts_at": "2026-10-29T11:00:00Z",
    "expires_at": "2027-04-29T11:00:00Z"
  },
  "message": "Payment verified and subscription activated successfully."
}
```

### 6.3 Check Payment Status
* **Endpoint:** `GET /api/v1/advertiser/payments/{id}`
* **Auth:** Required

### 6.4 Razorpay Webhook
* **Endpoint:** `POST /api/v1/webhooks/razorpay/advertiser`
* **Auth:** Validated via HMAC-SHA256 signature header `X-Razorpay-Signature`.
* **Idempotency:** Protected against replay attacks using `provider_event_id`.

---

## 7. Advertiser Campaign Management APIs

### 7.1 List My Campaigns
* **Endpoint:** `GET /api/v1/advertiser/campaigns`
* **Auth:** Required
* **Query Parameters:**
  * `status`: `draft` | `pending_review` | `changes_requested` | `approved` | `active` | `paused` | `completed`
  * `per_page`: `10` | `20` | `50`
* **Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": "2a1b0c9d-8e7f-6a5b-4c3d-2e1f0a9b8c7d",
        "campaign_number": "CMP-2026-000001",
        "name": "Diwali Cloud Upgrade Offer",
        "objective": "lead_generation",
        "status": "active",
        "cta_text": "Claim Offer",
        "destination_url": "https://pateltech.com/diwali-offer",
        "requested_starts_at": "2026-11-01T00:00:00Z",
        "requested_ends_at": "2026-11-30T23:59:59Z",
        "published_at": "2026-10-29T11:30:00Z",
        "created_at": "2026-10-29T11:00:00Z"
      }
    ],
    "total": 1
  },
  "message": "Campaigns retrieved successfully."
}
```

### 7.2 Create Campaign Draft
* **Endpoint:** `POST /api/v1/advertiser/campaigns`
* **Auth:** Required
* **Request Body:**
```json
{
  "name": "Diwali Cloud Upgrade Offer",
  "objective": "lead_generation",
  "description": "Exclusive 30% discount on cloud hosting for Peers Global Unity members.",
  "placement_ids": ["feed"],
  "asset_ids": ["6c7d8e9f-0a1b-2c3d-4e5f-6a7b8c9d0e1f"],
  "cta_text": "Claim Offer",
  "destination_url": "https://pateltech.com/diwali-offer",
  "target_audience": "Entrepreneurs and SMBs",
  "target_locations": ["Gujarat", "Maharashtra"],
  "requested_starts_at": "2026-11-01T00:00:00Z",
  "requested_ends_at": "2026-11-30T23:59:59Z",
  "subscription_id": "3a2b1c0d-9e8f-7a6b-5c4d-3e2f1a0b9c8d"
}
```
* **Response (HTTP 201):**
```json
{
  "success": true,
  "data": {
    "id": "2a1b0c9d-8e7f-6a5b-4c3d-2e1f0a9b8c7d",
    "campaign_number": "CMP-2026-000001",
    "name": "Diwali Cloud Upgrade Offer",
    "status": "draft",
    "created_at": "2026-10-29T11:00:00Z"
  },
  "message": "Campaign draft created successfully."
}
```

### 7.3 Get Campaign Details
* **Endpoint:** `GET /api/v1/advertiser/campaigns/{id}`
* **Auth:** Required (Ownership verified)
* **Response:**
```json
{
  "success": true,
  "data": {
    "id": "2a1b0c9d-8e7f-6a5b-4c3d-2e1f0a9b8c7d",
    "campaign_number": "CMP-2026-000001",
    "name": "Diwali Cloud Upgrade Offer",
    "objective": "lead_generation",
    "description": "Exclusive 30% discount on cloud hosting for Peers Global Unity members.",
    "status": "active",
    "cta_text": "Claim Offer",
    "destination_url": "https://pateltech.com/diwali-offer",
    "admin_notes": "Approved for general feed placement.",
    "applicant_feedback": null,
    "requested_placements": ["feed"],
    "target_audience": "Entrepreneurs and SMBs",
    "target_locations": ["Gujarat", "Maharashtra"],
    "requested_starts_at": "2026-11-01T00:00:00Z",
    "requested_ends_at": "2026-11-30T23:59:59Z",
    "approved_at": "2026-10-29T11:30:00Z",
    "published_at": "2026-10-29T11:30:00Z",
    "subscription": {
      "id": "3a2b1c0d-9e8f-7a6b-5c4d-3e2f1a0b9c8d",
      "package_name": "Growth Advertiser (6 Months)",
      "status": "active",
      "expires_at": "2027-04-29T11:00:00Z"
    },
    "ads": [
      {
        "ad_id": "1f0e9d8c-7b6a-5f4e-3d2c-1b0a9f8e7d6c",
        "title": "Diwali Cloud Upgrade Offer",
        "placement": "feed",
        "is_active": true,
        "status": "published"
      }
    ]
  },
  "message": "Campaign details retrieved successfully."
}
```

### 7.4 Edit Campaign Draft
* **Endpoint:** `PATCH /api/v1/advertiser/campaigns/{id}`
* **Auth:** Required
* **Allowed States:** `draft`, `changes_requested`

### 7.5 Submit Campaign for Review
* **Endpoint:** `POST /api/v1/advertiser/campaigns/{id}/submit`
* **Auth:** Required
* **Request Body:**
```json
{
  "submission_note": "Ready for admin verification."
}
```
* **Response:**
```json
{
  "success": true,
  "data": {
    "id": "2a1b0c9d-8e7f-6a5b-4c3d-2e1f0a9b8c7d",
    "status": "pending_review"
  },
  "message": "Campaign submitted for review successfully."
}
```

### 7.6 Cancel Campaign
* **Endpoint:** `POST /api/v1/advertiser/campaigns/{id}/cancel`
* **Auth:** Required
* **Request Body:**
```json
{
  "reason": "Campaign discontinued."
}
```

---

## 8. Advertiser Performance Analytics APIs

### 8.1 Campaign Scoped Performance Analytics
* **Endpoint:** `GET /api/v1/advertiser/campaigns/{id}/analytics`
* **Auth:** Required (Strictly isolated to owned campaign ads)
* **Query Parameters:**
  * `from`: `YYYY-MM-DD` (Optional, defaults to 30 days ago)
  * `to`: `YYYY-MM-DD` (Optional, defaults to today)
* **Response:**
```json
{
  "success": true,
  "data": {
    "campaign_id": "2a1b0c9d-8e7f-6a5b-4c3d-2e1f0a9b8c7d",
    "campaign_number": "CMP-2026-000001",
    "campaign_name": "Diwali Cloud Upgrade Offer",
    "status": "active",
    "period": {
      "from": "2026-10-01",
      "to": "2026-10-29"
    },
    "views": 14250,
    "unique_views": 10200,
    "clicks": 480,
    "unique_clicks": 390,
    "ctr_percent": 3.82,
    "daily": [
      {
        "date": "2026-10-28",
        "views": 620,
        "clicks": 24
      },
      {
        "date": "2026-10-29",
        "views": 750,
        "clicks": 31
      }
    ]
  },
  "message": "Campaign analytics retrieved successfully."
}
```

### 8.2 Advertiser Timeseries Trend
* **Endpoint:** `GET /api/v1/advertiser/analytics/timeseries`
* **Auth:** Required
* **Query Parameters:**
  * `metric`: `views` | `clicks`
  * `from`: `YYYY-MM-DD`
  * `to`: `YYYY-MM-DD`
  * `interval`: `day`

---

## 9. Admin Panel Advertiser APIs

*All admin endpoints require Sanctum bearer token with authorized admin roles (`global_admin`, `marketing_team`, `analytics_team`).*

### 9.1 Package Administration
* `GET /api/v1/admin/advertiser/packages` — List all packages (with draft/inactive filter)
* `POST /api/v1/admin/advertiser/packages` — Create package
* `GET /api/v1/admin/advertiser/packages/{id}` — Package details
* `PATCH /api/v1/admin/advertiser/packages/{id}` — Update package specifications
* `PATCH /api/v1/admin/advertiser/packages/{id}/status` — Toggle active status

### 9.2 Application Review Administration
* `GET /api/v1/admin/advertiser/applications` — Applications review queue with status filters
* `GET /api/v1/admin/advertiser/applications/{id}` — Application deep review
* `POST /api/v1/admin/advertiser/applications/{id}/start-review` — Mark under review
* `POST /api/v1/admin/advertiser/applications/{id}/request-corrections` — Send correction request
* `POST /api/v1/admin/advertiser/applications/{id}/approve` — Approve for payment
* `POST /api/v1/admin/advertiser/applications/{id}/reject` — Reject with reason
* `PATCH /api/v1/admin/advertiser/applications/{app_id}/assets/{asset_id}` — Approve/reject individual creative asset
* `GET /api/v1/admin/advertiser/applications/{id}/history` — View audit status history

### 9.3 Subscription & Payment Administration
* `GET /api/v1/admin/advertiser/subscriptions` — Subscriptions table
* `GET /api/v1/admin/advertiser/subscriptions/{id}` — Subscription details
* `GET /api/v1/admin/advertiser/payments` — Verified payments ledger
* `GET /api/v1/admin/advertiser/payments/{id}` — Payment details
* `POST /api/v1/admin/advertiser/payments/{id}/reconcile` — Reconcile with Razorpay

### 9.4 Campaign Review & Delivery Administration
* `GET /api/v1/admin/advertiser/campaigns` — Filtered campaign review list
* `GET /api/v1/admin/advertiser/campaigns/{id}` — Campaign details & analytics
* `POST /api/v1/admin/advertiser/campaigns/{id}/review` — Approve, Reject, or Request Changes
  * **Request Body:**
    ```json
    {
      "decision": "approved",
      "notes": "Approved for placement in main mobile feed."
    }
    ```
* `POST /api/v1/admin/advertiser/campaigns/{id}/publish` — Explicitly trigger feed ad generation
* `POST /api/v1/admin/advertiser/campaigns/{id}/pause` — Pause delivery
* `POST /api/v1/admin/advertiser/campaigns/{id}/resume` — Resume delivery

### 9.5 Admin Analytics Summary
* `GET /api/v1/admin/advertiser/analytics/summary?from=YYYY-MM-DD&to=YYYY-MM-DD`
* Returns aggregated commercial funnel, revenue numbers, and feed engagement totals.

---

## 10. Admin Web Blade Routes Summary

The Admin Panel includes interactive Blade screens protected by `admin.role` middleware:

| Route Name | Method | URL Path | Description |
|:---|:---:|:---|:---|
| `admin.advertiser.packages.index` | `GET` | `/admin/advertiser/packages` | Manage advertising packages |
| `admin.advertiser.packages.create` | `GET` | `/admin/advertiser/packages/create` | New package form |
| `admin.advertiser.packages.edit` | `GET` | `/admin/advertiser/packages/{package}/edit` | Edit package details |
| `admin.advertiser.applications.index` | `GET` | `/admin/advertiser/applications` | Applications review queue with KPI filters |
| `admin.advertiser.applications.show` | `GET` | `/admin/advertiser/applications/{application}` | Review application details & creatives |
| `admin.advertiser.campaigns.index` | `GET` | `/admin/advertiser/campaigns` | **Campaigns management & queue** |
| `admin.advertiser.campaigns.show` | `GET` | `/admin/advertiser/campaigns/{campaign}` | **Campaign details, review modals & analytics** |
| `admin.advertiser.campaigns.review` | `POST` | `/admin/advertiser/campaigns/{campaign}/review` | **Approve / Reject / Changes modal action** |
| `admin.advertiser.campaigns.publish` | `POST` | `/admin/advertiser/campaigns/{campaign}/publish` | **Publish campaign to Ads feed** |
| `admin.advertiser.campaigns.pause` | `POST` | `/admin/advertiser/campaigns/{campaign}/pause` | **Pause delivery** |
| `admin.advertiser.campaigns.resume` | `POST` | `/admin/advertiser/campaigns/{campaign}/resume` | **Resume delivery** |
| `admin.advertiser.subscriptions.index` | `GET` | `/admin/advertiser/subscriptions` | Active & expired subscriptions |
| `admin.advertiser.payments.index` | `GET` | `/admin/advertiser/payments` | Verified payments ledger |
| `admin.advertiser.analytics.index` | `GET` | `/admin/advertiser/analytics` | **Advertiser analytics, funnel & KPIs** |
| `admin.advertiser.analytics.export` | `GET` | `/admin/advertiser/analytics/export` | **Export CSV performance report** |
