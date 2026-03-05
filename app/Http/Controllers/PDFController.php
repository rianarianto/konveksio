<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PDFController extends Controller
{
    public function downloadReceipt(Order $order)
    {
        // Load relationships needed for the receipt
        $order->load(['customer', 'shop', 'orderItems', 'payments']);

        $pdf = Pdf::loadView('pdf.receipt', [
            'order' => $order,
        ]);

        $filename = 'Kuitansi-' . str_replace('#', '', $order->order_number) . '.pdf';

        return $pdf->stream($filename);
    }
}
