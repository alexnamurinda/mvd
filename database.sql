-- ============================================================
-- BusinessPro - Multi-Business Management App
-- Database Schema for MySQL 5.7+ / MariaDB 10+
-- ============================================================

CREATE DATABASE IF NOT EXISTS businesspro
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE businesspro;

-- ============================================================
-- USERS & AUTHENTICATION
-- ============================================================
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    full_name       VARCHAR(120) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    phone           VARCHAR(20),
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('ceo','manager','staff') DEFAULT 'ceo',
    avatar          VARCHAR(255) DEFAULT NULL,
    business_name   VARCHAR(150) DEFAULT 'My Business',
    currency        VARCHAR(10) DEFAULT 'UGX',
    timezone        VARCHAR(50) DEFAULT 'Africa/Kampala',
    receipt_footer  TEXT DEFAULT NULL,
    status          ENUM('active','suspended') DEFAULT 'active',
    last_login      DATETIME DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- BUSINESSES (multi-business support)
-- ============================================================
CREATE TABLE businesses (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    name            VARCHAR(150) NOT NULL,
    type            ENUM('wifi','retail','services','rental','other') DEFAULT 'wifi',
    description     TEXT,
    address         VARCHAR(255),
    phone           VARCHAR(20),
    is_active       TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- WIFI MODULE
-- ============================================================

-- Internet packages (Daily, Weekly, Monthly etc.)
CREATE TABLE wifi_packages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    business_id     INT NOT NULL,
    name            VARCHAR(100) NOT NULL,
    duration_days   INT NOT NULL,
    speed_mbps      VARCHAR(20),
    price           DECIMAL(12,2) NOT NULL,
    description     VARCHAR(255),
    is_active       TINYINT(1) DEFAULT 1,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
);

-- Customers (WiFi subscribers)
CREATE TABLE wifi_customers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    business_id     INT NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    phone           VARCHAR(20) NOT NULL,
    alt_phone       VARCHAR(20),
    email           VARCHAR(150),
    location        VARCHAR(255),
    house_no        VARCHAR(50),
    device_mac      VARCHAR(50),
    pppoe_username  VARCHAR(80),
    notes           TEXT,
    status          ENUM('active','suspended','disconnected') DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    INDEX (phone),
    INDEX (status)
);

-- Subscriptions / active plans per customer
CREATE TABLE wifi_subscriptions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    package_id      INT NOT NULL,
    start_date      DATE NOT NULL,
    expiry_date     DATE NOT NULL,
    status          ENUM('active','expired','cancelled') DEFAULT 'active',
    notes           VARCHAR(255),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES wifi_customers(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES wifi_packages(id),
    INDEX (expiry_date),
    INDEX (status)
);

-- Payments (linked to customer & optionally subscription)
CREATE TABLE wifi_payments (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    customer_id     INT NOT NULL,
    subscription_id INT DEFAULT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    method          ENUM('cash','mobile_money','bank','airtel_money','mtn_momo','other') DEFAULT 'cash',
    reference       VARCHAR(120),
    paid_on         DATE NOT NULL,
    received_by     VARCHAR(100),
    notes           VARCHAR(255),
    receipt_no      VARCHAR(50) UNIQUE,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES wifi_customers(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES wifi_subscriptions(id) ON DELETE SET NULL,
    INDEX (paid_on)
);

-- Vouchers (hotspot one-time codes)
CREATE TABLE wifi_vouchers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    business_id     INT NOT NULL,
    code            VARCHAR(50) NOT NULL UNIQUE,
    package_id      INT,
    price           DECIMAL(12,2),
    sold_to         VARCHAR(150),
    sold_phone      VARCHAR(20),
    status          ENUM('unused','sold','used','expired') DEFAULT 'unused',
    sold_on         DATE DEFAULT NULL,
    used_on         DATETIME DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
    FOREIGN KEY (package_id) REFERENCES wifi_packages(id),
    INDEX (status)
);

