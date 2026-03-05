# Postman: Circle Subscription Prices API

## Endpoint
`GET /api/v1/circles/{circle_uuid}/subscription-prices`

## Full URL (local)
`http://127.0.0.1:8000/api/v1/circles/{circle_uuid}/subscription-prices`

## Headers
- `Accept: application/json`
- `Authorization: Bearer <token>` (required because route is inside auth:sanctum group)

## Expected Response
```json
{
  "status": true,
  "message": "Circle subscription prices fetched successfully.",
  "data": [
    {
      "id": "...",
      "circle_id": "...",
      "duration_months": 1,
      "price": "999.00",
      "currency": "INR",
      "zoho_addon_id": "...",
      "zoho_addon_code": "...",
      "zoho_addon_name": "Circle - Monthly",
      "payload": {}
    },
    {
      "duration_months": 3
    },
    {
      "duration_months": 6
    },
    {
      "duration_months": 12
    }
  ],
  "meta": null
}
```
