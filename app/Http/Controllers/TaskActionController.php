<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\ProductionTask;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
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

        $service = app(WorkOrderService::class);
 
        if ($action === 'start') {
            $task->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);
 
            $wo = $task->orderItem ? \App\Models\WorkOrder::withoutGlobalScopes()
                ->whereIn('order_item_id', $task->orderItem->getItemsInGroup()->pluck('id'))
                ->first() : null;
 
            if ($wo) {
                if (!$wo->started_at) {
                    $wo->update(['started_at' => now()]);
                }
                // Jika ini QC_PERSIAPAN, pastikan status WO diset ke QC_PREP
                if ($task->stage_name === 'QC_PERSIAPAN' && $wo->status !== \App\Models\WorkOrder::STATUS_QC_PREP) {
                    $wo->update([
                        'status' => \App\Models\WorkOrder::STATUS_QC_PREP,
                        'stage_entered_at' => now(),
                    ]);
                }
            }
        } elseif ($action === 'done') {
            $task->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);
 
            // Jika QC_PERSIAPAN selesai, advance WO ke status pertama
            if ($task->stage_name === 'QC_PERSIAPAN') {
                $woId = $this->getWorkOrderId($orderItem);
                $service->advance($woId, true); // fromQcApproval = true
            } else {
                $woId = $this->getWorkOrderId($orderItem);
                $service->advance($woId);
            }
        } elseif ($action === 'approve') {
            $service->approveTask($task->id);
        } elseif ($action === 'reject') {
            $reason = $request->query('reason', 'Revisi dari Admin');
            $service->rejectTask($task->id, $reason);
        } else {
            abort(400, 'Aksi tidak dikenal.');
        }

        // Sync Order status berdasarkan WorkOrder status
        $this->syncOrderStatus($orderItem);

        // Redirect kembali ke halaman Control Produksi (tenant-aware)
        $order = $orderItem->order;
        $shop = $order?->shop;
        $tenantId = $shop?->id ?? 1;
        return redirect()
            ->to("/app/{$tenantId}/control-produksi")
            ->with('status', 'Status tugas berhasil diperbarui.');
    }

    /**
     * Handle WorkOrder-level actions (approve QC, advance dari QC_PREP).
     */
    public function handleWoAction(Request $request, string $action, int $item)
    {
        $orderItem = OrderItem::findOrFail($item);
        $service = app(WorkOrderService::class);
        $woId = $this->getWorkOrderId($orderItem);

        match ($action) {
            'approve_qc' => $service->approveQC($woId),
            'advance' => $service->advance($woId),
            default => abort(400, 'Aksi tidak dikenal.'),
        };

        $this->syncOrderStatus($orderItem);

        $order = $orderItem->order;
        $shop = $order?->shop;
        $tenantId = $shop?->id ?? 1;
        return redirect()
            ->to("/app/{$tenantId}/control-produksi")
            ->with('status', 'Status Work Order berhasil diperbarui.');
    }

    /**
     * Cari WorkOrder ID dari OrderItem.
     */
    protected function getWorkOrderId(OrderItem $orderItem): int
    {
        $groupItemIds = $orderItem->getItemsInGroup()->pluck('id');
        $wo = WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->first();

        if (!$wo) {
            abort(404, 'Work Order tidak ditemukan.');
        }

        return $wo->id;
    }

    /**
     * Sync Order status berdasarkan status semua WO terkait.
     */
    protected function syncOrderStatus(OrderItem $orderItem): void
    {
        $order = $orderItem->order;
        if (!$order) return;

        $groupItemIds = $order->orderItems()->pluck('id');
        $wos = WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->get();

        if ($wos->isEmpty()) return;

        $allCompleted = $wos->every(fn($wo) => $wo->isCompleted());
        $anyInProgress = $wos->some(fn($wo) => !$wo->isCompleted() && $wo->status !== WorkOrder::STATUS_CREATED);

        $newStatus = 'antrian';
        if ($allCompleted) {
            $newStatus = 'siap_diambil';
        } elseif ($anyInProgress) {
            $newStatus = 'diproses';
        }

        if ($order->status !== $newStatus) {
            $oldStatus = $order->status;
            $order->update(['status' => $newStatus]);

            // Notifikasi admin kalau produksi selesai
            if ($newStatus === 'siap_diambil' && $oldStatus !== 'siap_diambil') {
                $recipients = \App\Models\User::where('shop_id', $order->shop_id)
                    ->whereIn('role', ['owner', 'admin'])
                    ->get();

                Notification::make()
                    ->title('Produksi Selesai & Siap Diambil!')
                    ->body("Pesanan **{$order->order_number}** atas nama **{$order->customer->name}** telah selesai diproduksi dan siap diambil.")
                    ->success()
                    ->icon('heroicon-o-check-badge')
                    ->iconColor('success')
                    ->actions([
                        \Filament\Actions\Action::make('lihat')
                            ->label('Lihat Pesanan')
                            ->url(\App\Filament\Resources\Orders\OrderResource::getUrl('edit', ['record' => $order->id, 'tenant' => $order->shop_id])),
                    ])
                    ->sendToDatabase($recipients);
            }
        }
    }
}
