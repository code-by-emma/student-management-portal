<?php
/**
 * One-time seed script.
 *
 * Run this AFTER importing database/schema.sql, either:
 *   - from the CLI:      php database/seed.php
 *   - or from a browser: http://localhost/smp/database/seed.php
 *
 * It creates demo accounts (with correctly bcrypt-hashed passwords) plus a
 * sample class, subject, course, enrollment, timetable slot and exam so the
 * portal isn't empty on first login. Safe to run only once — it refuses to
 * run again if an admin account already exists.
 */

require_once __DIR__ . '/../config/database.php';
// config/database.php connects and exposes a ready-to-use $pdo global — no getDB() wrapper exists in this app.

$existing = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE r.name='admin'")->fetchColumn();
if ($existing > 0) {
    die("Seed data already present — an admin account exists. Nothing to do.\n");
}

$pdo->beginTransaction();
try {
    // --- Admin -----------------------------------------------------
    $adminPass = 'Admin@123';
    $stmt = $pdo->prepare("INSERT INTO users (role_id, first_name, last_name, email, username, password_hash, phone, status)
                            VALUES ((SELECT id FROM roles WHERE name='admin'), ?,?,?,?,?,?, 'active')");
    $stmt->execute(['System', 'Administrator', 'admin@smp.local', 'admin', password_hash($adminPass, PASSWORD_BCRYPT), '+2340000000']);

    // --- Class / subject / teacher / course (reuse the ones schema.sql seeded) ---
    $classId   = (int)$pdo->query("SELECT id FROM classes ORDER BY id LIMIT 1")->fetchColumn();
    $subjectId = (int)$pdo->query("SELECT id FROM subjects ORDER BY id LIMIT 1")->fetchColumn();

    $teacherPass = 'Teacher@123';
    $stmt = $pdo->prepare("INSERT INTO users (role_id, first_name, last_name, email, username, password_hash, phone, status)
                            VALUES ((SELECT id FROM roles WHERE name='teacher'), ?,?,?,?,?,?, 'active')");
    $stmt->execute(['Jane', 'Okoro', 'jane.okoro@smp.local', 'jokoro', password_hash($teacherPass, PASSWORD_BCRYPT), '+2348000000001']);
    $teacherUserId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO teachers (user_id, staff_no, department, hire_date) VALUES (?,?,?,CURDATE())");
    $stmt->execute([$teacherUserId, 'STF-001', 'Mathematics']);
    $teacherId = $pdo->lastInsertId();

    $pdo->prepare("UPDATE classes SET class_teacher_id=? WHERE id=?")->execute([$teacherId, $classId]);

    $stmt = $pdo->prepare("INSERT INTO courses (subject_id, class_id, teacher_id, term, academic_year, description)
                            VALUES (?,?,?, 'Term 1', '2026/2027', 'Introductory course covering the term 1 curriculum.')");
    $stmt->execute([$subjectId, $classId, $teacherId]);
    $courseId = $pdo->lastInsertId();

    // --- Student -----------------------------------------------------
    $studentPass = 'Student@123';
    $stmt = $pdo->prepare("INSERT INTO users (role_id, first_name, last_name, email, username, password_hash, phone, status)
                            VALUES ((SELECT id FROM roles WHERE name='student'), ?,?,?,?,?,?, 'active')");
    $stmt->execute(['Chidi', 'Umeh', 'chidi.umeh@smp.local', 'cumeh', password_hash($studentPass, PASSWORD_BCRYPT), '+2348000000002']);
    $studentUserId = $pdo->lastInsertId();

    $stmt = $pdo->prepare("INSERT INTO students (user_id, admission_no, class_id, guardian_name, guardian_phone, enrollment_date)
                            VALUES (?, 'ADM-2026-001', ?, 'Mrs. Umeh', '+2348000000003', CURDATE())");
    $stmt->execute([$studentUserId, $classId]);
    $studentId = $pdo->lastInsertId();

    $pdo->prepare("INSERT INTO enrollments (student_id, course_id) VALUES (?,?)")->execute([$studentId, $courseId]);

    // --- Sample timetable slot ---------------------------------------
    $pdo->prepare("INSERT INTO timetables (class_id, course_id, day_of_week, start_time, end_time, room)
                    VALUES (?,?, ?, '08:00:00', '09:00:00', 'Room 12')")
        ->execute([$classId, $courseId, date('l')]); // schedules today, so the dashboard has something to show

    // --- Sample exam (no grades yet — teacher enters those from the UI) ---
    $pdo->prepare("INSERT INTO exams (course_id, name, exam_date, max_score, weight_percent) VALUES (?, 'Midterm', CURDATE(), 100, 40)")
        ->execute([$courseId]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    die('Seeding failed: ' . $e->getMessage() . "\n");
}

echo "Seed data created successfully.\n\n";
echo "Demo accounts (change these passwords after first login):\n";
echo "  Admin    -> username: admin   password: {$adminPass}\n";
echo "  Teacher  -> username: jokoro  password: {$teacherPass}\n";
echo "  Student  -> username: cumeh   password: {$studentPass}\n";
