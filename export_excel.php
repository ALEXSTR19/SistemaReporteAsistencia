<?php
require_once __DIR__ . '/lib/functions.php';
require_login();

const EXCEL_MAX_ROWS = 10000;

$q = trim($_GET['q'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$show_deleted = isset($_GET['show_deleted']);

$where = [];
$params = [];

if (!$show_deleted) {
    $where[] = 'IFNULL(x.is_deleted,0)=0';
}

if ($q !== '') {
    $where[] = "(
        CAST(x.PersonID AS CHAR) COLLATE utf8mb4_general_ci LIKE :q OR
        CAST(x.PersonName AS CHAR) COLLATE utf8mb4_general_ci LIKE :q OR
        CAST(x.PerSonCardNo AS CHAR) COLLATE utf8mb4_general_ci LIKE :q OR
        CAST(x.DeviceName AS CHAR) COLLATE utf8mb4_general_ci LIKE :q
    )";
    $params['q'] = "%$q%";
}

if ($from !== '') {
    $where[] = 'x.AttendanceDateTime >= :fromdt';
    $params['fromdt'] = datetime_to_millis($from . ' 00:00:00');
}

if ($to !== '') {
    $where[] = 'x.AttendanceDateTime <= :todt';
    $params['todt'] = datetime_to_millis($to . ' 23:59:59');
}

$sql = 'SELECT
            x.PersonID,
            x.PersonName,
            x.PerSonCardNo,
            x.AttendanceDateTime,
            x.AttendanceState,
            x.AttendanceMethod,
            x.DeviceIPAddress,
            x.DeviceName,
            x.SnapshotsPath
        FROM (' . raw_select_sql() . ') x';

if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}

$sql .= ' ORDER BY x.AttendanceDateTime ASC LIMIT ' . EXCEL_MAX_ROWS;

$st = pdo()->prepare($sql);
$st->execute($params);

$filename = 'smartpss_asistencia_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$output = fopen('php://output', 'w');

// BOM UTF-8 para que Excel abra correctamente acentos y eñes sin advertir archivo corrupto.
fwrite($output, "\xEF\xBB\xBF");

$columns = [
    'PersonID',
    'PersonName',
    'PerSonCardNo',
    'AttendanceDateTime',
    'AttendanceState',
    'AttendanceMethod',
    'DeviceIPAddress',
    'DeviceName',
    'SnapshotsPath',
];

fputcsv($output, $columns);

while ($row = $st->fetch()) {
    $csvRow = [];

    foreach ($columns as $column) {
        $csvRow[] = (string)($row[$column] ?? '');
    }

    fputcsv($output, $csvRow);
}

fclose($output);
exit;
