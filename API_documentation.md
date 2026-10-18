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
- `GET /api/v1/server-token`  
- `POST /api/v1/users`  
- `PATCH /api/v1/users/{gaijin_id}`  
- `POST /api/v1/users/{gaijin_id}/context`  
- `PATCH /api/v1/users/{gaijin_id}/context/{id}`  
- `DELETE /api/v1/users/{gaijin_id}/context/{id}`  

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

## ThunderAPI Authentication  
Read endpoints that need squadron data (`GET /api/v1/applicants`, `GET /api/v1/logs`, and the derived `status` on user responses) use the server-wide ThunderAPI account (`THUNDERAPI_EMAIL` / `THUNDERAPI_PASS`) instead of any per-user token. If the ThunderAPI server account or `SQUADRON_ID` is not configured, those endpoints return HTTP `503`.  

## User Object  
User responses return these fields:  

```json  
{  
	"gaijin_id": 820003,  
	"discord_id": 123456789012345678,  
	"tz": 1,  
	"status": "member",  
	"sqb_part": null,  
	"joined_at": 1790000000  
}  
```  

Field notes:  
- `gaijin_id` (integer): Primary identifier.  
- `discord_id` (integer, nullable).  
- `tz` (integer, nullable, range `-11` to `12`).  
- `status` (string): one of `member`, `applicant`, `ex_member` (or `null` when the squadron roster cannot be resolved).  
- `sqb_part` (boolean, nullable).  
- `username` (string, nullable): ThunderAPI nickname, resolved via the server-wide ThunderAPI account on `GET /api/v1/users` and `GET /api/v1/users/{gaijin_id}`.  
- `joined_at` (integer, nullable): Unix timestamp of when the member joined the squadron.  

## User Context Types  
Allowed values:  
- `onceAbsence`  
- `recurringAbsence`  
- `misc`  

Context IDs are per-user IDs starting from `0`. New entries use the lowest unassigned ID for that user.  

## Cache Invalidation  
The application queues a background notification to a local Discord bot cache invalidation endpoint (`http://127.0.0.1:5000/invalidate-cache`) when a `peck_users` record is  
- created  
- updated  
- deleted  

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
- `search` (string, optional, max 255): matches `gaijin_id`, `discord_id`, or `username` using a case-insensitive partial match. Non-numeric terms are also matched against ThunderAPI nicknames.  
- `status` (string, optional): `member | applicant | ex_member`  
- `tz` (integer, optional): `-11..12`  
- `sort_by` (string, optional): `gaijin_id | status | discord_id | tz | sqb_part` (default `gaijin_id`)  
- `sort_direction` (string, optional): `asc | desc` (default `asc`)  
- `per_page` (integer, optional): `1..100` (default `15`)  
- `page` (integer, optional): `>= 1` (default `1`)  

Response `200`:  

```json  
{  
	"data": [  
		{  
		"gaijin_id": 800002,  
		"discord_id": null,  
		"tz": 2,  
		"status": "member",  
		"sqb_part": null,  
		"joined_at": 1790000000,  
		"username": "Maho_Yoshino"  
		}  
	]  
}  
```  

Notes:  
- This endpoint does not return pagination `links` or `meta`.  
- Each user includes `username`, resolved via the server-wide ThunderAPI account.  

### 2) Get Single User  
`GET /api/v1/users/{gaijin_id}`  

Response `200`:  

```json  
{  
	"data": {  
		"gaijin_id": 810001,  
		"discord_id": null,  
		"tz": null,  
		"status": "applicant",  
		"sqb_part": null,  
		"username": "Maho_Yoshino"  
	}  
}  
```  

Notes:  
- Includes `username`, resolved via the server-wide ThunderAPI account.  

Possible errors:  
- `404` if user is not found.  

### 3) Create User (Protected)  
`POST /api/v1/users`  

Required body fields:  
- `gaijin_id` (integer, unique)  

Optional body fields:  
- `discord_id` (integer, nullable)  
- `tz` (integer, nullable, `-11..12`)  
- `sqb_part` (boolean, nullable)  
- `token` (string, if authenticating via body)  

Response `201`:  

