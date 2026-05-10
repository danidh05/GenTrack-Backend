# GenTrack — Database & API Contract Reference
> Use this document to align the MySQL schema, PHP API, and Android SQLite/model layer.
> Every table, every column, every JSON field, every type, every constraint — in one place.

---

## Core Rules (apply to every table)

| Rule | Detail |
|---|---|
| `id` is internal | The MySQL auto-increment `id` column is **never sent to Android** |
| `owner_uid` comes from the token | Never from the request body — always from verified Firebase JWT |
| Unique key | Every row is identified by `(owner_uid, local_id)` — not MySQL `id` |
| `local_id` | The Android SQLite `_id` / AUTOINCREMENT value, sent up and stored as-is |
| Foreign keys by `local_id` | `customer_local_id` and `bill_local_id` reference Android local IDs, not MySQL IDs |
| DECIMAL comes back as string | PDO returns MySQL DECIMAL columns as PHP strings — Android must parse them |

---

---

## Table 1 — `customers`

### MySQL columns

| Column | MySQL Type | Nullable | Default | Constraint |
|---|---|---|---|---|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | Internal only, never sent to app |
| `owner_uid` | VARCHAR(128) | NO | — | From Firebase token |
| `local_id` | INT UNSIGNED | NO | — | Android SQLite row ID |
| `name` | VARCHAR(255) | NO | — | Max 255 chars, trimmed |
| `phone` | VARCHAR(32) | YES | NULL | Max 32 chars |
| `location` | VARCHAR(255) | YES | NULL | Max 255 chars |
| `amps` | INT UNSIGNED | NO | — | 1 – 1000 |
| `status` | ENUM | NO | `'Active'` | `Active` · `Unpaid` · `Disconnected` |
| `notes` | TEXT | YES | NULL | No length limit |
| `image_url` | VARCHAR(512) | YES | NULL | Max 512 chars |
| `created_at` | DATETIME | NO | — | Sent by Android, format: `YYYY-MM-DD HH:MM:SS` |
| `updated_at` | DATETIME | NO | — | Sent by Android, format: `YYYY-MM-DD HH:MM:SS` |

**Unique key:** `(owner_uid, local_id)`

---

### Android → API &nbsp;`POST /customers/create.php`

```json
{
  "local_id":   5,
  "name":       "John Doe",
  "phone":      "+96170000000",
  "location":   "Hamra, Beirut",
  "amps":       5,
  "status":     "Active",
  "notes":      "Pays on time",
  "image_url":  null,
  "created_at": "2025-05-01 10:00:00",
  "updated_at": "2025-05-01 10:00:00"
}
```

| Field | Required | Type rule |
|---|---|---|
| `local_id` | YES | positive integer |
| `name` | YES | non-empty string, max 255 |
| `amps` | YES | integer, 1–1000 |
| `status` | YES | exactly one of: `Active`, `Unpaid`, `Disconnected` |
| `created_at` | YES | `YYYY-MM-DD HH:MM:SS` |
| `updated_at` | YES | `YYYY-MM-DD HH:MM:SS` |
| `phone` | NO | string, max 32, may be `null` |
| `location` | NO | string, max 255, may be `null` |
| `notes` | NO | string, may be `null` |
| `image_url` | NO | string, may be `null` |

---

### API → Android &nbsp;`GET /customers/list.php` · `GET /sync/pull.php`

```json
{
  "local_id":   5,
  "name":       "John Doe",
  "phone":      "+96170000000",
  "location":   "Hamra, Beirut",
  "amps":       8,
  "status":     "Unpaid",
  "notes":      "Updated",
  "image_url":  null,
  "created_at": "2025-05-01 10:00:00",
  "updated_at": "2025-05-02 09:00:00"
}
```

`id` and `owner_uid` are excluded from the SELECT — they are never in the response.
Ordered by `name ASC`.

---

### Android SQLite equivalent

