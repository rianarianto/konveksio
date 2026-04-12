<?php

namespace App\Http\Controllers;

use App\Models\WorkerPayroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function print(WorkerPayroll $payroll)
    {
        // Security check (handled by multi-tenancy scope usually, but safe to check here)
        if ($payroll->shop_id !== filament()->getTenant()?->id) {
            abort(403);
        }

        $pdf = Pdf::loadView('pdf.worker-slip', compact('payroll'));
        
        // A5 format is 148 x 210 mm
        $pdf->setPaper('a5', 'portrait');

        return $pdf->stream("slip-upah-{$payroll->id}.pdf");
    }
}
