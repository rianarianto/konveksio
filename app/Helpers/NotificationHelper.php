<?php

namespace App\Helpers;

use App\Models\WorkOrder;
use App\Models\Worker;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationHelper
{
    /**
     * Kirim notifikasi WhatsApp ke pekerja terkait setelah perubahan status WO.
     * Berjalan di latar belakang (async via dispatch closure).
     */
    public static function notify(
        WorkOrder $wo,
        string    $newStatus,
        bool      $isReject = false,
        string    $rejectReason = ''
    ): void {
        // Load relasi yang dibutuhkan
        $wo->loadMissing('orderItem.order.customer', 'shop');

        // Tentukan tahap yang sedang aktif
        $stageName = ($newStatus === WorkOrder::STATUS_QC_REVIEW)
            ? ($wo->current_review_stage ?? $newStatus)
            : $newStatus;

        if ($newStatus === WorkOrder::STATUS_QC_PREP) {
            $stageName = 'QC_PERSIAPAN';
        }

        // Ambil semua task untuk WO ini di tahap tersebut
        $groupItemIds = $wo->orderItem
            ? $wo->orderItem->getItemsInGroup()->pluck('id')
            : [$wo->order_item_id];

        $tasks = \App\Models\ProductionTask::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->where('stage_name', $stageName)
            ->with('assignedTo')
            ->get();

        // Kumpulkan worker unik yang ditugaskan di tahap ini
        $workers = $tasks->map(fn($t) => $t->assignedTo)->filter()->unique('id');

        if ($workers->isEmpty()) {
            Log::info("[WA-Bot] Tidak ada pekerja untuk WO {$wo->wo_number} → Status: {$newStatus}");
            return;
        }

        // Label untuk pesan WA
        if ($newStatus === WorkOrder::STATUS_QC_PREP) {
            $label = 'QC Persiapan';
        } elseif ($newStatus === WorkOrder::STATUS_QC_REVIEW) {
            $label = 'QC ' . ($wo->current_review_stage ?? '');
        } else {
            $label = $newStatus;
        }

        $produk = $wo->orderItem->product_name ?? '-';
        $qty    = $wo->orderItem->quantity ?? '-';
        $customer = $wo->orderItem->order->customer->name ?? '-';

        // Deteksi tahap sebelumnya & siapa pekerja yang menyelesaikannya
        $prevStageInfo = '';
        $prevStageCode = $wo->getPreviousStatus();
        if ($prevStageCode && $prevStageCode !== WorkOrder::STATUS_CREATED) {
            $prevLabel = $prevStageCode;
            if ($prevStageCode === WorkOrder::STATUS_QC_PREP) {
                $prevLabel = 'QC Persiapan';
            } elseif ($prevStageCode === WorkOrder::STATUS_QC_REVIEW) {
                $prevLabel = 'QC ' . ($wo->current_review_stage ?? '');
            }

            // Cari siapa pekerjanya di ProductionTask
            $prevStageNameForQuery = $prevStageCode;
            if ($prevStageCode === WorkOrder::STATUS_QC_PREP) {
                $prevStageNameForQuery = 'QC_PERSIAPAN';
            } elseif ($prevStageCode === WorkOrder::STATUS_QC_REVIEW) {
                $prevStageNameForQuery = $wo->current_review_stage;
            }

            $prevTask = \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItemIds)
                ->where('stage_name', $prevStageNameForQuery)
                ->with('assignedTo')
                ->first();

            $prevWorkerName = null;
            if ($prevTask && $prevTask->assignedTo) {
                $prevWorkerName = $prevTask->assignedTo->name;
            } elseif ($prevStageCode === WorkOrder::STATUS_QC_PREP || $prevStageCode === WorkOrder::STATUS_QC_REVIEW) {
                // QC worker di WO
                if ($wo->qc_worker_id) {
                    $qcWorker = \App\Models\Worker::find($wo->qc_worker_id);
                    if ($qcWorker) {
                        $prevWorkerName = $qcWorker->name;
                    }
                }
            }

            if ($prevWorkerName) {
                $prevStageInfo = "✅ *Selesai Sebelumnya:* Tahap *{$prevLabel}* oleh *{$prevWorkerName}*\n";
            } else {
                $prevStageInfo = "✅ *Selesai Sebelumnya:* Tahap *{$prevLabel}*\n";
            }
        }

        // Kirim notifikasi ke tiap worker
        foreach ($workers as $worker) {
            if (!$worker->phone) continue;

            $phone = $worker->phone;
            $link  = url("/tugas?wo_id={$wo->id}&token={$wo->token}&wt={$worker->portal_token}");

            if ($isReject) {
                $pesan = "⚠️ *PENOLAKAN QC — {$wo->wo_number}*\n\n"
                    . "Halo {$worker->name},\n"
                    . "Tugas Anda pada tahap *{$label}* ditolak oleh QC.\n\n"
                    . "📋 *Detail:*\n"
                    . "• Produk: {$produk} ({$qty} pcs)\n"
                    . "• Pelanggan: {$customer}\n"
                    . "• Alasan: {$rejectReason}\n\n"
                    . "Silakan perbaiki dan kirim ulang.\n"
                    . "🔗 {$link}";
            } else {
                $pesan = "🔥 *TUGAS SIAP DIKERJAKAN — {$wo->wo_number}*\n\n"
                    . "Halo {$worker->name},\n"
                    . "Antrian tugas Anda untuk tahap *{$label}* kini sudah siap dikerjakan.\n\n"
                    . $prevStageInfo
                    . "\n📋 *Detail:*\n"
                    . "• Produk: {$produk} ({$qty} pcs)\n"
                    . "• Pelanggan: {$customer}\n\n"
                    . "Silakan klik link di bawah untuk mulai mengerjakan tugas ini:\n"
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

        // Notifikasi khusus ke QC Worker saat WO masuk QC_REVIEW
        // (QC worker tidak punya ProductionTask, jadi tidak masuk loop di atas)
        if ($newStatus === WorkOrder::STATUS_QC_REVIEW && !$isReject) {
            $qcWorker = $wo->qc_worker_id ? Worker::find($wo->qc_worker_id) : null;

            if ($qcWorker && $qcWorker->phone) {
                $reviewStageLabel = $wo->current_review_stage ?? 'produksi';
                $qcLink = $qcWorker->portal_token
                    ? url("/worker/{$qcWorker->portal_token}")
                    : url("/tugas?wo_id={$wo->id}&token={$wo->token}&wt={$qcWorker->portal_token}");

                $qcPesan = "🔍 *PERLU REVIEW QC — {$wo->wo_number}*\n\n"
                    . "Halo {$qcWorker->name},\n"
                    . "Tahap *{$reviewStageLabel}* telah selesai dikerjakan dan menunggu review QC Anda.\n\n"
                    . "📋 *Detail:*\n"
                    . "• Produk: {$produk} ({$qty} pcs)\n"
                    . "• Pelanggan: {$customer}\n\n"
                    . "Silakan buka portal untuk melakukan review:\n"
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

        // Ambil semua production tasks dengan pekerja, unik per pekerja
        $tasks = \App\Models\ProductionTask::withoutGlobalScopes()
            ->where('order_item_id', $item->id)
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
            $rincian = '';
            foreach ($workerTaskList as $task) {
                $rincian .= "• {$task->stage_name}: {$task->quantity} pcs — Rp " . number_format($task->wage_amount, 0, ',', '.') . "\n";
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
