# Brand Partner & Advertiser API Documentation

This document specifies the advertiser and brand partner onboarding, subscription management, payment capture, renewals, and dashboard analytics API contracts.

---

## 1. Overview of Key Changes & Standardization

### Issue 1: Application Status & Payment Metadata
- When payment verification succeeds (`POST /api/v1/advertiser/payments/verify`), the application's status is updated to `"active"`.
- The `next_action` field is set to `null` once the campaign is active and paid.
- The application object in `GET /api/v1/advertiser/applications/{id}` and `GET /api/v1/advertiser/my-applications` includes real-time payment and subscription metadata:
  - `status`: `"active"`
  - `payment_status`: `"captured"`
  - `is_paid`: `true`
  - `subscription_id`: UUID of active subscription
  - `valid_from`: ISO 8601 start timestamp
  - `valid_to`: ISO 8601 expiry timestamp
  - `next_action`: `null`

### Issue 2: Subscription Extension / Campaign Renewal
- Active or expired brand partners can renew or extend their campaign without repeating the onboarding application.
- **Endpoints:**
  - `POST /api/v1/advertiser/subscriptions/{subscription_id}/extend`
  - `POST /api/v1/advertiser/partners/{id}/renew`
- Request body accepts an optional `package_id` and `duration_months` (default `1`).
- Returns a Razorpay order payload (`payment_id`, `razorpay_order_id`, `amount`, `currency`, `checkout_key_id`).
- Upon payment verification via `POST /api/v1/advertiser/payments/verify`, `valid_to` is advanced by the specified duration. If renewed before expiry, the duration is added to the existing `valid_to`. If expired, it activates from current date.

### Issue 3: Standardized Field Names in Dashboard & Analytics
The backend supports both the legacy single-object campaign view and the multi-partner array view, and includes normalized aliases for all metrics:

| Metric Standard Name | Alias / Legacy Key | Description |
| :--- | :--- | :--- |
| `impressions` | `total_views` | Total brand partner card/banner views |
| `unique_reach` | `unique_views` | Unique authenticated users + sessions reached |
| `clicks` | `total_clicks` | Total clicks across all CTAs (website, call, email, share, coupon) |
| `unique_clicks` | `unique_clicks` | Unique users/sessions who clicked |
| `ctr_percentage` | `ctr_percent` | Click-Through-Rate percentage (`unique_clicks / unique_views * 100`) |

---

## 2. API Endpoints

### 2.1 Get My Applications
- **Method:** `GET`
- **URL:** `/api/v1/advertiser/my-applications`
- **Headers:** `Authorization: Bearer {token}`
- **Response:**
```json
{
  "success": true,
  "message": "My applications retrieved successfully.",
  "data": {
    "current_page": 1,
    "data": [
      {
        "application_id": "01940cb3-8a9d-723a-bc42-88226019a101",
        "application_number": "PGU-ADV-202610-0012",
        "company_name": "Apex Global Innovations LLP",
        "package_name": "Premium Brand Partner",
        "package_price": 5000,
        "currency": "INR",
        "status": "active",
        "payment_status": "captured",
        "is_paid": true,
        "subscription_id": "01a1776d-1ee5-70aa-877c-f9788fd128ae",
        "valid_from": "2026-10-26T08:57:18+00:00",
        "valid_to": "2026-11-26T08:57:18+00:00",
        "submitted_at": "2026-10-25T11:20:00+00:00",
        "next_action": null
      }
    ],
    "total": 1
  }
}
```

---

### 2.2 Get Application Details
- **Method:** `GET`
- **URL:** `/api/v1/advertiser/applications/{id}`
- **Headers:** `Authorization: Bearer {token}`
- **Response:**
```json
{
  "success": true,
  "message": "Application details retrieved successfully.",
  "data": {
    "application_id": "01940cb3-8a9d-723a-bc42-88226019a101",
    "application_number": "PGU-ADV-202610-0012",
    "status": "active",
    "payment_status": "captured",
    "is_paid": true,
    "subscription_id": "01a1776d-1ee5-70aa-877c-f9788fd128ae",
    "valid_from": "2026-10-26T08:57:18+00:00",
    "valid_to": "2026-11-26T08:57:18+00:00",
    "source": "mobile_app",
    "applicant": {
      "full_name": "Rahul Sharma",
      "email": "rahul@apextech.com",
      "mobile_number": "9876543210",
      "email_verified": true
    },
    "company": {
      "company_name": "Apex Global Innovations LLP",
      "legal_business_name": "Apex Global Innovations Private Limited",
      "business_type": "LLP",
      "industry": "Technology",
      "website": "https://apextech.com",
      "official_email": "contact@apextech.com",
      "phone": "+91 9876543210",
      "whatsapp": "+91 9876543210",
      "address": "Tech Hub, Sector 4",
      "city": "Mumbai",
      "state": "Maharashtra",
      "country": "India",
      "postal_code": "400001",
      "contact_person_name": "Rahul Sharma",
      "contact_person_designation": "Director",
      "description": "Leading provider of cutting-edge technology solutions."
    },
    "campaign": {
      "advertising_objective": "brand_awareness",
      "target_audience": "Tech founders and entrepreneurs",
      "target_locations": ["Mumbai", "Bengaluru"],
      "preferred_start_date": "2026-10-26",
      "additional_requirements": null
    },
    "package": {
      "id": "ff71ab6b-92eb-45a9-9ddf-124d5e279d39",
      "name": "Premium Brand Partner",
      "duration_months": 1,
      "price": 5000,
      "currency": "INR"
    },
    "assets": [],
    "feedback": null,
    "applicant_response": null,
    "status_history": [
      {
        "status": "active",
        "notes": "Payment captured and campaign activated.",
        "created_at": "2026-10-26T08:57:18+00:00"
      },
      {
        "status": "approved",
        "notes": "Application approved by admin.",
        "created_at": "2026-10-25T14:30:00+00:00"
      }
    ],
    "next_action": null,
    "submitted_at": "2026-10-25T11:20:00+00:00"
  }
}
```

