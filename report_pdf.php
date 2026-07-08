<?php
require_once __DIR__ . '/lib/functions.php';
require_login();

const PDF_MAX_ROWS = 1000;
const PDF_QUERY_LIMIT = PDF_MAX_ROWS + 1;
const PDF_LOGO_MAX_BYTES = 250000;
const PDF_LOGO_MAX_PIXELS = 500;

$q = trim($_GET['q'] ?? '');
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';

$where = ['IFNULL(x.is_deleted,0)=0'];
$params = [];

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

function report_select_sql(): string
{
    $hash = raw_hash_sql();
    $table = TABLE_RAW;

    return "
        SELECT
            r.PersonID,
            COALESCE(o.PersonName, r.PersonName) AS PersonName,
            COALESCE(o.PerSonCardNo, r.PerSonCardNo) AS PerSonCardNo,
            COALESCE(o.AttendanceDateTime, r.AttendanceDateTime) AS AttendanceDateTime,
            COALESCE(o.AttendanceState, r.AttendanceState) AS AttendanceState,
            COALESCE(o.AttendanceMethod, r.AttendanceMethod) AS AttendanceMethod,
            COALESCE(o.DeviceIPAddress, r.DeviceIPAddress) AS DeviceIPAddress,
            COALESCE(o.DeviceName, r.DeviceName) AS DeviceName,
            IFNULL(o.is_deleted,0) AS is_deleted
        FROM `$table` r
        LEFT JOIN app_attendance_overrides o ON o.record_hash = $hash
    ";
}

function render_pdf_limit_message(int $limit): void
{
    header('Content-Type: text/html; charset=UTF-8');
    app_header('Exportación PDF limitada');
    $query = $_GET;
    unset($query['page']);
    echo '<div class="card">';
    echo '<h2>No se generó el PDF</h2>';
    echo '<p class="notice">El resultado supera el límite de <strong>' . h($limit) . '</strong> registros permitidos para exportar a PDF.</p>';
    echo '<p>Usa filtros más específicos antes de exportar, por ejemplo por rango de fechas, nombre, ID, tarjeta o equipo. Esto evita consumo excesivo de memoria al generar el PDF.</p>';
    echo '<p><a class="btn" href="index.php?' . h(http_build_query($query)) . '">Volver a los registros</a></p>';
    echo '</div>';
    app_footer();
}

$fpdfPath = __DIR__ . '/lib/fpdf/fpdf.php';

if (!file_exists($fpdfPath)) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h3>Falta la librería FPDF</h3><p>Coloca <code>fpdf.php</code> en <code>lib/fpdf/</code>.</p>';
    exit;
}

require_once $fpdfPath;

if (!class_exists('FPDF')) {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h3>No se encontró la clase FPDF</h3><p>Verifica que <code>lib/fpdf/fpdf.php</code> contenga la librería FPDF.</p>';
    exit;
}

function pdf_text($value): string
{
    $text = html_entity_decode((string)$value, ENT_QUOTES, 'UTF-8');
    $converted = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);

    return $converted === false ? preg_replace('/[^\x20-\x7E]/', '', $text) : $converted;
}

function pdf_truncate(FPDF $pdf, string $text, float $width): string
{
    $text = pdf_text($text);

    while ($pdf->GetStringWidth($text) > ($width - 2) && strlen($text) > 0) {
        $text = substr($text, 0, -1);
    }

    return $text;
}

function prepare_pdf_logo(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }

    $info = @getimagesize($path);
    if (!$info) {
        return null;
    }

    $fileSize = filesize($path) ?: 0;
    $needsResize = $fileSize > PDF_LOGO_MAX_BYTES || max((int)$info[0], (int)$info[1]) > PDF_LOGO_MAX_PIXELS;

    if (!$needsResize) {
        return $path;
    }

    if (!function_exists('imagecreatetruecolor')) {
        return $fileSize <= PDF_LOGO_MAX_BYTES ? $path : null;
    }

    $mime = $info['mime'] ?? '';
    if ($mime === 'image/jpeg') {
        $source = @imagecreatefromjpeg($path);
    } elseif ($mime === 'image/png') {
        $source = @imagecreatefrompng($path);
    } else {
        return null;
    }

    if (!$source) {
        return null;
    }

    $width = imagesx($source);
    $height = imagesy($source);
    $scale = min(1, PDF_LOGO_MAX_PIXELS / max($width, $height));
    $newWidth = max(1, (int)round($width * $scale));
    $newHeight = max(1, (int)round($height * $scale));
    $resized = imagecreatetruecolor($newWidth, $newHeight);
    $white = imagecolorallocate($resized, 255, 255, 255);
    imagefill($resized, 0, 0, $white);
    imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    $tempPath = tempnam(sys_get_temp_dir(), 'pdf_logo_') . '.jpg';
    imagejpeg($resized, $tempPath, 75);
    imagedestroy($source);
    imagedestroy($resized);

    return $tempPath;
}

class AttendanceReportPdf extends FPDF
{
    public string $period = '';
    public string $search = '';
    public int $totalRows = 0;
    public ?string $leftLogo = null;
    public ?string $rightLogo = null;

