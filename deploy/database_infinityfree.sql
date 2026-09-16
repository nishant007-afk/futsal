-- ============================================================
-- Futsal Booking & Management System - Database Setup
-- ============================================================

-- ------------------------------------------------------------
-- Users table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20) DEFAULT '',
  avatar VARCHAR(255) DEFAULT '',
  password VARCHAR(255) NOT NULL,
  role ENUM('admin', 'manager', 'user') NOT NULL DEFAULT 'user',
  email_verified TINYINT(1) NOT NULL DEFAULT 1,
  verify_token VARCHAR(64) DEFAULT NULL,
  login_count INT NOT NULL DEFAULT 0,
  email_updates TINYINT(1) NOT NULL DEFAULT 0,
  notify_bookings TINYINT(1) NOT NULL DEFAULT 1,
  notify_promo TINYINT(1) NOT NULL DEFAULT 1,
  notify_expiry TINYINT(1) NOT NULL DEFAULT 1,
  notify_sms TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Grounds (futsal courts)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS grounds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  location VARCHAR(255) NOT NULL,
  description TEXT,
  price_per_hour DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  discount_price DECIMAL(8,2) DEFAULT NULL,
  image VARCHAR(255) DEFAULT '',
  payment_qr VARCHAR(255) DEFAULT '',
  capacity INT DEFAULT 10,
  manager_id INT NULL,
  is_active TINYINT(1) DEFAULT 1,
  slug VARCHAR(120) NULL,
  open_time TIME NOT NULL DEFAULT '08:00:00',
  close_time TIME NOT NULL DEFAULT '22:00:00',
   slot_interval INT NOT NULL DEFAULT 60,
   price_weekend DECIMAL(8,2) DEFAULT NULL,
   address VARCHAR(255) NOT NULL DEFAULT '',
   court_number VARCHAR(20) NULL DEFAULT NULL,
   latitude DECIMAL(10,7) NULL DEFAULT NULL,
   longitude DECIMAL(10,7) NULL DEFAULT NULL,
   created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Bookings table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  booking_ref VARCHAR(12) DEFAULT NULL,
  user_id INT NOT NULL,
  ground_id INT NOT NULL,
  booking_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
  payment_status ENUM('unpaid', 'partial', 'paid') NOT NULL DEFAULT 'unpaid',
   payment_type ENUM('advance', 'full') DEFAULT NULL,
   payment_method ENUM('online', 'at_court', 'qr') NOT NULL DEFAULT 'online',
   amount_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  paid_at DATETIME DEFAULT NULL,
  reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  promo_code VARCHAR(40) DEFAULT NULL,
  promo_id INT NULL,
  repeat_of INT NULL,
  repeat_weeks INT NOT NULL DEFAULT 1,
  slot_key VARCHAR(40) GENERATED ALWAYS AS (IF(status = 'cancelled', NULL, CONCAT(ground_id, '|', booking_date, '|', start_time))) STORED,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_active_slot (slot_key),
  KEY idx_ground_id (ground_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (ground_id) REFERENCES grounds(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Ground images table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ground_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ground_id INT NOT NULL,
  image VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ground_id) REFERENCES grounds(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Reviews table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ground_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL,
  comment TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ground_id) REFERENCES grounds(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Settings table (platform config)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(50) PRIMARY KEY,
  setting_value VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

INSERT INTO settings (setting_key, setting_value) VALUES ('platform_fee_percent', '10')
  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
INSERT INTO settings (setting_key, setting_value) VALUES ('manager_setup_fee', '2500')
  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
INSERT INTO settings (setting_key, setting_value) VALUES ('manager_monthly_fee', '800')
  ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- ------------------------------------------------------------
-- Blocked dates table (manager availability)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blocked_dates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ground_id INT NOT NULL,
  block_date DATE NOT NULL,
  note VARCHAR(255) DEFAULT '',
  UNIQUE KEY unique_block (ground_id, block_date),
  FOREIGN KEY (ground_id) REFERENCES grounds(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Manager subscriptions table (one-time setup + monthly service fee)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS manager_subscriptions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  manager_id INT NOT NULL UNIQUE,
  setup_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  setup_paid_at DATE DEFAULT NULL,
  monthly_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  period_start DATE DEFAULT NULL,
  period_end DATE DEFAULT NULL,
  last_paid_at DATE DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Settlements table (manager payouts)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settlements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  manager_id INT NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  gross DECIMAL(10,2) NOT NULL DEFAULT 0,
  fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  payout DECIMAL(10,2) NOT NULL DEFAULT 0,
  paid_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Waitlist table (players waiting for a freed slot)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS waitlist (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ground_id INT NOT NULL,
  booking_date DATE NOT NULL,
  start_time TIME NOT NULL,
  user_id INT NOT NULL,
  is_notified TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_wait (ground_id, booking_date, start_time, user_id),
  FOREIGN KEY (ground_id) REFERENCES grounds(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Promo codes table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS promo_codes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  manager_id INT DEFAULT NULL,
  code VARCHAR(40) NOT NULL UNIQUE,
  discount_type ENUM('percent','flat') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(10,2) NOT NULL,
  min_total DECIMAL(10,2) DEFAULT 0,
  max_uses INT DEFAULT 0,
  used_count INT DEFAULT 0,
  starts_at DATE DEFAULT NULL,
  expires_at DATE DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_manager (manager_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Password resets table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token VARCHAR(64) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  KEY idx_user (user_id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Notifications table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  body VARCHAR(255) DEFAULT '',
  icon VARCHAR(30) DEFAULT 'fa-bell',
  link VARCHAR(255) DEFAULT '',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_user (user_id, is_read),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed data
-- ------------------------------------------------------------
INSERT INTO users (name, email, phone, password, role) VALUES
  ('Admin User', 'admin@futsal.com', '9841000000', '$2y$10$OMmkJ9UnTpor8Psg8IeCdelLBzafZCAFJ9unOREXJNUJcfWlMYgFm', 'admin'),
  ('John Doe', 'john@example.com', '9842000000', '$2y$10$OMmkJ9UnTpor8Psg8IeCdelLBzafZCAFJ9unOREXJNUJcfWlMYgFm', 'user'),
  ('Ramesh Tamang', 'manager@futsal.com', '9843000000', '$2y$10$OMmkJ9UnTpor8Psg8IeCdelLBzafZCAFJ9unOREXJNUJcfWlMYgFm', 'manager');

-- Note: Seeded demo accounts (change passwords after import).
-- See README-DEPLOY.md for default credentials. Never deploy this file to production web root.

INSERT INTO grounds (name, location, description, price_per_hour, image, capacity, manager_id, address, court_number, latitude, longitude) VALUES
  ('Downtown Futsal Arena', 'New Road, Kathmandu', 'Indoor futsal court with wooden flooring, floodlights and changing rooms.', 2500.00, '', 12, 3, 'New Road, Kathmandu 44600, Nepal', 'Court 1', 27.7025, 85.3116),
  ('Golden City Futsal', 'Jawalakhel, Lalitpur', 'Outdoor turf pitch, ideal for evening games with floodlights.', 1800.00, '', 10, 3, 'Jawalakhel, Lalitpur, Nepal', 'Court 1', 27.6719, 85.3124),
  ('Riverside Sports Hub', 'Baneshwor, Kathmandu', 'Well-maintained court with a cafe and free parking on site.', 2200.00, '', 10, 3, 'Baneshwor, Kathmandu, Nepal', 'Court 1', 27.6817, 85.3253),
  ('Thamel Sports Complex', 'Thamel, Kathmandu', 'Busy indoor arena in the heart of the city with night floodlights.', 2100.00, '', 10, 3, 'Thamel, Kathmandu, Nepal', 'Court 1', 27.7033, 85.3146),
  ('Patan Futsal Dome', 'Patan, Lalitpur', 'Covered dome court ideal for evening games, all-weather surface.', 1900.00, '', 10, 3, 'Patan, Lalitpur, Nepal', 'Court 1', 27.6645, 85.3190),
  ('Balkumari Arena', 'Balkumari, Lalitpur', 'Spacious court with clean changing rooms and on-site parking.', 2300.00, '', 12, 3, 'Balkumari, Lalitpur, Nepal', 'Court 1', 27.6590, 85.3200),
  ('Koteshwor Kickoff', 'Koteshwor, Kathmandu', 'Community favourite with weekend leagues and free coaching.', 1750.00, '', 10, 3, 'Koteshwor, Kathmandu, Nepal', 'Court 1', 27.6800, 85.3350),
  ('Bouddha Sports House', 'Bouddha, Kathmandu', 'Modern facility near Bouddha with roof-top floodlight court.', 2400.00, '', 10, 3, 'Bouddha, Kathmandu, Nepal', 'Court 1', 27.7010, 85.3159),
  ('Newar Street Court', 'Lalitpur, Pulchowk', 'Compact community court with budget-friendly hourly rates.', 1500.00, '', 8, 3, 'Pulchowk, Lalitpur, Nepal', 'Court 1', 27.6820, 85.3180),
  ('Baneshwor Dome 2', 'Baneshwor, Kathmandu', 'Twin-dome complex with two full-size courts.', 2350.00, '', 12, 3, 'Baneshwor, Kathmandu, Nepal', 'Court 1', 27.6817, 85.3253),
  ('Gyaneshwor Grid', 'Gyaneshwor, Kathmandu', 'Neighbourhood court known for quick pickup games.', 1700.00, '', 10, 3, 'Gyaneshwor, Kathmandu, Nepal', 'Court 1', 27.7106, 85.3118);

INSERT INTO bookings (booking_ref, user_id, ground_id, booking_date, start_time, end_time, total_price, status, payment_status, payment_type, amount_paid) VALUES
  ('GS-A1B2C3', 2, 1, DATE_ADD(CURDATE(), INTERVAL 1 DAY), '18:00:00', '19:00:00', 2500.00, 'confirmed', 'partial', 'advance', 500.00),
  ('GS-D4E5F6', 2, 2, DATE_ADD(CURDATE(), INTERVAL 2 DAY), '19:00:00', '20:00:00', 1800.00, 'confirmed', 'unpaid', NULL, 0.00),
  ('GS-G7H8J9', 2, 3, DATE_ADD(CURDATE(), INTERVAL 3 DAY), '17:00:00', '18:00:00', 2200.00, 'cancelled', 'unpaid', NULL, 0.00);

-- ------------------------------------------------------------
-- Login attempts (throttling) table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(150) NOT NULL,
  ip VARCHAR(45) NOT NULL DEFAULT '',
  attempts INT DEFAULT 0,
  locked_until DATETIME NULL,
  suspended TINYINT(1) DEFAULT 0,
  last_attempt_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_identifier (identifier),
  KEY idx_ip (ip)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Contact messages table
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) DEFAULT '',
  email VARCHAR(150) NOT NULL,
  topic VARCHAR(50) DEFAULT 'general',
  subject VARCHAR(200) DEFAULT '',
  message TEXT NOT NULL,
  ip VARCHAR(45) DEFAULT '',
  user_id INT NULL,
  is_resolved TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email (email),
  KEY idx_topic (topic)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Pages table (editable legal/static pages)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(60) NOT NULL UNIQUE,
  title VARCHAR(150) NOT NULL,
  summary VARCHAR(255) DEFAULT '',
  body LONGTEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS otps (
  id INT AUTO_INCREMENT PRIMARY KEY,
  identifier VARCHAR(150) NOT NULL,
  purpose VARCHAR(30) NOT NULL,
  code VARCHAR(8) NOT NULL,
  attempts INT DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY idx_lookup (identifier, purpose)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  ground_id INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY favorites_user_ground (user_id, ground_id)
) ENGINE=InnoDB;


