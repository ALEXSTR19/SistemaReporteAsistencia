<?php
require_once __DIR__ . '/lib/functions.php';
require_login();
$hash = $_GET['h'] ?? $_POST['record_hash'] ?? '';
$record = get_record_by_hash($hash);
if (!$record) { app_header('No encontrado'); echo '<div class="error">Registro no encontrado.</div>'; app_footer(); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $restore = isset($_POST['restore']);
    $reason = $_POST['reason'] ?: ($restore ? 'Restauración manual' : 'Eliminación manual');
    $sql = "INSERT INTO app_attendance_overrides(record_hash, is_deleted, reason, updated_by)
            VALUES(:h, :d, :reason, :u)
            ON DUPLICATE KEY UPDATE is_deleted=VALUES(is_deleted), reason=VALUES(reason), updated_by=VALUES(updated_by)";
    pdo()->prepare($sql)->execute(['h'=>$hash, 'd'=>$restore?0:1, 'reason'=>$reason, 'u'=>$_SESSION['username'] ?? 'admin']);
    pdo()->prepare("INSERT INTO app_audit_log(record_hash, action_type, old_data, reason, username) VALUES(:h,:a,:old,:reason,:u)")->execute([
        'h'=>$hash, 'a'=>$restore?'RESTORE':'DELETE', 'old'=>json_encode($record, JSON_UNESCAPED_UNICODE), 'reason'=>$reason, 'u'=>$_SESSION['username'] ?? 'admin'
    ]);
    header('Location: index.php?show_deleted=1'); exit;
}
app_header('Eliminar registro');
?>
<div class="card"><h2><?= $record['is_deleted'] ? 'Restaurar' : 'Eliminar' ?> registro</h2>
<p class="notice">Esto no borra físicamente la checada original. Solo la oculta de reportes y queda auditada.</p>
<p><strong><?=h($record['PersonID'])?></strong> <?=h($record['PersonName'])?> — <?=h(attendance_datetime($record['AttendanceDateTime']))?></p>
<form method="post"><input type="hidden" name="record_hash" value="<?=h($hash)?>"><label>Motivo</label><textarea name="reason" required></textarea><br><br>
<?php if($record['is_deleted']): ?><button name="restore" value="1">Restaurar</button><?php else: ?><button class="btn danger">Marcar como eliminado</button><?php endif; ?> <a class="btn secondary" href="index.php">Cancelar</a></form></div>
<?php app_footer(); ?>
