<?php
require_once __DIR__ . '/lib/functions.php';
require_login();
$q = trim($_GET['q'] ?? ''); $from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
$where = ['IFNULL(is_deleted,0)=0']; $params=[];
if ($q !== '') { $where[] = '(PersonID LIKE :q OR PersonName LIKE :q OR PerSonCardNo LIKE :q OR DeviceName LIKE :q)'; $params['q']="%$q%"; }
if ($from !== '') { $where[] = 'AttendanceDateTime >= :fromdt'; $params['fromdt']=datetime_to_millis($from.' 00:00:00'); }
if ($to !== '') { $where[] = 'AttendanceDateTime <= :todt'; $params['todt']=datetime_to_millis($to.' 23:59:59'); }
$sql = 'SELECT * FROM (' . raw_select_sql() . ') x WHERE '.implode(' AND ', $where).' ORDER BY PersonName, AttendanceDateTime ASC LIMIT 5000';
$st=pdo()->prepare($sql); $st->execute($params); $rows=$st->fetchAll();

ob_start();
?>
<!doctype html><html><head><meta charset="utf-8"><style>
body{font-family:DejaVu Sans,Arial,sans-serif;font-size:11px;color:#222}.header{border-bottom:2px solid #1f4e79;padding-bottom:8px;margin-bottom:12px}.logos{width:100%;}.logos td{border:0}.title{text-align:center}.title h2{margin:0;color:#1f4e79}.title p{margin:3px 0}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:5px}th{background:#eaf1f8}.small{font-size:9px;color:#555}.footer{position:fixed;bottom:0;left:0;right:0;text-align:center;font-size:9px;color:#777;border-top:1px solid #ccc;padding-top:5px}
</style></head><body>
<div class="header"><table class="logos"><tr><td width="20%"><img src="assets/logo_left.png" width="90"></td><td class="title" width="60%"><h2><?=h(COMPANY_NAME)?></h2><p><strong>Reporte oficial de asistencia</strong></p><p>Periodo: <?=h($from ?: 'inicio')?> a <?=h($to ?: 'fin')?> | Generado: <?=date('Y-m-d H:i:s')?></p></td><td width="20%" style="text-align:right"><img src="assets/logo_right.png" width="90"></td></tr></table></div>
<table><thead><tr><th>Empleado</th><th>ID</th><th>Tarjeta</th><th>Fecha/Hora</th><th>Estado</th><th>Método</th><th>Equipo</th><th>IP</th></tr></thead><tbody>
<?php foreach($rows as $r): ?><tr><td><?=h($r['PersonName'])?></td><td><?=h($r['PersonID'])?></td><td><?=h($r['PerSonCardNo'])?></td><td><?=h(attendance_datetime($r['AttendanceDateTime']))?></td><td><?=h(state_label($r['AttendanceState']))?></td><td><?=h(method_label($r['AttendanceMethod']))?></td><td><?=h($r['DeviceName'])?></td><td><?=h($r['DeviceIPAddress'])?></td></tr><?php endforeach; ?>
</tbody></table><p class="small">Total de registros: <?=count($rows)?>. Las correcciones se aplican desde el sistema y la tabla original de SmartPSS Lite permanece intacta.</p><div class="footer">Documento generado por <?=h(APP_NAME)?> - <?=h(COMPANY_NAME)?></div></body></html>
<?php
$html = ob_get_clean();
$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    echo "<h3>Falta instalar Dompdf</h3><p>Ejecuta en esta carpeta:</p><pre>composer install</pre><p>Vista HTML:</p>" . $html;
    exit;
}
require_once $autoload;
use Dompdf\Dompdf;
use Dompdf\Options;
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('letter', 'landscape');
$dompdf->render();
$dompdf->stream('reporte_asistencia_' . date('Ymd_His') . '.pdf', ['Attachment' => true]);
