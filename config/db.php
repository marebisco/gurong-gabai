<?php
// ============================================================
// config/db.php — Database Connection
// NOTE: Default XAMPP credentials — root with blank password
// Change DB_PASS if you set a MySQL password
// ============================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'gurong_gabai_db');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    die("<div style='font-family:sans-serif;padding:40px;color:#DC2626;'>
        <h2>Database Connection Failed</h2>
        <p>Error: " . mysqli_connect_error() . "</p>
        <p>Make sure XAMPP MySQL is running and the database <strong>gurong_gabai_db</strong> exists.</p>
        <p>Run <strong>database_setup.sql</strong> in phpMyAdmin first.</p>
    </div>");
}

mysqli_set_charset($conn, 'utf8mb4');
// Set timezone to Philippines (UTC+8) para hindi mag-expire agad ang OTP
mysqli_query($conn, "SET time_zone = '+8:00'");
?>