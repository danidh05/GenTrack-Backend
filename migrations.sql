-- =============================================================
-- GenTrack API — Database Migrations
-- Run this in phpMyAdmin on your target database (e.g. gentrack)
-- =============================================================

-- -------------------------------------------------------------
-- 1. customers
--    One row per customer per owner (user).
--    local_id comes from the Android SQLite auto-increment.
-- -------------------------------------------------------------
CREATE TABLE customers (
  id         INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  owner_uid  VARCHAR(128)    NOT NULL,
  local_id   INT UNSIGNED    NOT NULL,
  name       VARCHAR(255)    NOT NULL,
  phone      VARCHAR(32),
  location   VARCHAR(255),
  amps       INT UNSIGNED    NOT NULL,
  status     ENUM('Active','Unpaid','Disconnected') NOT NULL DEFAULT 'Active',
  notes      TEXT,
  image_url  VARCHAR(512),
  created_at DATETIME        NOT NULL,
  updated_at DATETIME        NOT NULL,
  UNIQUE KEY uq_owner_local (owner_uid, local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- 2. bills
--    Monthly electricity bills per customer.
--    customer_local_id references customers.local_id (not .id).
-- -------------------------------------------------------------
CREATE TABLE bills (
  id               INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
  owner_uid        VARCHAR(128)    NOT NULL,
  local_id         INT UNSIGNED    NOT NULL,
  customer_local_id INT UNSIGNED   NOT NULL,
  month            VARCHAR(7)      NOT NULL,          -- format: YYYY-MM
  amps             INT UNSIGNED    NOT NULL,
  price_per_amp    DECIMAL(10,2)   NOT NULL,
  total            DECIMAL(12,2)   NOT NULL,
  previous_balance DECIMAL(12,2)   NOT NULL DEFAULT 0,
  final_total      DECIMAL(12,2)   NOT NULL,
  status           ENUM('Paid','Partial','Unpaid') NOT NULL DEFAULT 'Unpaid',
  billing_model    VARCHAR(20)     NOT NULL DEFAULT 'flat',
  current_reading  DECIMAL(10,2)  NOT NULL DEFAULT 0,
  previous_reading DECIMAL(10,2)  NOT NULL DEFAULT 0,
  consumption      DECIMAL(10,2)  NOT NULL DEFAULT 0,
  tier_fee         DECIMAL(10,2)  NOT NULL DEFAULT 0,
  created_at       DATETIME        NOT NULL,
  updated_at       DATETIME        NOT NULL,
  UNIQUE KEY uq_owner_local (owner_uid, local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- 3. payments
--    Payments made against a bill.
--    bill_local_id references bills.local_id (not .id).
-- -------------------------------------------------------------
CREATE TABLE payments (
  id                INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  owner_uid         VARCHAR(128)  NOT NULL,
  local_id          INT UNSIGNED  NOT NULL,
  bill_local_id     INT UNSIGNED  NOT NULL,
  amount_paid       DECIMAL(12,2) NOT NULL,
  date              DATE          NOT NULL,
  remaining_balance DECIMAL(12,2) NOT NULL,
  created_at        DATETIME      NOT NULL,
  updated_at        DATETIME      NOT NULL,
  UNIQUE KEY uq_owner_local (owner_uid, local_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- 4. remote_config
--    Per-user settings: default price per amp + generator capacity.
--    Primary key is owner_uid — one row per user.
-- -------------------------------------------------------------
CREATE TABLE remote_config (
  owner_uid             VARCHAR(128)  PRIMARY KEY,
  default_price_per_amp DECIMAL(10,2) NOT NULL DEFAULT 0,
  generator_capacity    INT UNSIGNED  DEFAULT 0,
  price_5a              DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_10a             DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_15a             DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_per_kwh         DECIMAL(10,2) NOT NULL DEFAULT 0,
  base_price_5a         DECIMAL(10,2) NOT NULL DEFAULT 0,
  base_price_10a        DECIMAL(10,2) NOT NULL DEFAULT 0,
  base_price_15a        DECIMAL(10,2) NOT NULL DEFAULT 0,
  currency              VARCHAR(10)   NOT NULL DEFAULT 'USD',
  updated_at            DATETIME      NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -------------------------------------------------------------
-- 5. monthly_reports
--    Aggregated billing summary per user per month.
--    Used by the dashboard bar chart (last 3 months).
-- -------------------------------------------------------------
CREATE TABLE monthly_reports (
  id                     INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  owner_uid              VARCHAR(128)  NOT NULL,
  month                  VARCHAR(7)    NOT NULL,       -- format: YYYY-MM
  total_customers_billed INT UNSIGNED  NOT NULL,
  total_expected_revenue DECIMAL(14,2) NOT NULL,
  created_at             DATETIME      NOT NULL,
  UNIQUE KEY uq_owner_month (owner_uid, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
