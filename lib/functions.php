<?php
require_once __DIR__ . '/../config.php';

function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function raw_hash_sql(string $alias = 'r'): string {
    $p = $alias !== '' ? $alias . '.' : '';
    return "SHA2(CONCAT_WS('|', IFNULL({$p}PersonID,''), IFNULL({$p}PersonName,''), IFNULL({$p}PerSonCardNo,''), IFNULL({$p}AttendanceDateTime,''), IFNULL({$p}AttendanceState,''), IFNULL({$p}AttendanceMethod,''), IFNULL({$p}DeviceIPAddress,''), IFNULL({$p}DeviceName,''), IFNULL({$p}SnapshotsPath,'')), 256)";
}

function raw_select_sql(): string {
    $hash = raw_hash_sql();
    $table = TABLE_RAW;
    return "
        SELECT
            $hash AS record_hash,
            r.PersonID,
            COALESCE(o.PersonName, r.PersonName) AS PersonName,
            COALESCE(o.PerSonCardNo, r.PerSonCardNo) AS PerSonCardNo,
            COALESCE(o.AttendanceDateTime, r.AttendanceDateTime) AS AttendanceDateTime,
            COALESCE(o.AttendanceState, r.AttendanceState) AS AttendanceState,
            COALESCE(o.AttendanceMethod, r.AttendanceMethod) AS AttendanceMethod,
            COALESCE(o.DeviceIPAddress, r.DeviceIPAddress) AS DeviceIPAddress,
            COALESCE(o.DeviceName, r.DeviceName) AS DeviceName,
            COALESCE(o.SnapshotsPath, r.SnapshotsPath) AS SnapshotsPath,
            r.PersonName AS original_PersonName,
            r.PerSonCardNo AS original_PerSonCardNo,
            r.AttendanceDateTime AS original_AttendanceDateTime,
            r.AttendanceState AS original_AttendanceState,
            r.AttendanceMethod AS original_AttendanceMethod,
            r.DeviceIPAddress AS original_DeviceIPAddress,
            r.DeviceName AS original_DeviceName,
            r.SnapshotsPath AS original_SnapshotsPath,
            IFNULL(o.is_deleted,0) AS is_deleted,
            o.updated_at AS corrected_at,
            o.reason AS correction_reason
        FROM `$table` r
        LEFT JOIN app_attendance_overrides o ON o.record_hash = $hash
    ";
}

function attendance_datetime($value): string {
    if ($value === null || $value === '') {
        return '';
    }

    $v = (int)$value;

    // SmartPSS puede guardar milisegundos: 1781796109000.
    // Si tiene 13 dígitos o más, convertimos a segundos.
    if ($v > 9999999999) {
        $v = intdiv($v, 1000);
    }

    $dt = new DateTime('@' . $v);
    $dt->setTimezone(new DateTimeZone('America/Mexico_City'));

    return $dt->format('Y-m-d H:i:s');
}

function datetime_to_millis(?string $dt): ?string {
    if (!$dt) {
        return null;
    }

    $date = new DateTime($dt, new DateTimeZone('America/Mexico_City'));

    return (string)($date->getTimestamp() * 1000);
}

function state_label($state): string {
    $map = [0 => 'Entrada', 1 => 'Salida', 2 => 'Descanso', 3 => 'Retorno'];
    return $map[(int)$state] ?? (string)$state;
}

function method_label($method): string {
    $map = [1=>'Contraseña', 2=>'Tarjeta', 4=>'Huella', 8=>'Rostro', 15=>'Combinado/Desconocido'];
    return $map[(int)$method] ?? (string)$method;
}

function require_login(): void {
    session_start();
    if (empty($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}

function app_header(string $title): void {
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>'.h($title).'</title><link rel="stylesheet" href="assets/style.css"></head><body>';
    echo '<header><div><strong>'.h(APP_NAME).'</strong><span>'.h(COMPANY_NAME).'</span></div><nav><a href="index.php">Registros</a><a href="report.php">Reporte</a><a href="logout.php">Salir</a></nav></header><main>';
}

function app_footer(): void { echo '</main></body></html>'; }

function get_record_by_hash(string $hash): ?array {
    $sql = raw_select_sql() . " WHERE " . raw_hash_sql() . " = :hash LIMIT 1";
    $st = pdo()->prepare($sql);
    $st->execute(['hash' => $hash]);
    $row = $st->fetch();
    return $row ?: null;
}
