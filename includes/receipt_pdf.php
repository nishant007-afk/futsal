<?php

/**
 * Minimal dependency-free PDF generator for payment receipts.
 * Produces a single A4 page of text using the standard Helvetica fonts.
 */

class ReceiptPdf
{
    private string $buf = '';
    private int $n = 0;
    private array $offsets = [];

    private const W = 595.28; // A4 width pt
    private const H = 841.89; // A4 height pt

    public function __construct()
    {
        $this->buf = "%PDF-1.4\n";
    }

    private function obj(int $id, string $body): void
    {
        $this->n = max($this->n, $id);
        $this->offsets[$id] = strlen($this->buf);
        $this->buf .= $id . " 0 obj\n" . $body . "\nendobj\n";
    }

    private function esc(?string $s): string
    {
        $s = (string)$s;
        $s = str_replace("\\", "\\\\", $s);
        $s = str_replace("(", "\\(", $s);
        $s = str_replace(")", "\\)", $s);
        return $s;
    }

    /**
     * Build the content stream from rows of [text, x, y, size, weight, color].
     * weight: 'b' bold, 'n' normal. color: hex like '16A35A'.
     */
    public function content(array $rows): void
    {
        $ops = '';
        foreach ($rows as $r) {
            [$text, $x, $y, $size, $weight, $color] = array_pad($r, 6, null);
            $color = $color === null ? '111111' : $color;
            $weight = $weight === null ? 'n' : $weight;
            $r2 = (int)hexdec(substr($color, 0, 2)) / 255;
            $g2 = (int)hexdec(substr($color, 2, 2)) / 255;
            $b2 = (int)hexdec(substr($color, 4, 2)) / 255;
            $font = $weight === 'b' ? 'F1' : 'F2';
            $ops .= "BT /{$font} {$size} Tf {$r2} {$g2} {$b2} rg {$x} {$y} Td (" . $this->esc($text) . ") Tj ET\n";
        }
        $stream = $ops;
        $this->obj(5, "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream");
    }

    public function output(): string
    {
        $this->obj(1, "<< /Type /Catalog /Pages 2 0 R >>");
        $this->obj(2, "<< /Type /Pages /Kids [3 0 R] /Count 1 >>");
        $this->obj(3, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 " . self::W . " " . self::H . "] /Resources 4 0 R /Contents 5 0 R >>");
        $this->obj(4, "<< /Font << /F1 6 0 R /F2 7 0 R >> >>");
        $this->obj(6, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>");
        $this->obj(7, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>");

        $xref = strlen($this->buf);
        $this->buf .= "xref\n0 " . ($this->n + 1) . "\n";
        $this->buf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $this->n; $i++) {
            $this->buf .= sprintf("%010d 00000 n \n", $this->offsets[$i] ?? 0);
        }
        $this->buf .= "trailer\n<< /Size " . ($this->n + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";
        return $this->buf;
    }
}
