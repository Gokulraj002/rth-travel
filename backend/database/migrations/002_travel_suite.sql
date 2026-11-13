-- ============================================================================
-- Migration 002 — Travel suite (Pro / Premium modules) + license + roles
-- For EXISTING installs (already imported schema.sql v1). Fresh installs: schema.sql already includes this.
-- MySQL 8.0+ / MariaDB 10.6+ · InnoDB · utf8mb4. Safe to run once.
-- ============================================================================
SET NAMES utf8mb4;

-- Roles: owner | manager | sales | accounts | editor (see config/permissions.php)
ALTER TABLE admins MODIFY role VARCHAR(30) NOT NULL DEFAULT 'editor';
ALTER TABLE admins ADD COLUMN phone VARCHAR(30) NULL AFTER email;

-- Enquiries become CRM leads
ALTER TABLE enquiries
  ADD COLUMN customer_id  INT UNSIGNED NULL AFTER id,
  ADD COLUMN stage        VARCHAR(30)  NOT NULL DEFAULT 'new' AFTER status,
  ADD COLUMN priority     ENUM('low','normal','high') NOT NULL DEFAULT 'normal' AFTER stage,
  ADD COLUMN assigned_to  INT UNSIGNED NULL AFTER priority,
  ADD COLUMN budget       INT UNSIGNED NULL AFTER hotel_category,
  ADD COLUMN travel_date  DATE NULL AFTER travel_month,
  ADD COLUMN next_follow_up_at DATETIME NULL AFTER assigned_to,
  ADD COLUMN lost_reason  VARCHAR(200) NULL AFTER notes,
  ADD KEY idx_enquiries_stage (stage, created_at),
  ADD KEY idx_enquiries_followup (next_follow_up_at),
  ADD KEY idx_enquiries_customer (customer_id);

