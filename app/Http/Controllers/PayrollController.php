<?php

namespace App\Http\Controllers;

use App\Models\WorkerPayroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function print(WorkerPayroll $payroll)
    {
        // Security check
        $user = auth()->user();
        if ($user->role !== 'owner' && $payroll->shop_id !== $user->shop_id) {
            abort(403);
        }

        $pdf = Pdf::loadView('pdf.worker-slip', compact('payroll'));
        $pdf->setPaper('a4', 'portrait');

        $pdfOutput = $pdf->output();
        $base64 = base64_encode($pdfOutput);
        
        return response()->make(
            '<html><head><title>Slip Upah '.$payroll->worker->name.'</title></head><body style="margin:0;padding:0;"><iframe src="data:application/pdf;base64,'.$base64.'" width="100%" height="100%" style="border:none;"></iframe></body></html>',
            200,
            ['Content-Type' => 'text/html']
        );
    }
}
