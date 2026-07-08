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

class AttendanceReportPdf extends FPDF
{
    public string $period = '';
    public string $search = '';
    public int $totalRows = 0;

    public function Header(): void
    {
        $leftLogo = __DIR__ . '/assets/logo_left.png';
        $rightLogo = __DIR__ . '/assets/logo_right.png';

        if (is_file($leftLogo)) {
            $this->Image($leftLogo, 12, 8, 28);
        }

        if (is_file($rightLogo)) {
            $this->Image($rightLogo, 245, 8, 28);
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

$pdf = new AttendanceReportPdf('L', 'mm', 'Letter');
$pdf->AliasNbPages();
$pdf->period = $period;
$pdf->search = $q !== '' ? $q : 'Sin filtro';
$pdf->totalRows = count($rows);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

$widths = [48, 24, 25, 34, 24, 34, 48, 40];
$pdf->TableHeader($widths);
$pdf->SetFont('Arial', '', 7);
$pdf->SetTextColor(35, 35, 35);
$pdf->SetDrawColor(210, 210, 210);

foreach ($rows as $row) {
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

$pdf->Ln(4);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(0, 5, pdf_text('Total de registros: ' . count($rows)), 0, 1, 'L');
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(0, 5, pdf_text('Las correcciones se aplican desde el sistema y la tabla original de SmartPSS Lite permanece intacta.'));

$pdf->Output('D', 'reporte_asistencia_' . date('Ymd_His') . '.pdf');
exit;
