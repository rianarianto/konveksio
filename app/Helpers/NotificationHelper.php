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

        $worker = static::resolveWorkerForStatus($wo, $newStatus);

        if (!$worker || !$worker->phone) {
            Log::info("[WA-Bot] Tidak ada pekerja/nomor HP untuk WO {$wo->wo_number} → Status: {$newStatus}");
            return;
        }

        $phone  = $worker->phone;
        $label  = WorkOrder::STATUS_LABELS[$newStatus] ?? $newStatus;
        $link   = url("/tugas?wo_id={$wo->id}&token={$wo->token}");
        $produk = $wo->orderItem->product_name ?? '-';
        $qty    = $wo->orderItem->quantity ?? '-';
        $customer = $wo->orderItem->order->customer->name ?? '-';

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
            $pesan = "🔔 *PENUGASAN BARU — {$wo->wo_number}*\n\n"
                . "Halo {$worker->name},\n"
                . "Anda mendapat tugas baru di tahap *{$label}*.\n\n"
                . "📋 *Detail:*\n"
                . "• Produk: {$produk} ({$qty} pcs)\n"
                . "• Pelanggan: {$customer}\n\n"
                . "Klik link di bawah untuk melihat detail dan memperbarui status:\n"
                . "🔗 {$link}";
        }

        // Kirim ke wa-bot secara asynchronous
        dispatch(function () use ($phone, $pesan) {
            try {
                Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5000') . '/api', [
                    'nohp'  => $phone,
                    'pesan' => $pesan,
                ]);
            } catch (\Exception $e) {
                Log::error('[WA-Bot] Gagal mengirim pesan: ' . $e->getMessage());
            }
        })->afterResponse();
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

            // Cari WorkOrder untuk link portal
            $wo = \App\Models\WorkOrder::withoutGlobalScopes()
                ->where('order_item_id', $item->id)
                ->first();

            $link = $wo
                ? url("/tugas?wo_id={$wo->id}&token={$wo->token}")
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

            dispatch(function () use ($worker, $pesan) {
                try {
                    Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5000') . '/api', [
                        'nohp'  => $worker->phone,
                        'pesan' => $pesan,
                    ]);
                } catch (\Exception $e) {
                    Log::error('[WA-Bot] Gagal kirim tugas ke ' . $worker->name . ': ' . $e->getMessage());
                }
            })->afterResponse();
        }
    }

    /**
     * Tentukan Worker yang harus menerima notifikasi berdasarkan status baru.
     */
    protected static function resolveWorkerForStatus(WorkOrder $wo, string $status): ?Worker
    {
        $col = WorkOrder::STATUS_WORKER_MAP[$status] ?? null;

        if ($col) {
            return $wo->$col;
        }

        // Untuk status QC → kirim notifikasi ke owner/admin toko (ambil dari users)
        // Ini bisa dikembangkan lebih lanjut jika ada user QC dedicated
        return null;
    }
}
