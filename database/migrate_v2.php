<?php
/**
 * Migration: Grade Level classification + School Fees system.
 *
 * Safe to run on an EXISTING installation that already has data — it never
 * drops or rewrites existing tables, only adds new ones plus one new
 * nullable column on `classes`. Safe to run more than once (every step
 * checks whether it's already been applied before doing anything).
 *
 * Run once, from the CLI:      php database/migrate_v2.php
 * or from a browser:           http://localhost/smp/database/migrate_v2.php
 */

require_once __DIR__ . '/../config/database.php';

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    $stmt->execute([$table, $column]);
    return (bool)$stmt->fetchColumn();
}

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$table]);
    return (bool)$stmt->fetchColumn();
}

function fk_exists(PDO $pdo, string $table, string $constraintName): bool {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?");
    $stmt->execute([$table, $constraintName]);
    return (bool)$stmt->fetchColumn();
}

$steps = [];

try {
    // 1. grade_levels ---------------------------------------------------
    if (!table_exists($pdo, 'grade_levels')) {
        $pdo->exec("CREATE TABLE grade_levels (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL UNIQUE,
            display_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $steps[] = 'Created grade_levels table.';
    } else {
        $steps[] = 'grade_levels table already exists — skipped.';
    }

    // Seed default grade levels only if the table is empty (won't touch
    // an install where an admin already customized this list).
    $count = (int)$pdo->query('SELECT COUNT(*) FROM grade_levels')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO grade_levels (name, display_order) VALUES (?, ?)');
        for ($i = 1; $i <= 12; $i++) {
            $stmt->execute(["Grade {$i}", $i]);
        }
        $steps[] = 'Seeded default grade levels (Grade 1–12). Rename/add/remove them from Admin → Grade Levels.';
    }

    // 2. classes.grade_level_id -----------------------------------------
    if (!column_exists($pdo, 'classes', 'grade_level_id')) {
        $pdo->exec('ALTER TABLE classes ADD COLUMN grade_level_id INT NULL AFTER name');
        $steps[] = 'Added classes.grade_level_id column.';
    } else {
        $steps[] = 'classes.grade_level_id already exists — skipped.';
    }
    if (!fk_exists($pdo, 'classes', 'fk_classes_grade_level')) {
        try {
            $pdo->exec('ALTER TABLE classes ADD CONSTRAINT fk_classes_grade_level
                        FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id) ON DELETE SET NULL');
            $steps[] = 'Added foreign key classes.grade_level_id → grade_levels.id.';
        } catch (PDOException $e) {
            $steps[] = 'NOTE: could not add the grade_level_id foreign key (' . $e->getMessage() . '). The column still works — this is just a safety constraint.';
        }
    }

    // Best-effort backfill: link any existing class whose free-text `name`
    // exactly matches a seeded grade level (e.g. an old "Grade 9" row).
    // Anything that doesn't match stays NULL — admin fixes it from the UI.
    $pdo->exec("UPDATE classes c JOIN grade_levels g ON g.name = c.name
                SET c.grade_level_id = g.id WHERE c.grade_level_id IS NULL");

    // 3. fee_structures ----------------------------------------------------
    if (!table_exists($pdo, 'fee_structures')) {
        $pdo->exec("CREATE TABLE fee_structures (
            id INT AUTO_INCREMENT PRIMARY KEY,
            grade_level_id INT NULL,
            fee_type VARCHAR(100) NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            term VARCHAR(30) NOT NULL,
            academic_year VARCHAR(20) NOT NULL,
            due_date DATE NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            -- Deliberately NOT ON DELETE CASCADE — see schema.sql for why:
            -- this must never let a grade-level deletion silently cascade
            -- away fee/payment history behind admin/fees.php's back.
            FOREIGN KEY (grade_level_id) REFERENCES grade_levels(id),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $steps[] = 'Created fee_structures table.';
    } else {
        $steps[] = 'fee_structures table already exists — skipped.';
    }

    // 4. student_fees (one invoice row per student per fee structure) ------
    if (!table_exists($pdo, 'student_fees')) {
        $pdo->exec("CREATE TABLE student_fees (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            fee_structure_id INT NOT NULL,
            amount_due DECIMAL(12,2) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_student_fee (student_id, fee_structure_id),
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $steps[] = 'Created student_fees table.';
    } else {
        $steps[] = 'student_fees table already exists — skipped.';
    }

    // 5. fee_payments --------------------------------------------------
    if (!table_exists($pdo, 'fee_payments')) {
        $pdo->exec("CREATE TABLE fee_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_fee_id INT NOT NULL,
            amount DECIMAL(12,2) NOT NULL,
            method ENUM('cash','bank_transfer','online','other') NOT NULL DEFAULT 'cash',
            reference VARCHAR(100) NULL,
            status ENUM('pending','success','failed') NOT NULL DEFAULT 'success',
            paid_at DATETIME NOT NULL,
            recorded_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_reference (reference),
            FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE,
            FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $steps[] = 'Created fee_payments table.';
    } else {
        $steps[] = 'fee_payments table already exists — skipped.';
    }

    echo "Migration complete.\n\n";
    foreach ($steps as $s) echo "- {$s}\n";
    echo "\nNext: log in as admin → 'Grade Levels' to review the seeded list, then 'Fees' to create your first fee structure.\n";
} catch (Throwable $e) {
    http_response_code(500);
    echo "Migration failed: " . $e->getMessage() . "\n";
    echo "Nothing already applied was rolled back — re-run this script after fixing the issue; already-applied steps are skipped automatically.\n";
}
