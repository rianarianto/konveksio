<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;


class PDFController extends Controller
{
    public function downloadReceipt(Order $order, Request $request)
    {
        // Load relationships needed for the receipt
        $order->load(['customer', 'shop', 'orderItems', 'payments']);

        $pdf = app('dompdf.wrapper')->loadView('pdf.receipt', [
            'order' => $order,
        ]);

        $filename = 'Kuitansi-' . str_replace('#', '', $order->order_number) . '.pdf';

        if ($request->query('download') == 1) {
            return $pdf->download($filename);
        }

        return $pdf->stream($filename);
    }
}