-- ----------------------------------------------------------------------------
-- Travel suite: customers, CRM, quotations, bookings, payments, invoices,
-- vendors, vehicles, services, tasks, templates, sequences
-- ----------------------------------------------------------------------------
-- (fresh) DROP TABLE IF EXISTS customers;
CREATE TABLE customers (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name        VARCHAR(120) NOT NULL,
  phone       VARCHAR(30)  NOT NULL,
  email       VARCHAR(190) NULL,
  city        VARCHAR(120) NULL,
  address     VARCHAR(255) NULL,
  date_of_birth DATE NULL,
  anniversary DATE NULL,
  source      VARCHAR(40)  NOT NULL DEFAULT 'website',   -- website | walk-in | referral | phone | instagram | other
  tags        VARCHAR(255) NULL,                         -- comma separated, e.g. "vip,repeat"
  notes       TEXT NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_customers_phone (phone),
  KEY idx_customers_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Timeline on a lead/customer: notes, calls, WhatsApp, emails, stage changes
-- (fresh) DROP TABLE IF EXISTS lead_activities;
CREATE TABLE lead_activities (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  enquiry_id  INT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  admin_id    INT UNSIGNED NULL,
  type        ENUM('note','call','whatsapp','email','meeting','stage','system') NOT NULL DEFAULT 'note',
  body        TEXT NOT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_lead_act_enquiry (enquiry_id, created_at),
  KEY idx_lead_act_customer (customer_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Follow-ups / reminders (shown on dashboard + calendar)
-- (fresh) DROP TABLE IF EXISTS tasks;
CREATE TABLE tasks (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title        VARCHAR(200) NOT NULL,
  notes        TEXT NULL,
  due_at       DATETIME NOT NULL,
  related_type ENUM('enquiry','customer','quotation','booking','none') NOT NULL DEFAULT 'none',
  related_id   INT UNSIGNED NULL,
  assigned_to  INT UNSIGNED NULL,
  done_at      DATETIME NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_tasks_due (done_at, due_at),
  KEY idx_tasks_related (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Quotations
-- (fresh) DROP TABLE IF EXISTS quotations;
CREATE TABLE quotations (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number        VARCHAR(40)  NOT NULL,                 -- e.g. QT-2026-0001
  customer_id   INT UNSIGNED NOT NULL,
  enquiry_id    INT UNSIGNED NULL,
  package_id    INT UNSIGNED NULL,
  title         VARCHAR(200) NOT NULL,
  travel_start  DATE NULL,
  travel_end    DATE NULL,
  adults        TINYINT UNSIGNED NOT NULL DEFAULT 2,
  children      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  hotel_category VARCHAR(40) NULL,
  subtotal      DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_percent   DECIMAL(5,2)  NOT NULL DEFAULT 0,
  tax_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
  total         DECIMAL(12,2) NOT NULL DEFAULT 0,
  valid_until   DATE NULL,
  status        ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  inclusions    TEXT NULL,                             -- one per line
  exclusions    TEXT NULL,                             -- one per line
  terms         TEXT NULL,
  notes         TEXT NULL,                             -- internal
  public_token  CHAR(32) NOT NULL,                     -- share link /q/{token}
  sent_at       DATETIME NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_quotations_number (number),
  UNIQUE KEY uq_quotations_token (public_token),
  KEY idx_quotations_customer (customer_id),
  KEY idx_quotations_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (fresh) DROP TABLE IF EXISTS quotation_items;
CREATE TABLE quotation_items (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quotation_id INT UNSIGNED NOT NULL,
  type         ENUM('package','hotel','transport','activity','flight','other') NOT NULL DEFAULT 'other',
  description  VARCHAR(300) NOT NULL,
  qty          DECIMAL(8,2)  NOT NULL DEFAULT 1,
  unit_price   DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
  position     INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_quotation_items (quotation_id, position),
  CONSTRAINT fk_quotation_items FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (fresh) DROP TABLE IF EXISTS quotation_days;
CREATE TABLE quotation_days (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  quotation_id INT UNSIGNED NOT NULL,
  day          TINYINT UNSIGNED NOT NULL,
  title        VARCHAR(200) NOT NULL,
  description  TEXT NOT NULL,
  stay         VARCHAR(120) NULL,
  PRIMARY KEY (id),
  KEY idx_quotation_days (quotation_id, day),
  CONSTRAINT fk_quotation_days FOREIGN KEY (quotation_id) REFERENCES quotations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bookings
-- (fresh) DROP TABLE IF EXISTS bookings;
CREATE TABLE bookings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number        VARCHAR(40)  NOT NULL,                 -- e.g. BK-2026-0001
  customer_id   INT UNSIGNED NOT NULL,
  quotation_id  INT UNSIGNED NULL,
  enquiry_id    INT UNSIGNED NULL,
  package_id    INT UNSIGNED NULL,
  title         VARCHAR(200) NOT NULL,
  travel_start  DATE NOT NULL,
  travel_end    DATE NOT NULL,
  adults        TINYINT UNSIGNED NOT NULL DEFAULT 2,
  children      TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pickup_point  VARCHAR(160) NULL,
  drop_point    VARCHAR(160) NULL,
  total_amount  DECIMAL(12,2) NOT NULL DEFAULT 0,      -- agreed selling price
  status        ENUM('pending','confirmed','ongoing','completed','cancelled') NOT NULL DEFAULT 'confirmed',
  special_requests TEXT NULL,
  notes         TEXT NULL,                             -- internal
  assigned_to   INT UNSIGNED NULL,
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_bookings_number (number),
  KEY idx_bookings_dates (travel_start, travel_end),
  KEY idx_bookings_status (status),
  KEY idx_bookings_customer (customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (fresh) DROP TABLE IF EXISTS booking_travellers;
CREATE TABLE booking_travellers (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id  INT UNSIGNED NOT NULL,
  name        VARCHAR(120) NOT NULL,
  age         TINYINT UNSIGNED NULL,
  gender      ENUM('male','female','other') NULL,
  phone       VARCHAR(30) NULL,
  id_proof    VARCHAR(80) NULL,                         -- e.g. "Aadhaar ••••1234" (store masked only)
  position    INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_booking_travellers (booking_id, position),
  CONSTRAINT fk_booking_travellers FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Money received from the customer
-- (fresh) DROP TABLE IF EXISTS payments;
CREATE TABLE payments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  receipt_no  VARCHAR(40) NOT NULL,                     -- e.g. RC-2026-0001
  booking_id  INT UNSIGNED NOT NULL,
  amount      DECIMAL(12,2) NOT NULL,
  method      ENUM('cash','upi','bank','card','cheque','other') NOT NULL DEFAULT 'upi',
  reference   VARCHAR(120) NULL,                        -- UPI txn id / cheque no.
  paid_at     DATE NOT NULL,
  notes       VARCHAR(255) NULL,
  created_by  INT UNSIGNED NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payments_receipt (receipt_no),
  KEY idx_payments_booking (booking_id),
  KEY idx_payments_date (paid_at),
  CONSTRAINT fk_payments_booking FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- GST invoices
-- (fresh) DROP TABLE IF EXISTS invoices;
CREATE TABLE invoices (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  number       VARCHAR(40) NOT NULL,                    -- e.g. INV-2026-0001
  booking_id   INT UNSIGNED NULL,
  customer_id  INT UNSIGNED NOT NULL,
  issue_date   DATE NOT NULL,
  due_date     DATE NULL,
  place_of_supply VARCHAR(80) NULL,
  subtotal     DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount     DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax_percent  DECIMAL(5,2)  NOT NULL DEFAULT 0,
  cgst         DECIMAL(12,2) NOT NULL DEFAULT 0,
  sgst         DECIMAL(12,2) NOT NULL DEFAULT 0,
  igst         DECIMAL(12,2) NOT NULL DEFAULT 0,
  total        DECIMAL(12,2) NOT NULL DEFAULT 0,
  status       ENUM('draft','issued','paid','void') NOT NULL DEFAULT 'issued',
  notes        TEXT NULL,
  created_by   INT UNSIGNED NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invoices_number (number),
  KEY idx_invoices_customer (customer_id),
  KEY idx_invoices_date (issue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- (fresh) DROP TABLE IF EXISTS invoice_items;
CREATE TABLE invoice_items (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  invoice_id  INT UNSIGNED NOT NULL,
  description VARCHAR(300) NOT NULL,
  hsn_sac     VARCHAR(20) NULL,                         -- 998555 tour operator services
  qty         DECIMAL(8,2)  NOT NULL DEFAULT 1,
  unit_price  DECIMAL(12,2) NOT NULL DEFAULT 0,
  amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
  position    INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_invoice_items (invoice_id, position),
  CONSTRAINT fk_invoice_items FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Suppliers: hotels, houseboats, transport, guides, activities
-- (fresh) DROP TABLE IF EXISTS vendors;
CREATE TABLE vendors (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  type           ENUM('hotel','houseboat','transport','guide','activity','other') NOT NULL DEFAULT 'hotel',
  name           VARCHAR(160) NOT NULL,
  contact_person VARCHAR(120) NULL,
  phone          VARCHAR(30)  NULL,
  email          VARCHAR(190) NULL,
  city           VARCHAR(120) NULL,
  address        VARCHAR(255) NULL,
  gstin          VARCHAR(20)  NULL,
  bank_details   VARCHAR(255) NULL,
  rating         TINYINT UNSIGNED NULL,
  notes          TEXT NULL,
  is_active      TINYINT(1) NOT NULL DEFAULT 1,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_vendors_type (type, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Own fleet + drivers
-- (fresh) DROP TABLE IF EXISTS vehicles;
CREATE TABLE vehicles (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name             VARCHAR(120) NOT NULL,              -- e.g. "Tempo Traveller 1"
  type             ENUM('sedan','suv','tempo','bus','4x4','other') NOT NULL DEFAULT 'sedan',
  registration_no  VARCHAR(30) NULL,
  seats            TINYINT UNSIGNED NOT NULL DEFAULT 4,
  driver_name      VARCHAR(120) NULL,
  driver_phone     VARCHAR(30)  NULL,
  insurance_expiry DATE NULL,
  permit_expiry    DATE NULL,
  notes            TEXT NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- What a booking consumes (hotel nights, cab days …) → cost vs selling price = profit
-- (fresh) DROP TABLE IF EXISTS booking_services;
CREATE TABLE booking_services (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id    INT UNSIGNED NOT NULL,
  service_type  ENUM('hotel','houseboat','transport','guide','activity','flight','other') NOT NULL DEFAULT 'hotel',
  vendor_id     INT UNSIGNED NULL,
  vehicle_id    INT UNSIGNED NULL,
  description   VARCHAR(300) NOT NULL,
  service_date  DATE NULL,
  nights        TINYINT UNSIGNED NULL,
  cost_price    DECIMAL(12,2) NOT NULL DEFAULT 0,       -- what we pay the supplier
  paid_to_vendor DECIMAL(12,2) NOT NULL DEFAULT 0,
  status        ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
  confirmation_no VARCHAR(80) NULL,
  notes         VARCHAR(255) NULL,
  position      INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_booking_services (booking_id, position),
  KEY idx_booking_services_vendor (vendor_id),
  KEY idx_booking_services_vehicle (vehicle_id, service_date),
  CONSTRAINT fk_booking_services FOREIGN KEY (booking_id) REFERENCES bookings (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp / email message templates with {{placeholders}}
-- (fresh) DROP TABLE IF EXISTS message_templates;
CREATE TABLE message_templates (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  channel    ENUM('whatsapp','email') NOT NULL DEFAULT 'whatsapp',
  name       VARCHAR(120) NOT NULL,
  subject    VARCHAR(200) NULL,
  body       TEXT NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Document number counters (QT/BK/INV/RC per year) — use App\Core\Sequence::next()
-- (fresh) DROP TABLE IF EXISTS sequences;
CREATE TABLE sequences (
  name       VARCHAR(40) NOT NULL,                      -- e.g. "quotation:2026"
  last_no    INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO message_templates (channel, name, subject, body) VALUES
  ('whatsapp', 'Enquiry — first reply', NULL, 'Hi {{customer_name}}, thank you for contacting {{company_name}}! We received your enquiry for {{trip}}. Our travel expert will share the best options shortly. — {{company_phone}}'),
  ('whatsapp', 'Quotation shared', NULL, 'Hi {{customer_name}}, here is your trip quotation {{quotation_number}} for {{trip}} ({{amount}}): {{quotation_link}} — valid till {{valid_until}}. Reply here for any changes. — {{company_name}}'),
  ('whatsapp', 'Booking confirmed', NULL, 'Hi {{customer_name}}, your booking {{booking_number}} for {{trip}} from {{travel_start}} is CONFIRMED. Total {{amount}}, paid {{paid}}, balance {{balance}}. Have a wonderful trip! — {{company_name}}'),
  ('whatsapp', 'Payment received', NULL, 'Hi {{customer_name}}, we received {{payment_amount}} for booking {{booking_number}} (receipt {{receipt_no}}). Balance due: {{balance}}. Thank you! — {{company_name}}'),
  ('whatsapp', 'Trip reminder', NULL, 'Hi {{customer_name}}, your {{trip}} starts on {{travel_start}}. Your driver details will be shared one day before. Carry warm clothes and ID proof. — {{company_name}}'),
  ('email', 'Quotation email', 'Your trip quotation {{quotation_number}} — {{company_name}}', 'Dear {{customer_name}},\n\nThank you for choosing {{company_name}}. Please find your quotation for {{trip}} here: {{quotation_link}}\n\nTotal: {{amount}} (valid till {{valid_until}}).\n\nWarm regards,\n{{company_name}}\n{{company_phone}}');