-- ============================================================
-- EXPENSES MODULE
-- ============================================================
CREATE TABLE expense_categories (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    name            VARCHAR(80) NOT NULL,
    icon            VARCHAR(40) DEFAULT 'bi-tag',
    color           VARCHAR(20) DEFAULT '#0F766E',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE expenses (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    user_id          INT NOT NULL,
    business_id      INT DEFAULT NULL,
    category_id      INT DEFAULT NULL,
    title            VARCHAR(150) NOT NULL,
    amount           DECIMAL(12,2) NOT NULL,
    expense_date     DATE NOT NULL,
    method           ENUM('cash','mtn_momo','airtel_money','bank','card','other') DEFAULT 'cash',
    vendor           VARCHAR(150),
    vendor_phone     VARCHAR(30) DEFAULT NULL,
    notes            TEXT,
    receipt_no       VARCHAR(50) UNIQUE,
    is_recurring     TINYINT(1) DEFAULT 0,
    recurring_period ENUM('daily','weekly','monthly','yearly') DEFAULT NULL,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
    INDEX (expense_date)
);

-- ============================================================
-- EARNINGS / INCOME MODULE
-- ============================================================
CREATE TABLE income_sources (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    name            VARCHAR(80) NOT NULL,
    icon            VARCHAR(40) DEFAULT 'bi-cash',
    color           VARCHAR(20) DEFAULT '#16a34a',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE incomes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    business_id INT DEFAULT NULL,
    source_id   INT DEFAULT NULL,
    title       VARCHAR(150) NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    income_date DATE NOT NULL,
    method      ENUM('cash','mtn_momo','airtel_money','bank','card','other') DEFAULT 'cash',
    notes       TEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE SET NULL,
    FOREIGN KEY (source_id) REFERENCES income_sources(id) ON DELETE SET NULL,
    INDEX (income_date)
);

-- ============================================================
-- PLANNER MODULE (Tasks, Goals)
-- ============================================================
CREATE TABLE tasks (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    title           VARCHAR(200) NOT NULL,
    description     TEXT,
    priority        ENUM('low','medium','high','urgent') DEFAULT 'medium',
    status          ENUM('pending','in_progress','done','cancelled') DEFAULT 'pending',
    due_date        DATE DEFAULT NULL,
    due_time        TIME DEFAULT NULL,
    reminder_at     DATETIME DEFAULT NULL,
    tag             VARCHAR(40) DEFAULT NULL,
    completed_at    DATETIME DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (due_date),
    INDEX (status)
);

CREATE TABLE goals (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    title           VARCHAR(200) NOT NULL,
    description     TEXT,
    category        VARCHAR(60) DEFAULT 'business',
    target_amount   DECIMAL(14,2) DEFAULT NULL,
    current_amount  DECIMAL(14,2) DEFAULT 0,
    target_date     DATE DEFAULT NULL,
    status          ENUM('active','achieved','paused','abandoned') DEFAULT 'active',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- NOTES
-- ============================================================
CREATE TABLE notes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    title           VARCHAR(200),
    content         TEXT,
    color           VARCHAR(20) DEFAULT '#FEF3C7',
    is_pinned       TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- CONTACTS (suppliers, customers, partners)
-- ============================================================
CREATE TABLE contacts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    full_name       VARCHAR(150) NOT NULL,
    phone           VARCHAR(20),
    alt_phone       VARCHAR(20),
    email           VARCHAR(150),
    company         VARCHAR(150),
    type            ENUM('customer','supplier','partner','staff','other') DEFAULT 'customer',
    address         TEXT,
    notes           TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (phone)
);

-- ============================================================
-- REMINDERS / ACTIVITY LOG
-- ============================================================
CREATE TABLE activity_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    action          VARCHAR(100) NOT NULL,
    details         VARCHAR(255),
    icon            VARCHAR(40) DEFAULT 'bi-activity',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX (created_at)
);

-- ============================================================
-- SEED DATA
-- ============================================================
-- Default demo user: email = demo@businesspro.app , password = demo1234
INSERT INTO users (full_name, email, phone, password_hash, role, business_name, currency)
VALUES ('Demo CEO', 'demo@businesspro.app', '+256700000000',
        '$2y$10$wHvKkqXqJVjQXrW7uS6e9eKjOaC6yqXwK3.PpZxQ9YyZGq4XkPvRC',
        'ceo', 'My Internet Business', 'UGX');

INSERT INTO businesses (user_id, name, type, description) VALUES
(1, 'My Internet/WiFi Business', 'wifi', 'Home and office WiFi internet provision');

INSERT INTO wifi_packages (business_id, name, duration_days, speed_mbps, price, description) VALUES
(1, 'Daily',    1,  '5',  2000.00,  'Daily access plan'),
(1, 'Weekly',   7,  '5',  10000.00, 'One week access'),
(1, 'Monthly',  30, '10', 35000.00, 'Standard monthly plan'),
(1, 'Premium Monthly', 30, '20', 60000.00, 'High speed monthly plan');

INSERT INTO expense_categories (user_id, name, icon, color) VALUES
(1, 'Power/Electricity', 'bi-lightning-charge', '#F59E0B'),
(1, 'Internet/Bandwidth', 'bi-router', '#0EA5E9'),
(1, 'Transport',         'bi-bus-front',       '#8B5CF6'),
(1, 'Food/Meals',        'bi-cup-hot',         '#EF4444'),
(1, 'Salaries',          'bi-people',          '#10B981'),
(1, 'Rent',              'bi-house',           '#6366F1'),
(1, 'Equipment',         'bi-tools',           '#64748B'),
(1, 'Airtime/Data',      'bi-phone',           '#EC4899'),
(1, 'Marketing',         'bi-megaphone',       '#F97316'),
(1, 'Other',             'bi-three-dots',      '#71717A');

INSERT INTO income_sources (user_id, name, icon, color) VALUES
(1, 'WiFi Subscriptions', 'bi-wifi',     '#0F766E'),
(1, 'WiFi Vouchers',      'bi-ticket-perforated', '#0EA5E9'),
(1, 'Installation Fees',  'bi-tools',    '#8B5CF6'),
(1, 'Salary/Office Job',  'bi-briefcase', '#16A34A'),
(1, 'Side Hustles',       'bi-cash-stack', '#F59E0B'),
(1, 'Other',              'bi-three-dots', '#71717A');

-- ============================================================
-- MIGRATIONS (run once on existing databases)
-- ============================================================
-- v1.1: Add vendor contact phone to expenses
ALTER TABLE expenses ADD COLUMN IF NOT EXISTS vendor_phone VARCHAR(30) DEFAULT NULL AFTER vendor;
