# Multi-currency API — reference and usage

This document describes how the Laravel API exposes currencies, exchange rates (including history), display conversion, and related client usage. All paths are relative to your application URL; API routes are registered under the `api` prefix (default: `/api`).

**Base path:** `/api/v1/…`

Authentication for protected routes uses **Laravel Passport** (`auth:api`): send a valid bearer token.

```
Authorization: Bearer {access_token}
Accept: application/json
Content-Type: application/json
```

---

## Response envelope

Most endpoints use the shared `sendResponse` helper. JSON typically looks like:

| Field | Type | Description |
|--------|------|-------------|
| `success` | boolean | Operation outcome |
| `message` | string | Human-readable message (may be translated) |
| `data` | mixed | Payload (object, array, or null) |

Pagination for rate history wraps rows under `data.data` and adds `data.meta` (see below).

---

## Money objects (listing vs display)

Amounts are **not** bare numbers on products and plans. Each monetary field uses a small object:

```json
{
  "amount": "99.00",
  "currency": "USD"
}
```

- **`amount`**: string, fixed decimal places for that ISO 4217 currency (e.g. USD/EUR/SAR → 2).
- **`currency`**: ISO 4217 code (uppercase in storage; clients should treat case-insensitively if needed).

**Listing** values are what was stored for the product/plan (`currency_id` on the model).

**Display** values are optional conversions for the **current request’s display currency** (see next section):

| Field (products) | Meaning |
|------------------|---------|
| `price` | Listing money |
| `price_display` | Converted money, or `null` if same as listing or conversion unavailable |
| `conversion_available` | `true` if a cross-rate was applied or listing equals display; `false` if a rate was missing |

**Plans (admin/public plan resource)** use the same pattern for:

- `monthly_price`, `monthly_price_display`, `monthly_conversion_available`
- `yearly_price`, `yearly_price_display`, `yearly_conversion_available`

**Important:** Unless your contracts say otherwise, treat converted amounts as **indicative** (reference / mid-market style), not a guaranteed settlement rate for payments.

---

## How the display currency is chosen

For each API request in the `api` middleware group, the app resolves **one display currency** in this order:

1. **Header override** (if enabled in config): default header name `X-App-Currency` with an enabled, active ISO code (e.g. `EUR`).
2. **Authenticated user preference** (`users.preferred_currency_id`), if set and still enabled/active.
3. **Base currency** from config (`APP_BASE_CURRENCY`, default `USD`).

Only currencies in the **allowlist** (`APP_ENABLED_CURRENCIES`, default `USD,EUR,SAR`) are accepted. Unknown header values are ignored and the resolver falls through.

Locale is unchanged: continue using `Accept-Language` and/or `X-App-Locale` as before.

---

## Environment configuration

| Variable | Default | Purpose |
|----------|---------|---------|
| `APP_ENABLED_CURRENCIES` | `USD,EUR,SAR` | Comma-separated allowlist; only these codes are seeded/accepted |
| `APP_BASE_CURRENCY` | `USD` | Pivot for stored rates: **1 base = rate × quote** |
| `APP_CURRENCY_HEADER` | `X-App-Currency` | Display currency override header name |
| `APP_CURRENCY_HEADER_ENABLED` | `true` | Whether the header is honored |
| `CURRENCY_CACHE_TTL` | `120` | Seconds to cache resolved “latest” rates per pair |
| `CURRENCY_RATE_MIN` / `CURRENCY_RATE_MAX` | `1e-10` / `1e10` | Bounds for manual (and validation) rates |
| `CURRENCY_FX_SYNC_ENABLED` | `false` | When `true`, scheduler runs `currency:sync-rates` (hourly) |
| `CURRENCY_FX_ALLOWED_HOSTS` | `api.frankfurter.app` | Allowlisted host(s) for the FX HTTP client |
| `CURRENCY_FX_TIMEOUT` | `10` | HTTP timeout (seconds) for FX fetch |

Config file: `config/currency.php`.

---

## Public endpoints

### List active allowlisted currencies

**`GET /api/v1/currencies`**

No authentication.

Returns only currencies that are **active** and in `APP_ENABLED_CURRENCIES`.

Example `data[]` item:

```json
{
  "code": "USD",
  "name": "US Dollar",
  "symbol": "$",
  "decimal_places": 2,
  "is_base": true
}
```

---

### Products (structured prices)

**`GET /api/v1/products`**

**`GET /api/v1/products/{id}`**

**`POST /api/v1/products`**

**`PUT /api/v1/products/{id}`**

Product payloads include `price`, `price_display`, and `conversion_available` as described above.

Optional body field when creating/updating:

| Field | Rules |
|-------|--------|
| `currency_code` | Optional ISO 4217; must be enabled and active. If omitted, **base currency** is used. |

---

### Plans (structured prices)

Public listing uses the **admin** `PlanController` index behind:

**`POST /api/v1/plans`**

(Existing app convention — not `GET`.)

Plan resources include monthly/yearly money objects and `_display` / `_conversion_available` fields. Optional `currency_code` on admin create/update (see Admin section).

---

## Authenticated (any role with `auth:api`)

### Update my display currency preference

**`PATCH /api/v1/me/currency-preference`**

**Body:**

```json
{
  "currency_code": "EUR"
}
```

**Rules:** `currency_code` required, 3 letters, must be in allowlist and exist as an **active** currency.

**Response:** Standard envelope; `data` uses `UserResource`, including `preferred_currency` when `preferredCurrency` is loaded (`code`, `symbol`).

---

## Admin (`auth:api` + admin role)

