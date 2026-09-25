<?php

/**
 * PDF helper
 */
function create_pdf(string $html, string $filename = ''): string
{
    // Configure Dompdf with DejaVu Sans to support Indian Rupee (₹) and UTF-8 characters
    $options = new Dompdf\Options();
    $options->setIsRemoteEnabled(true);
    $options->setIsPhpEnabled(false);
    $options->setDefaultFont('DejaVu Sans');

    $dompdf = new Dompdf\Dompdf($options);
    
    // Ensure UTF-8 meta tag exists if missing
    if (stripos($html, 'charset') === false) {
        $html = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>' . $html;
    }

    $dompdf->loadHtml(str_replace(['\n', '\r'], '', $html));
    $dompdf->render();

    if ($filename != '') {
        $dompdf->stream($filename . '.pdf');
    } else {
        return $dompdf->output();
    }

    return '';
}