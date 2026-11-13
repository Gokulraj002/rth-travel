-- ============================================================================
-- Reyaan Trip Holiday — CMS database schema
-- MySQL 8.0+ / MariaDB 10.6+ (Hostinger) · InnoDB · utf8mb4
-- Import order: schema.sql → seed.sql
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------------------------------------------------------
-- Admin users
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS admins;
CREATE TABLE admins (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name          VARCHAR(120) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('owner','editor') NOT NULL DEFAULT 'editor',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  last_login_at DATETIME NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admins_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Failed-login throttling (by IP and by email)
DROP TABLE IF EXISTS login_attempts;
CREATE TABLE login_attempts (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip           VARCHAR(45) NOT NULL,
  email        VARCHAR(190) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempts_ip (ip, attempted_at),
  KEY idx_attempts_email (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Destinations
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS destinations;
CREATE TABLE destinations (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug          VARCHAR(80)  NOT NULL,
  name          VARCHAR(120) NOT NULL,
  display_name  VARCHAR(120) NOT NULL,
  region        ENUM('kashmir','ladakh','jammu') NOT NULL,
  tagline       VARCHAR(200) NOT NULL DEFAULT '',
  description   TEXT NOT NULL,
  lat           DECIMAL(9,6) NOT NULL,
  lng           DECIMAL(9,6) NOT NULL,
  altitude_m    INT NOT NULL DEFAULT 0,
  best_time     VARCHAR(200) NOT NULL DEFAULT '',
  distance_note VARCHAR(200) NOT NULL DEFAULT '',
  image         VARCHAR(255) NOT NULL DEFAULT '',
  card_image    VARCHAR(255) NULL,
  credit        VARCHAR(120) NOT NULL DEFAULT '',
  status        ENUM('published','draft') NOT NULL DEFAULT 'published',
  sort_order    INT NOT NULL DEFAULT 0,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_destinations_slug (slug),
  KEY idx_destinations_order (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Highlights + things to do (ordered lists)
DROP TABLE IF EXISTS destination_items;
CREATE TABLE destination_items (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  destination_id INT UNSIGNED NOT NULL,
  type           ENUM('highlight','thing') NOT NULL,
  text           VARCHAR(300) NOT NULL,
  position       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_dest_items (destination_id, type, position),
  CONSTRAINT fk_dest_items_dest FOREIGN KEY (destination_id) REFERENCES destinations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS destination_gallery;
CREATE TABLE destination_gallery (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  destination_id INT UNSIGNED NOT NULL,
  image          VARCHAR(255) NOT NULL,
  position       INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_dest_gallery (destination_id, position),
  CONSTRAINT fk_dest_gallery_dest FOREIGN KEY (destination_id) REFERENCES destinations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Tour packages
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS packages;
CREATE TABLE packages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug        VARCHAR(120) NOT NULL,
  title       VARCHAR(160) NOT NULL,
  subtitle    VARCHAR(255) NOT NULL DEFAULT '',
  region      ENUM('kashmir','ladakh','jammu') NOT NULL,
  nights      TINYINT UNSIGNED NOT NULL,
  days        TINYINT UNSIGNED NOT NULL,
  price_from  INT UNSIGNED NOT NULL DEFAULT 0,          -- INR per person
  image       VARCHAR(255) NOT NULL DEFAULT '',
  featured    TINYINT(1) NOT NULL DEFAULT 0,
  status      ENUM('published','draft') NOT NULL DEFAULT 'draft',
  sort_order  INT NOT NULL DEFAULT 0,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_packages_slug (slug),
  KEY idx_packages_list (status, sort_order),
  KEY idx_packages_region (region)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Trip categories (honeymoon, family, adventure, pilgrimage, snow, group)
DROP TABLE IF EXISTS package_categories;
CREATE TABLE package_categories (
  package_id INT UNSIGNED NOT NULL,
  category   ENUM('honeymoon','family','adventure','pilgrimage','snow','group') NOT NULL,
  position   INT NOT NULL DEFAULT 0,
  PRIMARY KEY (package_id, category),
  CONSTRAINT fk_pkg_cat_pkg FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Destinations covered, in visit order (by slug, so packages survive destination edits)
DROP TABLE IF EXISTS package_destinations;
CREATE TABLE package_destinations (
  package_id       INT UNSIGNED NOT NULL,
  destination_slug VARCHAR(80) NOT NULL,
  position         INT NOT NULL DEFAULT 0,
  PRIMARY KEY (package_id, destination_slug),
  KEY idx_pkg_dest_slug (destination_slug),
  CONSTRAINT fk_pkg_dest_pkg FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Highlights / inclusions / exclusions (ordered lists)
DROP TABLE IF EXISTS package_items;
CREATE TABLE package_items (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  package_id INT UNSIGNED NOT NULL,
  type       ENUM('highlight','inclusion','exclusion') NOT NULL,
  text       VARCHAR(300) NOT NULL,
  position   INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_pkg_items (package_id, type, position),
  CONSTRAINT fk_pkg_items_pkg FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Day-by-day itinerary
DROP TABLE IF EXISTS package_itinerary;
CREATE TABLE package_itinerary (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  package_id  INT UNSIGNED NOT NULL,
  day         TINYINT UNSIGNED NOT NULL,
  title       VARCHAR(200) NOT NULL,
  description TEXT NOT NULL,
  stay        VARCHAR(120) NULL,
  PRIMARY KEY (id),
  KEY idx_pkg_itin (package_id, day),
  CONSTRAINT fk_pkg_itin_pkg FOREIGN KEY (package_id) REFERENCES packages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Reviews & FAQs
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS testimonials;
CREATE TABLE testimonials (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(120) NOT NULL,
  from_city  VARCHAR(120) NOT NULL DEFAULT '',
  trip       VARCHAR(160) NOT NULL DEFAULT '',
  quote      TEXT NOT NULL,
  rating     TINYINT UNSIGNED NOT NULL DEFAULT 5,
  status     ENUM('published','draft') NOT NULL DEFAULT 'published',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_testimonials (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS faqs;
CREATE TABLE faqs (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  question   VARCHAR(300) NOT NULL,
  answer     TEXT NOT NULL,
  status     ENUM('published','draft') NOT NULL DEFAULT 'published',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_faqs (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Enquiries (leads from the website form)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS enquiries;
CREATE TABLE enquiries (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name           VARCHAR(120) NOT NULL,
  phone          VARCHAR(30)  NOT NULL,
  email          VARCHAR(190) NULL,
  destination    VARCHAR(120) NULL,
  package        VARCHAR(160) NULL,
  travel_month   VARCHAR(40)  NULL,
  adults         TINYINT UNSIGNED NOT NULL DEFAULT 1,
  children       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  hotel_category VARCHAR(40)  NULL,
  message        TEXT NULL,
  source         VARCHAR(40)  NOT NULL DEFAULT 'website',   -- website | planner | package
  status         ENUM('new','contacted','quoted','booked','closed') NOT NULL DEFAULT 'new',
  notes          TEXT NULL,                                  -- internal admin notes
  ip             VARCHAR(45)  NULL,
  user_agent     VARCHAR(255) NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_enquiries_status (status, created_at),
  KEY idx_enquiries_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Site settings (key/value: phones, emails, addresses, social links …)
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS settings;
CREATE TABLE settings (
  `key`      VARCHAR(80) NOT NULL,
  `value`    TEXT NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- Uploaded media + activity log
-- ----------------------------------------------------------------------------
DROP TABLE IF EXISTS media;
CREATE TABLE media (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  path          VARCHAR(255) NOT NULL,          -- e.g. /uploads/2026/11/abc123.webp
  original_name VARCHAR(255) NOT NULL DEFAULT '',
  mime          VARCHAR(60)  NOT NULL,
  size_bytes    INT UNSIGNED NOT NULL DEFAULT 0,
  width         SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  height        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  uploaded_by   INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_path (path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS activity_log;
CREATE TABLE activity_log (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id   INT UNSIGNED NULL,
  action     VARCHAR(40)  NOT NULL,   -- created | updated | deleted | login | …
  entity     VARCHAR(40)  NOT NULL,   -- package | destination | …
  entity_id  INT UNSIGNED NULL,
  summary    VARCHAR(255) NOT NULL DEFAULT '',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
