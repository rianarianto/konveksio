<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;


class PDFController extends Controller
{
    public function downloadReceipt(Order $order)
    {
        // Load relationships needed for the receipt
        $order->load(['customer', 'shop', 'orderItems', 'payments']);

        $pdf = app('dompdf.wrapper')->loadView('pdf.receipt', [
            'order' => $order,
        ]);

        $pdfOutput = $pdf->output();
        $base64 = base64_encode($pdfOutput);
        
        return response()->make(
            '<html><head><title>Kuitansi '.$order->order_number.'</title></head><body style="margin:0;padding:0;"><iframe src="data:application/pdf;base64,'.$base64.'" width="100%" height="100%" style="border:none;"></iframe></body></html>',
            200,
            ['Content-Type' => 'text/html']
        );
    }
}
