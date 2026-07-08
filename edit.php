<?php
require_once __DIR__ . '/lib/functions.php';
require_login();
$hash = $_GET['h'] ?? $_POST['record_hash'] ?? '';
$record = get_record_by_hash($hash);
if (!$record) { app_header('No encontrado'); echo '<div class="error">Registro no encontrado.</div>'; app_footer(); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new = [
        'PersonName' => $_POST['PersonName'] ?: null,
        'PerSonCardNo' => $_POST['PerSonCardNo'] ?: null,
        'AttendanceDateTime' => datetime_to_millis($_POST['AttendanceDateTime'] ?? '') ?: null,
        'AttendanceState' => $_POST['AttendanceState'] !== '' ? (int)$_POST['AttendanceState'] : null,
        'AttendanceMethod' => $_POST['AttendanceMethod'] !== '' ? (int)$_POST['AttendanceMethod'] : null,
        'DeviceIPAddress' => $_POST['DeviceIPAddress'] ?: null,
        'DeviceName' => $_POST['DeviceName'] ?: null,
        'SnapshotsPath' => $_POST['SnapshotsPath'] ?: null,
        'reason' => $_POST['reason'] ?: 'Corrección manual',
        'updated_by' => $_SESSION['username'] ?? 'admin',
        'record_hash' => $hash,
    ];
    $sql = "INSERT INTO app_attendance_overrides
        (record_hash, PersonName, PerSonCardNo, AttendanceDateTime, AttendanceState, AttendanceMethod, DeviceIPAddress, DeviceName, SnapshotsPath, is_deleted, reason, updated_by)
        VALUES (:record_hash, :PersonName, :PerSonCardNo, :AttendanceDateTime, :AttendanceState, :AttendanceMethod, :DeviceIPAddress, :DeviceName, :SnapshotsPath, 0, :reason, :updated_by)
        ON DUPLICATE KEY UPDATE
        PersonName=VALUES(PersonName), PerSonCardNo=VALUES(PerSonCardNo), AttendanceDateTime=VALUES(AttendanceDateTime), AttendanceState=VALUES(AttendanceState), AttendanceMethod=VALUES(AttendanceMethod), DeviceIPAddress=VALUES(DeviceIPAddress), DeviceName=VALUES(DeviceName), SnapshotsPath=VALUES(SnapshotsPath), is_deleted=0, reason=VALUES(reason), updated_by=VALUES(updated_by)";
    pdo()->prepare($sql)->execute($new);
    pdo()->prepare("INSERT INTO app_audit_log(record_hash, action_type, old_data, new_data, reason, username) VALUES(:h,'EDIT',:old,:new,:reason,:u)")->execute([
        'h'=>$hash, 'old'=>json_encode($record, JSON_UNESCAPED_UNICODE), 'new'=>json_encode($new, JSON_UNESCAPED_UNICODE), 'reason'=>$new['reason'], 'u'=>$_SESSION['username'] ?? 'admin'
    ]);
    header('Location: index.php'); exit;
}
app_header('Editar registro');
?>
<div class="card"><h2>Editar registro</h2>
<p class="notice">La edición se guarda como corrección. El registro original de SmartPSS Lite queda intacto.</p>
<form method="post">
<input type="hidden" name="record_hash" value="<?=h($hash)?>">
<label>PersonID original</label><input value="<?=h($record['PersonID'])?>" disabled>
<label>Nombre</label><input name="PersonName" value="<?=h($record['PersonName'])?>">
<label>Tarjeta</label><input name="PerSonCardNo" value="<?=h($record['PerSonCardNo'])?>">
<label>Fecha y hora</label><input type="datetime-local" name="AttendanceDateTime" value="<?=h(str_replace(' ', 'T', attendance_datetime($record['AttendanceDateTime'])))?>">
<label>Estado</label><select name="AttendanceState">
<?php foreach([0=>'Entrada',1=>'Salida',2=>'Descanso',3=>'Retorno'] as $k=>$v): ?><option value="<?=$k?>" <?=((int)$record['AttendanceState']===$k?'selected':'')?>><?=h($v)?></option><?php endforeach; ?>
</select>
<label>Método</label><input name="AttendanceMethod" value="<?=h($record['AttendanceMethod'])?>">
<label>IP</label><input name="DeviceIPAddress" value="<?=h($record['DeviceIPAddress'])?>">
<label>Equipo</label><input name="DeviceName" value="<?=h($record['DeviceName'])?>">
<label>Snapshot</label><input name="SnapshotsPath" value="<?=h($record['SnapshotsPath'])?>">
<label>Motivo de corrección</label><textarea name="reason" required></textarea>
<br><br><button>Guardar corrección</button> <a class="btn secondary" href="index.php">Cancelar</a>
</form></div>
<?php app_footer(); ?>
