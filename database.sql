-- ============================================================
-- Jitu Unggul Inventory System - Database Setup
-- SECURE VERSION (FIXED — added missing stock_transactions table)
-- ============================================================

CREATE DATABASE IF NOT EXISTS jitu_unggul_secure
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE jitu_unggul_secure;

-- ============================================================
-- USERS TABLE — password stores bcrypt hash only
-- ============================================================
DROP TABLE IF EXISTS users;
CREATE TABLE users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50)  NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  role       VARCHAR(20)  NOT NULL DEFAULT 'user',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- INVENTORY TABLE
-- ============================================================
DROP TABLE IF EXISTS inventory_items;
CREATE TABLE inventory_items (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  item_code   VARCHAR(50)  NOT NULL UNIQUE,
  item_name   VARCHAR(200) NOT NULL,
  category    VARCHAR(100),
  quantity    INT NOT NULL DEFAULT 0,
  unit        VARCHAR(50),
  location    VARCHAR(100),
  remarks     TEXT,
  created_by  INT,
  created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ============================================================
-- STOCK TRANSACTIONS TABLE (NEW — was missing, caused
-- supply_movement.php and movement_history.php to crash)
-- ============================================================
DROP TABLE IF EXISTS stock_transactions;
CREATE TABLE stock_transactions (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  item_id           INT NOT NULL,
  client_company    VARCHAR(150),
  event_name        VARCHAR(150),
  client_phone      VARCHAR(30),
  trans_type        ENUM('IN','OUT') NOT NULL,
  quantity_changed  INT NOT NULL,
  created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE
);

-- ============================================================
-- SEED DATA — Passwords are bcrypt hashed
-- Admin password: admin123
-- Staff password: password
-- Demo password:  demo1234
-- ============================================================
-- These hashes were freshly generated and verified to actually
-- match the passwords below (the previous set did NOT match,
-- which is why login was failing with "Invalid username or password").
INSERT INTO users (username, password, role) VALUES
  ('admin', '$2b$10$QHBV7RSObZzyp8E7.GofyuPzFVWyYGDJgeCCmhbqG2vfaLqqdzi6S', 'admin'),
  ('staff', '$2b$10$n06ifIX56IgihZwHwdYlOe6PSdE1lvXGqS5jnUahZ8ikaHDJKynaG', 'user'),
  ('demo',  '$2b$10$3X4pxeNpyc0QHOK3DqDI/O.DSlIZtdKqmVcyYGg/wZ6FfxMIeupX2', 'user');

-- ============================================================
-- SAMPLE INVENTORY DATA
-- ============================================================
INSERT INTO inventory_items (item_code, item_name, category, quantity, unit, location, remarks, created_by) VALUES
  ('ITM-001', 'Laptop Dell Inspiron 15',       'Electronics',  5,  'unit', 'Stor A-01', 'Good condition',           1),
  ('ITM-002', 'Office Chair',                  'Furniture',   12,  'unit', 'Stor B-02', 'Standard black chair',     1),
  ('ITM-003', 'A4 Paper Ream',                 'Stationery', 200,  'ream', 'Stor C-01', '80gsm white paper',        2),
  ('ITM-004', 'Whiteboard Marker Set',         'Stationery',  30,  'set',  'Stor C-02', 'Mixed colours',            2),
  ('ITM-005', 'Projector Epson EB-X41',        'Electronics',  3,  'unit', 'Stor A-02', 'With remote & cables',     1),
  ('ITM-006', 'Steel Filing Cabinet 4-Drawer', 'Furniture',    6,  'unit', 'Stor B-01', 'Grey colour',              1),
  ('ITM-007', 'Printer HP LaserJet Pro',       'Electronics',  4,  'unit', 'Stor A-03', 'Black & white only',       1),
  ('ITM-008', 'Desk Telephone Set',            'Electronics', 15,  'unit', 'Stor A-04', 'Panasonic brand',          2),
  ('ITM-009', 'Stapler Heavy Duty',            'Stationery',  20,  'unit', 'Stor C-03', '50-sheet capacity',        2),
  ('ITM-010', 'Extension Cord 5-Way',          'Electronics',  8,  'unit', 'Stor A-05', '5 metre cable',            1);
