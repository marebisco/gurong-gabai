-- ============================================================
-- GURONG GABAI — Database Setup SQL — UPDATED v3 (June 2026)
-- CHANGES:
--   - lesson_plans: added 'ILAW' to format ENUM
--   - lesson_plans: added 'ILAW' to curriculum ENUM
--   - lesson_plans: expanded section TEXT columns (was VARCHAR → TEXT for long AI output)
--   - lesson_plan_history: new table for generation audit trail
-- ============================================================

-- ── STEP 1: Create the database (run once) ─────────────────
CREATE DATABASE IF NOT EXISTS gurong_gabai_db
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE gurong_gabai_db;

-- ── STEP 2: Teachers table ──────────────────────────────────
CREATE TABLE IF NOT EXISTS teachers (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    full_name    VARCHAR(100)  NOT NULL,
    school_name  VARCHAR(150)  NOT NULL,
    email        VARCHAR(100)  NOT NULL UNIQUE,
    password     VARCHAR(255)  NOT NULL,
    role         ENUM('teacher','admin') DEFAULT 'teacher',
    status       ENUM('pending','approved','rejected','deactivated') DEFAULT 'pending',
    otp_verified TINYINT(1) DEFAULT 0,
    profile_photo VARCHAR(255) DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── STEP 3: OTP Tokens ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS otp_tokens (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    otp_code   VARCHAR(10)  NOT NULL,
    expires_at DATETIME     NOT NULL,
    is_used    TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);

-- ── STEP 4: Password Resets ────────────────────────────────
CREATE TABLE IF NOT EXISTS password_resets (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    email      VARCHAR(100) NOT NULL,
    token      VARCHAR(255) NOT NULL,
    expires_at DATETIME     NOT NULL,
    is_used    TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── STEP 5: Lesson Plans (main library table) ───────────────
-- NOTE: format and curriculum include 'ILAW' for SY 2026-2027
CREATE TABLE IF NOT EXISTS lesson_plans (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id             INT NOT NULL,
    title                  VARCHAR(300) NOT NULL,
    grade_level            VARCHAR(20)  NOT NULL,
    subject                VARCHAR(100) NOT NULL,
    topic                  VARCHAR(300) NOT NULL,

    -- Curriculum: ILAW = MATATAG + Three-Term (SY 2026-2027)
    curriculum             ENUM('MATATAG','ILAW','K-12') DEFAULT 'ILAW',

    -- Format: ILAW is the new official format per DO No. 16, s. 2026
    format                 ENUM('ILAW','DLP','4As','5Es','Traditional','Semi','DLL') DEFAULT 'ILAW',

    strategy               VARCHAR(100) DEFAULT 'Discussion-Based',

    -- Lesson plan content sections (TEXT to handle long AI output)
    learning_objectives    TEXT,
    materials_needed       TEXT,
    introduction_motivation TEXT,
    lesson_body            TEXT,
    learning_activities    TEXT,
    assessment             TEXT,
    closure                TEXT,

    is_saved               TINYINT(1) DEFAULT 0,
    is_deleted             TINYINT(1) DEFAULT 0,  -- soft delete (trash bin)
    created_at             DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_teacher_saved   (teacher_id, is_saved),
    INDEX idx_teacher_deleted (teacher_id, is_deleted),
    INDEX idx_created         (created_at)
);

-- ── STEP 6: Generation History (audit trail) ───────────────
-- Logs every generation attempt (even unsaved ones)
CREATE TABLE IF NOT EXISTS lesson_plan_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id  INT NOT NULL,
    grade_level VARCHAR(20)  NOT NULL,
    subject     VARCHAR(100) NOT NULL,
    topic       VARCHAR(300) NOT NULL,
    curriculum  VARCHAR(20)  DEFAULT 'ILAW',
    format      VARCHAR(20)  DEFAULT 'ILAW',
    strategy    VARCHAR(100) DEFAULT 'Discussion-Based',
    content     LONGTEXT,   -- full JSON output from AI
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE,
    INDEX idx_teacher_history (teacher_id, created_at)
);

-- ── STEP 7: Default admin account ──────────────────────────
-- Email:    admin@guronggabai.com
-- Password: Admin@123
INSERT INTO teachers (full_name, school_name, email, password, role, status, otp_verified)
VALUES (
    'System Admin',
    'Gurong GabAI',
    'admin@guronggabai.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    'approved',
    1
) ON DUPLICATE KEY UPDATE role='admin', status='approved';


-- ════════════════════════════════════════════════════════════
-- MIGRATION SCRIPT (run this if you already have an old DB)
-- If your gurong_gabai_db already exists, run ONLY this part:
-- ════════════════════════════════════════════════════════════

-- Add 'ILAW' to curriculum enum if missing
ALTER TABLE lesson_plans
  MODIFY COLUMN curriculum ENUM('MATATAG','ILAW','K-12') DEFAULT 'ILAW';

-- Add 'ILAW' to format enum if missing
ALTER TABLE lesson_plans
  MODIFY COLUMN format ENUM('ILAW','DLP','4As','5Es','Traditional','Semi','DLL') DEFAULT 'ILAW';

-- Convert VARCHAR content columns to TEXT (safe even if already TEXT)
ALTER TABLE lesson_plans
  MODIFY COLUMN learning_objectives    TEXT,
  MODIFY COLUMN materials_needed       TEXT,
  MODIFY COLUMN introduction_motivation TEXT,
  MODIFY COLUMN lesson_body            TEXT,
  MODIFY COLUMN learning_activities    TEXT,
  MODIFY COLUMN assessment             TEXT,
  MODIFY COLUMN closure                TEXT;

-- Add strategy column if missing
ALTER TABLE lesson_plans
  ADD COLUMN IF NOT EXISTS strategy VARCHAR(100) DEFAULT 'Discussion-Based';

-- Add is_deleted column if missing (for trash/restore feature)
ALTER TABLE lesson_plans
  ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0;

-- Create history table if not exists (non-destructive)
CREATE TABLE IF NOT EXISTS lesson_plan_history (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id  INT NOT NULL,
    grade_level VARCHAR(20)  NOT NULL,
    subject     VARCHAR(100) NOT NULL,
    topic       VARCHAR(300) NOT NULL,
    curriculum  VARCHAR(20)  DEFAULT 'ILAW',
    format      VARCHAR(20)  DEFAULT 'ILAW',
    strategy    VARCHAR(100) DEFAULT 'Discussion-Based',
    content     LONGTEXT,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE CASCADE
);