All routes below are under **`/api/v1/admin/`** and use the `currency-admin` rate limiter (per user + IP).

### List currencies (allowlist, including inactive)

**`GET /api/v1/admin/currencies`**

`data[]` uses `CurrencyResource`: `id`, `code`, `name`, `symbol`, `decimal_places`, `is_base`, `is_active`, `sort_order`.

---

### Update a seeded currency (metadata / flags)

**`PATCH /api/v1/admin/currencies/{currency}`**

`{currency}` is the numeric **id** (route model binding).

**Body (partial):**

```json
{
  "is_active": true,
  "sort_order": 0
}
```

You cannot deactivate the **base** currency.

---

### Append a manual exchange rate (history)

**`POST /api/v1/admin/currency/rates`**

Appends one row to the **append-only** `currency_rates` ledger (no in-place edits).

**Body:**

```json
{
  "quote_currency_code": "EUR",
  "rate": 0.92,
  "effective_at": "2025-01-15T00:00:00Z"
}
```

| Field | Required | Notes |
|--------|----------|--------|
| `quote_currency_code` | yes | Must be allowlisted & active; **cannot** equal base |
| `rate` | yes | Positive; must be within configured min/max |
| `effective_at` | no | ISO 8601; interpreted in **UTC**; defaults to now UTC |

Semantics: **1 unit of base currency = `rate` units of quote** (e.g. 1 USD = 0.92 EUR).

**Response `data` example:**

```json
{
  "id": 1,
  "base": "USD",
  "quote": "EUR",
  "rate": "0.9200000000",
  "effective_at": "2025-01-15T00:00:00+00:00",
  "source": "manual"
}
```

---

### Current derived rates (per quote vs base)

**`GET /api/v1/admin/currency/rates/current`**

Returns an array of objects with `base`, `quote`, `rate`, `effective_at`, `source` (or `null` fields if no row exists yet). Uses the same resolution rules as the app (`latest` effective row, manual tie-break).

---

### Rate history (paginated)

**`GET /api/v1/admin/currency/rates/history`**

**Query parameters (all optional):**

| Param | Description |
|-------|-------------|
| `base_code` | Filter by base ISO code |
| `quote_code` | Filter by quote ISO code |
| `source` | `manual` or `api` |
| `sync_batch_id` | UUID of one Frankfurter sync run |
| `effective_from` | Inclusive lower bound |
| `effective_to` | Inclusive upper bound |
| `per_page` | 1–100, default 20 |

**Response shape:**

```json
{
  "success": true,
  "message": "...",
  "data": {
    "data": [
      {
        "id": 1,
        "base": "USD",
        "quote": "EUR",
        "rate": "0.9200000000",
        "effective_at": "2025-01-15T00:00:00+00:00",
        "source": "api",
        "sync_batch_id": "uuid-or-null",
        "created_at": "2025-01-15T12:00:00+00:00"
      }
    ],
    "meta": {
      "current_page": 1,
      "last_page": 1,
      "per_page": 20,
      "total": 1
    }
  }
}
```

Ordering: newest `effective_at` first, then highest `id`.

---

### Trigger FX sync on demand

**`POST /api/v1/admin/currency/sync-rates`**

Only succeeds when `CURRENCY_FX_SYNC_ENABLED=true`. Runs `php artisan currency:sync-rates` and returns command output in `data.output`. If sync is disabled, responds with `422` and a clear message.

---

### Plans — optional `currency_code`

Admin plan create/update (`StorePlanRequest`) accepts optional **`currency_code`** (same allowlist rules). If omitted, the **base** currency is used for `currency_id`.

---

## Artisan: sync from Frankfurter

```bash
php artisan currency:sync-rates
```

- If `CURRENCY_FX_SYNC_ENABLED` is `false`, the command exits successfully after logging that sync is skipped.
- If `true`, fetches latest rates for base → other enabled quotes from **Frankfurter** (allowlisted host only), inserts **new** `api` rows with a shared `sync_batch_id`, and busts rate cache.
- Failures do **not** delete existing history.

When enabled, the app scheduler runs this command **hourly** (see `bootstrap/app.php`).

---

## Client integration checklist

1. Call **`GET /api/v1/currencies`** to populate a currency picker (only active allowlisted codes).
2. Send **`X-App-Currency`** on catalog requests if the shopper picked a display currency without logging in.
3. After login, optionally **`PATCH /api/v1/me/currency-preference`** so later requests default to that currency without the header.
4. Read **`price`** / **`price_display`** and **`conversion_available`** on products (and the analogous plan fields). If `conversion_available` is `false`, show listing only or hide converted amounts.
5. For admin tooling, use **rates/history** and **rates/current**; use **POST …/currency/rates** for manual corrections (always new rows).

---

## Related code (for maintainers)

| Area | Location |
|------|-----------|
| Config | `config/currency.php` |
| Display resolution | `App\Services\Currency\CurrencyDisplayResolver`, `CurrencyContext` |
| Rates & conversion | `App\Services\Currency\ExchangeRateService` |
| Manual / API inserts | `App\Services\Currency\CurrencyRateLedger` |
| Frankfurter client | `App\Services\Currency\FrankfurterExchangeRateClient` |
| Middleware | `App\Http\Middleware\SetRequestCurrency` |
| Models | `App\Models\Currency`, `App\Models\CurrencyExchangeRate` |
| Product money shaping | `App\Support\Currency\MoneyPresenter`, `ProductResource` |
| Plan money shaping | `Admin\PlanResource` |
| Feature tests | `tests/Feature/CurrencyApiTest.php` |
