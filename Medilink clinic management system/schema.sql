CREATE DATABASE IF NOT EXISTS medilink_emergency_clinic
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE medilink_emergency_clinic;

CREATE TABLE IF NOT EXISTS patients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  date_of_birth DATE NULL,
  medical_record_number VARCHAR(60) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_patients_medical_record_number (medical_record_number),
  KEY idx_patients_name (name),
  KEY idx_patients_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL,
  password VARCHAR(255) NOT NULL,
  patient_id INT UNSIGNED NULL,
  role ENUM('doctor', 'patient', 'admin') NOT NULL DEFAULT 'doctor',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_patient_id (patient_id),
  KEY idx_users_role (role),
  KEY idx_users_created_at (created_at),
  CONSTRAINT fk_users_patient
    FOREIGN KEY (patient_id) REFERENCES patients (id)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS doctors (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS appointments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  doctor_id INT UNSIGNED NULL,
  appointment_type ENUM('scheduled', 'emergency') NOT NULL DEFAULT 'scheduled',
  scheduled_for DATETIME NOT NULL,
  status ENUM('scheduled', 'completed', 'cancelled') NOT NULL DEFAULT 'scheduled',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS emergency_calls (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id INT UNSIGNED NOT NULL,
  location VARCHAR(255) NOT NULL,
  priority ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium',
  status ENUM('pending', 'assigned', 'completed') NOT NULL DEFAULT 'pending',
  timestamp TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  notes TEXT NULL,
  KEY idx_calls_patient_id (patient_id),
  KEY idx_calls_priority (priority),
  KEY idx_calls_status (status),
  KEY idx_calls_timestamp (timestamp),
  CONSTRAINT fk_emergency_calls_patient
    FOREIGN KEY (patient_id) REFERENCES patients (id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
