# Peers Global — Ask / Requirement Discovery System
## Complete API Architecture & Developer Integration Guide

> **Version:** 1.0.0  
> **Environment Base URL:** `http://localhost:8000/api` (Local) / `https://dev.peersunity.com/api` (Dev)  
> **Target Products:** Mobile App (Flutter), Web, Admin Panel  
> **Architecture Pattern:** Common Ask Engine powering **Collaboration**, **Referral**, and **Get Help** journeys.

---

## Table of Contents
1. [Authentication & Headers](#authentication--headers)
2. [Canva Screens & API Mapping](#canva-screens--api-mapping)
3. [Canonical Peer Data Contract](#canonical-peer-data-contract)
4. [Part 1: Dynamic Configuration APIs (APIs 1–3)](#part-1-dynamic-configuration-apis)
5. [Part 2: Ask Creation, Editing & Publishing (APIs 4–10)](#part-2-ask-creation-editing--publishing)
6. [Part 3: Ask Listing & Details (APIs 11–14)](#part-3-ask-listing--details)
7. [Part 4: Matching System (APIs 15–17)](#part-4-matching-system)
8. [Part 5: Peer Response System (APIs 18–22)](#part-5-peer-response-system)
9. [Part 6: Status & Contact History (APIs 23–25)](#part-6-status--contact-history)
10. [Part 7: Existing Referral Integration (API 26)](#part-7-referral-integration)
11. [Database Schema Reference](#database-schema-reference)

---

## Authentication & Headers
All requests must include standard Laravel Sanctum Bearer token headers:

```http
Authorization: Bearer {{token}}
Accept: application/json
Content-Type: application/json
```

---

## Canonical Peer Data Contract
Whenever an endpoint returns peer/member information, it strictly follows the canonical Peer schema.
**Note:** Only `profile_photo_image` is used. Duplicate fields (`profile_image`, `profile_photo_url`, `avatar`, etc.) are removed:

```json
{
  "id": "b8be8e51-6ba9-4269-8b20-d5fc60de4271",
  "name": "Vinit Chavda",
  "display_name": "Vinit Chavda",
  "first_name": "Vinit",
  "last_name": "Chavda",
  "city": "Vadodara, IN",
  "company_name": "CK FutureTech",
  "life_impacted_count": 42754,
  "introduced_count": 0,
  "profile_photo_image": "https://dev.peersunity.com/api/v1/files/01a0950a-4c7e-72f1-9cf7-4473b0bc2491",
  "membership_status": "free_trial_peer",
  "designation": "Developer",
  "level4_category": "DGFT Consultants",
  "is_bookmark": false,
  "is_following": false,
  "is_verified": false,
  "is_pro": false,
  "is_connected": false,
  "connection_status": "pending_sent",
  "is_requested": true,
  "can_send_connection_request": false,
  "match_percentage": 88
}
```

---

## Canva Screens & API Mapping

| Canva Screen | Description | Primary API Endpoint |
| :--- | :--- | :--- |
| **Slide 1** | Home / Ask Entry Screen (*Find Collaborator, Ask Referral, Get Help*) | `GET /api/asks/flows` |
| **Slide 3, 14, 23** | Category / Type Picker (*Joint Venture, Co-Founder, etc.*) | `GET /api/asks/flows/{flow}/types` |
| **Slide 4, 15, 24** | Step 1: Brief (*Goal, What I Bring, What I Need*) | `POST /api/asks`<br>`PUT /api/asks/{id}` |
| **Slide 5, 16** | Step 2: Filters (*Industry, Geography, Stage, Timeline*) | `PUT /api/asks/{id}/filters` |
| **Slide 6, 17, 25** | Step 3: Preview Request, Visibility Pill & Post | `GET /api/asks/{id}/preview`<br>`PUT /api/asks/{id}/visibility`<br>`POST /api/asks/{id}/publish` |
| **Slide 7, 18, 26** | Match Screen (*"6 Peers match your request"*) | `GET /api/asks/{id}/matches` |
| **Slide 8, 20, 27** | Express Interest / Responder Screen (4 options) | `GET /api/asks/{id}/respond`<br>`POST /api/asks/{id}/responses` |
| **Slide 10, 29** | Outcome & Close Ask | `PATCH /api/asks/{id}/status` |
| **Slide 30, 31** | My Asks Dashboard (*Open, In Progress, Fulfilled, Expired*) | `GET /api/asks` |

---

# PART 1: Dynamic Configuration APIs

### API 1: Get Ask Flows
Loads the 3 primary flow modules.

- **Method:** `GET`
- **URL:** `/api/asks/flows`
- **Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "b5f14d54-01df-4b59-9d93-6c40df8c107a",
      "code": "collaboration",
      "name": "Find a Collaborator",
      "description": "Find the right peer for collaboration.",
      "icon": null,
      "sort_order": 1,
      "is_active": true,
      "metadata": {}
    },
    {
      "id": "c6a23e54-02ef-4c60-8e12-7d51ef9d208b",
      "code": "referral",
      "name": "Ask for a Referral",
      "description": "Ask peers for a relevant introduction.",
      "icon": null,
      "sort_order": 2,
      "is_active": true,
      "metadata": {}
    },
    {
      "id": "d7b34f65-03fa-4d71-9f23-8e62fa0e319c",
      "code": "help",
      "name": "Get Help",
      "description": "Ask peers for advice, mentorship, tasks, introductions and support.",
      "icon": null,
      "sort_order": 3,
      "is_active": true,
      "metadata": {}
    }
  ]
}
```

---

### API 2: Get Ask Types
Loads hierarchical subcategories for a flow.

- **Method:** `GET`
- **URL:** `/api/asks/flows/{flow}/types` *(Accepts flow code e.g. `collaboration` or flow UUID)*
- **Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "e8c45a76-14ab-4e82-af34-9f73ab1f420d",
      "flow_id": "b5f14d54-01df-4b59-9d93-6c40df8c107a",
      "parent_id": null,
      "code": "joint_venture",
      "name": "Joint Venture",
      "level": 1,
      "sort_order": 1,
      "is_active": true,
      "children": []
    },
    {
      "id": "1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d",
      "flow_id": "b5f14d54-01df-4b59-9d93-6c40df8c107a",
      "parent_id": null,
      "code": "manufacturing_partner",
      "name": "Manufacturing Partner",
      "level": 1,
      "sort_order": 5,
      "is_active": true,
      "children": []
    }
  ]
}
```

---

### API 3: Get Dynamic Form Configuration
Supplies the exact dynamic questions, option groups, and choices for Flutter screens.

- **Method:** `GET`
- **URL:** `/api/asks/form-config?flow=collaboration&type=manufacturing_partner`
- **Response:**
```json
{
  "success": true,
  "flow": {
    "id": "b5f14d54-01df-4b59-9d93-6c40df8c107a",
    "code": "collaboration",
    "name": "Find a Collaborator"
  },
  "type": {
    "id": "1a2b3c4d-5e6f-7a8b-9c0d-1e2f3a4b5c6d",
    "code": "manufacturing_partner",
    "name": "Manufacturing Partner"
  },
  "sections": {
    "details": ["goal", "collaboration_bring", "collaboration_need"],
    "filters": ["industry", "geography", "business_stage", "timeline", "expected_outcome"]
  },
  "groups": [
    {
      "id": "3009af97-6487-4675-b58e-e18719b804bf",
      "code": "industry",
      "name": "Industry",
      "input_type": "single_select",
      "is_multi_select": false,
      "options": [
        { "id": "05b26aa1-cee6-4344-9d85-a066759e0616", "code": "manufacturing", "label": "Manufacturing" },
        { "id": "098cb28d-9f6a-463e-9916-2b54fdee42b6", "code": "tech", "label": "Tech" }
      ]
    }
  ]
}
```

---

# PART 2: Ask Creation, Editing & Publishing

### API 4: Create Ask Draft
- **Method:** `POST`
- **URL:** `/api/asks`
- **Body:**
```json
{
  "flow": "collaboration",
  "type": "manufacturing_partner",
  "title": "Need Partner to Co-manufacture New Product Line"
}
```
- **Response:**
```json
{
  "success": true,
  "ask_id": "ced7cd43-bdf1-4e27-8131-cc2351af3680",
  "status": "draft",
  "data": { ... }
}
```

---

### API 5: Save / Update Ask Details
Accepts both option codes (e.g., `"expertise"`) and UUIDs.

- **Method:** `PUT`
- **URL:** `/api/asks/{ask_id}`
- **Body:**
```json
{
  "answers": [
    {
      "field_key": "goal",
      "value_text": "Find a partner to co-manufacture our new product line"
    },
    {
      "field_key": "collaboration_bring",
      "option_ids": ["expertise", "brand"]
    },
    {
      "field_key": "collaboration_need",
      "option_ids": ["capital", "manufacturing"]
    }
  ]
}
```
- **Response:**
```json
{
  "success": true,
  "message": "Ask details saved successfully.",
  "data": { ... }
}
```

---

### API 6: Save Ask Filters
- **Method:** `PUT`
- **URL:** `/api/asks/{ask_id}/filters`
- **Body:**
```json
{
  "industry": ["manufacturing"],
  "geography": ["my_city"],
  "business_stage": ["grow"],
  "timeline": ["three_months"],
  "expected_outcome": "Find a manufacturing partner within 3 months."
}
```
- **Response:**
```json
{
  "success": true,
  "message": "Ask filters saved successfully.",
  "data": { ... }
}
```

---

### API 7: Set Ask Visibility (Slide 6)
- **Method:** `PUT`
- **URL:** `/api/asks/{ask_id}/visibility`
- **Body:**
```json
{
  "visibility_type": "district",
  "district_id": "9426f86a-7f61-46ab-b924-f7b58c70fa52",
  "circle_id": null
}
```
- **Response:**
```json
{
  "success": true,
  "message": "Ask visibility updated successfully.",
  "data": { ... }
}
```

---

### API 8: Set Timeline Preference
- **Method:** `PUT`
- **URL:** `/api/asks/{ask_id}/timeline-preference`
- **Body:**
```json
{
  "publish_to_timeline": true
}
```
- **Response:**
```json
{
  "success": true,
  "message": "Timeline preference updated successfully.",
  "data": { ... }
}
```

---

### API 9: Preview Ask (Slide 6 Preview Card)
- **Method:** `GET`
- **URL:** `/api/asks/{ask_id}/preview`
- **Response:**
```json
{
  "success": true,
  "data": {
    "id": "ced7cd43-bdf1-4e27-8131-cc2351af3680",
    "flow": { "code": "collaboration", "name": "Find a Collaborator" },
    "type": { "code": "manufacturing_partner", "name": "Manufacturing Partner" },
    "title": "Find a partner to co-manufacture our new product line",
    "status": "draft",
    "details": { ... },
    "filters": { ... },
    "visibility": { "visibility_type": "district", "district": "Vadodara" },
    "publish_to_timeline": true,
    "creator": { ... }
  }
}
```

---

### API 10: Publish Ask
Publishes the request, creates the timeline post (if enabled), triggers matching, and sends notifications.

- **Method:** `POST`
- **URL:** `/api/asks/{ask_id}/publish`
- **Response:**
```json
{
  "success": true,
  "message": "Ask published successfully.",
  "data": {
    "id": "ced7cd43-bdf1-4e27-8131-cc2351af3680",
    "status": "published",
    "published_at": "2026-09-24T12:05:00.000000Z",
    "match_count": 6
  }
}
```

---

# PART 3: Ask Listing & Management

### API 11: My Asks (Slide 30 Dashboard)
- **Method:** `GET`
- **URL:** `/api/asks?flow=collaboration&status=published&page=1&per_page=15`
- **Response:**
```json
{
  "success": true,
  "data": [ ... ],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 1,
    "last_page": 1
  }
}
```

---

### API 12: Ask Details
- **Method:** `GET`
- **URL:** `/api/asks/{ask_id}`
- **Response:**
```json
{
  "success": true,
  "data": {
    "id": "ced7cd43-bdf1-4e27-8131-cc2351af3680",
    "title": "Find a partner to co-manufacture our new product line",
    "status": "published",
    "match_count": 6,
    "response_count": 1
  }
}
```

---

### API 13: Update Ask
- **Method:** `PATCH`
- **URL:** `/api/asks/{ask_id}`
- **Body:**
```json
{
  "title": "Seeking Precision Manufacturing Partner for Consumer Electronics"
}
```
- **Response:**
```json
{
  "success": true,
  "message": "Ask updated successfully.",
  "data": { ... }
}
```

---

### API 14: Cancel / Close Ask (Slide 10)
- **Method:** `PATCH`
- **URL:** `/api/asks/{ask_id}/status`
- **Body:**
```json
{
  "status": "closed",
  "reason": "Found a manufacturing partner"
}
```
- **Response:**
```json
{
  "success": true,
  "message": "Ask status updated successfully.",
  "data": {
    "id": "ced7cd43-bdf1-4e27-8131-cc2351af3680",
    "status": "closed",
    "closed_at": "2026-09-24T12:30:00.000000Z"
  }
}
```

---

# PART 4: Matching System

### API 15: Generate Matches
- **Method:** `POST`
- **URL:** `/api/asks/{ask_id}/matches/generate`
- **Response:**
```json
{
  "success": true,
  "message": "Matches generated successfully.",
  "count": 6,
  "data": [ ... ]
}
```

---

### API 16: Get Matched Peers (Slide 7)
- **Method:** `GET`
- **URL:** `/api/asks/{ask_id}/matches`
- **Response:**
```json
{
  "success": true,
  "data": [
    {
      "match_id": "78a9bc12-34de-5f67-89ab-cdef01234567",
      "ask_id": "ced7cd43-bdf1-4e27-8131-cc2351af3680",
      "match_status": "suggested",
      "match_score": 87.5,
      "matches": {
        "industry": "match",
        "geography": "match",
        "stage": "match"
      },
      "match_reason": "Industry match, City match",
      "algorithm_version": "v1",
      "peer": {
        "id": "b8f67e45-d8cf-48af-91bc-341e3d368e7f",
        "name": "Aarav Patel",
        "company_name": "Apex Contract Manufacturing",
        "profile_photo_image": "https://dev.peersunity.com/api/v1/files/01a0950a-4c7e-72f1-9cf7-4473b0bc2491",
        "membership_status": "pro_peer",
        "designation": "Managing Director",
        "match_percentage": 88
      }
    }
  ]
}
```

---

### API 17: Update Match Action
- **Method:** `PATCH`
- **URL:** `/api/asks/{ask_id}/matches/{match_id}`
- **Body:**
```json
{
  "match_status": "interested"
}
```
- **Response:**
```json
{
  "success": true,
  "message": "Match status updated successfully.",
  "data": { ... }
}
```

---

# PART 5: Peer Response System

### API 18: Get Ask For Response (Slide 8 & 20)
- **Method:** `GET`
- **URL:** `/api/asks/{ask_id}/respond`
- **Response:**
```json
{
  "success": true,
  "ask": { ... },
  "available_response_types": [
    { "code": "can_help_directly", "label": "I can help directly" },
    { "code": "can_introduce_peer", "label": "I can introduce a peer" },
    { "code": "know_someone", "label": "I know someone" },
    { "code": "not_relevant", "label": "Not relevant" }
  ],
  "existing_response": null
}
```

---

### API 19: Submit Ask Response
Handles all 4 responder branches:

#### Branch A: Direct Help
```json
{
  "response_type": "can_help_directly",
  "message": "I can handle this production in our Vadodara plant."
}
```

#### Branch B: Introduce Peer
```json
{
  "response_type": "can_introduce_peer",
  "introduced_user_id": "b8f67e45-d8cf-48af-91bc-341e3d368e7f",
  "message": "Aarav is an experienced contract manufacturer in your circle."
}
```

#### Branch C: I Know Someone (External Contact)
```json
{
  "response_type": "know_someone",
  "contact": {
    "full_name": "Rahul Shah",
    "company_name": "ABC Manufacturing Ltd",
    "designation": "Director of Operations",
    "email": "rahul.shah@abcmanufacturing.com",
    "phone": "+919876543210",
    "notes": "Met at Gujarat Engineering Expo"
  }
}
```

#### Branch D: Not Relevant
```json
{
  "response_type": "not_relevant"
}
```

- **Method:** `POST`
- **URL:** `/api/asks/{ask_id}/responses`
- **Response:**
```json
{
  "success": true,
  "message": "Response submitted successfully.",
  "data": {
    "id": "91a2b3c4-d5e6-7890-abcd-ef0123456789",
    "ask_id": "ced7cd43-bdf1-4e27-8131-cc2351af3680",
    "response_type": "know_someone",
    "status": "pending",
    "contact": {
      "full_name": "Rahul Shah",
      "company_name": "ABC Manufacturing Ltd",
      "phone": "+919876543210"
    }
  }
}
```

---

### API 20: Get Ask Responses (Ask Owner View)
- **Method:** `GET`
- **URL:** `/api/asks/{ask_id}/responses`
- **Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "91a2b3c4-d5e6-7890-abcd-ef0123456789",
      "response_type": "know_someone",
      "status": "pending",
      "responder": { "name": "Jane Smith" },
      "contact": { "full_name": "Rahul Shah", "phone": "+919876543210" }
    }
  ]
}
```

---

### API 21: Response Details
- **Method:** `GET`
- **URL:** `/api/asks/{ask_id}/responses/{response_id}`
- **Response:**
```json
{
  "success": true,
  "data": {
    "id": "91a2b3c4-d5e6-7890-abcd-ef0123456789",
    "response_type": "know_someone",
    "status": "pending",
    "contact": { ... }
  }
}
```

---

### API 22: Update Response Status
- **Method:** `PATCH`
- **URL:** `/api/asks/{ask_id}/responses/{response_id}`
- **Body:**
```json
{
  "status": "accepted",
  "note": "Thank you, connecting now."
}
```
- **Response:**
```json
{
  "success": true,
  "message": "Response status updated successfully.",
  "data": {
    "id": "91a2b3c4-d5e6-7890-abcd-ef0123456789",
    "status": "accepted"
  }
}
```

---

# PART 6: Status & Contact History

### API 23: Response Status History
- **Method:** `GET`
- **URL:** `/api/asks/{ask_id}/responses/{response_id}/history`
- **Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "h1a2b3c4-d5e6-7890-1234-567890abcdef",
      "old_status": "pending",
      "new_status": "accepted",
      "note": "Thank you, connecting now.",
      "created_at": "2026-09-24T12:20:00.000000Z"
    }
  ]
}
```

---

### API 24: Ask Status History
- **Method:** `GET`
- **URL:** `/api/asks/{ask_id}/history`
- **Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": "ah2b3c4d-5e6f-7890-1234-567890abcdef",
      "old_status": "published",
      "new_status": "closed",
      "reason": "Found a manufacturing partner",
      "created_at": "2026-09-24T12:30:00.000000Z"
    }
  ]
}
```

---

### API 25: Update Response Contact
- **Method:** `PATCH`
- **URL:** `/api/asks/{ask_id}/responses/{response_id}/contact`
- **Body:**
```json
{
  "designation": "Executive Director & Partner",
  "phone": "+919876500000"
}
```
- **Response:**
```json
{
  "success": true,
  "message": "Contact information updated successfully.",
  "data": {
    "full_name": "Rahul Shah",
    "designation": "Executive Director & Partner",
    "phone": "+919876500000"
  }
}
```

---

# PART 7: Referral Integration

### API 26: Link Existing Referral to Ask
- **Method:** `POST`
- **URL:** `/api/asks/{ask_id}/referral-link`
- **Body:**
```json
{
  "referral_id": "e4312159-23d3-450c-8b52-28af6ab275c2"
}
```
- **Response:**
```json
{
  "success": true,
  "message": "Referral linked to Ask successfully."
}
```