---

### 2.3 Extend Subscription / Renew Campaign
- **Method:** `POST`
- **URLs (both supported):**
  - `/api/v1/advertiser/subscriptions/{subscription_id}/extend`
  - `/api/v1/advertiser/partners/{id}/renew`
- **Headers:**
  - `Content-Type: application/json`
  - `Authorization: Bearer {token}`
- **Request Body:**
```json
{
  "package_id": "ff71ab6b-92eb-45a9-9ddf-124d5e279d39",
  "duration_months": 1
}
```
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "Renewal order created successfully.",
  "data": {
    "payment_id": "01940cc5-412f-7634-871d-55b6ef128ab1",
    "razorpay_order_id": "order_PK98xL2Z110",
    "amount": 500000,
    "currency": "INR",
    "checkout_key_id": "rzp_test_YourKeyHere",
    "business_name": "Peers Global Unity",
    "description": "Advertiser Subscription Renewal - Premium Brand Partner",
    "prefill": {
      "name": "Rahul Sharma",
      "email": "rahul@apextech.com",
      "contact": "9876543210"
    },
    "notes": {
      "subscription_id": "01a1776d-1ee5-70aa-877c-f9788fd128ae",
      "application_id": "01940cb3-8a9d-723a-bc42-88226019a101",
      "is_renewal": "true"
    }
  }
}
```

---

### 2.4 Verify Payment (Initial & Renewal)
- **Method:** `POST`
- **URL:** `/api/v1/advertiser/payments/verify`
- **Headers:**
  - `Content-Type: application/json`
  - `Authorization: Bearer {token}`
- **Request Body:**
```json
{
  "payment_id": "01940cc5-412f-7634-871d-55b6ef128ab1",
  "razorpay_order_id": "order_PK98xL2Z110",
  "razorpay_payment_id": "pay_PK991xK245",
  "razorpay_signature": "0f81d137b0c96c4b2234088a531e2ec2978a5719910e14ac24"
}
```
- **Response (200 OK):**
```json
{
  "success": true,
  "message": "Payment verified successfully.",
  "data": {
    "payment_id": "01940cc5-412f-7634-871d-55b6ef128ab1",
    "payment_status": "captured",
    "subscription_id": "01a1776d-1ee5-70aa-877c-f9788fd128ae",
    "subscription_status": "active",
    "publication_status": "published",
    "next_action": "view_dashboard"
  }
}
```

---

### 2.5 Advertiser Dashboard Summary
- **Method:** `GET`
- **URL:** `/api/v1/advertiser/dashboard`
- **Headers:** `Authorization: Bearer {token}`
- **Response:**
```json
{
  "success": true,
  "message": "Advertiser dashboard retrieved successfully.",
  "data": {
    "current_campaign": {
      "company_name": "Apex Global Innovations LLP",
      "brand_name": "Apex Tech",
      "publication_status": "published",
      "valid_from": "2026-10-26",
      "valid_to": "2026-11-26"
    },
    "metrics": {
      "impressions": 1420,
      "total_views": 1420,
      "unique_reach": 1130,
      "unique_views": 1130,
      "clicks": 185,
      "total_clicks": 185,
      "unique_clicks": 148,
      "ctr_percentage": 13.10,
      "ctr_percent": 13.10
    },
    "application_summary": {
      "total_applications": 1,
      "pending_review": 0,
      "changes_requested": 0,
      "approved": 0,
      "rejected": 0
    },
    "active_partners": 1,
    "pending_payments": 0,
    "active_subscriptions": 1,
    "partners": [
      {
        "brand_partner_id": "01940cc1-2940-7e32-a541-6927a44fbc99",
        "brand_name": "Apex Tech",
        "application_id": "01940cb3-8a9d-723a-bc42-88226019a101",
        "application_status": "active",
        "subscription_status": "active",
        "publication_status": "published",
        "valid_from": "2026-10-26T08:57:18+00:00",
        "valid_to": "2026-11-26T08:57:18+00:00",
        "metrics": {
          "impressions": 1420,
          "total_views": 1420,
          "unique_reach": 1130,
          "unique_views": 1130,
          "clicks": 185,
          "total_clicks": 185,
          "unique_clicks": 148,
          "ctr_percentage": 13.10,
          "ctr_percent": 13.10
        }
      }
    ]
  }
}
```
