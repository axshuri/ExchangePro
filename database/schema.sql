-- ============================================================================
-- CURRENCY EXCHANGE MANAGEMENT & ACCOUNTING SYSTEM - MySQL 8 Schema
-- Money is always DECIMAL(30,10). Never use FLOAT/DOUBLE for money.
-- ============================================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------- auth / users
CREATE TABLE IF NOT EXISTS users (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username         VARCHAR(64)  NOT NULL UNIQUE,
    email            VARCHAR(190) NOT NULL UNIQUE,
    password_hash    VARCHAR(255) NOT NULL,
    full_name        VARCHAR(120) NOT NULL,
    role_id          INT UNSIGNED NOT NULL,
    status           ENUM('active','inactive','locked') NOT NULL DEFAULT 'active',
    totp_secret      VARCHAR(64)  NULL,
    totp_enabled     TINYINT(1)   NOT NULL DEFAULT 0,
    must_change_pwd  TINYINT(1)   NOT NULL DEFAULT 0,
    last_login_at    DATETIME     NULL,
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(64)  NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    is_system   TINYINT(1)   NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS permissions (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(64)  NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS role_permissions (
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rp_perm FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_history (
    id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NULL,
    username   VARCHAR(64)  NOT NULL,
    ip         VARCHAR(64)  NULL,
    user_agent VARCHAR(255) NULL,
    success    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_user (user_id),
    KEY idx_login_time (created_at)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- currencies
CREATE TABLE IF NOT EXISTS currencies (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code              VARCHAR(8)   NOT NULL UNIQUE,       -- ISO 4217
    name              VARCHAR(80)  NOT NULL,
    localized_name    VARCHAR(80)  NULL,
    symbol            VARCHAR(8)   NULL,
    amount_precision  TINYINT      NOT NULL DEFAULT 2,     -- display decimals
    rate_precision    TINYINT      NOT NULL DEFAULT 4,
    is_base           TINYINT(1)   NOT NULL DEFAULT 0,
    is_active         TINYINT(1)   NOT NULL DEFAULT 1,
    min_amount        DECIMAL(30,10) NULL,
    max_amount        DECIMAL(30,10) NULL,
    notes             TEXT         NULL,
    created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS exchange_rates (
    currency_id      INT UNSIGNED PRIMARY KEY,
    buy_rate         DECIMAL(30,10) NOT NULL DEFAULT 0,
    sell_rate        DECIMAL(30,10) NOT NULL DEFAULT 0,
    mid_rate         DECIMAL(30,10) NOT NULL DEFAULT 0,
    previous_buy     DECIMAL(30,10) NULL,
    previous_sell    DECIMAL(30,10) NULL,
    -- External reference rate (online market/reference rate, e.g. Frankfurter).
    reference_rate      DECIMAL(30,10) NULL,
    previous_reference  DECIMAL(30,10) NULL,
    -- Automatic Buy/Sell calculation from the reference rate.
    -- NULL = use the global default spread from Settings.
    buy_spread_type     ENUM('fixed','percent') NULL,
    buy_spread_value    DECIMAL(30,10) NULL,
    sell_spread_type    ENUM('fixed','percent') NULL,
    sell_spread_value   DECIMAL(30,10) NULL,
    -- Manual overrides: when set, they win over automatic calculation.
    buy_override        DECIMAL(30,10) NULL,
    sell_override       DECIMAL(30,10) NULL,
    -- 0 = temporary override (until next sync), 1 = persistent (sync never touches it).
    override_persistent TINYINT(1) NOT NULL DEFAULT 0,
    source           VARCHAR(64)    NULL,                 -- manual | api | name
    is_manual        TINYINT(1)     NOT NULL DEFAULT 1,
    provider         VARCHAR(64)    NULL,                 -- provider identifier, e.g. frankfurter
    provider_timestamp DATETIME     NULL,                 -- rate date reported by the provider
    retrieved_at     DATETIME       NULL,                 -- when the app fetched the rate
    rate_status      ENUM('online','cached','stale','manual') NOT NULL DEFAULT 'manual',
    updated_by       INT UNSIGNED   NULL,
    updated_at       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rates_currency FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE CASCADE,
    CONSTRAINT fk_rates_user FOREIGN KEY (updated_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS rate_history (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    currency_id  INT UNSIGNED NOT NULL,
    buy_rate     DECIMAL(30,10) NOT NULL,
    sell_rate    DECIMAL(30,10) NOT NULL,
    mid_rate     DECIMAL(30,10) NOT NULL,
    source       VARCHAR(64) NULL,
    is_manual    TINYINT(1)  NOT NULL DEFAULT 1,
    changed_by   INT UNSIGNED NULL,
    changed_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ratehist_currency FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE CASCADE,
    KEY idx_ratehist_cur_time (currency_id, changed_at)
) ENGINE=InnoDB;

-- Append-only external reference-rate history (never overwritten).
CREATE TABLE IF NOT EXISTS exchange_rate_history (
    id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    currency_id       INT UNSIGNED NOT NULL,
    base_currency_id  INT UNSIGNED NOT NULL,
    reference_rate    DECIMAL(30,10) NOT NULL,
    provider          VARCHAR(64) NULL,
    provider_timestamp DATETIME   NULL,
    retrieved_at      DATETIME    NOT NULL,
    sync_id           BIGINT UNSIGNED NULL,
    created_at        DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_erh_currency FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE CASCADE,
    KEY idx_erh_cur_time (currency_id, retrieved_at)
) ENGINE=InnoDB;

-- Every synchronization attempt (login / manual / cron) is logged here.
CREATE TABLE IF NOT EXISTS rate_sync_logs (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider            VARCHAR(64) NOT NULL,
    status              ENUM('success','failed','partial','skipped') NOT NULL DEFAULT 'success',
    triggered_by        ENUM('login','manual','cron') NOT NULL DEFAULT 'manual',
    started_at          DATETIME NOT NULL,
    completed_at        DATETIME NULL,
    currencies_updated  INT UNSIGNED NOT NULL DEFAULT 0,
    currencies_skipped  INT UNSIGNED NOT NULL DEFAULT 0,
    currencies_failed   INT UNSIGNED NOT NULL DEFAULT 0,
    error_message       TEXT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_rsl_time (started_at),
    KEY idx_rsl_status (status)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- chart of accounts (P&L side)
CREATE TABLE IF NOT EXISTS gl_accounts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(32)  NOT NULL UNIQUE,
    name        VARCHAR(120) NOT NULL,
    type        ENUM('income','expense','equity','asset','liability') NOT NULL DEFAULT 'income',
    is_system   TINYINT(1)   NOT NULL DEFAULT 0,
    is_active   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------- locations / accounts
CREATE TABLE IF NOT EXISTS accounts (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(32)  NOT NULL UNIQUE,
    name           VARCHAR(120) NOT NULL,
    type           ENUM('cash_desk','vault','bank','wallet','other') NOT NULL DEFAULT 'cash_desk',
    is_active      TINYINT(1)   NOT NULL DEFAULT 1,
    bank_name      VARCHAR(120) NULL,     -- for bank type
    account_number VARCHAR(120) NULL,     -- protected identifier
    account_holder VARCHAR(120) NULL,
    notes          TEXT         NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS account_currencies (
    account_id  INT UNSIGNED NOT NULL,
    currency_id INT UNSIGNED NOT NULL,
    balance     DECIMAL(30,10) NOT NULL DEFAULT 0,  -- derived from ledger (cache)
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (account_id, currency_id),
    CONSTRAINT fk_acctcur_account FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
    CONSTRAINT fk_acctcur_currency FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------- customers
CREATE TABLE IF NOT EXISTS customers (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                VARCHAR(32)  NOT NULL UNIQUE,
    full_name           VARCHAR(160) NOT NULL,
    phone               VARCHAR(40)  NULL,
    email               VARCHAR(190) NULL,
    address             TEXT         NULL,
    id_type             VARCHAR(32)  NULL,
    id_number           VARCHAR(64)  NULL,
    notes               TEXT         NULL,
    status              ENUM('active','inactive','blocked') NOT NULL DEFAULT 'active',
    created_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_transaction_at DATETIME     NULL
) ENGINE=InnoDB;

-- Customer balances: positive = customer owes us (receivable), negative = we owe customer (payable)
CREATE TABLE IF NOT EXISTS customer_accounts (
    customer_id INT UNSIGNED NOT NULL,
    currency_id INT UNSIGNED NOT NULL,
    balance     DECIMAL(30,10) NOT NULL DEFAULT 0,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id, currency_id),
    CONSTRAINT fk_ca_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
    CONSTRAINT fk_ca_currency FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------- transactions
CREATE TABLE IF NOT EXISTS transactions (
    id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tx_number                VARCHAR(40)  NOT NULL UNIQUE,
    type                     ENUM('buy','sell','exchange','reversal','adjustment','deposit','withdrawal','expense','income','transfer') NOT NULL,
    status                   ENUM('draft','pending','completed','cancelled','reversed') NOT NULL DEFAULT 'draft',
    customer_id              INT UNSIGNED NULL,
    employee_id              INT UNSIGNED NOT NULL,
    currency_id              INT UNSIGNED NULL,   -- primary currency for buy/sell
    rate                     DECIMAL(30,10) NULL, -- rate stored permanently (never overwritten)
    foreign_amount           DECIMAL(30,10) NULL,
    base_amount              DECIMAL(30,10) NULL,
    fee_amount               DECIMAL(30,10) NULL,
    fee_currency_id          INT UNSIGNED NULL,
    discount_amount          DECIMAL(30,10) NULL,
    total_amount             DECIMAL(30,10) NULL,
    payment_method           ENUM('cash','bank_transfer','card','internal_balance','other') NULL,
    source_account_id        INT UNSIGNED NULL,
    destination_account_id   INT UNSIGNED NULL,
    notes                    TEXT NULL,
    original_transaction_id  BIGINT UNSIGNED NULL,
    reversal_transaction_id  BIGINT UNSIGNED NULL,
    compliance_status        ENUM('normal','requires_review','reviewed','escalated') NOT NULL DEFAULT 'normal',
    is_large                 TINYINT(1) NOT NULL DEFAULT 0,
    tx_date                  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at             DATETIME NULL,
    updated_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tx_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
    CONSTRAINT fk_tx_employee FOREIGN KEY (employee_id) REFERENCES users(id),
    CONSTRAINT fk_tx_currency FOREIGN KEY (currency_id) REFERENCES currencies(id),
    CONSTRAINT fk_tx_orig FOREIGN KEY (original_transaction_id) REFERENCES transactions(id),
    CONSTRAINT fk_tx_rev FOREIGN KEY (reversal_transaction_id) REFERENCES transactions(id),
    KEY idx_tx_type_status (type, status),
    KEY idx_tx_date (tx_date),
    KEY idx_tx_customer (customer_id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaction_items (
    id                 BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id     BIGINT UNSIGNED NOT NULL,
    line_no            TINYINT NOT NULL DEFAULT 1,
    source_currency_id INT UNSIGNED NOT NULL,   -- customer gives
    target_currency_id INT UNSIGNED NOT NULL,   -- customer receives
    source_amount      DECIMAL(30,10) NOT NULL,
    target_amount      DECIMAL(30,10) NOT NULL,
    rate               DECIMAL(30,10) NOT NULL, -- target per 1 source (cross rate)
    base_amount        DECIMAL(30,10) NOT NULL,
    CONSTRAINT fk_ti_tx FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_ti_src FOREIGN KEY (source_currency_id) REFERENCES currencies(id),
    CONSTRAINT fk_ti_tgt FOREIGN KEY (target_currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transaction_fees (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT UNSIGNED NOT NULL,
    type           ENUM('fixed','percent','currency','customer','transaction') NOT NULL,
    amount         DECIMAL(30,10) NOT NULL,
    currency_id    INT UNSIGNED NOT NULL,
    base_amount    DECIMAL(30,10) NOT NULL,
    description    VARCHAR(255) NULL,
    CONSTRAINT fk_tf_tx FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE,
    CONSTRAINT fk_tf_currency FOREIGN KEY (currency_id) REFERENCES currencies(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- double-entry ledger
CREATE TABLE IF NOT EXISTS journal_entries (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_no    VARCHAR(40) NOT NULL UNIQUE,
    transaction_id BIGINT UNSIGNED NULL,
    description VARCHAR(255) NULL,
    created_by  INT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_je_tx FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    CONSTRAINT fk_je_user FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS journal_lines (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entry_id     BIGINT UNSIGNED NOT NULL,
    account_id   INT UNSIGNED NULL,     -- location (cash desk/vault/bank)
    gl_account_id INT UNSIGNED NULL,    -- P&L account
    currency_id  INT UNSIGNED NOT NULL,
    debit        DECIMAL(30,10) NOT NULL DEFAULT 0,
    credit       DECIMAL(30,10) NOT NULL DEFAULT 0,
    base_debit   DECIMAL(30,10) NOT NULL DEFAULT 0,
    base_credit  DECIMAL(30,10) NOT NULL DEFAULT 0,
    rate         DECIMAL(30,10) NULL,
    note         VARCHAR(255) NULL,
    CONSTRAINT fk_jl_entry FOREIGN KEY (entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    CONSTRAINT fk_jl_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_jl_gl FOREIGN KEY (gl_account_id) REFERENCES gl_accounts(id),
    CONSTRAINT fk_jl_currency FOREIGN KEY (currency_id) REFERENCES currencies(id),
    CONSTRAINT chk_jl_single_side CHECK (
        (debit > 0 AND credit = 0) OR (credit > 0 AND debit = 0)
    ),
    KEY idx_jl_account_cur (account_id, currency_id),
    KEY idx_jl_gl (gl_account_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- inventory
CREATE TABLE IF NOT EXISTS inventory_costings (
    currency_id INT UNSIGNED PRIMARY KEY,
    qty         DECIMAL(30,10) NOT NULL DEFAULT 0,
    total_cost  DECIMAL(30,10) NOT NULL DEFAULT 0, -- in base currency
    avg_cost    DECIMAL(30,10) NOT NULL DEFAULT 0,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_cost_currency FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS inventory_movements (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT UNSIGNED NULL,
    account_id     INT UNSIGNED NOT NULL,
    currency_id    INT UNSIGNED NOT NULL,
    direction      ENUM('in','out') NOT NULL,
    amount         DECIMAL(30,10) NOT NULL,
    rate           DECIMAL(30,10) NULL,
    base_amount    DECIMAL(30,10) NOT NULL,
    balance_after  DECIMAL(30,10) NULL,
    note           VARCHAR(255) NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_im_tx FOREIGN KEY (transaction_id) REFERENCES transactions(id),
    CONSTRAINT fk_im_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_im_currency FOREIGN KEY (currency_id) REFERENCES currencies(id),
    KEY idx_im_account_cur (account_id, currency_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- expenses & income
CREATE TABLE IF NOT EXISTS expenses (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_number  VARCHAR(40) NOT NULL UNIQUE,
    category    VARCHAR(64) NOT NULL,
    amount      DECIMAL(30,10) NOT NULL,
    currency_id INT UNSIGNED NOT NULL,
    base_amount DECIMAL(30,10) NOT NULL,
    rate        DECIMAL(30,10) NULL,
    account_id  INT UNSIGNED NOT NULL,        -- paid from
    expense_date DATE NOT NULL,
    description TEXT NULL,
    employee_id INT UNSIGNED NOT NULL,
    reference_no VARCHAR(64) NULL,
    attachment_path VARCHAR(255) NULL,
    gl_account_id INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_exp_currency FOREIGN KEY (currency_id) REFERENCES currencies(id),
    CONSTRAINT fk_exp_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_exp_employee FOREIGN KEY (employee_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS income (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_number  VARCHAR(40) NOT NULL UNIQUE,
    category    VARCHAR(64) NOT NULL,
    amount      DECIMAL(30,10) NOT NULL,
    currency_id INT UNSIGNED NOT NULL,
    base_amount DECIMAL(30,10) NOT NULL,
    rate        DECIMAL(30,10) NULL,
    account_id  INT UNSIGNED NOT NULL,        -- received into
    income_date DATE NOT NULL,
    description TEXT NULL,
    employee_id INT UNSIGNED NOT NULL,
    gl_account_id INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_inc_currency FOREIGN KEY (currency_id) REFERENCES currencies(id),
    CONSTRAINT fk_inc_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_inc_employee FOREIGN KEY (employee_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS transfers (
    id                   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ref_number           VARCHAR(40) NOT NULL UNIQUE,
    source_account_id    INT UNSIGNED NOT NULL,
    destination_account_id INT UNSIGNED NOT NULL,
    currency_id          INT UNSIGNED NOT NULL,
    amount               DECIMAL(30,10) NOT NULL,
    base_amount          DECIMAL(30,10) NOT NULL,
    rate                 DECIMAL(30,10) NULL,
    transfer_date        DATE NOT NULL,
    note                 TEXT NULL,
    employee_id          INT UNSIGNED NOT NULL,
    status               ENUM('completed','cancelled') NOT NULL DEFAULT 'completed',
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_tr_src FOREIGN KEY (source_account_id) REFERENCES accounts(id),
    CONSTRAINT fk_tr_dst FOREIGN KEY (destination_account_id) REFERENCES accounts(id),
    CONSTRAINT fk_tr_currency FOREIGN KEY (currency_id) REFERENCES currencies(id),
    CONSTRAINT fk_tr_employee FOREIGN KEY (employee_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- cash counting
CREATE TABLE IF NOT EXISTS currency_denominations (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    currency_id INT UNSIGNED NOT NULL,
    kind        ENUM('banknote','coin') NOT NULL DEFAULT 'banknote',
    value       DECIMAL(30,10) NOT NULL,
    label       VARCHAR(32) NULL,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    CONSTRAINT fk_den_currency FOREIGN KEY (currency_id) REFERENCES currencies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cash_counts (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    count_number VARCHAR(40) NOT NULL UNIQUE,
    account_id   INT UNSIGNED NOT NULL,
    count_date   DATE NOT NULL,
    status       ENUM('draft','confirmed') NOT NULL DEFAULT 'draft',
    employee_id  INT UNSIGNED NOT NULL,
    notes        TEXT NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cc_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_cc_employee FOREIGN KEY (employee_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cash_count_items (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cash_count_id  BIGINT UNSIGNED NOT NULL,
    currency_id    INT UNSIGNED NOT NULL,
    denomination_id INT UNSIGNED NULL,
    quantity       DECIMAL(30,10) NOT NULL DEFAULT 0,
    total          DECIMAL(30,10) NOT NULL DEFAULT 0,
    CONSTRAINT fk_cci_count FOREIGN KEY (cash_count_id) REFERENCES cash_counts(id) ON DELETE CASCADE,
    CONSTRAINT fk_cci_currency FOREIGN KEY (currency_id) REFERENCES currencies(id),
    CONSTRAINT fk_cci_denom FOREIGN KEY (denomination_id) REFERENCES currency_denominations(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- reconciliation
CREATE TABLE IF NOT EXISTS reconciliations (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rec_number   VARCHAR(40) NOT NULL UNIQUE,
    account_id   INT UNSIGNED NOT NULL,
    currency_id  INT UNSIGNED NOT NULL,
    system_balance   DECIMAL(30,10) NOT NULL,
    physical_balance DECIMAL(30,10) NOT NULL,
    difference       DECIMAL(30,10) NOT NULL,
    reason           TEXT NULL,
    status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    created_by   INT UNSIGNED NOT NULL,
    approved_by  INT UNSIGNED NULL,
    approved_at  DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_rec_account FOREIGN KEY (account_id) REFERENCES accounts(id),
    CONSTRAINT fk_rec_currency FOREIGN KEY (currency_id) REFERENCES currencies(id),
    CONSTRAINT fk_rec_created FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_rec_approved FOREIGN KEY (approved_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- daily closing
CREATE TABLE IF NOT EXISTS daily_closings (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    closing_date DATE NOT NULL UNIQUE,
    status       ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
    opened_by    INT UNSIGNED NOT NULL,
    closed_by    INT UNSIGNED NULL,
    opened_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    closed_at    DATETIME NULL,
    opening_balances JSON NULL,
    closing_balances JSON NULL,
    differences  JSON NULL,
    notes        TEXT NULL,
    CONSTRAINT fk_dc_opened FOREIGN KEY (opened_by) REFERENCES users(id),
    CONSTRAINT fk_dc_closed FOREIGN KEY (closed_by) REFERENCES users(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------- audit & misc
CREATE TABLE IF NOT EXISTS audit_logs (
    id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED NULL,
    action       VARCHAR(64) NOT NULL,
    entity_type  VARCHAR(64) NOT NULL,
    entity_id    VARCHAR(64) NULL,
    previous_value JSON NULL,
    new_value    JSON NULL,
    ip           VARCHAR(64) NULL,
    user_agent   VARCHAR(255) NULL,
    reason       VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_time (created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS attachments (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(64) NOT NULL,
    entity_id   BIGINT UNSIGNED NOT NULL,
    file_name   VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime        VARCHAR(128) NULL,
    size        BIGINT UNSIGNED NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NULL,     -- NULL = all users
    type        VARCHAR(64) NOT NULL,
    title       VARCHAR(190) NOT NULL,
    message     TEXT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at     DATETIME NULL,
    KEY idx_notif_user_read (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key   VARCHAR(64) NOT NULL UNIQUE,
    setting_value TEXT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS backup_records (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name   VARCHAR(255) NOT NULL,
    file_path   VARCHAR(255) NOT NULL,
    size        BIGINT UNSIGNED NULL,
    kind        ENUM('manual','scheduled','restore_point') NOT NULL DEFAULT 'manual',
    status      ENUM('ok','failed') NOT NULL DEFAULT 'ok',
    created_by  INT UNSIGNED NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
