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
        
        // A5 format is 148 x 210 mm
        $pdf->setPaper('a5', 'portrait');

        return $pdf->stream("slip-upah-{$payroll->id}.pdf");
    }
}
