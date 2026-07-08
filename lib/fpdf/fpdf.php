<?php
/**
 * Minimal local FPDF-compatible implementation for this project.
 * It implements the subset of the classic FPDF API used by report_pdf.php:
 * pages, headers/footers via subclass overrides, text cells, simple lines,
 * JPEG/PNG images (PNG is converted through GD), and browser/file output.
 */
class FPDF
{
    protected string $orientation;
    protected string $unit;
    protected string $format;
    protected float $k;
    protected float $w;
    protected float $h;
    protected float $x = 0;
    protected float $y = 0;
    protected float $lMargin = 10;
    protected float $tMargin = 10;
    protected float $rMargin = 10;
    protected bool $autoPageBreak = true;
    protected float $bMargin = 20;
    protected array $pages = [];
    protected array $images = [];
    protected array $current = [];
    protected int $page = 0;
    protected string $fontFamily = 'Helvetica';
    protected string $fontStyle = '';
    protected float $fontSize = 12;
    protected array $textColor = [0, 0, 0];
    protected array $drawColor = [0, 0, 0];
    protected array $fillColor = [255, 255, 255];
    protected float $lineWidth = 0.2;
    protected bool $aliasNbPages = false;
    protected bool $inHeader = false;
    protected bool $inFooter = false;

    public function __construct($orientation = 'P', $unit = 'mm', $format = 'A4')
    {
        $this->orientation = strtoupper($orientation);
        $this->unit = $unit;
        $this->format = is_string($format) ? $format : 'A4';
        $this->k = $unit === 'pt' ? 1 : ($unit === 'cm' ? 72 / 2.54 : 72 / 25.4);
        [$w, $h] = $this->formatSize($format);
        if ($this->orientation === 'L') {
            [$w, $h] = [$h, $w];
        }
        $this->w = $w;
        $this->h = $h;
        $this->SetMargins(10, 10, 10);
    }

    public function Header(): void {}
    public function Footer(): void {}

    public function AliasNbPages($alias = '{nb}'): void
    {
        $this->aliasNbPages = true;
    }

    public function SetMargins($left, $top, $right = null): void
    {
        $this->lMargin = (float)$left;
        $this->tMargin = (float)$top;
        $this->rMargin = $right === null ? (float)$left : (float)$right;
        $this->x = $this->lMargin;
    }

    public function SetAutoPageBreak($auto, $margin = 0): void
    {
        $this->autoPageBreak = (bool)$auto;
        $this->bMargin = (float)$margin;
    }

    public function AddPage($orientation = '', $format = ''): void
    {
        if ($this->page > 0) {
            $this->inFooter = true;
            $this->Footer();
            $this->inFooter = false;
            $this->pages[$this->page - 1] = $this->current;
        }
        $this->page++;
        $this->current = [];
        $this->x = $this->lMargin;
        $this->y = $this->tMargin;
        $this->inHeader = true;
        $this->Header();
        $this->inHeader = false;
    }

    public function SetTextColor($r, $g = null, $b = null): void
    {
        $this->textColor = $this->colorArray($r, $g, $b);
    }

    public function SetDrawColor($r, $g = null, $b = null): void
    {
        $this->drawColor = $this->colorArray($r, $g, $b);
    }

    public function SetFillColor($r, $g = null, $b = null): void
    {
        $this->fillColor = $this->colorArray($r, $g, $b);
    }

    public function SetLineWidth($width): void
    {
        $this->lineWidth = (float)$width;
    }

