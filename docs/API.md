# REST API

Base URL: `http://localhost/club-event-system/api/`

All requests and responses use JSON. Authentication uses the PHP session cookie
(Postman and browsers keep it automatically). Requests that change data (`POST`, `PUT`,
`DELETE`) must send the CSRF token in the `X-CSRF-Token` header. The token is returned by
`login.php` and `me.php`.

## Endpoints

| # | Method | Endpoint | Who | Success | Main errors |
|---|--------|----------|-----|---------|-------------|
| 1 | POST | `login.php` | anyone | 200 | 400, 401 |
| 2 | POST | `logout.php` | logged in | 200 | 401, 403 |
| 3 | GET | `me.php` | logged in | 200 | 401 |
| 4 | GET | `events.php` | logged in | 200 | 400, 401 |
| 5 | GET | `events.php?scope=manage` | admin, organizer | 200 | 401, 403 |
| 6 | GET | `events.php?id=ID` | logged in | 200 | 400, 401, 404 |
| 7 | POST | `events.php` | admin, organizer | 201 | 401, 403, 415, 422 |
| 8 | PUT | `events.php?id=ID` | owner, admin | 200 | 401, 403, 404, 422 |
| 9 | DELETE | `events.php?id=ID` | owner, admin | 204 | 401, 403, 404 |
| 10 | GET | `registrations.php` | logged in | 200 | 401 |
| 11 | POST | `registrations.php` | logged in | 201 | 401, 403, 404, 409, 422 |
| 12 | DELETE | `registrations.php?event_id=ID` | logged in | 200 | 401, 403, 404 |

## Status codes

| Code | Meaning here |
|------|--------------|
| 200 / 201 / 204 | OK / created / deleted (no body) |
| 400 | Bad request (invalid id, invalid JSON) |
| 401 | Not logged in, or wrong email or password |
| 403 | No permission, not your event, or missing CSRF token |
| 404 | Not found (or hidden: unpublished events are hidden from members) |
| 405 | Method not allowed (see the `Allow` header) |
| 409 | Conflict: already registered, or event is full |
| 415 | `Content-Type` is not `application/json` |
| 422 | Validation failed (the body lists each field) |

## Examples

Login request body:
```json
{ "email": "member@example.com", "password": "secret123" }
```
Login response:
```json
{ "user": { "id": 2, "name": "Rafi", "role": "member" }, "csrf_token": "..." }
```

Create event (`POST events.php`):
```json
{
  "title": "Cultural Night",
  "venue": "Main Auditorium",
  "event_date": "2027-03-20",
  "start_time": "18:00",
  "end_time": "21:00",
  "capacity": 100,
  "category_id": 4,
  "status": "published"
}
```

Validation error (422):
```json
{
  "error": "Validation failed.",
  "errors": { "end_time": "end_time must be after start_time." }
}
```

Register for an event (`POST registrations.php`): `{ "event_id": 5 }`