    public function Header(): void
    {
        if ($this->leftLogo !== null) {
            $this->Image($this->leftLogo, 12, 8, 28);
        }

        if ($this->rightLogo !== null) {
            $this->Image($this->rightLogo, 245, 8, 28);
        }

        $this->SetTextColor(31, 78, 121);
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 7, pdf_text(COMPANY_NAME), 0, 1, 'C');
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, pdf_text('Reporte oficial de asistencia'), 0, 1, 'C');
        $this->SetTextColor(60, 60, 60);
        $this->SetFont('Arial', '', 8);
        $this->Cell(0, 5, pdf_text('Periodo: ' . $this->period . ' | Búsqueda: ' . $this->search . ' | Generado: ' . date('Y-m-d H:i:s')), 0, 1, 'C');
        $this->SetDrawColor(31, 78, 121);
        $this->SetLineWidth(0.6);
        $this->Line(10, 30, 287, 30);
        $this->Ln(8);
    }

    public function Footer(): void
    {
        $this->SetY(-15);
        $this->SetDrawColor(200, 200, 200);
        $this->Line(10, $this->GetY(), 287, $this->GetY());
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 8, pdf_text(APP_NAME . ' - ' . COMPANY_NAME . ' | Página ' . $this->PageNo() . '/{nb}'), 0, 0, 'C');
    }

    public function TableHeader(array $widths): void
    {
        $headers = ['Empleado', 'ID', 'Tarjeta', 'Fecha/Hora', 'Estado', 'Método', 'Equipo', 'IP'];
        $this->SetFillColor(234, 241, 248);
        $this->SetTextColor(20, 45, 70);
        $this->SetDrawColor(180, 190, 200);
        $this->SetFont('Arial', 'B', 8);

        foreach ($headers as $i => $header) {
            $this->Cell($widths[$i], 7, pdf_text($header), 1, 0, 'C', true);
        }

        $this->Ln();
    }
}

$period = 'Todos los registros';
if ($from !== '' && $to !== '') {
    $period = 'Del ' . $from . ' al ' . $to;
} elseif ($from !== '') {
    $period = 'Desde ' . $from;
} elseif ($to !== '') {
    $period = 'Hasta ' . $to;
}

$countSql = 'SELECT COUNT(*) FROM (
            SELECT 1 FROM (' . report_select_sql() . ') x
            WHERE ' . implode(' AND ', $where) . '
            LIMIT ' . PDF_QUERY_LIMIT . '
        ) limited_rows';
$countSt = pdo()->prepare($countSql);
$countSt->execute($params);
$matchingRows = (int)$countSt->fetchColumn();

if ($matchingRows > PDF_MAX_ROWS) {
    render_pdf_limit_message(PDF_MAX_ROWS);
    exit;
}

$sql = 'SELECT * FROM (' . report_select_sql() . ') x
        WHERE ' . implode(' AND ', $where) . '
        ORDER BY x.AttendanceDateTime ASC
        LIMIT ' . PDF_MAX_ROWS;

$st = pdo()->prepare($sql);
$st->execute($params);

$pdf = new AttendanceReportPdf('L', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->period = $period;
$pdf->search = $q !== '' ? $q : 'Sin filtro';
$pdf->leftLogo = prepare_pdf_logo(__DIR__ . '/assets/logo_left.png');
$pdf->rightLogo = prepare_pdf_logo(__DIR__ . '/assets/logo_right.png');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

$widths = [48, 24, 25, 34, 24, 34, 48, 40];
$pdf->TableHeader($widths);
$pdf->SetFont('Arial', '', 7);
$pdf->SetTextColor(35, 35, 35);
$pdf->SetDrawColor(210, 210, 210);

$totalRows = 0;
while ($row = $st->fetch()) {
    $totalRows++;

    if ($pdf->GetY() > 190) {
        $pdf->AddPage();
        $pdf->TableHeader($widths);
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(35, 35, 35);
        $pdf->SetDrawColor(210, 210, 210);
    }

    $values = [
        $row['PersonName'] ?? '',
        $row['PersonID'] ?? '',
        $row['PerSonCardNo'] ?? '',
        attendance_datetime($row['AttendanceDateTime'] ?? ''),
        state_label($row['AttendanceState'] ?? ''),
        method_label($row['AttendanceMethod'] ?? ''),
        $row['DeviceName'] ?? '',
        $row['DeviceIPAddress'] ?? '',
    ];

    foreach ($values as $i => $value) {
        $align = in_array($i, [1, 2, 3, 4, 7], true) ? 'C' : 'L';
        $pdf->Cell($widths[$i], 6, pdf_truncate($pdf, (string)$value, $widths[$i]), 1, 0, $align);
    }

    $pdf->Ln();
}

$pdf->totalRows = $totalRows;
$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 5, pdf_text('Total de registros: ' . $totalRows), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);

$pdf->Output('D', 'reporte_asistencia_' . date('Ymd_His') . '.pdf');
foreach ([$pdf->leftLogo, $pdf->rightLogo] as $logoPath) {
    if ($logoPath !== null && str_starts_with($logoPath, sys_get_temp_dir()) && is_file($logoPath)) {
        @unlink($logoPath);
    }
}
exit;
