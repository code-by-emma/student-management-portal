-- =========================================================
-- Student Management Portal (SMP) - Database Schema
-- Engine: MySQL 8+ / MariaDB 10.4+
-- Charset: utf8mb4
-- =========================================================

CREATE DATABASE IF NOT EXISTS smp_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smp_db;

-- ---------------------------------------------------------
-- ROLES
-- ---------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(20) NOT NULL UNIQUE  -- admin, teacher, student
);

INSERT INTO roles (name) VALUES ('admin'), ('teacher'), ('student');

-- ---------------------------------------------------------
-- USERS (base account for every role)
-- ---------------------------------------------------------
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    first_name VARCHAR(60) NOT NULL,
    last_name VARCHAR(60) NOT NULL,
    email VARCHAR(120) NOT NULL UNIQUE,
    username VARCHAR(60) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30),
    profile_picture VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    reset_token VARCHAR(255) DEFAULT NULL,
    reset_token_expires DATETIME DEFAULT NULL,
    remember_token VARCHAR(255) DEFAULT NULL,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- ---------------------------------------------------------
-- CLASSES (e.g. Grade 10 - Section A)
-- ---------------------------------------------------------
-- ---------------------------------------------------------
-- GRADE LEVELS (e.g. "Grade 1") — classes below are the sections/arms
-- within a grade level (e.g. "Grade 1" -> "1A", "1B")
-- ---------------------------------------------------------
CREATE TABLE grade_levels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_order INT NOT NULL DEFAULT 0, -- controls sort order, since "Grade 10" < "Grade 2" alphabetically
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,          -- kept in sync with grade_levels.name for backward compatibility
    grade_level_id INT DEFAULT NULL,     -- FK to grade_levels; the source of truth for `name` above
    section VARCHAR(20) DEFAULT NULL,    -- e.g. "A" -> together with the grade level this makes "Grade 1A"
    academic_year VARCHAR(20) NOT NULL,  -- e.g. "2026/2027"
    class_teacher_id INT DEFAULT NULL,  -- FK added below, after `teachers` table exists
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- STUDENTS (extends users)
-- ---------------------------------------------------------
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    admission_no VARCHAR(30) NOT NULL UNIQUE,
    class_id INT DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,
    gender ENUM('male','female','other') DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    guardian_name VARCHAR(120) DEFAULT NULL,
    guardian_phone VARCHAR(30) DEFAULT NULL,
    enrollment_date DATE DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- TEACHERS (extends users)
-- ---------------------------------------------------------
CREATE TABLE teachers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    staff_no VARCHAR(30) NOT NULL UNIQUE,
    department VARCHAR(100) DEFAULT NULL,
    qualification VARCHAR(150) DEFAULT NULL,
    hire_date DATE DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

ALTER TABLE classes
    ADD CONSTRAINT fk_class_teacher FOREIGN KEY (class_teacher_id) REFERENCES teachers(id) ON DELETE SET NULL;

