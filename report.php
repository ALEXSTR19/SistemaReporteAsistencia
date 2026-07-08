<?php
require_once __DIR__ . '/lib/functions.php';
require_login();

$queryString = http_build_query($_GET);
$location = 'report_pdf.php' . ($queryString !== '' ? '?' . $queryString : '');

header('Location: ' . $location);
exit;
