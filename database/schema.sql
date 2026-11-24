-- Club & Event Management System
-- Database schema (MySQL 8 / MariaDB 10.4+, XAMPP compatible)

CREATE DATABASE IF NOT EXISTS club_event_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE club_event_db;

-- 1. USERS (admin / organizer / member)
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100)  NOT NULL,
  email         VARCHAR(150)  NOT NULL UNIQUE,
  password_hash VARCHAR(255)  NOT NULL,
  role          ENUM('admin','organizer','member') NOT NULL DEFAULT 'member',
  student_id    VARCHAR(20)   NULL,
  phone         VARCHAR(20)   NULL,
  is_active     TINYINT(1)    NOT NULL DEFAULT 1,
  created_at    TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. CATEGORIES (for filtering events)
CREATE TABLE categories (
  id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 3. EVENTS
CREATE TABLE events (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(150) NOT NULL,
  description TEXT         NULL,
  category_id INT UNSIGNED NULL,
  venue       VARCHAR(150) NOT NULL,
  event_date  DATE         NOT NULL,
  start_time  TIME         NOT NULL,
  end_time    TIME         NOT NULL,
  capacity    INT UNSIGNED NOT NULL DEFAULT 50,
  status      ENUM('draft','published','cancelled','completed') NOT NULL DEFAULT 'draft',
  created_by  INT UNSIGNED NOT NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_events_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_events_creator  FOREIGN KEY (created_by)  REFERENCES users(id)      ON DELETE RESTRICT,
  CONSTRAINT chk_event_time CHECK (end_time > start_time),
  INDEX idx_events_date   (event_date),
  INDEX idx_events_status (status)
) ENGINE=InnoDB;

-- 4. REGISTRATIONS (member joins an event + attendance tracking)
CREATE TABLE registrations (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id      INT UNSIGNED NOT NULL,
  user_id       INT UNSIGNED NOT NULL,
  status        ENUM('registered','cancelled','attended','absent') NOT NULL DEFAULT 'registered',
  registered_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checked_in_at TIMESTAMP    NULL,
  CONSTRAINT fk_reg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_reg_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
  UNIQUE KEY uq_event_user (event_id, user_id)   -- no duplicate registration
) ENGINE=InnoDB;

-- 5. PERFORMANCES (schedule / performers inside an event)
CREATE TABLE performances (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_id     INT UNSIGNED NOT NULL,
  title        VARCHAR(150) NOT NULL,
  performer_id INT UNSIGNED NULL,          -- club member (optional)
  performer_name VARCHAR(100) NULL,        -- guest performer (optional)
  start_time   TIME NOT NULL,
  end_time     TIME NOT NULL,
  notes        VARCHAR(255) NULL,
  CONSTRAINT fk_perf_event     FOREIGN KEY (event_id)     REFERENCES events(id) ON DELETE CASCADE,
  CONSTRAINT fk_perf_performer FOREIGN KEY (performer_id) REFERENCES users(id)  ON DELETE SET NULL,
  CONSTRAINT chk_perf_time CHECK (end_time > start_time)
) ENGINE=InnoDB;

-- Seed: categories
INSERT INTO categories (name) VALUES
  ('Music'), ('Dance'), ('Drama'), ('Cultural Night'), ('Workshop'), ('Competition');

-- NOTE: Do NOT insert users with a plain password.
-- Create the first admin using seed_admin.php (uses password_hash()).
