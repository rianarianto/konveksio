<?php

namespace App\Services;

use App\Models\WorkOrder;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkOrderService
{
    /**
     * Memajukan status WO ke tahap berikutnya (digunakan oleh Tukang saat menyelesaikan tugas).
     * Atomic: menggunakan lockForUpdate untuk mencegah race condition.
     */
    public function advance(int $woId): WorkOrder
    {
        return DB::transaction(function () use ($woId) {
            $wo = WorkOrder::lockForUpdate()->findOrFail($woId);

            $nextStatus = $wo->getNextStatus();

            if (!$nextStatus) {
                throw new \RuntimeException("Work Order [{$wo->wo_number}] sudah selesai atau tidak bisa dilanjutkan.");
            }

            $now = now();

            $updateData = ['status' => $nextStatus];

            // Catat waktu mulai saat pertama kali keluar dari CREATED
            if ($wo->status === 'CREATED' && !$wo->started_at) {
                $updateData['started_at'] = $now;
            }

            // Catat waktu selesai saat mencapai COMPLETED
            if ($nextStatus === 'COMPLETED') {
                $updateData['completed_at'] = $now;
            }

            // Sync ProductionTask status to done
            $completedStatus = $wo->status;
            if (in_array($completedStatus, WorkOrder::WORKER_STATUSES)) {
                $task = $this->getProductionTaskForStatus($wo, $completedStatus);
                if ($task) {
                    $task->update([
                        'status' => 'done',
                        'completed_at' => $now,
                    ]);
                }
            }

            $wo->update($updateData);
            $wo->refresh();

            // Kirim notifikasi WA di latar belakang
            NotificationHelper::notify($wo, $nextStatus);

            return $wo;
        });
    }

    /**
     * QC Approve: meneruskan ke divisi berikutnya dari status QC.
     */
    public function approveQC(int $woId): WorkOrder
    {
        return $this->advance($woId);
    }

    /**
     * QC Reject: mengembalikan status ke divisi pengerjaan sebelumnya.
     * Menyimpan alasan penolakan dan bukti foto (opsional).
     */
    public function rejectQC(int $woId, string $reason, ?string $proofImagePath = null): WorkOrder
    {
        return DB::transaction(function () use ($woId, $reason, $proofImagePath) {
            $wo = WorkOrder::lockForUpdate()->findOrFail($woId);

            if (!$wo->isInQcStage()) {
                throw new \RuntimeException("Work Order [{$wo->wo_number}] tidak sedang dalam tahap QC.");
            }

            $prevStatus = $wo->getPreviousStatus();

            if (!$prevStatus) {
                throw new \RuntimeException("Tidak ada status sebelumnya yang bisa dikembalikan.");
            }

            // Sync ProductionTask status back to in_progress
            if (in_array($prevStatus, WorkOrder::WORKER_STATUSES)) {
                $task = $this->getProductionTaskForStatus($wo, $prevStatus);
                if ($task) {
                    $task->update([
                        'status' => 'in_progress',
                        'completed_at' => null,
                    ]);
                }
            }

            $wo->update([
                'status'             => $prevStatus,
                'reject_reason'      => $reason,
                'reject_proof_image' => $proofImagePath,
            ]);

            $wo->refresh();

            // Kirim notifikasi WA penolakan ke pekerja terkait
            NotificationHelper::notify($wo, $prevStatus, isReject: true, rejectReason: $reason);

            return $wo;
        });
    }

    /**
     * Mulai mengerjakan tugas (CREATED/awal divisi → in_progress sinyal).
     * Secara state machine, ini hanya mencatat started_at jika belum ada.
     * Untuk tombol "Mulai Kerjakan" di UI.
     */
    public function startTask(int $woId): WorkOrder
    {
        return DB::transaction(function () use ($woId) {
            $wo = WorkOrder::lockForUpdate()->findOrFail($woId);

            if (!$wo->started_at) {
                $wo->update(['started_at' => now()]);
            }

            // Sync ProductionTask status to in_progress
            if (in_array($wo->status, WorkOrder::WORKER_STATUSES)) {
                $task = $this->getProductionTaskForStatus($wo, $wo->status);
                if ($task && $task->status === 'pending') {
                    $task->update(['status' => 'in_progress']);
                }
            }

            $wo->refresh();
            return $wo;
        });
    }

    /**
     * Cari ProductionTask yang sesuai dengan status/tahap WorkOrder saat ini.
     */
    protected function getProductionTaskForStatus(WorkOrder $wo, string $status): ?\App\Models\ProductionTask
    {
        $workerCol = WorkOrder::STATUS_WORKER_MAP[$status] ?? null;
        if (!$workerCol) {
            return null;
        }

        $workerId = $wo->$workerCol;
        if (!$workerId) {
            return null;
        }

        $groupItemIds = $wo->orderItem ? $wo->orderItem->getItemsInGroup()->pluck('id') : [$wo->order_item_id];

        $query = \App\Models\ProductionTask::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->where('assigned_to', $workerId);

        // Cari pencocokan tahap yang ketat berdasarkan kata kunci
        $strictQuery = clone $query;
        $stages = [];
        foreach (WorkOrder::STAGE_NAME_WORKER_MAP as $stageKeyword => $col) {
            if ($col === $workerCol) {
                $stages[] = $stageKeyword;
            }
        }

        if (!empty($stages)) {
            $strictQuery->where(function ($q) use ($stages) {
                foreach ($stages as $stage) {
                    $q->orWhere('stage_name', 'like', "%{$stage}%");
                }
            });

            $task = $strictQuery->first();
            if ($task) {
                return $task;
            }
        }

        return $query->first();
    }
}