-- ---------------------------------------------------------
-- SUBJECTS
-- ---------------------------------------------------------
CREATE TABLE subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ---------------------------------------------------------
-- COURSES (a subject taught to a class by a teacher in a term)
-- ---------------------------------------------------------
CREATE TABLE courses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    teacher_id INT DEFAULT NULL,
    term VARCHAR(30) DEFAULT NULL,        -- e.g. "Term 1"
    academic_year VARCHAR(20) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES teachers(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- ENROLLMENTS (student <-> course)
-- ---------------------------------------------------------
CREATE TABLE enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrolled_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_enrollment (student_id, course_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- ATTENDANCE
-- ---------------------------------------------------------
CREATE TABLE attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present','absent','late','excused') NOT NULL DEFAULT 'present',
    marked_by INT DEFAULT NULL, -- teacher user id
    remarks VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_attendance (student_id, course_id, date),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- ASSIGNMENTS
-- ---------------------------------------------------------
CREATE TABLE assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT,
    file_path VARCHAR(255) DEFAULT NULL,
    max_score DECIMAL(6,2) DEFAULT 100,
    due_date DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- ASSIGNMENT SUBMISSIONS
-- ---------------------------------------------------------
CREATE TABLE assignment_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    submission_text TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    score DECIMAL(6,2) DEFAULT NULL,
    feedback TEXT,
    graded_at DATETIME DEFAULT NULL,
    graded_by INT DEFAULT NULL,
    UNIQUE KEY uniq_submission (assignment_id, student_id),
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (graded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- EXAMS
-- ---------------------------------------------------------
CREATE TABLE exams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,   -- e.g. "Midterm", "Final"
    exam_date DATE DEFAULT NULL,
    max_score DECIMAL(6,2) DEFAULT 100,
    weight_percent DECIMAL(5,2) DEFAULT 100,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- GRADES (exam scores per student)
-- ---------------------------------------------------------
CREATE TABLE grades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    student_id INT NOT NULL,
    score DECIMAL(6,2) DEFAULT NULL,
    grade_letter VARCHAR(5) DEFAULT NULL,
    remarks VARCHAR(255) DEFAULT NULL,
    recorded_by INT DEFAULT NULL,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_grade (exam_id, student_id),
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- RESULTS (aggregated final result per student per course/term)
-- ---------------------------------------------------------
CREATE TABLE results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    term VARCHAR(30) DEFAULT NULL,
    academic_year VARCHAR(20) NOT NULL,
    final_score DECIMAL(6,2) DEFAULT NULL,
    final_grade VARCHAR(5) DEFAULT NULL,
    position INT DEFAULT NULL,
    remarks VARCHAR(255) DEFAULT NULL,
    published TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_result (student_id, course_id, term, academic_year),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- TIMETABLES
-- ---------------------------------------------------------
CREATE TABLE timetables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    course_id INT NOT NULL,
    day_of_week ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50) DEFAULT NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- ANNOUNCEMENTS
-- ---------------------------------------------------------
CREATE TABLE announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    body TEXT NOT NULL,
    audience ENUM('all','admin','teacher','student') DEFAULT 'all',
    class_id INT DEFAULT NULL,      -- optional: target a specific class
    created_by INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- NOTIFICATIONS (per-user system notifications)
-- ---------------------------------------------------------
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NOT NULL,
    type VARCHAR(40) DEFAULT 'general', -- assignment, grade, attendance, announcement, exam
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ---------------------------------------------------------
-- AUDIT LOGS
-- ---------------------------------------------------------
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(100) NOT NULL,     -- e.g. "student.create", "auth.login"
    entity_type VARCHAR(60) DEFAULT NULL,
    entity_id INT DEFAULT NULL,
    details TEXT,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ---------------------------------------------------------
-- SCHOOL FEES
-- ---------------------------------------------------------

-- A fee an admin defines once per grade level/term/year (e.g. "Tuition Fee",
-- Grade 1, Term 1 2026/2027, ₦150,000). grade_level_id NULL = applies to
-- every grade level (e.g. a school-wide "PTA Levy").
CREATE TABLE fee_structures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    grade_level_id INT DEFAULT NULL,
    fee_type VARCHAR(100) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    term VARCHAR(30) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    due_date DATE DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Deliberately NOT ON DELETE CASCADE: a grade level with fee structures
    -- (and therefore possibly real payment history several tables deep)
    -- must never be deletable in a way that bypasses admin/fees.php's own
    -- "block delete if payments exist" check. MySQL's default action when
    -- none is given is RESTRICT, which is what we want here.
    FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
);

-- One invoice row per student per fee structure, generated in bulk by the
-- admin ("Generate Invoices"). amount_due is copied from the structure at
-- generation time so a later change to the structure's amount doesn't
-- silently alter an invoice a student may have already started paying.
CREATE TABLE student_fees (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_structure_id INT NOT NULL,
    amount_due DECIMAL(12,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_student_fee (student_id, fee_structure_id),
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE CASCADE
);

-- A payment against one invoice. `reference` holds the Paystack transaction
-- reference for online payments (kept UNIQUE so a page refresh after
-- payment can never credit the same transaction twice); NULL for manual
-- entries, and MySQL allows unlimited NULLs in a unique index.
CREATE TABLE fee_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_fee_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    method ENUM('cash','bank_transfer','online','other') NOT NULL DEFAULT 'cash',
    reference VARCHAR(100) DEFAULT NULL,
    status ENUM('pending','success','failed') NOT NULL DEFAULT 'success',
    paid_at DATETIME NOT NULL,
    recorded_by INT DEFAULT NULL, -- admin who recorded a manual payment; NULL for online self-service
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_reference (reference),
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
);

-- =========================================================
-- SEED DATA
-- =========================================================

-- Grade levels (classes below are the sections/arms within each one)
INSERT INTO grade_levels (name, display_order) VALUES
('Grade 1', 1), ('Grade 2', 2), ('Grade 3', 3), ('Grade 4', 4), ('Grade 5', 5), ('Grade 6', 6),
('Grade 7', 7), ('Grade 8', 8), ('Grade 9', 9), ('Grade 10', 10), ('Grade 11', 11), ('Grade 12', 12);

-- NOTE: User accounts (admin/teacher/student) are NOT seeded here because
-- password hashes must be generated with PHP's password_hash() at seed time.
-- After creating this schema, run:  php database/seed.php
-- to create the demo accounts listed in README.md.

-- Sample classes
INSERT INTO classes (name, grade_level_id, section, academic_year) VALUES
('Grade 9', (SELECT id FROM grade_levels WHERE name = 'Grade 9'), 'A', '2026/2027'),
('Grade 10', (SELECT id FROM grade_levels WHERE name = 'Grade 10'), 'A', '2026/2027');

-- Sample subjects
INSERT INTO subjects (name, code) VALUES
('Mathematics', 'MATH101'),
('English Language', 'ENG101'),
('Basic Science', 'SCI101');
