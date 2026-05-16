# API v1 — Authentication, login modes, and OTP

This reference matches **Laravel 13.7.x**, **Passport 13.7.x**, and **Fortify** as installed in this project (versions confirmed via Laravel Boost `application-info`). All paths assume the default Laravel **`/api`** prefix: **`https://{host}/api/v1/...`**.

## Standard JSON envelope

Successful and error responses from most auth controllers use `sendResponse()`:

```json
{
  "success": true,
  "message": "Human-readable message",
  "data": {}
}
```

Failure:

```json
{
  "success": false,
  "message": "Human-readable message",
  "data": null,
  "code": "OPTIONAL_MACHINE_CODE"
}
```

`code` appears for known API errors (for example `LOGIN_OTP_DISABLED`, `SMS_OTP_NOT_AVAILABLE`, `OTP_RESEND_TOO_SOON`). **Validation errors** (422) from `FormRequest` use Laravel’s normal shape:

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

## OAuth2 access token (Passport)

After login or OTP verification (when 2FA is off), `data` includes Personal Access Token fields:

| Field | Description |
|-------|-------------|
| `token_type` | Always `Bearer` |
| `access_token` | JWT-style Passport token for `Authorization: Bearer {token}` |
| `user` | User object from [`UserResource`](../app/Http/Resources/Api/V1/UserResource.php) |

Authenticated routes use middleware **`auth:api`** (Passport guard in [`config/auth.php`](../config/auth.php)).

---

## Login modes (environment)

Configure with **`LOGIN_TYPE`** (see [`config/auth_login.php`](../config/auth_login.php) and `.env.example`).

### Mode A — `LOGIN_TYPE=password` (default)

- **Register** and **email/password login** are enabled.
- **Forgot / reset password** use Laravel’s password broker.
- **OTP login routes** (`/otp/request`, `/otp/verify`) return **422** with `code: LOGIN_OTP_DISABLED`.

### Mode B — `LOGIN_TYPE=otp`

- **Passwordless login** via **`POST /otp/request`** + **`POST /otp/verify`**, or **`POST /login`** (same OTP send as `/otp/request`).
- **`POST /register`** is disabled (`code: PASSWORD_REGISTRATION_DISABLED`).
- **Forgot / reset password** return **403** (`code: PASSWORD_RESET_NOT_AVAILABLE`).
- Contact verification OTP for logged-in users remains available under **`/verification/otp/*`**.

### OTP-related environment

| Variable | Purpose |
|----------|---------|
| `LOGIN_IDENTIFIERS` | Comma-separated: `email`, `phone`, `username` — which kinds of sign-in the API may accept; the server **infers** the kind from the `identifier` string (see below) |
| `OTP_DELIVERY` | `email` \| `phone` \| `user_choice` — how outbound channel is chosen when both email and phone identifiers exist |
| `OTP_CODE_TTL_MINUTES` | OTP validity (stored hashed in cache) |
| `OTP_CODE_LENGTH` | Numeric OTP length (default 6) |
| `OTP_RESEND_SECONDS` | Minimum seconds between send attempts for the same login identifier; **0** disables this cooldown (HTTP throttles still apply). When too soon, API returns **429** with `Retry-After` and `code: OTP_RESEND_TOO_SOON` |
| `OTP_ALLOW_REGISTRATION_ON_LOGIN` | **true** (default): unknown identifier may create a user on first OTP request. **false**: only existing users may request a code (422 on `identifier` if unknown) |

Fortify’s **`email`** field name for web flows is configured in [`config/fortify.php`](../config/fortify.php); API `LoginRequest` uses the same `fortify.username` for the primary login field.

---

## Rate limiting (public auth)

Defined in [`AppServiceProvider`](../app/Providers/AppServiceProvider.php). Keys are based on **identifier + IP** or **email + IP** as noted.

| Middleware name | Typical limit | Applied to |
|-----------------|---------------|------------|
| `api-login` | 5 / minute | `POST /login` (in OTP mode: same key as `api-otp-request`), `POST /two-factor-challenge`, and password-mode `POST /login` |
| `api-register` | 3 / minute | `POST /register` |
| `api-password-reset` | 3 / minute | `POST /forgot-password` |
| `api-password-reset-verify` | 10 / minute | `POST /reset-password` |
| `api-otp-request` | 5 / minute | `POST /otp/request`, `POST /otp/resend` — key: resolved identifier type + normalized identifier + IP (same resolution as runtime) |
| `api-otp-verify` | 10 / minute | `POST /otp/verify` |

