-- ParlReg: Parliament Event Registration Suite
-- Database Migration 001 — Full Initial Schema
-- Engine: MySQL 8.x | Charset: utf8mb4

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Users ────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fullname       VARCHAR(255)  NOT NULL,
    email          VARCHAR(255)  NOT NULL UNIQUE,
    password_hash  VARCHAR(255)  NOT NULL,
    role           ENUM('admin','organizer','viewer') NOT NULL DEFAULT 'organizer',
    is_active      TINYINT(1)    NOT NULL DEFAULT 1,
    last_login     TIMESTAMP     NULL,
    created_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_role  (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── SMTP Profiles ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS smtp_profiles (
    id                 INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name               VARCHAR(100) NOT NULL,
    host               VARCHAR(255) NOT NULL,
    port               SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    encryption         ENUM('tls','ssl','none') NOT NULL DEFAULT 'tls',
    username           VARCHAR(255) NOT NULL,
    password_encrypted TEXT NOT NULL,
    created_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Events ───────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS events (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug             VARCHAR(160)  NOT NULL UNIQUE,
    name_en          VARCHAR(255)  NOT NULL,
    name_fr          VARCHAR(255)  NULL,
    date_start       DATETIME      NOT NULL,
    date_end         DATETIME      NOT NULL,
    location_en      VARCHAR(255)  NULL,
    location_fr      VARCHAR(255)  NULL,
    meta_title_en    VARCHAR(255)  NULL,
    meta_title_fr    VARCHAR(255)  NULL,
    meta_desc_en     TEXT          NULL,
    meta_desc_fr     TEXT          NULL,
    config_json      JSON          NULL COMMENT 'section order, toggle states, per-section content',
    form_schema_json JSON          NULL COMMENT 'form builder field array',
    status           ENUM('draft','published','closed') NOT NULL DEFAULT 'draft',
    capacity         INT UNSIGNED  NULL,
    approval_mode    ENUM('auto','manual') NOT NULL DEFAULT 'auto',
    theme_color      CHAR(7)       NULL COMMENT 'hex e.g. #1B3A6B',
    registration_deadline DATETIME NULL,
    smtp_profile_id  INT UNSIGNED  NULL,
    created_by       INT UNSIGNED  NOT NULL,
    created_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug   (slug),
    INDEX idx_status (status),
    FOREIGN KEY (created_by)      REFERENCES users(id),
    FOREIGN KEY (smtp_profile_id) REFERENCES smtp_profiles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── FAQs ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS faqs (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id     INT UNSIGNED NOT NULL,
    question_en  TEXT NOT NULL,
    question_fr  TEXT NULL,
    answer_en    TEXT NOT NULL,
    answer_fr    TEXT NULL,
    sort_order   INT UNSIGNED NOT NULL DEFAULT 0,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_id (event_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Registrations ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS registrations (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id       INT UNSIGNED  NOT NULL,
    fullname       VARCHAR(255)  NOT NULL,
    email          VARCHAR(255)  NOT NULL,
    phone          VARCHAR(50)   NULL,
    organisation   VARCHAR(255)  NULL,
    country        VARCHAR(100)  NULL,
    status         ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    reference_no   VARCHAR(20)   NOT NULL UNIQUE,
    data_json      JSON          NULL COMMENT 'full raw field responses',
    consent_given  TINYINT(1)    NOT NULL DEFAULT 0,
    consent_ts     TIMESTAMP     NULL,
    consent_ip     VARCHAR(45)   NULL,
    submitted_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_id    (event_id),
    INDEX idx_email       (email),
    INDEX idx_status      (status),
    INDEX idx_reference   (reference_no),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Uploaded Files ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS uploaded_files (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    registration_id   INT UNSIGNED  NOT NULL,
    event_id          INT UNSIGNED  NOT NULL,
    field_name        VARCHAR(100)  NOT NULL,
    stored_filename   VARCHAR(255)  NOT NULL COMMENT 'UUID-based name on disk',
    original_filename VARCHAR(255)  NOT NULL,
    mime_type         VARCHAR(100)  NOT NULL,
    filesize          INT UNSIGNED  NOT NULL COMMENT 'bytes',
    stored_path       VARCHAR(500)  NOT NULL COMMENT 'relative to /storage/uploads/',
    uploaded_at       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_registration_id (registration_id),
    FOREIGN KEY (registration_id) REFERENCES registrations(id) ON DELETE CASCADE,
    FOREIGN KEY (event_id)        REFERENCES events(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Email Templates ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS email_templates (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id     INT UNSIGNED NULL COMMENT 'NULL = global default',
    type         ENUM('confirmation','admin_notification','approval','rejection') NOT NULL,
    subject_en   VARCHAR(255) NOT NULL,
    subject_fr   VARCHAR(255) NULL,
    body_en      TEXT NOT NULL,
    body_fr      TEXT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Password Reset Tokens ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED  NOT NULL,
    token_hash  VARCHAR(255)  NOT NULL,
    expires_at  DATETIME      NOT NULL,
    used        TINYINT(1)    NOT NULL DEFAULT 0,
    created_at  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Audit Log ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_log (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id      INT UNSIGNED  NULL,
    action       VARCHAR(100)  NOT NULL,
    entity_type  VARCHAR(50)   NULL,
    entity_id    INT UNSIGNED  NULL,
    detail       TEXT          NULL,
    ip_address   VARCHAR(45)   NULL,
    created_at   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_user   (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Rate Limit Tracker ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS rate_limits (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    identifier  VARCHAR(255) NOT NULL COMMENT 'IP or user_id:action',
    action      VARCHAR(100) NOT NULL,
    attempts    INT UNSIGNED NOT NULL DEFAULT 1,
    window_start DATETIME   NOT NULL,
    INDEX idx_identifier_action (identifier, action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ─── Seed: Default Admin User ────────────────────────────────────────────────
-- Password: Admin@ParlReg1 (bcrypt, change immediately after first login)
INSERT INTO users (fullname, email, password_hash, role) VALUES
('System Administrator', 'admin@parliament.local',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- ─── Seed: Default Global Email Templates ─────────────────────────────────────
INSERT INTO email_templates (event_id, type, subject_en, subject_fr, body_en, body_fr) VALUES
(NULL, 'confirmation',
 'Registration Confirmed — {{event_name}}',
 'Inscription confirmée — {{event_name}}',
 'Dear {{participant_name}},\n\nYour registration for {{event_name}} has been received.\n\nReference Number: {{reference_number}}\nEvent Date: {{event_date}}\nLocation: {{event_location}}\n\nPlease retain this reference number for your records.\n\nKind regards,\nParliamentary Services',
 'Cher(e) {{participant_name}},\n\nVotre inscription à {{event_name}} a bien été reçue.\n\nNuméro de référence: {{reference_number}}\nDate de l\'événement: {{event_date}}\nLieu: {{event_location}}\n\nVeuillez conserver ce numéro de référence.\n\nCordialement,\nServices Parlementaires'),
(NULL, 'admin_notification',
 'New Registration — {{event_name}}',
 'Nouvelle inscription — {{event_name}}',
 'A new registration has been received for {{event_name}}.\n\nParticipant: {{participant_name}}\nEmail: {{participant_email}}\nOrganisation: {{participant_organisation}}\nCountry: {{participant_country}}\nSubmitted: {{submitted_at}}\nReference: {{reference_number}}',
 'Une nouvelle inscription a été reçue pour {{event_name}}.\n\nParticipant: {{participant_name}}\nEmail: {{participant_email}}');
