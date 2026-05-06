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

        // DEBUG: Return view directly to check if data is ok
        return view('pdf.worker-slip', compact('payroll'));
        
        // $pdf = Pdf::loadView('pdf.worker-slip', compact('payroll'));
        // $pdf->setPaper('a5', 'portrait');
        // return $pdf->stream("slip-upah-{$payroll->id}.pdf");
    }
}