```json  
{  
	"data": {  
		"gaijin_id": 820003,  
		"discord_id": 123456789012345678,  
		"tz": 1,  
		"status": "member",  
		"sqb_part": null  
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
- `discord_id` (integer, nullable)  
- `tz` (integer, nullable, `-11..12`)  
- `sqb_part` (boolean, nullable)  
- `token` (string, if authenticating via body)  

Response `200`:  

```json  
{  
	"data": {  
		"gaijin_id": 830001,  
		"discord_id": null,  
		"tz": null,  
		"status": "member",  
		"sqb_part": null  
	}  
}  
```  

Possible errors:  
- `401` invalid/missing API key  
- `403` authenticated key owner is not authorized (`level < 1`)  
- `404` user not found  
- `422` validation errors  

### 5) Get User Context  
`GET /api/v1/users/{gaijin_id}/context`  

This endpoint is public. It returns a direct JSON array.  

Response `200`:  

```json  
[  
	{  
		"id": 0,  
		"type": "onceAbsence",  
		"from": "2026-05-01",  
		"to": "2026-06-01"  
	},  
	{  
		"id": 1,  
		"type": "recurringAbsence",  
		"weekdays": [0, 3, 6]  
	},  
	{  
		"id": 2,  
		"type": "recurringAbsence",  
		"monthDay": 26  
	},  
	{  
		"id": 3,  
		"type": "misc",  
		"comment": "Max rank: 14.7"  
	}  
]  
```  

Possible errors:  
- `404` user not found  

### 6) Create User Context (Protected)  
`POST /api/v1/users/{gaijin_id}/context`  

Body fields:  
- `type` (required): `onceAbsence | recurringAbsence | misc`  
- `from` (required for `onceAbsence`): `YYYY-MM-DD`  
- `to` (required for `onceAbsence`): `YYYY-MM-DD`, on or after `from`  
- `weekdays` (required for `recurringAbsence` unless `monthDay` is present): array of integers `0..6`  
- `monthDay` (required for `recurringAbsence` unless `weekdays` is present): integer `1..31`  
- `comment` (required for `misc`): string, max 1000 characters  
- `token` (string, if authenticating via body)  

If both `weekdays` and `monthDay` are sent for a recurring absence, two entries are created.  

Response `201`:  

```json  
[  
	{  
		"id": 1,  
		"type": "onceAbsence",  
		"from": "2026-06-01",  
		"to": "2026-06-15"  
	}  
]  
```  

Possible errors:  
- `401` invalid/missing API key  
- `403` authenticated key owner is not authorized (`level < 1`)  
- `404` user not found  
- `422` validation errors  

### 7) Update User Context (Protected)  
`PATCH /api/v1/users/{gaijin_id}/context/{id}`  

Body fields are optional, but the resulting context must remain valid for its type.  

Example body:  

```json  
{  
	"comment": "Speaks occasionally, but prefers typing"  
}  
```  

Response `200`:  

```json  
{  
	"id": 4,  
	"type": "misc",  
	"comment": "Speaks occasionally, but prefers typing"  
}  
```  

Possible errors:  
- `401` invalid/missing API key  
- `403` authenticated key owner is not authorized (`level < 1`)  
- `404` user or context entry not found  
- `422` validation errors  

### 8) Delete User Context (Protected)  
`DELETE /api/v1/users/{gaijin_id}/context/{id}`  

Response `204`: empty body.  

Possible errors:  
- `401` invalid/missing API key  
- `403` authenticated key owner is not authorized (`level < 1`)  
- `404` user or context entry not found  

### 9) List Squadron Applicants  
`GET /api/v1/applicants`  

This endpoint is public. It uses the server-wide ThunderAPI account and the configured `SQUADRON_ID`.  

Response `200`:  

```json  
{  
	"data": [  
		{  
			"uid": "820003",  
			"nickname": "Maho_Yoshino",  
			"timestamp": 1790000000,  
			"comment": "Hello",  
			"country": "DE",  
			"timezone": 2  
		}  
	]  
}  
```  

Field notes:  
- `uid` (string): applicant Gaijin ID.  
- `nickname` (string).  
- `timestamp` (integer, nullable): application timestamp (Unix seconds).  
- `comment` (string).  
- `country` (string, nullable): ISO country code, when available.  
- `timezone` (integer, nullable): UTC offset in hours, when available.  

Possible errors:  
- `503` squadron ID or ThunderAPI server account is not configured  
- `502` ThunderAPI returned an error  
- `503` ThunderAPI could not be reached  

### 10) List Squadron Logs  
`GET /api/v1/logs`  

This endpoint is public. It uses the server-wide ThunderAPI account and the configured `SQUADRON_ID`.  

Query parameters:  
- `limit` (integer, optional): `1..50` (default `10`)  
- `from_entry` (string, optional): pagination cursor from a previous response's `lastLog`  

Response `200`:  

```json  
{  
	"lastLog": "log-marker-abc",  
	"logs": [  
		{  
			"timestamp": 1790000000,  
			"action": { "value": 1, "detail": "Accept membership request" },  
			"admin": { "_id": 111, "nickname": "AdminOne" },  
			"affected": { "_id": 222, "nickname": "NewMember" }  
		}  
	]  
}  
```  

Field notes:  
- `lastLog` (string): pagination cursor. Pass it back as `from_entry` to fetch the next page.  
- `logs` (array): raw ThunderAPI log entries. See the ThunderAPI documentation for the full entry shape.  

Possible errors:  
- `422` invalid query parameters  
- `503` squadron ID or ThunderAPI server account is not configured  
- `502` ThunderAPI returned an error  
- `503` ThunderAPI could not be reached  

### 11) Get Server ThunderAPI Token (Protected)  
`GET /api/v1/server-token`  

Returns the active server-wide ThunderAPI token. Requires an API key owned by a user with write access (`level >= 1` and a verified email).  

Response `200`:  

```json  
{  
	"token": "server-token"  
}  
```  

Possible errors:  
- `401` invalid/missing API key  
- `403` authenticated key owner is not authorized (`level < 1`)  
- `503` ThunderAPI server account is not configured  
- `503` ThunderAPI could not be reached  
- `502` ThunderAPI token could not be resolved  

## Validation Error Shape  
Validation failures follow Laravel's default JSON validation format (HTTP `422`), for example:  

```json  
{  
	"message": "The given data was invalid.",  
	"errors": {  
		"tz": [  
		"The timezone must be between -11 and 12."  
		]  
	}  
}  
```  
