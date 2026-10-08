<?php

namespace App\Services;

use Dompdf\{Dompdf, Options};

class SalesReportExportService
{
    public function pdf(string $view, array $data, string $filename)
    {
        $options = new Options(['isRemoteEnabled'=>false, 'isPhpEnabled'=>false, 'isJavascriptEnabled'=>false,
            'defaultFont'=>'DejaVu Sans', 'tempDir'=>storage_path('app'), 'fontCache'=>storage_path('app')]);
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view($view,$data+['pdf'=>true])->render(),'UTF-8');
        $pdf->setPaper('A4','portrait');
        $pdf->render();
        $pdf->getCanvas()->page_text(460,810,'Page {PAGE_NUM} of {PAGE_COUNT}',$pdf->getFontMetrics()->getFont('DejaVu Sans'),8,[0.4,0.4,0.4]);
        return response($pdf->output(),200,['Content-Type'=>'application/pdf',
            'Content-Disposition'=>'attachment; filename="'.$filename.'"', 'Cache-Control'=>'private, no-store']);
    }

    public function csvText(string $value): string
    {
        return preg_match('/^[\s]*[=+@\-\t\r\n]/u',$value) ? "'".$value : $value;
    }
}