```sql
CREATE TABLE customers (
  _id        INTEGER PRIMARY KEY AUTOINCREMENT,  -- becomes local_id
  name       TEXT    NOT NULL,
  phone      TEXT,
  location   TEXT,
  amps       INTEGER NOT NULL,
  status     TEXT    NOT NULL DEFAULT 'Active',  -- Active | Unpaid | Disconnected
  notes      TEXT,
  image_url  TEXT,
  created_at TEXT    NOT NULL,
  updated_at TEXT    NOT NULL
);
```

---

---

## Table 2 — `bills`

### MySQL columns

| Column | MySQL Type | Nullable | Default | Constraint |
|---|---|---|---|---|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | Internal only |
| `owner_uid` | VARCHAR(128) | NO | — | From Firebase token |
| `local_id` | INT UNSIGNED | NO | — | Android SQLite row ID |
| `customer_local_id` | INT UNSIGNED | NO | — | References `customers.local_id` (Android ID) |
| `month` | VARCHAR(7) | NO | — | Format: `YYYY-MM` |
| `amps` | INT UNSIGNED | NO | — | > 0 |
| `price_per_amp` | DECIMAL(10,2) | NO | — | >= 0 |
| `total` | DECIMAL(12,2) | NO | — | >= 0 |
| `previous_balance` | DECIMAL(12,2) | NO | `0` | >= 0 |
| `final_total` | DECIMAL(12,2) | NO | — | >= 0 |
| `status` | ENUM | NO | `'Unpaid'` | `Paid` · `Partial` · `Unpaid` |
| `created_at` | DATETIME | NO | — | Sent by Android |
| `updated_at` | DATETIME | NO | — | Sent by Android |

**Unique key:** `(owner_uid, local_id)`

---

### Android → API &nbsp;`POST /bills/create.php`

```json
{
  "local_id":          12,
  "customer_local_id": 5,
  "month":             "2025-05",
  "amps":              5,
  "price_per_amp":     10.50,
  "total":             52.50,
  "previous_balance":  25.00,
  "final_total":       77.50,
  "status":            "Unpaid",
  "created_at":        "2025-05-01 10:00:00",
  "updated_at":        "2025-05-01 10:00:00"
}
```

| Field | Required | Type rule |
|---|---|---|
| `local_id` | YES | positive integer |
| `customer_local_id` | YES | positive integer |
| `month` | YES | string matching `YYYY-MM` |
| `amps` | YES | positive integer |
| `price_per_amp` | YES | decimal >= 0 |
| `total` | YES | decimal >= 0 |
| `previous_balance` | YES | decimal >= 0 |
| `final_total` | YES | decimal >= 0 |
| `status` | YES | exactly one of: `Paid`, `Partial`, `Unpaid` |
| `created_at` | YES | `YYYY-MM-DD HH:MM:SS` |
| `updated_at` | YES | `YYYY-MM-DD HH:MM:SS` |

---

### API → Android &nbsp;`GET /bills/list.php` · `GET /sync/pull.php`

```json
{
  "local_id":          12,
  "customer_local_id": 5,
  "month":             "2025-05",
  "amps":              5,
  "price_per_amp":     "10.50",
  "total":             "52.50",
  "previous_balance":  "25.00",
  "final_total":       "77.50",
  "status":            "Unpaid",
  "created_at":        "2025-05-01 10:00:00",
  "updated_at":        "2025-05-01 10:00:00"
}
```

> **DECIMAL fields come back as strings.**
> Android must call `Double.parseDouble()` or use `BigDecimal` on:
> `price_per_amp`, `total`, `previous_balance`, `final_total`

Ordered by `created_at DESC`. Optional filter: `?customer_local_id=5`

---

### Android SQLite equivalent

```sql
CREATE TABLE bills (
  _id                INTEGER PRIMARY KEY AUTOINCREMENT,  -- becomes local_id
  customer_id        INTEGER NOT NULL,   -- local customer _id → becomes customer_local_id
  month              TEXT    NOT NULL,   -- YYYY-MM
  amps               INTEGER NOT NULL,
  price_per_amp      REAL    NOT NULL,
  total              REAL    NOT NULL,
  previous_balance   REAL    NOT NULL DEFAULT 0,
  final_total        REAL    NOT NULL,
  status             TEXT    NOT NULL DEFAULT 'Unpaid',  -- Paid | Partial | Unpaid
  created_at         TEXT    NOT NULL,
  updated_at         TEXT    NOT NULL
);
```