    public function SetFont($family, $style = '', $size = 0): void
    {
        $family = strtolower((string)$family);
        $this->fontFamily = in_array($family, ['courier', 'times'], true) ? ucfirst($family) : 'Helvetica';
        $this->fontStyle = strtoupper((string)$style);
        if ((float)$size > 0) {
            $this->fontSize = (float)$size;
        }
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = ''): void
    {
        $w = (float)$w;
        $h = (float)$h;
        if ($w === 0.0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        if ($this->autoPageBreak && !$this->inHeader && !$this->inFooter && $this->y + $h > $this->h - $this->bMargin) {
            $this->AddPage();
        }
        if ($fill) {
            $this->rect($this->x, $this->y, $w, $h, 'F');
        }
        if ($border) {
            $this->rect($this->x, $this->y, $w, $h, 'D');
        }
        $tx = $this->x + 1;
        $textWidth = $this->GetStringWidth((string)$txt);
        if ($align === 'C') {
            $tx = $this->x + max(1, ($w - $textWidth) / 2);
        } elseif ($align === 'R') {
            $tx = $this->x + max(1, $w - $textWidth - 1);
        }
        $this->text($tx, $this->y + ($h / 2) + ($this->fontSize / $this->k / 3), (string)$txt);
        $this->x += $w;
        if ((int)$ln === 1) {
            $this->Ln($h);
        }
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false): void
    {
        $maxChars = max(1, (int)floor(((float)$w ?: ($this->w - $this->lMargin - $this->rMargin)) / 2));
        foreach (explode("\n", wordwrap((string)$txt, $maxChars, "\n", true)) as $line) {
            $this->Cell($w, $h, $line, $border, 1, $align === 'J' ? 'L' : $align, $fill);
            $this->x = $this->lMargin;
        }
    }

    public function Ln($h = null): void
    {
        $this->x = $this->lMargin;
        $this->y += $h === null ? $this->fontSize / $this->k : (float)$h;
    }

    public function Line($x1, $y1, $x2, $y2): void
    {
        $this->current[] = $this->drawColorCmd() . sprintf(' %.2F w %.2F %.2F m %.2F %.2F l S', $this->lineWidth * $this->k, $x1 * $this->k, ($this->h - $y1) * $this->k, $x2 * $this->k, ($this->h - $y2) * $this->k);
    }

    public function Image($file, $x = null, $y = null, $w = 0, $h = 0, $type = '', $link = ''): void
    {
        if (!is_file($file)) {
            return;
        }
        $key = realpath($file) ?: $file;
        if (!isset($this->images[$key])) {
            $this->images[$key] = $this->loadImage($file, $type);
            if ($this->images[$key]) {
                $validImages = count(array_filter($this->images));
                $this->images[$key]['name'] = 'I' . $validImages;
            }
        }
        if (!$this->images[$key]) {
            return;
        }
        $image = $this->images[$key];
        if ((float)$w === 0.0 && (float)$h === 0.0) {
            $w = $image['w'] / 96 * 25.4;
            $h = $image['h'] / 96 * 25.4;
        } elseif ((float)$w === 0.0) {
            $w = (float)$h * $image['w'] / $image['h'];
        } elseif ((float)$h === 0.0) {
            $h = (float)$w * $image['h'] / $image['w'];
        }
        $this->current[] = sprintf('q %.2F 0 0 %.2F %.2F %.2F cm /%s Do Q', $w * $this->k, $h * $this->k, (float)$x * $this->k, ($this->h - (float)$y - (float)$h) * $this->k, $image['name']);
    }

    public function GetY(): float { return $this->y; }
    public function SetY($y): void { $this->y = $y < 0 ? $this->h + (float)$y : (float)$y; }
    public function GetStringWidth($s): float { return strlen((string)$s) * $this->fontSize * 0.35; }
    public function PageNo(): int { return $this->page; }

    public function Output($dest = '', $name = '', $isUTF8 = false): string
    {
        if ($this->page > 0) {
            $this->inFooter = true;
            $this->Footer();
            $this->inFooter = false;
            $this->pages[$this->page - 1] = $this->current;
            $this->current = [];
        }
        $pdf = $this->buildPdf();
        $dest = strtoupper((string)($dest ?: 'I'));
        if ($dest === 'S') {
            return $pdf;
        }
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Length: ' . strlen($pdf));
            $disposition = $dest === 'D' ? 'attachment' : 'inline';
            header('Content-Disposition: ' . $disposition . '; filename="' . basename((string)$name) . '"');
        }
        echo $pdf;
        return '';
    }

    protected function rect(float $x, float $y, float $w, float $h, string $style): void
    {
        $color = $style === 'F' ? $this->fillColorCmd() : $this->drawColorCmd();
        $op = $style === 'F' ? 'f' : 'S';
        $this->current[] = $color . sprintf(' %.2F w %.2F %.2F %.2F %.2F re %s', $this->lineWidth * $this->k, $x * $this->k, ($this->h - $y) * $this->k, $w * $this->k, -$h * $this->k, $op);
    }

