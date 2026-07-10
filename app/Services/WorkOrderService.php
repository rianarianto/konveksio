<?php

namespace App\Services;

use App\Models\WorkOrder;
use App\Helpers\NotificationHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WorkOrderService
{
    /**
     * Memajukan status WO ke tahap berikutnya (dinamis berdasarkan stage_sequence).
     */
    public function advance(int $woId): WorkOrder
    {
        return DB::transaction(function () use ($woId) {
            $wo = WorkOrder::lockForUpdate()->findOrFail($woId);

            $now = now();

            // If currently in worker stage, check if ALL tasks for this stage are done
            if ($wo->isInWorkerStage()) {
                $currentStage = $wo->status;
                
                // Get all tasks for this stage
                $groupItemIds = $wo->orderItem
                    ? $wo->orderItem->getItemsInGroup()->pluck('id')
                    : [$wo->order_item_id];
                
                $allTasksForStage = \App\Models\ProductionTask::withoutGlobalScopes()
                    ->whereIn('order_item_id', $groupItemIds)
                    ->where('stage_name', $currentStage)
                    ->get();
                
                $pendingTasks = $allTasksForStage->where('status', '!=', 'done');
                
                // If there are still pending/in_progress tasks, don't advance yet
                if ($pendingTasks->count() > 0) {
                    // Just return the WO without advancing
                    return $wo;
                }
            }

            $nextStatus = $wo->getNextStatus();

            if (!$nextStatus) {
                throw new \RuntimeException("Work Order [{$wo->wo_number}] sudah selesai atau tidak bisa dilanjutkan.");
            }

            $updateData = ['status' => $nextStatus, 'stage_entered_at' => $now];

            // Catat waktu mulai saat pertama kali keluar dari CREATED
            if ($wo->status === WorkOrder::STATUS_CREATED && !$wo->started_at) {
                $updateData['started_at'] = $now;
            }

            // Catat waktu selesai saat mencapai COMPLETED
            if ($nextStatus === WorkOrder::STATUS_COMPLETED) {
                $updateData['completed_at'] = $now;
            }

            // Update current_stage_index
            $stages = $wo->stage_sequence ?? [];
            $idx = array_search($nextStatus, $stages);
            if ($idx !== false) {
                $updateData['current_stage_index'] = $idx;
            }

            // Handle QC_REVIEW: simpan tahap yang di-review
            if ($nextStatus === WorkOrder::STATUS_QC_REVIEW) {
                $updateData['current_review_stage'] = $wo->status;
            } else {
                $updateData['current_review_stage'] = null;
            }

            $wo->update($updateData);
            $wo->refresh();

            // Kirim notifikasi WA (async, tidak blocking)
            $woId2 = $wo->id;
            $status2 = $nextStatus;
            dispatch(function () use ($woId2, $status2) {
                $wo = WorkOrder::withoutGlobalScopes()->find($woId2);
                if ($wo) {
                    NotificationHelper::notify($wo, $status2);
                }
            })->afterResponse();

            return $wo;
        });
    }

    /**
     * Advance tanpa kirim notifikasi WA (untuk auto-advance internal).
     */
    public function advanceSilent(int $woId): WorkOrder
    {
        return DB::transaction(function () use ($woId) {
            $wo = WorkOrder::lockForUpdate()->findOrFail($woId);

            $now = now();

            $nextStatus = $wo->getNextStatus();
            if (!$nextStatus) {
                return $wo;
            }

            $updateData = ['status' => $nextStatus, 'stage_entered_at' => $now];

            if ($wo->status === WorkOrder::STATUS_CREATED && !$wo->started_at) {
                $updateData['started_at'] = $now;
            }

            if ($nextStatus === WorkOrder::STATUS_COMPLETED) {
                $updateData['completed_at'] = $now;
            }

            $stages = $wo->stage_sequence ?? [];
            $idx = array_search($nextStatus, $stages);
            if ($idx !== false) {
                $updateData['current_stage_index'] = $idx;
            }

            if ($nextStatus === WorkOrder::STATUS_QC_REVIEW) {
                $updateData['current_review_stage'] = $wo->status;
            } else {
                $updateData['current_review_stage'] = null;
            }

            $wo->update($updateData);
            $wo->refresh();

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
     * QC Reject: mengembalikan status ke tahap yang sedang di-review.
     */
    public function rejectQC(int $woId, string $reason, ?string $proofImagePath = null): WorkOrder
    {
        return DB::transaction(function () use ($woId, $reason, $proofImagePath) {
            $wo = WorkOrder::lockForUpdate()->findOrFail($woId);

            if (!$wo->isInQcStage()) {
                throw new \RuntimeException("Work Order [{$wo->wo_number}] tidak sedang dalam tahap QC.");
            }

            $prevStatus = $wo->current_review_stage;
            if (!$prevStatus) {
                throw new \RuntimeException("Tidak ada tahap yang sedang di-review.");
            }

            // Sync ProductionTask kembali ke in_progress
            $task = $this->getProductionTaskForStage($wo, $prevStatus);
            if ($task) {
                $task->update(['status' => 'in_progress', 'completed_at' => null]);
            }

            $wo->update([
                'status'             => $prevStatus,
                'current_review_stage' => null,
                'reject_reason'      => $reason,
                'reject_proof_image' => $proofImagePath,
                'stage_entered_at'   => now(),
            ]);

            $wo->refresh();

            // Kirim notifikasi WA penolakan
            NotificationHelper::notify($wo, $prevStatus, isReject: true, rejectReason: $reason);

            return $wo;
        });
    }

    /**
     * Mulai mengerjakan tugas (worker stage).
     */
    public function startTask(int $woId, ?int $workerId = null): WorkOrder
    {
        return DB::transaction(function () use ($woId, $workerId) {
            $wo = WorkOrder::lockForUpdate()->findOrFail($woId);

            if (!$wo->started_at) {
                $wo->update(['started_at' => now()]);
            }

            // Determine the actual stage name for task lookup
            $stageName = $wo->status;
            if ($wo->status === WorkOrder::STATUS_QC_PREP) {
                $stageName = 'QC_PERSIAPAN';
            }

            // Check if this is a valid worker stage
            $stages = $wo->stage_sequence ?? [];
            $isValidWorkerStage = in_array($stageName, $stages);

            // Sync ProductionTask ke in_progress
            if ($isValidWorkerStage) {
                $task = $this->getProductionTaskForStage($wo, $stageName, $workerId);
                if ($task && $task->status === 'pending') {
                    $task->update([
                        'status'      => 'in_progress',
                        'is_revision' => false,
                    ]);
                }
            }

            $wo->refresh();
            return $wo;
        });
    }

    /**
     * Cari ProductionTask yang match nama tahap.
     */
    protected function getProductionTaskForStage(WorkOrder $wo, string $stageName, ?int $workerId = null): ?\App\Models\ProductionTask
    {
        if (!$workerId) {
            $workerCol = WorkOrder::resolveWorkerColumnForStage($stageName);
            $workerId = $workerCol ? $wo->$workerCol : null;
        }

        $groupItemIds = $wo->orderItem
            ? $wo->orderItem->getItemsInGroup()->pluck('id')
            : [$wo->order_item_id];

        $query = \App\Models\ProductionTask::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->where('stage_name', $stageName);

        if ($workerId) {
            $query->where('assigned_to', $workerId);
        }

        return $query->first();
    }
}
