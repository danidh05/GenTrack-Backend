# GenTrack Backend API — Specification Document
> PHP + MySQL REST API for the GenTrack Android app.
> Read this fully before writing any code.
> Keep it simple, keep it clean, keep it secure.

---

## 0. What This Is

A PHP REST API that acts as the online database layer for the GenTrack Android app. The Android app posts data here after saving locally to SQLite. This API also serves remote config (default price per amp) and monthly billing reports back to the app.

**Stack:** PHP 8.x, MySQL, no frameworks, no Composer  
**Hosting:** Awardspace.net  
**Auth:** Firebase ID token verification via Firebase REST API  
**Response format:** JSON only  
**No web frontend** — this is consumed only by the Android app via Volley

---

## 1. Architecture Rules

- Every endpoint (except `/health.php`) verifies a Firebase ID token before doing anything. No exceptions.
- The UID used in every query comes from the verified token — never from the request body.
- All SQL uses PDO with prepared statements. No string concatenation into queries. Ever.
- Validate all input before touching the database.
- Return consistent JSON for every response — success and error alike.
- Never echo PHP errors or stack traces. Use `error_reporting(0)` in production.
- DB credentials and Firebase API key live in `config.php` only.
- Each endpoint is its own PHP file. One file = one operation. Easy to read, easy to debug.

---

## 2. Folder Structure

```
gentrack-api/                    ← root (NOT the webroot)
  config.php                     ← DB credentials + Firebase API key
  db.php                         ← PDO connection function
  auth.php                       ← Firebase token verification function
  helpers.php                    ← respond(), respondError(), validate functions
  
  customers/
    create.php                   ← POST: upsert customer
    delete.php                   ← POST: delete customer
    list.php                     ← GET: all customers for this user
  
  bills/
    create.php                   ← POST: upsert bill
    list.php                     ← GET: bills, optionally filtered by customer
  
  payments/
    create.php                   ← POST: upsert payment
    list.php                     ← GET: payments for a bill
  
  config/
    get.php                      ← GET: default price per amp
    update.php                   ← POST: update default price per amp
  
  reports/
    save.php                     ← POST: save monthly report summary
    list.php                     ← GET: last 3 monthly reports
  
  sync/
    pull.php                     ← GET: all data for first-login device pull
  
  health.php                     ← GET: no auth, just returns 200 OK
  
  .htaccess                      ← protects config.php from direct access
```

On Awardspace, put this entire folder inside your hosting account but point the domain to it directly. If Awardspace only gives you a `public_html/` or `httpdocs/` webroot, place everything inside that and protect `config.php` via `.htaccess`.

---

## 3. MySQL Database Schema

Run these in phpMyAdmin on Awardspace after creating your database.