    protected function text(float $x, float $y, string $txt): void
    {
        $font = $this->fontStyle === 'B' ? 'F2' : 'F1';
        $this->current[] = $this->textColorCmd() . sprintf(' BT /%s %.2F Tf %.2F %.2F Td (%s) Tj ET', $font, $this->fontSize, $x * $this->k, ($this->h - $y) * $this->k, $this->escape($txt));
    }

    protected function buildPdf(): string
    {
        $objects = [];
        $objects[] = '<< /Type /Catalog /Pages 2 0 R >>';
        $kids = [];
        $pageCount = count($this->pages);
        $imageCount = count(array_filter($this->images));
        $firstPageObj = 5 + $imageCount;
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = ($firstPageObj + ($i * 2)) . ' 0 R';
        }
        $objects[] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $pageCount . ' >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $imageNames = [];
        $idx = 1;
        foreach ($this->images as $key => $image) {
            if (!$image) {
                continue;
            }
            $imageNames[$key] = ['name' => $image['name'], 'obj' => count($objects) + 1];
            $objects[] = "<< /Type /XObject /Subtype /Image /Width {$image['w']} /Height {$image['h']} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($image['data']) . " >>\nstream\n" . $image['data'] . "\nendstream";
            $idx++;
        }

        foreach ($this->pages as $content) {
            $resources = '<< /Font << /F1 3 0 R /F2 4 0 R >>';
            if ($imageNames) {
                $resources .= ' /XObject <<';
                foreach ($imageNames as $img) {
                    $resources .= ' /' . $img['name'] . ' ' . $img['obj'] . ' 0 R';
                }
                $resources .= ' >>';
            }
            $resources .= ' >>';
            $contentText = implode("\n", $content);
            if ($this->aliasNbPages) {
                $contentText = str_replace('{nb}', (string)$pageCount, $contentText);
            }
            $contentObj = count($objects) + 2;
            $objects[] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources %s /Contents %d 0 R >>', $this->w * $this->k, $this->h * $this->k, $resources, $contentObj);
            $objects[] = "<< /Length " . strlen($contentText) . " >>\nstream\n" . $contentText . "\nendstream";
        }

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $i => $obj) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
        return $pdf;
    }

    protected function loadImage(string $file, string $type): ?array
    {
        $info = @getimagesize($file);
        if (!$info) {
            return null;
        }
        $mime = $info['mime'] ?? '';
        if ($mime === 'image/jpeg') {
            return ['w' => $info[0], 'h' => $info[1], 'data' => file_get_contents($file)];
        }
        if ($mime === 'image/png' && function_exists('imagecreatefrompng')) {
            $im = @imagecreatefrompng($file);
            if (!$im) {
                return null;
            }
            $bg = imagecreatetruecolor(imagesx($im), imagesy($im));
            $white = imagecolorallocate($bg, 255, 255, 255);
            imagefill($bg, 0, 0, $white);
            imagecopy($bg, $im, 0, 0, 0, 0, imagesx($im), imagesy($im));
            ob_start();
            imagejpeg($bg, null, 90);
            $data = ob_get_clean();
            imagedestroy($im);
            imagedestroy($bg);
            return ['w' => $info[0], 'h' => $info[1], 'data' => $data];
        }
        return null;
    }

    protected function formatSize($format): array
    {
        if (is_array($format)) {
            return [(float)$format[0], (float)$format[1]];
        }
        return strtoupper((string)$format) === 'LETTER' ? [215.9, 279.4] : [210, 297];
    }

    protected function colorArray($r, $g, $b): array
    {
        if ($g === null || $b === null) {
            $g = $b = $r;
        }
        return [(int)$r, (int)$g, (int)$b];
    }

    protected function colorCmd(array $color, string $op): string
    {
        return sprintf('%.3F %.3F %.3F %s', $color[0] / 255, $color[1] / 255, $color[2] / 255, $op);
    }

    protected function textColorCmd(): string { return $this->colorCmd($this->textColor, 'rg'); }
    protected function drawColorCmd(): string { return $this->colorCmd($this->drawColor, 'RG'); }
    protected function fillColorCmd(): string { return $this->colorCmd($this->fillColor, 'rg'); }

    protected function escape(string $txt): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $txt);
    }
}
