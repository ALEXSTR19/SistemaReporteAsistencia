<?php
require_once __DIR__ . '/lib/functions.php';
require_login();

$q = trim($_GET['q'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$show_deleted = isset($_GET['show_deleted']);
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

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

$sqlBase = 'SELECT * FROM (' . raw_select_sql() . ') x';

if ($where) {
    $sqlBase .= ' WHERE ' . implode(' AND ', $where);
}

$sql = $sqlBase . ' ORDER BY x.AttendanceDateTime DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

$st = pdo()->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

app_header('Registros de asistencia');
?>
<div class="card">
<h2>Registros de asistencia</h2>
<p class="notice">Este sistema NO modifica la tabla original de SmartPSS Lite. Las ediciones y eliminaciones se guardan en tablas separadas de corrección y auditoría.</p>
<form class="filters" method="get">
  <div><label>Buscar</label><input name="q" value="<?=h($q)?>" placeholder="ID, nombre, tarjeta o equipo"></div>
  <div><label>Desde</label><input type="date" name="from" value="<?=h($from)?>"></div>
  <div><label>Hasta</label><input type="date" name="to" value="<?=h($to)?>"></div>
  <div><label>Mostrar eliminados</label><select name="show_deleted"><option value="">No</option><option value="1" <?=$show_deleted?'selected':''?>>Sí</option></select></div>
  <div><button>Filtrar</button> <a class="btn secondary" href="report_pdf.php?<?=h(http_build_query($_GET))?>">PDF</a> <a class="btn secondary" href="export_excel.php?<?=h(http_build_query($_GET))?>">CSV para Excel</a></div>
</form>
</div>
<div class="card">
<table>
<thead><tr><th>Fecha/Hora</th><th>ID</th><th>Nombre</th><th>Tarjeta</th><th>Estado</th><th>Método</th><th>Equipo</th><th>IP</th><th>Acciones</th></tr></thead>
<tbody>
<?php foreach($rows as $r): ?>
<tr class="<?=$r['is_deleted']?'deleted':''?>">
<td><?=h(attendance_datetime($r['AttendanceDateTime']))?></td>
<td><?=h($r['PersonID'])?></td>
<td><?=h($r['PersonName'])?></td>
<td><?=h($r['PerSonCardNo'])?></td>
<td><?=h(state_label($r['AttendanceState']))?></td>
<td><?=h(method_label($r['AttendanceMethod']))?></td>
<td><?=h($r['DeviceName'])?></td>
<td><?=h($r['DeviceIPAddress'])?></td>
<td class="actions"><a class="btn" href="edit.php?h=<?=h($r['record_hash'])?>">Editar</a> <a class="btn danger" href="delete.php?h=<?=h($r['record_hash'])?>">Eliminar</a></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<p><a class="btn secondary" href="?<?=h(http_build_query(array_merge($_GET,['page'=>$page+1])))?>">Siguiente</a></p>
</div>
<?php app_footer(); ?>
