<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\ProductionTask;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;

class TaskActionController extends Controller
{
    public function handle(Request $request, ProductionTask $task, string $action, int $item)
    {
        // Security: ensure the task belongs to the right shop/item
        $orderItem = OrderItem::findOrFail($item);

        if ($task->order_item_id !== $orderItem->id) {
            abort(403, 'Aksi tidak diizinkan.');
        }

        match ($action) {
            'start' => $task->update(['status' => 'in_progress']),
            'done' => $task->update(['status' => 'done']),
            default => abort(400, 'Aksi tidak dikenal.'),
        };

        // Sinkronisasi status order
        $order = $orderItem->order;
        if ($order) {
            $allTasks = $order->orderItems()->with('productionTasks')->get()
                ->flatMap(fn($i) => $i->productionTasks);

            $total = $allTasks->count();
            $done = $allTasks->where('status', 'done')->count();
            $inProgress = $allTasks->where('status', 'in_progress')->count();

            if ($total > 0) {
                if ($done === $total) {
                    $newStatus = 'selesai';
                } elseif ($inProgress > 0 || $done > 0) {
                    $newStatus = 'diproses';
                } else {
                    $newStatus = 'antrian';
                }
                if ($order->status !== $newStatus) {
                    $order->update(['status' => $newStatus]);
                }
            }
        }

        // Redirect kembali ke halaman Control Produksi (tenant-aware)
        $shop = $order?->shop;
        $tenantSlug = $shop?->id ?? 1;
        return redirect()
            ->to("/admin/{$tenantSlug}/control-produksis")
            ->with('status', 'Status tugas berhasil diperbarui.');
    }
}