---

---

## Table 3 — `payments`

### MySQL columns

| Column | MySQL Type | Nullable | Default | Constraint |
|---|---|---|---|---|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | Internal only |
| `owner_uid` | VARCHAR(128) | NO | — | From Firebase token |
| `local_id` | INT UNSIGNED | NO | — | Android SQLite row ID |
| `bill_local_id` | INT UNSIGNED | NO | — | References `bills.local_id` (Android ID) |
| `amount_paid` | DECIMAL(12,2) | NO | — | **Strictly > 0** |
| `date` | DATE | NO | — | Format: `YYYY-MM-DD`, must be a valid calendar date |
| `remaining_balance` | DECIMAL(12,2) | NO | — | >= 0 |
| `created_at` | DATETIME | NO | — | Sent by Android |
| `updated_at` | DATETIME | NO | — | Sent by Android |

**Unique key:** `(owner_uid, local_id)`

---

### Android → API &nbsp;`POST /payments/create.php`

```json
{
  "local_id":          8,
  "bill_local_id":     12,
  "amount_paid":       30.00,
  "date":              "2025-05-15",
  "remaining_balance": 47.50,
  "created_at":        "2025-05-15 14:20:00",
  "updated_at":        "2025-05-15 14:20:00"
}
```

| Field | Required | Type rule |
|---|---|---|
| `local_id` | YES | positive integer |
| `bill_local_id` | YES | positive integer |
| `amount_paid` | YES | decimal **> 0** (zero is rejected) |
| `date` | YES | `YYYY-MM-DD`, valid calendar date (e.g. `2025-02-30` is rejected) |
| `remaining_balance` | YES | decimal >= 0 |
| `created_at` | YES | `YYYY-MM-DD HH:MM:SS` |
| `updated_at` | YES | `YYYY-MM-DD HH:MM:SS` |

---

### API → Android &nbsp;`GET /payments/list.php` · `GET /sync/pull.php`

```json
{
  "local_id":          8,
  "bill_local_id":     12,
  "amount_paid":       "30.00",
  "date":              "2025-05-15",
  "remaining_balance": "47.50",
  "created_at":        "2025-05-15 14:20:00",
  "updated_at":        "2025-05-15 14:20:00"
}
```

> **DECIMAL fields come back as strings.**
> Android must parse `amount_paid` and `remaining_balance` to double.

Ordered by `date DESC`. Filter required: `?bill_local_id=12` — missing it returns 400.

---

### Android SQLite equivalent

```sql
CREATE TABLE payments (
  _id               INTEGER PRIMARY KEY AUTOINCREMENT,  -- becomes local_id
  bill_id           INTEGER NOT NULL,  -- local bill _id → becomes bill_local_id
  amount_paid       REAL    NOT NULL,  -- must be > 0
  date              TEXT    NOT NULL,  -- YYYY-MM-DD
  remaining_balance REAL    NOT NULL,
  created_at        TEXT    NOT NULL,
  updated_at        TEXT    NOT NULL
);
```

---

---

## Table 4 — `remote_config`

### MySQL columns

| Column | MySQL Type | Nullable | Default | Constraint |
|---|---|---|---|---|
| `owner_uid` | VARCHAR(128) | NO | — | **PRIMARY KEY** — one row per user, no `id` column |
| `default_price_per_amp` | DECIMAL(10,2) | NO | `0` | >= 0 |
| `generator_capacity` | INT UNSIGNED | YES | `0` | >= 0 |
| `updated_at` | DATETIME | NO | — | Set by server via `now()` — Android does not send this |

**No `id` column. No `local_id`. No `updated_at` sent from Android.**
The server sets `updated_at` itself on every upsert.

---

### Android → API &nbsp;`POST /config/update.php`

```json
{
  "default_price_per_amp": 10.50,
  "generator_capacity":    100
}
```

| Field | Required | Type rule |
|---|---|---|
| `default_price_per_amp` | YES | decimal >= 0 |
| `generator_capacity` | YES | integer >= 0 |

---