When **`LOGIN_TYPE=otp`**, `POST /api/v1/login` uses the **same rate-limit key shape** as `api-otp-request` (identifier type + identifier + IP), so abuse windows are aligned with `/otp/request`. Other `api-login` routes (e.g. `POST /two-factor-challenge` in password mode) keep the original Fortify-username + IP key.

---

## Public endpoints (`routes/api/v1/public.php`)

### `POST /api/v1/register`

**When:** `LOGIN_TYPE=password` only.

**Request body:**

```json
{
  "name": "Jane Doe",
  "email": "jane@example.com",
  "phone": "+15551234567",
  "password": "secret123",
  "password_confirmation": "secret123",
  "locale": "en",
  "device_name": "iPhone 15",
  "role": "user"
}
```

**Response (201) when `LOGIN_TYPE=password`:** Account is created, the sign-in identifier is marked verified, and the client receives a Passport token immediately (no registration OTP step). `password` and `password_confirmation` are **required**.

```json
{
  "success": true,
  "message": "Registration successful.",
  "data": {
    "token_type": "Bearer",
    "access_token": "<token>",
    "user": { }
  }
}
```

New drivers are created with `approval_status: pending` and may log in, but driver API routes return **403** until an admin approves the account (see driver middleware).

**Response (201) when `LOGIN_TYPE=otp`:** Sends a verification OTP. Complete registration with **`POST /otp/register/verify`**, which returns the same `token_type`, `access_token`, and `user` shape as password login.

```json
{
  "success": true,
  "message": "A verification code has been sent to your email address. Please verify to register your account.",
  "data": {
    "expires_in_minutes": 10
  }
}
```

**Password mode disabled (422) when `LOGIN_TYPE=otp`:**

```json
{
  "success": false,
  "message": "Password registration is disabled when OTP login is enabled.",
  "data": null,
  "code": "PASSWORD_REGISTRATION_DISABLED"
}
```

---

### `POST /api/v1/login`

Behavior depends on `LOGIN_TYPE`.

#### Password mode

**Request:**

```json
{
  "email": "user@example.com",
  "password": "password",
  "device_name": "Web"
}
```

Use the Fortify username field name if not `email` (e.g. `phone`).

