<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use Illuminate\Http\Request;

class WorkTaskController extends Controller
{
    public function index(Request $request)
    {
        $woId  = $request->query('wo_id');
        $token = $request->query('token');

        // Validasi parameter wajib
        if (!$woId || !$token) {
            abort(403, 'Link tidak valid. Parameter wo_id dan token diperlukan.');
        }

        // Verifikasi token — tanpa scope (token bersifat global unik)
        $wo = WorkOrder::withoutGlobalScopes()
            ->with([
                'orderItem.order.customer',
                'orderItem.order.shop',
                'shop',
                'cuttingWorker',
                'sewingWorker',
                'decorationWorker',
                'buttonWorker',
                'finishingWorker',
            ])
            ->where('id', $woId)
            ->where('token', $token)
            ->first();

        if (!$wo) {
            abort(404, 'Work Order tidak ditemukan atau token tidak valid.');
        }

        // Render halaman Livewire, passing wo_id & token
        return view('work-task', [
            'woId'  => $wo->id,
            'token' => $token,
        ]);
    }
}
