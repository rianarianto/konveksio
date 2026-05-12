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
            'done'  => $task->update(['status' => 'done', 'completed_at' => now()]),
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
                    $oldStatus = $order->status;
                    $order->update(['status' => $newStatus]);

                    // Jika baru saja jadi 'selesai', kirim notifikasi ke Admin/Owner
                    if ($newStatus === 'selesai' && $oldStatus !== 'selesai') {
                        $recipients = \App\Models\User::where('shop_id', $order->shop_id)
                            ->whereIn('role', ['owner', 'admin'])
                            ->get();

                        Notification::make()
                            ->title('Produksi Selesai! 🏁')
                            ->body("Pesanan **{$order->order_number}** atas nama **{$order->customer->name}** telah selesai diproduksi dan siap diproses lebih lanjut.")
                            ->success()
                            ->icon('heroicon-o-check-badge')
                            ->iconColor('success')
                            ->actions([
                                \Filament\Actions\Action::make('lihat')
                                    ->label('Lihat Pesanan')
                                    ->url(\App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $order->id])),
                            ])
                            ->sendToDatabase($recipients);
                    }
                }
            }
        }

        // Redirect kembali ke halaman Control Produksi (tenant-aware)
        $shop = $order?->shop;
        $tenantId = $shop?->id ?? 1;
        return redirect()
            ->to("/app/{$tenantId}/control-produksi")
            ->with('status', 'Status tugas berhasil diperbarui.');
    }
}
