-- Salon Appointment Scheduling and Customer Management System
-- Database schema (MySQL / MariaDB)

CREATE DATABASE IF NOT EXISTS salon_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE salon_db;

DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS stylist_services;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS stylists;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

-- Login accounts for all three roles
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Customer profiles (user_id is NULL for walk-ins without an account)
CREATE TABLE customers (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL UNIQUE,
  full_name  VARCHAR(100) NOT NULL,
  phone      VARCHAR(30) NULL,
  email      VARCHAR(150) NULL,
  notes      TEXT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_customers_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE stylists (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(100) NOT NULL,
  phone     VARCHAR(30) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE services (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(100) NOT NULL,
  price            DECIMAL(10,2) NOT NULL,
  duration_minutes SMALLINT UNSIGNED NOT NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT chk_services_price CHECK (price >= 0),
  CONSTRAINT chk_services_duration CHECK (duration_minutes > 0)
) ENGINE=InnoDB;

-- Which stylist performs which service
CREATE TABLE stylist_services (
  stylist_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (stylist_id, service_id),
  CONSTRAINT fk_ss_stylist FOREIGN KEY (stylist_id) REFERENCES stylists(id) ON DELETE CASCADE,
  CONSTRAINT fk_ss_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE appointments (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id      INT UNSIGNED NOT NULL,
  stylist_id       INT UNSIGNED NOT NULL,
  service_id       INT UNSIGNED NOT NULL,
  appointment_date DATE NOT NULL,
  start_time       TIME NOT NULL,
  end_time         TIME NOT NULL,
  status           ENUM('pending','confirmed','completed','cancelled','no-show')
                   NOT NULL DEFAULT 'pending',
  source           ENUM('online','walk-in','phone') NOT NULL DEFAULT 'online',
  notes            TEXT NULL,
  created_by       INT UNSIGNED NULL,
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_appt_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_appt_stylist  FOREIGN KEY (stylist_id)  REFERENCES stylists(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_appt_service  FOREIGN KEY (service_id)  REFERENCES services(id)  ON DELETE RESTRICT,
  CONSTRAINT fk_appt_creator  FOREIGN KEY (created_by)  REFERENCES users(id)     ON DELETE SET NULL,
  CONSTRAINT chk_appt_times CHECK (end_time > start_time),
  INDEX idx_appt_date (appointment_date),
  INDEX idx_appt_stylist_date (stylist_id, appointment_date),
  INDEX idx_appt_status (status)
) ENGINE=InnoDB;

-- One review per completed appointment
CREATE TABLE reviews (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  appointment_id INT UNSIGNED NOT NULL UNIQUE,
  customer_id    INT UNSIGNED NOT NULL,
  rating         TINYINT UNSIGNED NOT NULL,
  comment        VARCHAR(500) NULL,
  is_approved    TINYINT(1) NOT NULL DEFAULT 0,
  created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_appt     FOREIGN KEY (appointment_id) REFERENCES appointments(id) ON DELETE CASCADE,
  CONSTRAINT fk_reviews_customer FOREIGN KEY (customer_id)    REFERENCES customers(id)    ON DELETE CASCADE,
  CONSTRAINT chk_reviews_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB;

-- Key/value settings (e.g. daily_appointment_limit)
CREATE TABLE settings (
  setting_key   VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB;
