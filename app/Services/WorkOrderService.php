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
     * QC Approve per-task: set qc_approved=true, lalu cek apakah semua task stage sudah disetujui.
     * Jika semua approved → advance WO ke tahap berikutnya.
     * Return: ['advanced' => bool, 'wo' => WorkOrder]
     */
    public function approveTask(int $taskId): array
    {
        return DB::transaction(function () use ($taskId) {
            $task = \App\Models\ProductionTask::withoutGlobalScopes()
                ->lockForUpdate()
                ->with('orderItem')
                ->findOrFail($taskId);

            // Tandai task ini sudah disetujui QC
            $task->update([
                'qc_approved'    => true,
                'qc_reviewed_at' => now(),
            ]);

            // Cari WO terkait
            $wo = WorkOrder::withoutGlobalScopes()
                ->where('order_item_id', $task->order_item_id)
                ->where('status', WorkOrder::STATUS_QC_REVIEW)
                ->first();

            if (!$wo) {
                return ['advanced' => false, 'wo' => null];
            }

            // Cek apakah semua task di stage ini sudah qc_approved
            $groupItemIds = $task->orderItem
                ? $task->orderItem->getItemsInGroup()->pluck('id')
                : [$task->order_item_id];

            $unapprovedCount = \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItemIds)
                ->where('stage_name', $wo->current_review_stage)
                ->where(fn($q) => $q->where('qc_approved', false)->orWhereNull('qc_approved'))
                ->count();

            if ($unapprovedCount === 0) {
                // Semua approved → advance WO
                $this->advance($wo->id);
                return ['advanced' => true, 'wo' => $wo->fresh()];
            }

            return ['advanced' => false, 'wo' => $wo->fresh()];
        });
    }

    /**
     * QC Reject per-task: kembalikan task spesifik ke pending (revisi),
     * dan kembalikan WO ke tahap yang sedang di-review jika masih di qc_review.
     */
    public function rejectTask(int $taskId, string $reason): \App\Models\ProductionTask
    {
        return DB::transaction(function () use ($taskId, $reason) {
            $task = \App\Models\ProductionTask::withoutGlobalScopes()
                ->lockForUpdate()
                ->with('orderItem')
                ->findOrFail($taskId);

            // Reset task ke pending + tandai revisi
            $task->update([
                'status'         => 'pending',
                'is_revision'    => true,
                'qc_approved'    => false,
                'qc_reviewed_at' => now(),
                'completed_at'   => null,
            ]);

            // Kembalikan WO ke tahap sebelumnya (jika masih di qc_review)
            $wo = WorkOrder::withoutGlobalScopes()
                ->where('order_item_id', $task->order_item_id)
                ->where('status', WorkOrder::STATUS_QC_REVIEW)
                ->first();

            if ($wo && $wo->current_review_stage) {
                $prevStage = $wo->current_review_stage;
                $wo->update([
                    'status'               => $prevStage,
                    'current_review_stage' => null,
                    'reject_reason'        => $reason,
                    'stage_entered_at'     => now(),
                ]);
            }

            // Kirim notifikasi WA ke worker yang direvisi
            \App\Helpers\NotificationHelper::notifyTaskRejected($task, $reason, $wo ?? WorkOrder::withoutGlobalScopes()->where('order_item_id', $task->order_item_id)->first());

            return $task->fresh();
        });
    }

    /**
     * QC Approve legacy: meneruskan ke divisi berikutnya dari status QC.
     * @deprecated Gunakan approveTask() untuk per-task approval
     */
    public function approveQC(int $woId): WorkOrder
    {
        return $this->advance($woId);
    }

    /**
     * QC Reject legacy: mengembalikan status ke tahap yang sedang di-review.
     * @deprecated Gunakan rejectTask() untuk per-task rejection
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
                'status'               => $prevStatus,
                'current_review_stage' => null,
                'reject_reason'        => $reason,
                'reject_proof_image'   => $proofImagePath,
                'stage_entered_at'     => now(),
            ]);

            $wo->refresh();

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