### API → Android &nbsp;`GET /config/get.php` · `GET /sync/pull.php`

```json
{
  "default_price_per_amp": 10.5,
  "generator_capacity":    100
}
```

Both fields are explicitly cast by PHP (`(float)` and `(int)`), so they arrive as proper numbers, not strings.
If no config row exists yet for this user, returns `{ "default_price_per_amp": 0, "generator_capacity": 0 }`.

---

### Android storage
This does not need a SQLite table. Store in `SharedPreferences` (or a single-row settings table if preferred).
No `local_id` involved.

---

---

## Table 5 — `monthly_reports`

### MySQL columns

| Column | MySQL Type | Nullable | Default | Constraint |
|---|---|---|---|---|
| `id` | INT UNSIGNED | NO | AUTO_INCREMENT | Internal only |
| `owner_uid` | VARCHAR(128) | NO | — | From Firebase token |
| `month` | VARCHAR(7) | NO | — | Format: `YYYY-MM` |
| `total_customers_billed` | INT UNSIGNED | NO | — | >= 0 |
| `total_expected_revenue` | DECIMAL(14,2) | NO | — | >= 0 |
| `created_at` | DATETIME | NO | — | Set by server via `now()` on first insert only |

**Unique key:** `(owner_uid, month)`

> **No `local_id`** — reports are keyed by `month` string, not Android row ID.
> **No `updated_at`** — this is the only table that lacks it. On upsert, only the data columns are overwritten; `created_at` stays from the original insert.

---

### Android → API &nbsp;`POST /reports/save.php`

```json
{
  "month":                  "2025-05",
  "total_customers_billed": 35,
  "total_expected_revenue": 1850.00
}
```

| Field | Required | Type rule |
|---|---|---|
| `month` | YES | string matching `YYYY-MM` |
| `total_customers_billed` | YES | integer >= 0 |
| `total_expected_revenue` | YES | decimal >= 0 |

---

### API → Android &nbsp;`GET /reports/list.php`

```json
{
  "month":                  "2025-05",
  "total_customers_billed": 36,
  "total_expected_revenue": 1920.0
}
```

Both numeric fields are cast by PHP. Ordered by `month DESC`.
Default limit: 3. Accepted range: 1–12. Pass via `?limit=3`.

---

---

## Summary: Type Parsing Cheat Sheet for Android

| Column | MySQL type | JSON arrives as | Android must parse to |
|---|---|---|---|
| `local_id`, `amps`, `customer_local_id`, `bill_local_id` | INT | number | `int` |
| `price_per_amp`, `total`, `previous_balance`, `final_total` | DECIMAL | **string** e.g. `"10.50"` | `Double.parseDouble()` |
| `amount_paid`, `remaining_balance` | DECIMAL | **string** e.g. `"30.00"` | `Double.parseDouble()` |
| `default_price_per_amp` | DECIMAL | number (PHP-cast) | `double` |
| `generator_capacity` | INT | number (PHP-cast) | `int` |
| `total_customers_billed` | INT | number (PHP-cast) | `int` |
| `total_expected_revenue` | DECIMAL | number (PHP-cast) | `double` |
| `status` | ENUM | string | `String` / enum |
| `month` | VARCHAR(7) | string `YYYY-MM` | `String` |
| `date` | DATE | string `YYYY-MM-DD` | `String` / `LocalDate` |
| `created_at`, `updated_at` | DATETIME | string `YYYY-MM-DD HH:MM:SS` | `String` / `LocalDateTime` |
| `phone`, `location`, `notes`, `image_url` | VARCHAR/TEXT | string or `null` | nullable `String` |

---

## Summary: What Each Table Needs from Android

| Table | Android sends `local_id`? | Android sends timestamps? | Server sets any field? |
|---|---|---|---|
| `customers` | YES | YES (both) | Only `owner_uid` from token |
| `bills` | YES | YES (both) | Only `owner_uid` from token |
| `payments` | YES | YES (both) | Only `owner_uid` from token |
| `remote_config` | NO | NO | `owner_uid` + `updated_at` |
| `monthly_reports` | NO | NO | `owner_uid` + `created_at` |
