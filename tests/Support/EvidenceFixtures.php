<?php

namespace Tests\Support;

use FPDF;

class EvidenceFixtures
{
    public static function pdfBytes(string $text = 'Synthetic supporting document'): string
    {
        $pdf = new FPDF;
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(0, 10, $text);

        return $pdf->Output('S');
    }

    public static function signatureBytes(string $ink = 'synthetic'): string
    {
        $image = imagecreatetruecolor(64, 24);
        $hash = hash('sha256', $ink);
        $color = imagecolorallocate($image, hexdec(substr($hash, 0, 2)), hexdec(substr($hash, 2, 2)), hexdec(substr($hash, 4, 2)));
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imageline($image, 2, 18, 60, 4, $color);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
