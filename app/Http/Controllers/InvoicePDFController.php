<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoicePDFController extends Controller
{
    public function generatePDF(Invoice $invoice)
    {
        $invoice->load(['customer', 'driver', 'items.shipment', 'payments']);

        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'default_font' => 'cairo'
        ]);

        $html = view('invoices.pdf', compact('invoice'))->render();

        $mpdf->WriteHTML($html);

        return $mpdf->Output("invoice_{$invoice->invoice_number}.pdf", 'D');
    }
}
