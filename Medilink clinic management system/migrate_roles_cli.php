<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

try {
    $columnCheck = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND COLUMN_NAME = :column_name'
    );

    $columnCheck->execute([
        'table_name' => 'users',
        'column_name' => 'patient_id',
    ]);

    if (!(bool) $columnCheck->fetchColumn()) {
        $pdo->exec('ALTER TABLE users ADD COLUMN patient_id INT UNSIGNED NULL AFTER password');
    }

    $pdo->exec("ALTER TABLE users MODIFY role ENUM('doctor', 'patient', 'admin') NOT NULL DEFAULT 'doctor'");

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS doctors (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            specialty VARCHAR(120) NULL,
            license_number VARCHAR(80) NULL,
            phone VARCHAR(30) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_doctors_user_id (user_id),
            KEY idx_doctors_specialty (specialty),
            CONSTRAINT fk_doctors_user
              FOREIGN KEY (user_id) REFERENCES users (id)
              ON UPDATE CASCADE
              ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS admins (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            department VARCHAR(120) NULL,
            phone VARCHAR(30) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_admins_user_id (user_id),
            KEY idx_admins_department (department),
            CONSTRAINT fk_admins_user
              FOREIGN KEY (user_id) REFERENCES users (id)
              ON UPDATE CASCADE
              ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS appointments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            patient_id INT UNSIGNED NOT NULL,
            doctor_id INT UNSIGNED NULL,
            appointment_type ENUM("scheduled", "emergency") NOT NULL DEFAULT "scheduled",
            scheduled_for DATETIME NOT NULL,
            status ENUM("scheduled", "completed", "cancelled") NOT NULL DEFAULT "scheduled",
            notes TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_appointments_patient_id (patient_id),
            KEY idx_appointments_doctor_id (doctor_id),
            KEY idx_appointments_scheduled_for (scheduled_for),
            CONSTRAINT fk_appointments_patient
              FOREIGN KEY (patient_id) REFERENCES patients (id)
              ON UPDATE CASCADE
              ON DELETE CASCADE,
            CONSTRAINT fk_appointments_doctor
              FOREIGN KEY (doctor_id) REFERENCES users (id)
              ON UPDATE CASCADE
              ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );

    $indexCheck = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND INDEX_NAME = :index_name'
    );

    $indexCheck->execute([
        'table_name' => 'users',
        'index_name' => 'idx_users_patient_id',
    ]);

    if (!(bool) $indexCheck->fetchColumn()) {
        $pdo->exec('ALTER TABLE users ADD KEY idx_users_patient_id (patient_id)');
    }

    $constraintCheck = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :table_name
           AND CONSTRAINT_NAME = :constraint_name'
    );

    $constraintCheck->execute([
        'table_name' => 'users',
        'constraint_name' => 'fk_users_patient',
    ]);

    if (!(bool) $constraintCheck->fetchColumn()) {
        $pdo->exec(
            'ALTER TABLE users
             ADD CONSTRAINT fk_users_patient
             FOREIGN KEY (patient_id) REFERENCES patients (id)
             ON UPDATE CASCADE
             ON DELETE SET NULL'
        );
    }

    $pdo->exec(
        'INSERT INTO doctors (user_id)
         SELECT u.id
         FROM users u
         LEFT JOIN doctors d ON d.user_id = u.id
         WHERE u.role = "doctor" AND d.user_id IS NULL'
    );

    $pdo->exec(
        'INSERT INTO admins (user_id)
         SELECT u.id
         FROM users u
         LEFT JOIN admins a ON a.user_id = u.id
         WHERE u.role = "admin" AND a.user_id IS NULL'
    );

    echo "Auth model migration complete.", PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, 'Auth model migration failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
