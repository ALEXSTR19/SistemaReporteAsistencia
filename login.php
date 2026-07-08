<?php
require_once __DIR__ . '/lib/functions.php';
session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $st = pdo()->prepare('SELECT * FROM app_users WHERE username = :u LIMIT 1');
    $st->execute(['u' => $_POST['username'] ?? '']);
    $user = $st->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        header('Location: index.php'); exit;
    }
    $error = 'Usuario o contraseña incorrectos.';
}
?><!doctype html><html lang="es"><head><meta charset="utf-8"><title>Entrar</title><link rel="stylesheet" href="assets/style.css"></head><body><main class="login"><div class="card"><h2><?=h(APP_NAME)?></h2><?php if($error): ?><p class="error"><?=h($error)?></p><?php endif; ?><form method="post"><label>Usuario</label><input name="username" required value="admin"><label>Contraseña</label><input name="password" type="password" required value="admin123"><br><br><button>Entrar</button></form><p class="notice">Cambia la contraseña inicial después de instalar.</p></div></main></body></html>