**Success (200):**

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token_type": "Bearer",
    "access_token": "eyJ0eXAiOiJKV1QiLCJh...",
    "user": { "...": "UserResource" }
  }
}
```

**Invalid credentials (401):**

```json
{
  "success": false,
  "message": "These credentials do not match our records.",
  "data": null
}
```

#### OTP mode (`LOGIN_TYPE=otp`)

Password is **optional** and ignored. The same rules apply as for **`/otp/request`**: with **one** login identifier (e.g. email only), send Fortify’s username field (usually **`email`**) **or** **`identifier`**; with **multiple** kinds enabled, send **`identifier`** (generic string). If nothing is provided, validation errors reference the **dedicated** field (e.g. `email`) when a single kind is configured.

**Request (only `LOGIN_IDENTIFIERS=email`):**

```json
{
  "email": "user@example.com",
  "device_name": "Web"
}
```

**Request (multiple identifier kinds):**

```json
{
  "identifier": "user@example.com",
  "device_name": "Web"
}
```

**Success (200)** — OTP send triggered (same as `POST /otp/request` / `POST /otp/resend`); the exact `message` string comes from app locale (e.g. `api.otp_sent_to_email`).

```json
{
  "success": true,
  "message": "If eligible, a verification code has been sent to your email address.",
  "data": {
    "expires_in_minutes": 10
  }
}
```

If SMS is required but not implemented, service returns **503** with `code: SMS_OTP_NOT_AVAILABLE` (see `/otp/request`).

---

### `POST /api/v1/two-factor-challenge`

Completes login when the user has **2FA** enabled: first step returns `two_factor_token`, second step verifies TOTP or recovery code.

**Step 1 response** (from login or OTP verify) when 2FA is on:

```json
{
  "success": true,
  "message": "Two-factor authentication required.",
  "data": {
    "two_factor": true,
    "two_factor_token": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

**Request (TOTP):**

```json
{
  "two_factor_token": "550e8400-e29b-41d4-a716-446655440000",
  "code": "123456",
  "device_name": "Web"
}
```

**Request (recovery code):**

```json
{
  "two_factor_token": "550e8400-e29b-41d4-a716-446655440000",
  "recovery_code": "xxxx-xxxx",
  "device_name": "Web"
}
```

**Success (200):** Same token payload as password login (`token_type`, `access_token`, `user`).

---

### `POST /api/v1/forgot-password` / `POST /api/v1/reset-password`

Available in **password** mode; disabled in **OTP** mode (**403**, `code: PASSWORD_RESET_NOT_AVAILABLE`).

Flow uses a **one-time code emailed to the user** (no reset link). Cooldown between sends: **`PASSWORD_RESET_OTP_RESEND_SECONDS`** (see `config/account.php`). Code TTL: **`PASSWORD_RESET_OTP_TTL_MINUTES`**. Code length matches **`OTP_CODE_LENGTH`**.

**Forgot password** body:

```json
{
  "email": "user@example.com"
}
```

**Response (200):** Always returns success with a generic message (does not reveal whether the email exists). If the user exists, they receive the HTML OTP email.

**Reset password** body:

```json
{
  "email": "user@example.com",
  "code": "123456",
  "password": "new-password-here",
  "password_confirmation": "new-password-here"
}
```

On success, existing Passport tokens for that user are **revoked**.

---

### `POST /api/v1/otp/request`

Primary endpoint to **send** login OTP. **Requires** `LOGIN_TYPE=otp`. Calling again before **`OTP_RESEND_SECONDS`** elapses returns **429** (see below).

**Which fields to send**

| `LOGIN_IDENTIFIERS` | Required input |
|---------------------|----------------|
| **Several** (e.g. email + phone + username) | **`identifier`** — one string; the API infers email vs phone vs username (optional **`identifier_type`** override). |
| **Email only** | Either **`identifier`** **or** **`email`**. If both are omitted, validation fails on **`email`** (not `identifier`). |
| **Phone only** | Either **`identifier`** **or** **`phone`**. If both omitted, validation fails on **`phone`**. |
| **Username only** | Either **`identifier`** **or** **`username`**. If both omitted, validation fails on **`username`**. |

Auto-detection uses [`LoginIdentifierDetector`](../app/Services/Auth/LoginIdentifierDetector.php). Optional **`identifier_type`** forces a specific kind when you already pass a combined `identifier` string.

Detection order when multiple kinds are allowed: valid email format first, then phone-like numeric strings, then username.

### `POST /api/v1/otp/resend`

**Alias** for `POST /otp/request` (same validation, throttles, cooldown, and handler). Use whichever fits your client naming.

**Request** (same as `/otp/request`). Example when only email sign-in is enabled — you may send **`email`** instead of **`identifier`**:

```json
{
  "email": "user@example.com",
  "device_name": "Web"
}
```

Example with multiple identifier kinds enabled (must include **`identifier`**):

```json
{
  "identifier": "user@example.com",
  "name": "Optional for new users",
  "email": "supplement@example.com",
  "phone": "+15559876543",
  "delivery": "email",
  "device_name": "Web"
}
```

- `delivery` is required when `OTP_DELIVERY=user_choice` and both email and phone identifiers are configured.
- If **`OTP_ALLOW_REGISTRATION_ON_LOGIN=true`** (default) and the identifier is unknown, the app may **create a user** after validation. If **`false`**, unknown identifiers receive **422** on `identifier`.

**Success (200):**

```json
{
  "success": true,
  "message": "If eligible, a verification code has been sent to your email address.",
  "data": {
    "expires_in_minutes": 10
  }
}
```

**Too soon (429)** — when `OTP_RESEND_SECONDS` > 0 and another send is attempted inside the cooldown window. Response includes **`Retry-After`** (seconds) and:

```json
{
  "success": false,
  "message": "Please wait before requesting another code.",
  "data": {
    "retry_after_seconds": 45
  },
  "code": "OTP_RESEND_TOO_SOON"
}
```

**Wrong mode (422):**

```json
{
  "success": false,
  "message": "OTP login is disabled.",
  "data": null,
  "code": "LOGIN_OTP_DISABLED"
}
```

**SMS not available (503):**

```json
{
  "success": false,
  "message": "SMS one-time codes are not available. Configure an SMS provider.",
  "data": null,
  "code": "SMS_OTP_NOT_AVAILABLE"
}
```

---

### `POST /api/v1/otp/verify`

Verify login OTP and receive Passport token (or 2FA challenge). Use the **same** credential shape as `/otp/request` (`identifier` and/or the dedicated **`email`** / **`phone`** / **`username`** field when a single identifier mode is configured), plus **`code`**.

**Request:**

```json
{
  "email": "user@example.com",
  "code": "123456",
  "device_name": "Web"
}
```

Or with a generic identifier:

```json
{
  "identifier": "user@example.com",
  "code": "123456",
  "device_name": "Web"
}
```

**Success (200)** — no 2FA:

```json
{
  "success": true,
  "message": "Login successful.",
  "data": {
    "token_type": "Bearer",
    "access_token": "eyJ0eXAiOiJKV1QiLCJh...",
    "user": { "...": "UserResource" }
  }
}
```

**Invalid code (422):** Laravel validation style with `errors.code`.

---

### Other public routes

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/v1/languages` | Language list |
| GET | `/api/v1/currencies` | Currency list |

---

## Authenticated routes (`auth:api`) — auth-related

From [`routes/api/v1/common.php`](../routes/api/v1/common.php).

### `POST /api/v1/logout`

**Headers:** `Authorization: Bearer {access_token}`

**Response (200):**

```json
{
  "success": true,
  "message": "Logged out successfully.",
  "data": null
}
```

Revokes Passport tokens for the current user.

---

### `POST /api/v1/verification/otp/request`

Request OTP to verify **email** or **phone** for the **logged-in** user (not login OTP).

**Request:**

```json
{
  "channel": "email"
}
```

**Success (200):** Similar success envelope; message from `RequestContactVerificationOtpAction`. **503** + `SMS_OTP_NOT_AVAILABLE` if `channel` is `phone` and SMS is not configured.

### `POST /api/v1/verification/otp/verify`

**Request:**

```json
{
  "channel": "email",
  "code": "123456"
}
```

---

### `GET /api/v1/me`

Returns current user (`UserResource`).

---

## Two-factor management (`/api/v1/two-factor/*`)

Requires **`auth:api`**. Used to configure Fortify TOTP 2FA (QR, confirm, recovery codes, disable). See [`routes/api/v1/common.php`](../routes/api/v1/common.php) for the full list.

### Step-up before sensitive 2FA operations (industry standard)

Disabling 2FA, starting enable (secret + QR), and regenerating recovery codes are **sensitive**: the API must confirm the same factor the user used to sign in (password vs OTP).

| `LOGIN_TYPE` | What to send on `POST /two-factor/enable`, `DELETE /two-factor/disable`, `POST /two-factor/recovery-codes/regenerate` |
|--------------|------------------------------------------------------------------------------------------------------------------------|
| `password` | Current account password: **`password`** (validated with `current_password:api`). |
| `otp` | One-time code: **`otp`** (same length as login OTP, e.g. 6 digits). **Request a code first** (see below). Do not send `password`. |

**Request a step-up OTP (only when `LOGIN_TYPE=otp`):**

- **`POST /api/v1/two-factor/reauthentication/otp`**
- Throttle: `api-sensitive-action-otp-request` (5 / minute / user + IP).
- Respects **`OTP_RESEND_SECONDS`**, **`OTP_CODE_TTL_MINUTES`**, **`OTP_DELIVERY`**, and **`LOGIN_IDENTIFIERS`** the same way as login OTP (e.g. if both email and phone are allowed identifiers, you may need **`delivery`**: `email` or `phone` when `OTP_DELIVERY=user_choice`).
- If **`LOGIN_TYPE=password`**, this route returns **422** with `code: SENSITIVE_ACTION_OTP_DISABLED` (use `password` on the sensitive endpoints instead).
- **200** response includes `data.expires_in_minutes` and the user receives the code on the selected channel (email in the current stack; SMS returns **503** `SMS_OTP_NOT_AVAILABLE` if not implemented—same as login OTP).

**Example (OTP login):** `POST /two-factor/reauthentication/otp` with optional `{"delivery":"email"}` when required, then `POST /two-factor/enable` with `{"otp":"123456"}`.

---

## Locale

API routes under `v1` use [`ResolveApiLocale`](../app/Http/Middleware/ResolveApiLocale.php). Prefer configuring locale via project-supported headers/query as documented in your middleware and `.env.example` (`APP_SUPPORTED_LOCALES`, optional locale header).

---

## OTP email delivery and queues

[`OtpCodeNotification`](../app/Notifications/Auth/OtpCodeNotification.php) implements **`ShouldQueue`**, so OTP emails are dispatched to the **default queue connection**. Run **`php artisan queue:work`** (or your supervisor config) in environments using `QUEUE_CONNECTION` other than `sync`. Failed sends honor **`tries`** / **`backoff`** on the notification class.

---

## Related reading

- OTP hardening roadmap: Cursor plan `otp_endpoints_and_hardening_80c29137.plan.md` (not stored under `docs/`)
- [Laravel Passport — authorization](https://laravel.com/docs/13.x/passport) (match installed **13.7.x**)
- [Laravel Fortify](https://laravel.com/docs/13.x/fortify) — 2FA and username field
