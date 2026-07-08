<?php
// CONFIGURACIÓN PRINCIPAL
// Cambia estos datos según tu MySQL.
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'asistencias_db');
define('DB_USER', 'root');
define('DB_PASS', 'Cem30dit01.');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'Sistema de Asistencias');
define('COMPANY_NAME', 'NOMBRE DE TU EMPRESA');
define('TABLE_RAW', 'attendancerecordinfo'); // tabla que llena SmartPSS Lite

function pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}
