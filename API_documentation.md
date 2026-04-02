# API Documentation

## Overview
- API root: `/api`
- Versioned user API base path: `/api/v1`
- Content type: `application/json`
- Route model binding for `{peckUser}` uses `gaijin_id`.

## Authentication
Protected endpoints require:
- A valid API key (`api.key` middleware)
- An API key owner user with `level >= 1`

Protected endpoints:
- `POST /api/invalidate-cache`
- `POST /api/v1/users`
- `PATCH /api/v1/users/{gaijin_id}`
- `POST /api/v1/users/{gaijin_id}/leave_info`
- `PATCH /api/v1/users/{gaijin_id}/leave_info`

You can provide the API key in any of these ways:
- JSON body: `token`
- Query string: `?token=...`
- Header: `X-Api-Key: ...`
- Header: `Authorization: Bearer ...`

When the key is missing/invalid, the API returns HTTP `401`:

```json
{
  "message": "Unauthorized."
}
```

If the key owner is authenticated but not allowed (`level < 1`), the API returns HTTP `403`.

## User Object
User responses return these fields:

```json
{
  "gaijin_id": 820003,
  "username": "created_via_api",
  "discord_id": 123456789012345678,
  "tz": 1,
  "status": "member",
  "joindate": "2026-03-24",
  "initiator": 820002
}
```

Field notes:
- `gaijin_id` (integer): Primary identifier.
- `username` (string, max 255).
- `discord_id` (integer, nullable).
- `tz` (integer, nullable, range `-11` to `12`).
- `status` (string): one of `applicant`, `unverified`, `ex_member`, `member`.
- `joindate` (string, nullable): format `YYYY-MM-DD`.
- `initiator` (integer, nullable): must exist in `officers.gaijin_id` and cannot equal the target user's `gaijin_id`.

## Leave Info Type
Allowed values:
- `Left`
- `LeftServer`
- `LeftSquadron`

## Cache Invalidation
The application queues a background notification to a local Discord bot cache invalidation endpoint (`http://127.0.0.1:5000/invalidate-cache`) when:
- A `peck_users` record is created
- A `peck_users` record is updated
- A `peck_users` record is deleted

The outgoing request uses:
- Method: `POST`
- Header: `Authorization: Bearer <shared_secret>`
- Implementation: Laravel HTTP client in a queued job
- Failure behavior: silent if the bot is offline/unreachable

Manual trigger endpoint:
- `POST /api/invalidate-cache`

Response `202`:

```json
{
  "status": "queued"
}
```

## Endpoints

### 1) List Users
`GET /api/v1/users`

Query parameters:
- `search` (string, optional, max 255): matches `gaijin_id`, `username`, or `discord_id` using partial match.
- `status` (string, optional): `applicant | unverified | ex_member | member`
- `tz` (integer, optional): `-11..12`
- `sort_by` (string, optional): `gaijin_id | username | status | discord_id | tz | joindate | initiator` (default `gaijin_id`)
- `sort_direction` (string, optional): `asc | desc` (default `asc`)
- `per_page` (integer, optional): `1..100` (default `15`)
- `page` (integer, optional): `>= 1` (default `1`)

Response `200`:

```json
{
  "data": [
    {
      "gaijin_id": 800002,
      "username": "api_target",
      "discord_id": null,
      "tz": 2,
      "status": "member",
      "joindate": null,
      "initiator": 800001
    }
  ]
}
```

Notes:
- This endpoint does not return pagination `links` or `meta`.

### 2) Get Single User
`GET /api/v1/users/{gaijin_id}`

Response `200`:

```json
{
  "data": {
    "gaijin_id": 810001,
    "username": "show_target",
    "discord_id": null,
    "tz": null,
    "status": "unverified",
    "joindate": null,
    "initiator": null
  }
}
```

Possible errors:
- `404` if user is not found.

### 3) Create User (Protected)
`POST /api/v1/users`

Required body fields:
- `gaijin_id` (integer, unique)
- `username` (string, max 255, unique)
- `status` (`applicant | unverified | ex_member | member`)

Optional body fields:
- `discord_id` (integer, nullable)
- `tz` (integer, nullable, `-11..12`)
- `joindate` (string, nullable, `YYYY-MM-DD`)
- `initiator` (integer, nullable, must exist in officers table, cannot equal `gaijin_id`)
- `token` (string, if authenticating via body)

Response `201`:

```json
{
  "data": {
    "gaijin_id": 820003,
    "username": "created_via_api",
    "discord_id": 123456789012345678,
    "tz": 1,
    "status": "member",
    "joindate": "2026-03-24",
    "initiator": 820002
  }
}
```

Possible errors:
- `401` invalid/missing API key
- `403` authenticated key owner is not authorized (`level < 1`)
- `422` validation errors

### 4) Update User (Protected)
`PATCH /api/v1/users/{gaijin_id}`

Body fields are all optional, but validated when present:
- `username` (string, max 255, unique except current user)
- `discord_id` (integer, nullable)
- `tz` (integer, nullable, `-11..12`)
- `status` (`applicant | unverified | ex_member | member`)
- `joindate` (string, nullable, `YYYY-MM-DD`)
- `initiator` (integer, nullable, must exist in officers table, cannot equal target `gaijin_id`)
- `token` (string, if authenticating via body)

Response `200`:

```json
{
  "data": {
    "gaijin_id": 830001,
    "username": "patched_user",
    "discord_id": null,
    "tz": null,
    "status": "member",
    "joindate": null,
    "initiator": 830002
  }
}
```

Behavior note:
- If a user changes from `ex_member` to any other status, their `leave_info` record is deleted.

Possible errors:
- `401` invalid/missing API key
- `403` authenticated key owner is not authorized (`level < 1`)
- `404` user not found
- `422` validation errors

### 5) Get Leave Info
`GET /api/v1/users/{gaijin_id}/leave_info`

Response `200`:

```json
{
  "status": "success",
  "data": "LeftServer"
}
```

If no leave info exists, `data` is `null`.

Possible errors:
- `404` user not found

### 6) Create/Update Leave Info via POST (Protected)
`POST /api/v1/users/{gaijin_id}/leave_info`

### 7) Create/Update Leave Info via PATCH (Protected)
`PATCH /api/v1/users/{gaijin_id}/leave_info`

Both endpoints use the same behavior (`upsert`):
- Creates `leave_info` if missing
- Updates existing `leave_info` if present

Body fields:
- `type` (required): `Left | LeftServer | LeftSquadron`
- `token` (string, if authenticating via body)

Response `200`:

```json
{
  "status": "success",
  "data": "LeftSquadron"
}
```

Possible errors:
- `401` invalid/missing API key
- `403` authenticated key owner is not authorized (`level < 1`)
- `404` user not found
- `422` validation errors

## Validation Error Shape
Validation failures follow Laravel's default JSON validation format (HTTP `422`), for example:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "status": [
      "The selected status is invalid."
    ]
  }
}
```
