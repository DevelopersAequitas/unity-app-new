# User Posts and Member Profile Video API Examples

## Fetch a user's posts

```http
GET /api/v1/users/{user_id}/posts?page=1&per_page=10
Authorization: Bearer {token}
Accept: application/json
```

Example:

```http
GET /api/v1/users/5066c9ed-39f6-43ad-bd33-adf78a8f0cbf/posts?page=1&per_page=10
```

Expected success response shape:

```json
{
  "success": true,
  "message": "User posts fetched successfully.",
  "data": {
    "user_id": "5066c9ed-39f6-43ad-bd33-adf78a8f0cbf",
    "total": 20,
    "current_page": 1,
    "per_page": 10,
    "last_page": 2,
    "items": []
  }
}
```

Notes:

- `per_page` defaults to `10` and is capped at `50`.
- Posts are ordered newest first.
- Invalid or unknown users return `404`.

## Manual database note

No migration file is included for `users.profile_video_id`. If the production database does not already have the column, add it manually before relying on the profile video response fields:

```sql
ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_video_id uuid NULL;
```

## Confirm member profile video fields

```http
GET /api/v1/members
Authorization: Bearer {token}
Accept: application/json
```

Each member item includes these additive fields:

```json
{
  "profile_video_id": "019e1fd9-0000-0000-0000-000000000000",
  "profile_video": {
    "id": "019e1fd9-0000-0000-0000-000000000000",
    "url": "https://peersunity.com/api/v1/files/019e1fd9-0000-0000-0000-000000000000"
  },
  "profile_video_url": "https://peersunity.com/api/v1/files/019e1fd9-0000-0000-0000-000000000000"
}
```

If the member has no profile video:

```json
{
  "profile_video_id": null,
  "profile_video": null,
  "profile_video_url": null
}
```

## Open a profile video file

```http
GET /api/v1/files/{profile_video_id}
Authorization: Bearer {token}
```
