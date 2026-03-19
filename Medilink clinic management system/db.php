<?php
declare(strict_types=1);

/**
 * Central PDO connection file for the Medilink Emergency Clinic system.
 *
 * Update the credentials below to match your XAMPP MySQL setup.
 * This connection uses:
 * - UTF-8 via utf8mb4
 * - exception-based error handling
 * - safe defaults for prepared statements
 */

$dbHost = '127.0.0.1';
$dbName = 'medilink_emergency_clinic';
$dbUser = 'root';
$dbPass = '';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
];

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, $pdoOptions);
} catch (PDOException $exception) {
    exit('Database connection failed. Please confirm your database settings in db.php.');
}
