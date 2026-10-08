-- ---------------------------------------------------------------------------
-- Bubble Cycler — database schema
-- MySQL 5.7+ / 8.x or MariaDB 10.4+ (InnoDB, utf8mb4)
--
-- Money is stored as BIGINT "micro-units": 1.00 = 1000000.
-- Ad credits are plain integers (1 credit = 1 ad view).
-- The installer (public/install.php) runs this file automatically; you can
-- also import it by hand and then create the admin account with the installer.
-- ---------------------------------------------------------------------------

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  `key`   VARCHAR(64) NOT NULL,
  `value` TEXT        NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username         VARCHAR(32)  NOT NULL,
  email            VARCHAR(190) NOT NULL,
  password_hash    VARCHAR(255) NOT NULL,
  role             ENUM('user','admin')    NOT NULL DEFAULT 'user',
  status           ENUM('active','banned') NOT NULL DEFAULT 'active',
  referrer_id      INT UNSIGNED NULL,
  purchase_balance BIGINT       NOT NULL DEFAULT 0,  -- funded by deposits, spent on bubbles
  cash_balance     BIGINT       NOT NULL DEFAULT 0,  -- bubble payouts + referral commissions, withdrawable
  ad_credits       BIGINT       NOT NULL DEFAULT 0,  -- unallocated advertising credits
  total_deposited  BIGINT       NOT NULL DEFAULT 0,
  total_earned     BIGINT       NOT NULL DEFAULT 0,
  total_withdrawn  BIGINT       NOT NULL DEFAULT 0,
  total_ref_earned BIGINT       NOT NULL DEFAULT 0,
  bubbles_bought   INT UNSIGNED NOT NULL DEFAULT 0,
  pops_seen_at     DATETIME     NULL,
  last_login_at    DATETIME     NULL,
  last_ip          VARCHAR(45)  NULL,
  register_ip      VARCHAR(45)  NULL,
  totp_secret      VARCHAR(255) NULL,   -- encrypted two-factor secret
  totp_recovery    TEXT         NULL,   -- JSON list of hashed recovery codes
  totp_last_step   BIGINT       NULL,   -- last accepted code (anti-replay)
  totp_enabled_at  DATETIME     NULL,
  lang             CHAR(2)      NULL,     -- preferred language (en, fr): pages and emails
  created_at       DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_referrer (referrer_id),
  KEY idx_users_register_ip (register_ip)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Single-row table (id = 1). Every purchase locks this row, which serialises