```sql
CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_uid VARCHAR(128) NOT NULL,
  local_id INT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  phone VARCHAR(32),
  location VARCHAR(255),
  amps INT UNSIGNED NOT NULL,
  status ENUM('Active','Unpaid','Disconnected') NOT NULL DEFAULT 'Active',
  notes TEXT,
  image_url VARCHAR(512),
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_owner_local (owner_uid, local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bills (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_uid VARCHAR(128) NOT NULL,
  local_id INT UNSIGNED NOT NULL,
  customer_local_id INT UNSIGNED NOT NULL,
  month VARCHAR(7) NOT NULL,
  amps INT UNSIGNED NOT NULL,
  price_per_amp DECIMAL(10,2) NOT NULL,
  total DECIMAL(12,2) NOT NULL,
  previous_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  final_total DECIMAL(12,2) NOT NULL,
  status ENUM('Paid','Partial','Unpaid') NOT NULL DEFAULT 'Unpaid',
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_owner_local (owner_uid, local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_uid VARCHAR(128) NOT NULL,
  local_id INT UNSIGNED NOT NULL,
  bill_local_id INT UNSIGNED NOT NULL,
  amount_paid DECIMAL(12,2) NOT NULL,
  date DATE NOT NULL,
  remaining_balance DECIMAL(12,2) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  UNIQUE KEY uq_owner_local (owner_uid, local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE remote_config (
  owner_uid VARCHAR(128) PRIMARY KEY,
  default_price_per_amp DECIMAL(10,2) NOT NULL DEFAULT 0,
  generator_capacity INT UNSIGNED DEFAULT 0,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE monthly_reports (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  owner_uid VARCHAR(128) NOT NULL,
  month VARCHAR(7) NOT NULL,
  total_customers_billed INT UNSIGNED NOT NULL,
  total_expected_revenue DECIMAL(14,2) NOT NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_owner_month (owner_uid, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Schema decisions explained
- `owner_uid + local_id` is the unique identifier for every row. The server's own `id` column is internal only — we never send it to Android.
- `customer_local_id` and `bill_local_id` reference the Android app's local IDs, not the server's IDs. This avoids the ID mismatch problem between SQLite and MySQL.
- All upserts use `INSERT ... ON DUPLICATE KEY UPDATE` so the same endpoint handles both create and update.
- `updated_at` is included on all tables for future sync ordering if needed.

---

## 4. Security — Firebase Token Verification

### Why this matters
Without verification, anyone who discovers the API URL can send any UID and read or write any user's data. Token verification closes this completely.

### How it works
1. Android sends every request with an `Authorization: Bearer <firebase_id_token>` header
2. PHP calls Firebase's token verification REST endpoint with the token
3. Firebase returns the user's UID if the token is valid
4. PHP uses that UID for all database queries — the body-sent UID is ignored entirely

### Implementation in auth.php
```php
<?php
function verifyFirebaseToken(): string {
    $cfg = require __DIR__ . '/config.php';

    // Get the token from the Authorization header
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';

    if (!str_starts_with($authHeader, 'Bearer ')) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Missing or invalid Authorization header']);
        exit;
    }

    $idToken = substr($authHeader, 7);

    // Call Firebase REST API to verify the token
    $url = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . $cfg['firebase_web_api_key'];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$result) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Token verification failed']);
        exit;
    }

    $data = json_decode($result, true);
    $uid = $data['users'][0]['localId'] ?? null;

    if (!$uid) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Invalid token']);
        exit;
    }

    return $uid;
}
```

---

## 5. Shared Files

### config.php
```php
<?php
return [
    'db_host'              => 'localhost',
    'db_name'              => 'YOUR_DB_NAME',
    'db_user'              => 'YOUR_DB_USER',
    'db_pass'              => 'YOUR_DB_PASS',
    'firebase_web_api_key' => 'YOUR_FIREBASE_WEB_API_KEY',
];
```

Provide `config.example.php` in the repo. Create `config.php` directly on the server. Never commit the real one.

The Firebase Web API Key is found in Firebase Console → Project Settings → General → Web API Key. This is not a secret key (it's public-safe), but we keep it in config for cleanliness.

### db.php
```php
<?php
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $cfg = require __DIR__ . '/config.php';
        $pdo = new PDO(
            "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset=utf8mb4",
            $cfg['db_user'],
            $cfg['db_pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );
    }
    return $pdo;
}
```

### helpers.php
```php
<?php
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function respondError(string $message, int $code = 400): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function getBody(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function requireParam(array $data, string $key): mixed {
    if (!isset($data[$key]) || $data[$key] === '') {
        respondError("Missing required field: $key");
    }
    return $data[$key];
}

function isValidStatus(string $val, array $allowed): bool {
    return in_array($val, $allowed, true);
}

function isPositiveInt(mixed $val): bool {
    return filter_var($val, FILTER_VALIDATE_INT) !== false && (int)$val > 0;
}

function isPositiveDecimal(mixed $val): bool {
    return filter_var($val, FILTER_VALIDATE_FLOAT) !== false && (float)$val >= 0;
}

function now(): string {
    return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}
```

### Standard endpoint template (every endpoint follows this pattern)
```php
<?php
// 1. Turn off error display
error_reporting(0);

// 2. Include shared files
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../helpers.php';

// 3. Verify token — returns the UID or exits with 401
$uid = verifyFirebaseToken();

// 4. Parse input
$body = getBody();

// 5. Validate
$localId = requireParam($body, 'local_id');
if (!isPositiveInt($localId)) respondError('local_id must be a positive integer');

// 6. Execute SQL with prepared statements
$db = getDB();
$stmt = $db->prepare("...");
$stmt->execute([...]);

// 7. Return JSON
respond(['message' => 'OK']);
```

---

## 6. Endpoint Specifications

### GET /health.php
No auth. Returns 200 if server is up.
```php
<?php
header('Content-Type: application/json');
echo json_encode(['success' => true, 'data' => ['status' => 'ok']]);
```

---

### POST /customers/create.php
Upsert a customer. If `(owner_uid, local_id)` already exists → update it. If not → insert.

**Android sends:**
```json
{
  "local_id": 5,
  "name": "John Doe",
  "phone": "+96170000000",
  "location": "Hamra, Beirut",
  "amps": 5,
  "status": "Active",
  "notes": "Pays on time",
  "image_url": null,
  "created_at": "2025-05-01 10:30:00",
  "updated_at": "2025-05-01 10:30:00"
}
```

**Validation:**
- `local_id`: required, positive integer
- `name`: required, string, not empty, max 255 chars
- `amps`: required, positive integer, max 1000
- `status`: required, must be one of: Active, Unpaid, Disconnected
- `phone`: optional, max 32 chars
- `location`: optional, max 255 chars

**SQL:**
```sql
INSERT INTO customers 
  (owner_uid, local_id, name, phone, location, amps, status, notes, image_url, created_at, updated_at)
VALUES 
  (:uid, :local_id, :name, :phone, :location, :amps, :status, :notes, :image_url, :created_at, :updated_at)
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  phone = VALUES(phone),
  location = VALUES(location),
  amps = VALUES(amps),
  status = VALUES(status),
  notes = VALUES(notes),
  image_url = VALUES(image_url),
  updated_at = VALUES(updated_at)
```

**Response 200:** `{ "success": true, "data": { "message": "Customer saved" } }`

---

### GET /customers/list.php
Return all customers for the authenticated user. Called on first-login device pull and can be called on demand.

**SQL:**
```sql
SELECT local_id, name, phone, location, amps, status, notes, image_url, created_at, updated_at
FROM customers
WHERE owner_uid = :uid
ORDER BY name ASC
```

**Response 200:**
```json
{
  "success": true,
  "data": {
    "customers": [ {...}, {...} ]
  }
}
```

---

### POST /customers/delete.php
Delete a customer if no bills exist for them.

**Android sends:** `{ "local_id": 5 }`

**Logic:**
1. Check if any row in `bills` has `owner_uid = :uid AND customer_local_id = :local_id`
2. If yes → return 409 with `"error": "Customer has existing bills and cannot be deleted"`
3. If no → delete from customers

---

### POST /bills/create.php
Upsert a bill.

**Android sends:**
```json
{
  "local_id": 12,
  "customer_local_id": 5,
  "month": "2025-05",
  "amps": 5,
  "price_per_amp": 10.50,
  "total": 52.50,
  "previous_balance": 25.00,
  "final_total": 77.50,
  "status": "Unpaid",
  "created_at": "2025-05-01 10:00:00",
  "updated_at": "2025-05-01 10:00:00"
}
```

**Validation:**
- `local_id`, `customer_local_id`: required, positive integers
- `month`: required, format `YYYY-MM`
- `amps`: required, positive integer
- `price_per_amp`, `total`, `previous_balance`, `final_total`: required, non-negative decimals
- `status`: required, one of: Paid, Partial, Unpaid

---

### GET /bills/list.php
Return bills for the authenticated user. Optional filter by customer.

**Query string:** `?customer_local_id=5` (optional)

```sql
-- Without filter:
SELECT * FROM bills WHERE owner_uid = :uid ORDER BY created_at DESC

-- With filter:
SELECT * FROM bills WHERE owner_uid = :uid AND customer_local_id = :cid ORDER BY created_at DESC
```

---

### POST /payments/create.php
Upsert a payment.

**Android sends:**
```json
{
  "local_id": 8,
  "bill_local_id": 12,
  "amount_paid": 30.00,
  "date": "2025-05-15",
  "remaining_balance": 47.50,
  "created_at": "2025-05-15 14:20:00",
  "updated_at": "2025-05-15 14:20:00"
}
```

**Validation:**
- `local_id`, `bill_local_id`: required, positive integers
- `amount_paid`: required, decimal greater than 0
- `date`: required, valid date format `YYYY-MM-DD`
- `remaining_balance`: required, decimal >= 0

---

### GET /payments/list.php
Return payments for a specific bill.

**Query string:** `?bill_local_id=12` (required)

---

### GET /config/get.php
Return the default price per amp for this user. If not set yet, return defaults.

```sql
SELECT default_price_per_amp, generator_capacity
FROM remote_config
WHERE owner_uid = :uid
```

If no row found, return `{ "default_price_per_amp": 0, "generator_capacity": 0 }`.

---

### POST /config/update.php
Upsert the remote config.

**Android sends:**
```json
{ "default_price_per_amp": 10.50, "generator_capacity": 100 }
```

```sql
INSERT INTO remote_config (owner_uid, default_price_per_amp, generator_capacity, updated_at)
VALUES (:uid, :price, :capacity, :now)
ON DUPLICATE KEY UPDATE
  default_price_per_amp = VALUES(default_price_per_amp),
  generator_capacity = VALUES(generator_capacity),
  updated_at = VALUES(updated_at)
```

---

### POST /reports/save.php
Save or update a monthly billing summary. Called after batch bill generation.

**Android sends:**
```json
{
  "month": "2025-05",
  "total_customers_billed": 35,
  "total_expected_revenue": 1850.00
}
```

Uses `INSERT ... ON DUPLICATE KEY UPDATE` keyed on `(owner_uid, month)`.

---

### GET /reports/list.php
Return the last 3 monthly reports for this user, newest first. Used by the dashboard bar chart.

**Query string:** `?limit=3`

```sql
SELECT month, total_customers_billed, total_expected_revenue
FROM monthly_reports
WHERE owner_uid = :uid
ORDER BY month DESC
LIMIT :limit
```

---

### GET /sync/pull.php
Returns all data for a user in one call. Used on first login when local SQLite is empty.

**SQL:** Four separate queries — customers, bills, payments, remote_config — then merge into one JSON response.

**Response:**
```json
{
  "success": true,
  "data": {
    "customers": [...],
    "bills": [...],
    "payments": [...],
    "config": { "default_price_per_amp": 10.50, "generator_capacity": 100 }
  }
}
```

---

## 7. .htaccess

Place in the project root (same level as config.php):

```apache
# Prevent direct access to config.php
<Files "config.php">
  Order allow,deny
  Deny from all
</Files>

# Force HTTPS
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Set content type for all responses
AddType application/json .php
```

---

## 8. Error Handling Standard

Every endpoint wraps its main logic in try/catch:

```php
try {
    // ... logic here
} catch (PDOException $e) {
    // Log to file, not to response
    error_log('[GenTrack] DB error in ' . __FILE__ . ': ' . $e->getMessage());
    respondError('A database error occurred. Please try again.', 500);
} catch (Exception $e) {
    error_log('[GenTrack] Error in ' . __FILE__ . ': ' . $e->getMessage());
    respondError('An unexpected error occurred.', 500);
}
```

Never return the actual exception message to the client. Log it, return generic message.

---

## 9. Build Phases

### Phase 1 — Shared files + health check
- `config.php` + `config.example.php`
- `db.php`
- `auth.php`
- `helpers.php`
- `health.php`
- `.htaccess`

**Verify:** Call `health.php` directly in browser → `{"success":true,"data":{"status":"ok"}}`

### Phase 2 — Database
- Write and run `migrations.sql` containing all 5 CREATE TABLE statements
- Verify in phpMyAdmin that all tables exist with correct columns

### Phase 3 — Customer endpoints
- `customers/create.php`
- `customers/delete.php`
- `customers/list.php`

**Verify:** Test with Postman using a real Firebase token.

### Phase 4 — Bill endpoints
- `bills/create.php`
- `bills/list.php`

### Phase 5 — Payment endpoints
- `payments/create.php`
- `payments/list.php`

### Phase 6 — Config + Reports
- `config/get.php`
- `config/update.php`
- `reports/save.php`
- `reports/list.php`

### Phase 7 — Sync
- `sync/pull.php`

### Phase 8 — Deploy to Awardspace
See deployment checklist below.

---

## 10. Deployment Checklist (Awardspace)

1. Create Awardspace account, note the domain
2. Create MySQL database via control panel, note db name, user, password
3. Open phpMyAdmin, run `migrations.sql`
4. Upload all PHP files via FTP (use FileZilla)
5. Create `config.php` directly on the server via Awardspace file manager — paste in credentials
6. Verify `health.php` returns 200 via browser
7. Open Postman, set `BASE_URL` to your Awardspace domain
8. Set `FIREBASE_TOKEN` to a token from your Android app (log it temporarily during testing)
9. Test every endpoint top to bottom

---

## 11. Firebase Setup (do this alongside Phase 1)

1. Go to [console.firebase.google.com](https://console.firebase.google.com)
2. Create project → name it `GenTrack`
3. Go to Authentication → Sign-in method → Enable Email/Password
4. Authentication → Users → Add user → create your admin email + password (this is your one account)
5. Go to Project Settings → General → copy the **Web API Key** → paste into `config.php`
6. Go to Firestore → Create database → Start in production mode
7. Firestore → Rules → paste these rules:

```javascript
rules_version = '2';
service cloud.firestore {
  match /databases/{database}/documents {
    match /announcements/{doc} {
      allow read: if request.auth != null && request.auth.uid == resource.data.uid;
      allow create: if request.auth != null && request.auth.uid == request.resource.data.uid;
      allow update, delete: if request.auth != null && request.auth.uid == resource.data.uid;
    }
  }
}
```

8. Go to Project Settings → Add app → Android
   - Package name: `com.gentrack`
   - Download `google-services.json` → place in Android `app/` folder
9. Run this in terminal to get your debug SHA-1 and paste it in Firebase → Project Settings → Your apps:
```
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android
```

---

## 12. Endpoint Summary

| Method | File | Auth | Purpose |
|---|---|---|---|
| GET | /health.php | No | Server health check |
| POST | /customers/create.php | Yes | Upsert customer |
| GET | /customers/list.php | Yes | List all customers |
| POST | /customers/delete.php | Yes | Delete customer (blocks if bills exist) |
| POST | /bills/create.php | Yes | Upsert bill |
| GET | /bills/list.php | Yes | List bills (filter by customer) |
| POST | /payments/create.php | Yes | Upsert payment |
| GET | /payments/list.php | Yes | List payments for a bill |
| GET | /config/get.php | Yes | Get default price per amp |
| POST | /config/update.php | Yes | Update default price per amp |
| POST | /reports/save.php | Yes | Save monthly billing summary |
| GET | /reports/list.php | Yes | Get last N monthly reports |
| GET | /sync/pull.php | Yes | Pull all data (first login) |

---

## 13. What Claude Code Must NOT Do

- Do not use any framework (Laravel, Slim, CodeIgniter)
- Do not use Composer or any external packages
- Do not trust UID from request body — always from verified Firebase token
- Do not use `mysqli` — PDO only
- Do not concatenate user input into SQL — prepared statements always
- Do not return PHP error messages or stack traces in responses
- Do not use `$_GET` for POST data or vice versa — be explicit
- Do not skip validation
- Do not commit `config.php` to the repo
- Do not write files into the webroot except PHP source files

---

*End of specification. Start with Phase 1. Test each phase with Postman before moving on.*
