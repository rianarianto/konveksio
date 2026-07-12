<?php

namespace App\Helpers;

use App\Models\WorkOrder;
use App\Models\Worker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationHelper
{
    /**
     * Format nama tahap jadi label yang rapi untuk pesan WA.
     */
    protected static function formatStageLabel(string $stage): string
    {
        $map = [
            'QC_PERSIAPAN' => 'QC Persiapan',
            'QC_PREP'      => 'QC Persiapan',
            'QC_REVIEW'    => 'QC Review',
            'CREATED'      => 'Dibuat',
            'COMPLETED'    => 'Selesai',
        ];
        return $map[strtoupper($stage)] ?? str_replace('_', ' ', $stage);
    }

    /**
     * Kirim notifikasi WhatsApp ke pekerja terkait setelah perubahan status WO.
     */
    public static function notify(
        WorkOrder $wo,
        string    $newStatus,
        bool      $isReject = false,
        string    $rejectReason = ''
    ): void {
        // Load relasi yang dibutuhkan
        $wo->loadMissing('orderItem.order.customer', 'shop');

        // Tentukan tahap yang sedang aktif (untuk cari ProductionTask)
        $stageName = match (true) {
            $newStatus === WorkOrder::STATUS_QC_PREP   => 'QC_PERSIAPAN',
            $newStatus === WorkOrder::STATUS_QC_REVIEW => $wo->current_review_stage ?? $newStatus,
            default                                    => $newStatus,
        };

        // Ambil semua task untuk WO ini di tahap tersebut
        $groupItemIds = $wo->orderItem
            ? $wo->orderItem->getItemsInGroup()->pluck('id')
            : [$wo->order_item_id];

        $groupItems = \App\Models\OrderItem::whereIn('id', $groupItemIds)->get();
        $totalQty   = $groupItems->sum('quantity');

        $tasks = \App\Models\ProductionTask::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->where('stage_name', $stageName)
            ->with('assignedTo')
            ->get();

        // Kumpulkan worker unik yang ditugaskan di tahap ini
        $workers = $tasks->map(fn($t) => $t->assignedTo)->filter()->unique('id');

        if ($workers->isEmpty()) {
            Log::info("[WA-Bot] Tidak ada pekerja untuk WO {$wo->wo_number} → Status: {$newStatus}");
            // Lanjut cek QC_REVIEW notification di bawah
        }

        // Label tahap yang rapi
        $label = match (true) {
            $newStatus === WorkOrder::STATUS_QC_PREP   => 'QC Persiapan',
            $newStatus === WorkOrder::STATUS_QC_REVIEW => 'QC ' . self::formatStageLabel($wo->current_review_stage ?? ''),
            default                                    => self::formatStageLabel($newStatus),
        };

        $produk   = $wo->orderItem->product_name ?? '-';
        $customer = $wo->orderItem->order->customer->name ?? '-';
        $deadline = $wo->orderItem->order->deadline
            ? \Carbon\Carbon::parse($wo->orderItem->order->deadline)->format('d M Y')
            : '-';

        // Info tahap sebelumnya (hanya tampil jika bukan tahap pertama / bukan CREATED)
        $prevStageInfo = '';
        $prevTask = null;
        $prevStageCode = $wo->getPreviousStatus();
        if ($prevStageCode && !in_array($prevStageCode, [WorkOrder::STATUS_CREATED, $newStatus])) {
            $prevLabel = match (true) {
                $prevStageCode === WorkOrder::STATUS_QC_PREP   => 'QC Persiapan',
                $prevStageCode === WorkOrder::STATUS_QC_REVIEW => 'QC Review',
                default                                        => self::formatStageLabel($prevStageCode),
            };

            $prevStageNameForQuery = match (true) {
                $prevStageCode === WorkOrder::STATUS_QC_PREP   => 'QC_PERSIAPAN',
                $prevStageCode === WorkOrder::STATUS_QC_REVIEW => $wo->current_review_stage,
                default                                        => $prevStageCode,
            };

            $prevTask = \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItemIds)
                ->where('stage_name', $prevStageNameForQuery)
                ->with('assignedTo')
                ->first();

            $prevWorkerName = $prevTask?->assignedTo?->name;
            if (!$prevWorkerName && in_array($prevStageCode, [WorkOrder::STATUS_QC_PREP, WorkOrder::STATUS_QC_REVIEW])) {
                $prevWorkerName = $wo->qc_worker_id
                    ? \App\Models\Worker::find($wo->qc_worker_id)?->name
                    : null;
            }

            if ($prevWorkerName) {
                $prevStageInfo = "\n✅ *Diselesaikan:* Tahap *{$prevLabel}* oleh *{$prevWorkerName}*";
            } else {
                $prevStageInfo = "\n✅ *Diselesaikan:* Tahap *{$prevLabel}*";
            }
        }

        // Rincian ukuran dari task worker ini
        $rincianUkuran = '';
        $firstTask = $tasks->first();
        if ($firstTask && !empty($firstTask->size_quantities)) {
            $parts = [];
            foreach ($firstTask->size_quantities as $sz => $qty) {
                if (!str_starts_with($sz, '_') && $qty > 0) {
                    $parts[] = "{$sz}: {$qty}";
                }
            }
            if (!empty($parts)) {
                $rincianUkuran = "\n• Rincian: " . implode(', ', $parts);
            }
        }

        // Dapatkan ID worker tahap sebelumnya
        $prevWorkerId = null;
        if (isset($prevStageCode) && $prevStageCode) {
            $prevWorkerId = $prevTask?->assigned_to;
            if (!$prevWorkerId && in_array($prevStageCode, [WorkOrder::STATUS_QC_PREP, WorkOrder::STATUS_QC_REVIEW])) {
                $prevWorkerId = $wo->qc_worker_id;
            }
        }

        // Kirim notifikasi ke tiap worker (kecuali jika WO masuk tahap QC_REVIEW atau QC_AKHIR, karena itu ditangani khusus ke Petugas QC di bawah)
        if ($newStatus !== WorkOrder::STATUS_QC_REVIEW && $newStatus !== WorkOrder::STATUS_QC_AKHIR) {
            foreach ($workers as $worker) {
                if (!$worker->phone) continue;

                // Jika worker tahap baru sama dengan worker tahap sebelumnya, lewati (karena dia yang menyelesaikan)
                if ($prevWorkerId && $worker->id === $prevWorkerId) {
                    Log::info("[WA-Bot] Lewati notifikasi ke {$worker->name} karena dia yang menyelesaikan tahap sebelumnya.");
                    continue;
                }

                $phone = $worker->phone;
                $link  = url("/tugas?wo_id={$wo->id}&token={$wo->token}&wt={$worker->portal_token}");

                // Qty untuk worker ini (dari task-nya)
                $workerTask = $tasks->firstWhere('assigned_to', $worker->id);
                $workerQty  = $workerTask?->quantity ?? $totalQty;

                if ($isReject) {
                    $pesan = "⚠️ *REVISI QC — {$wo->wo_number}*\n\n"
                        . "Halo {$worker->name},\n"
                        . "Hasil kerja Anda pada tahap *{$label}* perlu diperbaiki.\n\n"
                        . "📋 *Detail:*\n"
                        . "• Produk: {$produk}\n"
                        . "• Pelanggan: {$customer}\n"
                        . "• Total Qty: {$totalQty} pcs (bagian Anda: {$workerQty} pcs){$rincianUkuran}\n"
                        . "• Deadline: {$deadline}\n"
                        . "• Alasan: {$rejectReason}\n\n"
                        . "Silakan perbaiki dan selesaikan kembali.\n"
                        . "🔗 {$link}";
                } else {
                    $pesan = "🔥 *TUGAS SIAP DIKERJAKAN — {$wo->wo_number}*\n\n"
                        . "Halo {$worker->name},\n"
                        . "Tugas Anda untuk tahap *{$label}* kini siap dikerjakan.{$prevStageInfo}\n\n"
                        . "📋 *Detail:*\n"
                        . "• Produk: {$produk}\n"
                        . "• Pelanggan: {$customer}\n"
                        . "• Total Qty: {$totalQty} pcs (bagian Anda: {$workerQty} pcs){$rincianUkuran}\n"
                        . "• Deadline: {$deadline}\n\n"
                        . "Buka portal tugas Anda:\n"
                        . "🔗 {$link}";
                }

                try {
                    Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5001') . '/api', [
                        'nohp'  => $phone,
                        'pesan' => $pesan,
                    ]);
                } catch (\Exception $e) {
                    Log::error('[WA-Bot] Gagal mengirim pesan: ' . $e->getMessage());
                }
            }
        }

        // Notifikasi khusus ke QC Worker saat WO masuk QC_REVIEW
        if ($newStatus === WorkOrder::STATUS_QC_REVIEW && !$isReject) {
            $qcWorker = $wo->qc_worker_id ? Worker::find($wo->qc_worker_id) : null;

            if ($qcWorker && $qcWorker->phone) {
                $reviewStageLabel = self::formatStageLabel($wo->current_review_stage ?? 'produksi');
                $qcLink = $qcWorker->portal_token
                    ? url("/worker/{$qcWorker->portal_token}")
                    : url("/tugas?wo_id={$wo->id}&token={$wo->token}&wt={$qcWorker->portal_token}");

                $qcPesan = "🔍 *PERLU REVIEW QC — {$wo->wo_number}*\n\n"
                    . "Halo {$qcWorker->name},\n"
                    . "Tahap *{$reviewStageLabel}* telah selesai dikerjakan dan menunggu verifikasi QC Anda.\n\n"
                    . "📋 *Detail:*\n"
                    . "• Produk: {$produk}\n"
                    . "• Pelanggan: {$customer}\n"
                    . "• Total Qty: {$totalQty} pcs\n"
                    . "• Deadline: {$deadline}\n\n"
                    . "Silakan buka portal untuk melakukan verifikasi:\n"
                    . "🔗 {$qcLink}";

                try {
                    Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5001') . '/api', [
                        'nohp'  => $qcWorker->phone,
                        'pesan' => $qcPesan,
                    ]);
                } catch (\Exception $e) {
                    Log::error('[WA-Bot] Gagal kirim notif QC review: ' . $e->getMessage());
                }
            } else {
                Log::info("[WA-Bot] QC Review: tidak ada QC worker atau nomor HP untuk WO {$wo->wo_number}");
            }
        }

        // Notifikasi khusus ke QC Worker saat WO masuk QC_AKHIR
        if ($newStatus === WorkOrder::STATUS_QC_AKHIR && !$isReject) {
            $qcWorker = $wo->qc_worker_id ? Worker::find($wo->qc_worker_id) : null;

            if ($qcWorker && $qcWorker->phone) {
                $qcLink = url("/worker/{$qcWorker->portal_token}");

                $qcPesan = "🏁 *QC AKHIR PERLU DILAKUKAN — {$wo->wo_number}*\n\n"
                    . "Halo {$qcWorker->name},\n"
                    . "Semua tahap produksi telah selesai. Silakan lakukan *Verifikasi QC Akhir* sebelum pesanan diserahkan ke admin.\n\n"
                    . "📋 *Detail:*\n"
                    . "• Produk: {$produk}\n"
                    . "• Pelanggan: {$customer}\n"
                    . "• Total Qty: {$totalQty} pcs\n"
                    . "• Deadline: {$deadline}\n\n"
                    . "Buka portal QC Akhir:\n"
                    . "🔗 {$qcLink}";

                try {
                    Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5001') . '/api', [
                        'nohp'  => $qcWorker->phone,
                        'pesan' => $qcPesan,
                    ]);
                } catch (\Exception $e) {
                    Log::error('[WA-Bot] Gagal kirim notif QC Akhir: ' . $e->getMessage());
                }
            }
        }

        // Notifikasi khusus ke Admin/Owner saat WO selesai (STATUS_COMPLETED)
        if ($newStatus === WorkOrder::STATUS_COMPLETED && !$isReject) {
            // 1. WhatsApp notification to Admin (from Worker settings with name containing 'admin')
            $adminWorker = \App\Models\Worker::withoutGlobalScopes()
                ->where('shop_id', $wo->shop_id)
                ->where('name', 'like', '%admin%')
                ->where('is_active', true)
                ->first();

            if ($adminWorker && $adminWorker->phone) {
                $pesanAdmin = "✅ *WORK ORDER SELESAI — {$wo->wo_number}*\n\n"
                    . "Halo Admin ({$adminWorker->name}),\n"
                    . "Work Order *{$wo->wo_number}* telah selesai diproduksi. Seluruh tahap QC telah disetujui.\n\n"
                    . "📋 *Detail:*\n"
                    . "• Produk: {$produk}\n"
                    . "• Pelanggan: {$customer}\n"
                    . "• Total Qty: {$totalQty} pcs\n"
                    . "• Deadline: {$deadline}\n\n"
                    . "Silakan cek di dashboard admin.";

                try {
                    Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5001') . '/api', [
                        'nohp'  => $adminWorker->phone,
                        'pesan' => $pesanAdmin,
                    ]);
                } catch (\Exception $e) {
                    Log::error('[WA-Bot] Gagal kirim notif WO Selesai ke Admin WA: ' . $e->getMessage());
                }
            }

            // 2. WhatsApp notification to Owner (from Shop settings)
            $shop = $wo->shop;
            if ($shop && $shop->phone) {
                $pesanOwner = "✅ *WORK ORDER SELESAI — {$wo->wo_number}*\n\n"
                    . "Halo Owner,\n"
                    . "Work Order *{$wo->wo_number}* telah selesai diproduksi. Seluruh tahap QC telah disetujui.\n\n"
                    . "📋 *Detail:*\n"
                    . "• Produk: {$produk}\n"
                    . "• Pelanggan: {$customer}\n"
                    . "• Total Qty: {$totalQty} pcs\n"
                    . "• Deadline: {$deadline}\n\n"
                    . "Silakan pantau perkembangan order di sistem.";

                try {
                    Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5001') . '/api', [
                        'nohp'  => $shop->phone,
                        'pesan' => $pesanOwner,
                    ]);
                } catch (\Exception $e) {
                    Log::error('[WA-Bot] Gagal kirim notif WO Selesai ke Owner WA: ' . $e->getMessage());
                }
            }

            // 3. Database/System notification to admin & owner users in this shop
            $recipients = \App\Models\User::where(function ($q) use ($wo) {
                    $q->where('shop_id', $wo->shop_id)
                      ->orWhere('role', 'owner');
                })
                ->whereIn('role', ['owner', 'admin'])
                ->get();

            if ($recipients->isNotEmpty()) {
                try {
                    \Filament\Notifications\Notification::make()
                        ->title('Work Order Selesai!')
                        ->body("Work Order **{$wo->wo_number}** untuk produk **{$produk}** telah selesai.")
                        ->success()
                        ->icon('heroicon-o-check-badge')
                        ->iconColor('success')
                        ->sendToDatabase($recipients);
                } catch (\Exception $e) {
                    Log::error('[Notification] Gagal kirim database notification: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Kirim notifikasi WhatsApp ke SEMUA pekerja yang ditugaskan pada suatu OrderItem.
     * Dipanggil saat Admin menekan tombol "Kirim Tugas" di Control Produksi.
     */
    public static function notifyAllWorkers(\App\Models\OrderItem $item): void
    {
        $item->loadMissing(['order.customer', 'order.shop']);

        $produk   = $item->product_name ?? '-';
        $customer = $item->order?->customer?->name ?? '-';
        $deadline = $item->order?->deadline ? \Carbon\Carbon::parse($item->order->deadline)->format('d M Y') : '-';

        // Hitung total qty seluruh group (untuk ditampilkan di QC_PERSIAPAN)
        $groupItemIds = $item->getItemsInGroup()->pluck('id');
        $totalGroupQty = \App\Models\OrderItem::whereIn('id', $groupItemIds)->sum('quantity');

        // Ambil semua production tasks dari seluruh group (QC_PERSIAPAN bisa di item lain dalam group)
        $tasks = \App\Models\ProductionTask::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->with('assignedTo')
            ->get();

        // Grup per pekerja (satu pekerja bisa punya lebih dari 1 tahap)
        $workerTasks = $tasks->groupBy('assigned_to');

        foreach ($workerTasks as $workerId => $workerTaskList) {
            $worker = $workerTaskList->first()?->assignedTo;
            if (!$worker || !$worker->phone) {
                Log::info("[WA-Bot] Kirim Tugas: pekerja ID {$workerId} tidak punya nomor HP.");
                continue;
            }

            // Link ke dashboard worker (list semua tugas)
            $link = $worker->portal_token
                ? url("/worker/{$worker->portal_token}")
                : url('/');

            // Rincian tugas per tahap
            // QC_PERSIAPAN: tampilkan total qty group (bukan qty task individual yg hanya 1 item)
            $rincian = '';
            foreach ($workerTaskList as $task) {
                $displayQty = ($task->stage_name === 'QC_PERSIAPAN')
                    ? $totalGroupQty
                    : $task->quantity;
                $rincian .= "• {$task->stage_name}: {$displayQty} pcs — Rp " . number_format($task->wage_amount, 0, ',', '.') . "\n";
            }

            $pesan = "📋 *PENUGASAN PRODUKSI — {$produk}*\n\n"
                . "Halo {$worker->name},\n"
                . "Anda mendapat penugasan baru di Konveksio.\n\n"
                . "🧾 *Detail Pesanan:*\n"
                . "• Produk: {$produk}\n"
                . "• Pelanggan: {$customer}\n"
                . "• Deadline: {$deadline}\n\n"
                . "⚙️ *Tugas Anda:*\n"
                . $rincian
                . "\nKlik link di bawah untuk melihat dan memperbarui status tugas Anda:\n"
                . "🔗 {$link}";

            try {
                Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5001') . '/api', [
                    'nohp'  => $worker->phone,
                    'pesan' => $pesan,
                ]);
            } catch (\Exception $e) {
                Log::error('[WA-Bot] Gagal kirim tugas ke ' . $worker->name . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Tentukan Worker yang harus menerima notifikasi berdasarkan status baru.
     */
    protected static function resolveWorkerForStatus(WorkOrder $wo, string $status): ?Worker
    {
        // Kalau QC_REVIEW, yang perlu notifikasi = worker dari tahap yang di-review
        $stageName = ($status === WorkOrder::STATUS_QC_REVIEW)
            ? ($wo->current_review_stage ?? $status)
            : $status;

        $col = WorkOrder::resolveWorkerColumnForStage($stageName);
        if ($col) {
            $workerId = $wo->$col;
            return $workerId ? Worker::find($workerId) : null;
        }

        return null;
    }

    /**
     * Kirim notifikasi WA ke satu worker tertentu saat task-nya ditolak QC.
     */
    public static function notifyTaskRejected(
        \App\Models\ProductionTask $task,
        string $reason,
        WorkOrder $wo
    ): void {
        $worker = Worker::find($task->assigned_to);

        if (!$worker || !$worker->phone) {
            Log::info("[WA-Bot] Tidak ada nomor HP untuk task ID {$task->id} (worker ID {$task->assigned_to}).");
            return;
        }

        $wo->loadMissing('orderItem.order.customer');
        $produk   = $wo->orderItem->product_name ?? '-';
        $qty      = $task->quantity ?? '-';
        $customer = $wo->orderItem?->order?->customer?->name ?? '-';
        $link     = $worker->portal_token
            ? url("/worker/{$worker->portal_token}")
            : url("/tugas?wo_id={$wo->id}&token={$wo->token}");

        $pesan = "⚠️ *REVISI QC — {$wo->wo_number}*\n\n"
            . "Halo {$worker->name},\n"
            . "Hasil kerja Anda pada tahap *{$task->stage_name}* perlu diperbaiki.\n\n"
            . "📋 *Detail:*\n"
            . "• Produk: {$produk} ({$qty} pcs)\n"
            . "• Pelanggan: {$customer}\n"
            . "• Alasan: {$reason}\n\n"
            . "Silakan perbaiki dan selesaikan kembali.\n"
            . "🔗 {$link}";

        try {
            Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5001') . '/api', [
                'nohp'  => $worker->phone,
                'pesan' => $pesan,
            ]);
        } catch (\Exception $e) {
            Log::error('[WA-Bot] Gagal kirim notif revisi QC: ' . $e->getMessage());
        }
    }

    /**
     * Kirim notifikasi ke QC Worker saat worker mengirim ulang revisi.
     * Dipanggil manual karena advance() return early saat WO tetap di QC_REVIEW.
     */
    public static function notifyRevisionResubmitted(
        \App\Models\ProductionTask $task,
        WorkOrder $wo
    ): void {
        $qcWorker = $wo->qc_worker_id ? Worker::find($wo->qc_worker_id) : null;

        if (!$qcWorker || !$qcWorker->phone) {
            Log::info("[WA-Bot] Revisi dikirim ulang tapi tidak ada QC worker/HP untuk WO {$wo->wo_number}");
            return;
        }

        $wo->loadMissing('orderItem.order.customer');
        $produk    = $wo->orderItem?->product_name ?? '-';
        $qty       = $task->quantity ?? '-';
        $customer  = $wo->orderItem?->order?->customer?->name ?? '-';
        $stageName = $task->stage_name ?? ($wo->current_review_stage ?? '-');
        $worker    = Worker::find($task->assigned_to);
        $workerName = $worker?->name ?? 'Pekerja';

        $qcLink = $qcWorker->portal_token
            ? url("/worker/{$qcWorker->portal_token}")
            : url("/tugas?wo_id={$wo->id}&token={$wo->token}&wt={$qcWorker->portal_token}");

        $pesan = "🔄 *REVISI SELESAI — PERLU RE-REVIEW QC — {$wo->wo_number}*\n\n"
            . "Halo {$qcWorker->name},\n"
            . "*{$workerName}* telah menyelesaikan revisi pada tahap *{$stageName}* dan mengirimkan kembali ke QC.\n\n"
            . "📋 *Detail:*\n"
            . "• Produk: {$produk} ({$qty} pcs)\n"
            . "• Pelanggan: {$customer}\n\n"
            . "Silakan buka portal untuk melakukan review ulang:\n"
            . "🔗 {$qcLink}";

        try {
            Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5001') . '/api', [
                'nohp'  => $qcWorker->phone,
                'pesan' => $pesan,
            ]);
        } catch (\Exception $e) {
            Log::error('[WA-Bot] Gagal kirim notif re-review QC: ' . $e->getMessage());
        }
    }
}