-- the FIFO queue and keeps the pool maths race-free.
CREATE TABLE IF NOT EXISTS pool (
  id              TINYINT UNSIGNED NOT NULL,
  balance         BIGINT       NOT NULL DEFAULT 0,  -- money in the pool, not paid out yet
  total_in        BIGINT       NOT NULL DEFAULT 0,  -- everything ever credited to the pool
  total_injected  BIGINT       NOT NULL DEFAULT 0,  -- part of total_in added by admins
  total_out       BIGINT       NOT NULL DEFAULT 0,  -- everything paid to expired bubbles
  site_revenue    BIGINT       NOT NULL DEFAULT 0,  -- platform share of purchases
  referral_paid   BIGINT       NOT NULL DEFAULT 0,  -- commissions paid out of the platform share
  bubbles_sold    INT UNSIGNED NOT NULL DEFAULT 0,
  bubbles_expired INT UNSIGNED NOT NULL DEFAULT 0,
  target_sold     BIGINT       NOT NULL DEFAULT 0,  -- sum of targets of every bubble sold
  updated_at      DATETIME     NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchases (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED NOT NULL,
  quantity    INT UNSIGNED NOT NULL,
  unit_price  BIGINT       NOT NULL,
  total       BIGINT       NOT NULL,
  pool_amount BIGINT       NOT NULL,
  wallet      ENUM('purchase','cash') NOT NULL,
  ad_credits  INT UNSIGNED NOT NULL DEFAULT 0,
  ad_view_id  BIGINT UNSIGNED NULL,
  first_bubble INT UNSIGNED NOT NULL,
  last_bubble  INT UNSIGNED NOT NULL,
  created_at  DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_purchases_user (user_id, id),
  KEY idx_purchases_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A bubble's id is its dense queue number (1, 2, 3 …). Bubbles expire strictly
-- in id order, so the head of the queue is always bubbles_expired + 1.
CREATE TABLE IF NOT EXISTS bubbles (
  id           INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  purchase_id  INT UNSIGNED NOT NULL,
  price        BIGINT       NOT NULL,
  target       BIGINT       NOT NULL,             -- expires (pays out) at this amount
  cum_target   BIGINT       NOT NULL,             -- running sum of targets up to this bubble
  ahead_at_buy INT UNSIGNED NOT NULL DEFAULT 0,   -- bubbles in front of it when bought
  earned       BIGINT       NOT NULL DEFAULT 0,
  status       ENUM('active','expired') NOT NULL DEFAULT 'active',
  created_at   DATETIME     NOT NULL,
  expired_at   DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_bubbles_status (status, id),
  KEY idx_bubbles_user (user_id, status, id),
  KEY idx_bubbles_expired (expired_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ledger: every balance movement, with the wallet balance right after it.
CREATE TABLE IF NOT EXISTS transactions (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED    NOT NULL,
  wallet        ENUM('purchase','cash','ads') NOT NULL,
  type          VARCHAR(32)     NOT NULL,
  amount        BIGINT          NOT NULL,
  balance_after BIGINT          NOT NULL,
  description   VARCHAR(255)    NOT NULL,
  ref_type      VARCHAR(20)     NULL,
  ref_id        BIGINT UNSIGNED NULL,
  created_at    DATETIME        NOT NULL,
  PRIMARY KEY (id),
  KEY idx_tx_user (user_id, id),
  KEY idx_tx_type (type, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manual deposit & withdrawal methods, managed from the admin panel.
CREATE TABLE IF NOT EXISTS payment_methods (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type           ENUM('deposit','withdrawal') NOT NULL,
  name           VARCHAR(80)  NOT NULL,
  currency       VARCHAR(20)  NOT NULL DEFAULT 'USD',
  logo_url       VARCHAR(500) NULL,
  color          VARCHAR(7)   NOT NULL DEFAULT '#8b5cf6',
  account_label  VARCHAR(80)  NOT NULL DEFAULT '',
  account_value  VARCHAR(500) NOT NULL DEFAULT '',
  instructions   TEXT         NULL,
  min_amount     BIGINT       NOT NULL DEFAULT 0,
  max_amount     BIGINT       NOT NULL DEFAULT 0,   -- 0 = no maximum
  fee_fixed      BIGINT       NOT NULL DEFAULT 0,
  fee_percent_bp INT UNSIGNED NOT NULL DEFAULT 0,   -- basis points: 250 = 2.50 %
  require_proof  TINYINT(1)   NOT NULL DEFAULT 0,
  status         ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order     INT          NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL,
  updated_at     DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_methods_type (type, status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS deposits (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  method_id     INT UNSIGNED NULL,
  method_name   VARCHAR(80)  NOT NULL,
  amount        BIGINT       NOT NULL,
  fee           BIGINT       NOT NULL DEFAULT 0,
  credit_amount BIGINT       NOT NULL,
  reference     VARCHAR(190) NOT NULL DEFAULT '',
  sender        VARCHAR(190) NOT NULL DEFAULT '',
  proof_file    VARCHAR(64)  NULL,
  status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  admin_note    VARCHAR(255) NULL,
  processed_by  INT UNSIGNED NULL,
  processed_at  DATETIME     NULL,
  created_at    DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_deposits_status (status, id),
  KEY idx_deposits_user (user_id, id),
  KEY idx_deposits_reference (method_id, reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS withdrawals (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       INT UNSIGNED NOT NULL,
  method_id     INT UNSIGNED NULL,
  method_name   VARCHAR(80)  NOT NULL,
  amount        BIGINT       NOT NULL,
  fee           BIGINT       NOT NULL DEFAULT 0,
  payout_amount BIGINT       NOT NULL,
  account       VARCHAR(500) NOT NULL,
  status        ENUM('pending','paid','rejected','cancelled') NOT NULL DEFAULT 'pending',
  txid          VARCHAR(190) NULL,
  admin_note    VARCHAR(255) NULL,
  processed_by  INT UNSIGNED NULL,
  processed_at  DATETIME     NULL,
  created_at    DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_withdrawals_status (status, id),
  KEY idx_withdrawals_user (user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Advertising: member campaigns (paid with ad credits) and unlimited house ads.
CREATE TABLE IF NOT EXISTS ad_campaigns (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           INT UNSIGNED NULL,
  is_house          TINYINT(1)   NOT NULL DEFAULT 0,
  title             VARCHAR(80)  NOT NULL,
  description       VARCHAR(220) NOT NULL DEFAULT '',
  url               VARCHAR(500) NOT NULL,
  image_url         VARCHAR(500) NULL,
  cta_label         VARCHAR(30)  NOT NULL DEFAULT 'Visit site',
  credits_total     BIGINT       NOT NULL DEFAULT 0,
  credits_remaining BIGINT       NOT NULL DEFAULT 0,
  views             INT UNSIGNED NOT NULL DEFAULT 0,
  clicks            INT UNSIGNED NOT NULL DEFAULT 0,
  status            ENUM('pending','active','paused','completed','rejected') NOT NULL DEFAULT 'pending',
  admin_note        VARCHAR(255) NULL,
  last_shown_at     DATETIME     NULL,
  created_at        DATETIME     NOT NULL,
  updated_at        DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_ads_rotation (status, is_house, last_shown_at),
  KEY idx_ads_user (user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per ad shown in front of the buy form. A completed, unused view
-- unlocks exactly one purchase.
CREATE TABLE IF NOT EXISTS ad_views (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campaign_id  INT UNSIGNED    NOT NULL,
  user_id      INT UNSIGNED    NOT NULL,
  token        CHAR(32)        NOT NULL,
  started_at   DATETIME        NOT NULL,
  completed_at DATETIME        NULL,
  used_at      DATETIME        NULL,
  purchase_id  INT UNSIGNED    NULL,
  clicked      TINYINT(1)      NOT NULL DEFAULT 0,
  ip           VARCHAR(45)     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ad_views_token (token),
  KEY idx_ad_views_user (user_id, id),
  KEY idx_ad_views_campaign (campaign_id),
  KEY idx_ad_views_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip           VARCHAR(45)  NOT NULL,
  login        VARCHAR(190) NOT NULL,
  attempted_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_login_attempts_ip (ip, attempted_at),
  KEY idx_login_attempts_time (attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Generic throttling (registrations, password resets, two-factor attempts).
CREATE TABLE IF NOT EXISTS rate_limits (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  bucket     VARCHAR(32)  NOT NULL,
  subject    VARCHAR(190) NOT NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_rate_limits_lookup (bucket, subject, created_at),
  KEY idx_rate_limits_time (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password reset links: only a SHA-256 hash of the emailed token is stored.
CREATE TABLE IF NOT EXISTS password_resets (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id    INT UNSIGNED NOT NULL,
  token_hash CHAR(64)     NOT NULL,
  expires_at DATETIME     NOT NULL,
  used_at    DATETIME     NULL,
  ip         VARCHAR(45)  NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_password_resets_token (token_hash),
  KEY idx_password_resets_user (user_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_logs (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id   INT UNSIGNED NOT NULL,
  action     VARCHAR(64)  NOT NULL,
  details    VARCHAR(500) NOT NULL DEFAULT '',
  ip         VARCHAR(45)  NULL,
  created_at DATETIME     NOT NULL,
  PRIMARY KEY (id),
  KEY idx_admin_logs_admin (admin_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
