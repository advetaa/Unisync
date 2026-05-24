-- UniSync Database Setup
-- Run this in phpMyAdmin → SQL tab

CREATE DATABASE IF NOT EXISTS unisync;
USE unisync;

-- USERS
CREATE TABLE IF NOT EXISTS users (
  user_id    INT AUTO_INCREMENT PRIMARY KEY,
  full_name  VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,
  course     VARCHAR(100) DEFAULT '',
  semester   INT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- TASKS
CREATE TABLE IF NOT EXISTS tasks (
  task_id     INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  title       VARCHAR(200) NOT NULL,
  description TEXT,
  due_date    DATE,
  priority    ENUM('low','medium','high') DEFAULT 'medium',
  status      ENUM('pending','completed') DEFAULT 'pending',
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- EXPENSES
CREATE TABLE IF NOT EXISTS expenses (
  expense_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  title        VARCHAR(200) NOT NULL,
  amount       DECIMAL(10,2) NOT NULL,
  category     VARCHAR(100) DEFAULT 'Other',
  expense_date DATE NOT NULL,
  notes        TEXT,
  created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- STUDY SESSIONS
CREATE TABLE IF NOT EXISTS study_sessions (
  session_id    INT AUTO_INCREMENT PRIMARY KEY,
  user_id       INT NOT NULL,
  subject       VARCHAR(200) NOT NULL,
  hours_studied DECIMAL(4,2) NOT NULL,
  session_date  DATE NOT NULL,
  notes         TEXT,
  created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- CALENDAR EVENTS
CREATE TABLE IF NOT EXISTS calendar_events (
  event_id    INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  title       VARCHAR(200) NOT NULL,
  event_date  DATE NOT NULL,
  start_time  TIME,
  end_time    TIME,
  event_type  ENUM('lecture','exam','assignment','study','budget','other') DEFAULT 'other',
  color       VARCHAR(20) DEFAULT 'dark',
  notes       TEXT,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- NOTIFICATIONS
CREATE TABLE IF NOT EXISTS notifications (
  notif_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NOT NULL,
  message    VARCHAR(300) NOT NULL,
  is_read    TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- BUDGET CATEGORIES (monthly budgets)
CREATE TABLE IF NOT EXISTS budget_months (
  budget_id   INT AUTO_INCREMENT PRIMARY KEY,
  user_id     INT NOT NULL,
  month_year  VARCHAR(7) NOT NULL,   -- e.g. '2026-03'
  total_budget DECIMAL(10,2) DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY user_month (user_id, month_year),
  FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
);

-- Sample user (password: "password123")
INSERT IGNORE INTO users (full_name, email, password, course, semester)
VALUES ('Adveta Singh', 'adveta@example.com',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'B.Tech CSE', 3);
