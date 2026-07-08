<?php
require_once __DIR__ . '/lib/functions.php';
require_login();

$q = trim($_GET['q'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = ['IFNULL(x.is_deleted,0)=0'];
$params = [];

/*
  NO USAMOS LIKE EN SQL.
  La tabla de SmartPSS Lite usa collation utf8mb4_bin y provoca error 1267.
  Solo filtramos por fecha en MySQL.
*/

if ($from !== '') {
    $where[] = 'x.AttendanceDateTime >= :fromdt';
    $params['fromdt'] = datetime_to_millis($from . ' 00:00:00');
}

if ($to !== '') {
    $where[] = 'x.AttendanceDateTime <= :todt';
    $params['todt'] = datetime_to_millis($to . ' 23:59:59');
}

$sql = 'SELECT * FROM (' . raw_select_sql() . ') x
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY x.AttendanceDateTime ASC
        LIMIT 10000';

$st = pdo()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

/*
  Filtro por nombre, ID, tarjeta o equipo desde PHP.
  Así evitamos completamente el error de collation en MySQL.
*/
if ($q !== '') {
    $q_lower = mb_strtolower($q, 'UTF-8');

    $rows = array_filter($rows, function ($r) use ($q_lower) {
        $texto = implode(' ', [
            $r['PersonID'] ?? '',
            $r['PersonName'] ?? '',
            $r['PerSonCardNo'] ?? '',
            $r['DeviceName'] ?? '',
        ]);

        $texto = mb_strtolower($texto, 'UTF-8');

        return mb_strpos($texto, $q_lower) !== false;
    });

    $rows = array_values($rows);
}

$rows = array_slice($rows, 0, 5000);

/*
  Si tienes Dompdf instalado con Composer, genera PDF.
*/
$autoload = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    die('No está instalado Dompdf. Ejecuta composer install en la carpeta del sistema.');
}

require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

$logoLeft = __DIR__ . '/assets/logo_left.png';
$logoRight = __DIR__ . '/assets/logo_right.png';

$logoLeftHtml = file_exists($logoLeft)
    ? '<img src="' . $logoLeft . '" style="height:60px;">'
    : '';

$logoRightHtml = file_exists($logoRight)
    ? '<img src="' . $logoRight . '" style="height:60px;">'
    : '';

$periodo = 'Todos los registros';

if ($from !== '' && $to !== '') {
    $periodo = 'Del ' . h($from) . ' al ' . h($to);
} elseif ($from !== '') {
    $periodo = 'Desde ' . h($from);
} elseif ($to !== '') {
    $periodo = 'Hasta ' . h($to);
}

$html = '
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
    body {
        font-family: DejaVu Sans, sans-serif;
        font-size: 11px;
        color: #222;
    }

    .header {
        width: 100%;
        border-bottom: 2px solid #333;
        padding-bottom: 10px;
        margin-bottom: 15px;
    }

    .header-table {
        width: 100%;
        border-collapse: collapse;
    }

    .header-table td {
        vertical-align: middle;
        border: none;
    }

    .logo {
        width: 20%;
        text-align: center;
    }

    .title {
        width: 60%;
        text-align: center;
    }

    h1 {
        font-size: 18px;
        margin: 0;
        padding: 0;
    }

    h2 {
        font-size: 13px;
        margin: 4px 0 0 0;
        font-weight: normal;
    }

    .meta {
        margin-bottom: 12px;
        font-size: 11px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        background: #eeeeee;
        border: 1px solid #999;
        padding: 5px;
        font-weight: bold;
        text-align: left;
    }

    td {
        border: 1px solid #bbb;
        padding: 4px;
    }

    .footer {
        margin-top: 20px;
        font-size: 10px;
        text-align: center;
        color: #555;
    }
</style>
</head>
<body>

<div class="header">
    <table class="header-table">
        <tr>
            <td class="logo">' . $logoLeftHtml . '</td>
            <td class="title">
                <h1>Reporte Oficial de Asistencia</h1>
                <h2>Sistema de registros SmartPSS Lite</h2>
            </td>
            <td class="logo">' . $logoRightHtml . '</td>
        </tr>
    </table>
</div>

<div class="meta">
    <strong>Periodo:</strong> ' . $periodo . '<br>
    <strong>Búsqueda:</strong> ' . h($q !== '' ? $q : 'Sin filtro') . '<br>
    <strong>Total de registros:</strong> ' . count($rows) . '<br>
    <strong>Fecha de generación:</strong> ' . date('Y-m-d H:i:s') . '
</div>

<table>
    <thead>
        <tr>
            <th>Empleado</th>
            <th>ID</th>
            <th>Tarjeta</th>
            <th>Fecha/Hora</th>
            <th>Estado</th>
            <th>Método</th>
            <th>Equipo</th>
            <th>IP</th>
        </tr>
    </thead>
    <tbody>
';

foreach ($rows as $r) {
    $html .= '
        <tr>
            <td>' . h($r['PersonName'] ?? '') . '</td>
            <td>' . h($r['PersonID'] ?? '') . '</td>
            <td>' . h($r['PerSonCardNo'] ?? '') . '</td>
            <td>' . h(attendance_datetime($r['AttendanceDateTime'] ?? '')) . '</td>
            <td>' . h(state_label($r['AttendanceState'] ?? '')) . '</td>
            <td>' . h(method_label($r['AttendanceMethod'] ?? '')) . '</td>
            <td>' . h($r['DeviceName'] ?? '') . '</td>
            <td>' . h($r['DeviceIPAddress'] ?? '') . '</td>
        </tr>
    ';
}

$html .= '
    </tbody>
</table>

<div class="footer">
    Reporte generado automáticamente. Las ediciones o eliminaciones realizadas en el sistema PHP no modifican la tabla original de SmartPSS Lite.
</div>

</body>
</html>
';

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'reporte_asistencia_' . date('Ymd_His') . '.pdf';

$dompdf->stream($filename, ['Attachment' => true]);
exit;