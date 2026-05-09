# API v1 Ride Lifecycle

This document describes the user, driver, and admin ride lifecycle endpoints and the enforced business rules.

## Business Rules

- A user can request rides only from approved, non-suspended drivers.
- A user can have only one `active` ride at a time.
- A driver can have only one `active` ride at a time.
- A user cannot create duplicate `pending` requests to the same driver.
- When one `pending` ride is accepted, all competing `pending` rides for the same user or same driver are auto-marked as `system_cancelled`.
- `system_cancelled` rides are hidden from role detail/list endpoints.
- Allowed flow is `pending -> active -> completed/cancelled`.
- No downgrade is allowed (an `active` ride cannot go back to `pending`).
- `completed`, `system_cancelled`, and `expired` are terminal and immutable.
- Ride request expiry is config-driven:
  - config key: `rides.request_expire_minutes`
  - env key: `RIDE_REQUEST_EXPIRE_MINUTES`
  - default: `10`
- Pending rides past `expired_at` are automatically marked `expired` by scheduler.

## Scheduler / Expiry

- Scheduled command: `rides:expire-pending`
- Schedule frequency: every minute
- Implementation:
  - command dispatches `ExpirePendingRidesJob`
  - job calls `RideLifecycleService::expirePendingRides()`

## User Endpoints (`/api/v1/user`)

### GET `/rides`
List user rides (excluding `system_cancelled`).

Query params:
- `status[]` (optional)
- `q` (optional)
- `from` (optional date)
- `to` (optional date)
- `sort` (`latest|oldest`)
- `per_page` (1-100)
- `page` (>=1)

### POST `/rides`
Create a ride request.

Body:
```json
{
  "driver_id": 123,
  "pickup_location": "Point A",
  "dropoff_location": "Point B",
  "notes": "optional"
}
```

### GET `/rides/active`
Get current active ride for the authenticated user.

### GET `/rides/{ride}`
Get ride details (returns 404 for `system_cancelled` or non-owned ride).

### POST `/rides/{ride}/cancel`
Cancel ride by user (`pending` or `active` only).

Body:
```json
{
  "reason": "optional reason"
}
```

### POST `/rides/{ride}/complete`
Complete ride by user (`active` only).

## Driver Endpoints (`/api/v1/driver`)

### GET `/dashboard`
Driver ride summary.

### GET `/rides`
List driver rides by tab/status.

Query params:
- `tab` (`pending|active|history`)
- `status[]` (optional)
- `q` (optional)
- `from` (optional date)
- `to` (optional date)
- `sort` (`latest|oldest`)
- `per_page` (1-100)

### GET `/rides/incoming`
List incoming `pending` ride requests.

### POST `/rides/{ride}/accept`
Accept a pending ride and activate it.

Body:
```json
{
  "eta_minutes": 15
}
```

### POST `/rides/{ride}/eta`
Update ETA for active ride with mandatory reason.

Body:
```json
{
  "eta_minutes": 25,
  "reason": "Traffic delay"
}
```

### POST `/rides/{ride}/cancel`
Cancel ride by driver (`pending` or `active` only).

Body:
```json
{
  "reason": "optional reason"
}
```

### POST `/rides/{ride}/complete-request`
Complete ride by driver (`active` only).

## Admin Endpoints (`/api/v1/admin`)

Admin ride endpoints remain read-only:

- GET `/dashboard`
- GET `/rides`
- GET `/rides/{ride}`
- GET `/drivers`
- GET `/customers`

`system_cancelled` rides are excluded from list/detail views.

## Timeline Tracking

Ride timeline updates are logged in `ride_histories`:

- `status` entries for state transitions
- `estimated_time` entries for initial ETA and ETA updates
- `complete` entries for completion

`RideResource` exposes:
- `timeline.eta_updates_count` (when histories are loaded)

