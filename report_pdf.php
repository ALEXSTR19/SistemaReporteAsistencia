<?php
require_once __DIR__ . '/lib/functions.php';
require_login();

$q = trim($_GET['q'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = ['IFNULL(x.is_deleted,0)=0'];
$params = [];

// Filtramos las fechas en MySQL. La búsqueda de texto se hace en PHP para
// evitar errores de collation en instalaciones de SmartPSS Lite con utf8mb4_bin.
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

if ($q !== '') {
    $qLower = mb_strtolower($q, 'UTF-8');

    $rows = array_filter($rows, function (array $row) use ($qLower): bool {
        $searchableText = implode(' ', [
            $row['PersonID'] ?? '',
            $row['PersonName'] ?? '',
            $row['PerSonCardNo'] ?? '',
            $row['DeviceName'] ?? '',
        ]);

        return mb_strpos(mb_strtolower($searchableText, 'UTF-8'), $qLower) !== false;
    });

    $rows = array_values($rows);
}

$rows = array_slice($rows, 0, 5000);

function pdf_logo_html(string $relativePath): string
{
    $path = __DIR__ . '/' . $relativePath;

    if (!is_file($path)) {
        return '';
    }

    return '<img src="file://' . h($path) . '" width="90" alt="Logo">';
}

$period = 'Todos los registros';
if ($from !== '' && $to !== '') {
    $period = 'Del ' . $from . ' al ' . $to;
} elseif ($from !== '') {
    $period = 'Desde ' . $from;
} elseif ($to !== '') {
    $period = 'Hasta ' . $to;
}

ob_start();
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
@page { margin: 24px 24px 40px 24px; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; color: #222; }
.header { border-bottom: 2px solid #1f4e79; padding-bottom: 8px; margin-bottom: 12px; }
.logos { width: 100%; border-collapse: collapse; }
.logos td { border: 0; vertical-align: middle; }
.title { text-align: center; }
.title h2 { margin: 0; color: #1f4e79; font-size: 18px; }
.title p { margin: 3px 0; font-size: 11px; }
table.report { width: 100%; border-collapse: collapse; table-layout: fixed; }
table.report th, table.report td { border: 1px solid #ccc; padding: 4px; word-wrap: break-word; }
table.report th { background: #eaf1f8; font-size: 10px; }
.small { font-size: 9px; color: #555; margin-top: 10px; }
.footer { position: fixed; bottom: -24px; left: 0; right: 0; text-align: center; font-size: 9px; color: #777; border-top: 1px solid #ccc; padding-top: 5px; }
</style>
</head>
<body>
<div class="header">
    <table class="logos">
        <tr>
            <td width="20%"><?=pdf_logo_html('assets/logo_left.png')?></td>
            <td class="title" width="60%">
                <h2><?=h(COMPANY_NAME)?></h2>
                <p><strong>Reporte oficial de asistencia</strong></p>
                <p>Periodo: <?=h($period)?> | Búsqueda: <?=h($q !== '' ? $q : 'Sin filtro')?> | Generado: <?=date('Y-m-d H:i:s')?></p>
            </td>
            <td width="20%" style="text-align:right"><?=pdf_logo_html('assets/logo_right.png')?></td>
        </tr>
    </table>
</div>
<table class="report">
    <thead>
        <tr>
            <th width="18%">Empleado</th>
            <th width="10%">ID</th>
            <th width="10%">Tarjeta</th>
            <th width="14%">Fecha/Hora</th>
            <th width="10%">Estado</th>
            <th width="14%">Método</th>
            <th width="14%">Equipo</th>
            <th width="10%">IP</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $row): ?>
        <tr>
            <td><?=h($row['PersonName'] ?? '')?></td>
            <td><?=h($row['PersonID'] ?? '')?></td>
            <td><?=h($row['PerSonCardNo'] ?? '')?></td>
            <td><?=h(attendance_datetime($row['AttendanceDateTime'] ?? ''))?></td>
            <td><?=h(state_label($row['AttendanceState'] ?? ''))?></td>
            <td><?=h(method_label($row['AttendanceMethod'] ?? ''))?></td>
            <td><?=h($row['DeviceName'] ?? '')?></td>
            <td><?=h($row['DeviceIPAddress'] ?? '')?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<p class="small">Total de registros: <?=count($rows)?>. Las correcciones se aplican desde el sistema y la tabla original de SmartPSS Lite permanece intacta.</p>
<div class="footer">Documento generado por <?=h(APP_NAME)?> - <?=h(COMPANY_NAME)?></div>
</body>
</html>
<?php
$html = ob_get_clean();
$autoload = __DIR__ . '/vendor/autoload.php';

if (!file_exists($autoload)) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h3>Falta instalar Dompdf</h3><p>Ejecuta en esta carpeta:</p><pre>composer install</pre><p>Vista HTML:</p>' . $html;
    exit;
}

require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('chroot', __DIR__);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream('reporte_asistencia_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
exit;
