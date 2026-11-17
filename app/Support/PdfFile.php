<?php

namespace App\Support;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

class PdfFile
{
    public static function download(string $html, string $filename, string $orientation = 'portrait'): Response
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', $orientation === 'landscape' ? 'landscape' : 'portrait');
        $pdf->render();

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'bill';
        if (! str_ends_with(strtolower($name), '.pdf')) {
            $name .= '.pdf';
        }

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }
}
