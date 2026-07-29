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
    public function advance(int $woId, bool $fromQcApproval = false): WorkOrder
    {
        return DB::transaction(function () use ($woId, $fromQcApproval) {
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
                    return $wo;
                }

                // Jika ini adalah task perbaikan retur / revisi,
                // setelah selesai harus LANGSUNG kembali ke QC_AKHIR
                $hasQcAkhirRevision = $allTasksForStage->contains(fn($t) => $t->is_revision || $t->revision_source === 'qc_akhir')
                    || \App\Models\OrderReturn::whereIn('order_item_id', $groupItemIds)
                        ->whereIn('status', ['pending', 'diproses'])
                        ->exists();

                if ($hasQcAkhirRevision) {
                    // Reset revision_source tasks
                    \App\Models\ProductionTask::withoutGlobalScopes()
                        ->whereIn('order_item_id', $groupItemIds)
                        ->where('stage_name', $currentStage)
                        ->update(['revision_source' => null, 'is_revision' => false]);

                    $wo->update([
                        'status'               => WorkOrder::STATUS_QC_AKHIR,
                        'current_review_stage' => null,
                        'stage_entered_at'     => $now,
                    ]);
                    $wo->refresh();

                    try {
                        NotificationHelper::notify($wo, WorkOrder::STATUS_QC_AKHIR);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error("[WA QC Akhir] Gagal kirim WA ke QC Worker: " . $e->getMessage());
                    }

                    return $wo;
                }
            }

            // Jika sedang di tahap QC Review atau QC Akhir, jangan auto-advance saat worker menyelesaikan revisi
            if (($wo->status === WorkOrder::STATUS_QC_REVIEW || $wo->status === WorkOrder::STATUS_QC_AKHIR) && !$fromQcApproval) {
                return $wo;
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
                \App\Models\OrderReturn::where('order_item_id', $wo->order_item_id)
                    ->whereIn('status', ['pending', 'diproses'])
                    ->update(['status' => 'selesai']);
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

            if ($fromQcApproval) {
                $groupItemIds = $wo->orderItem
                    ? $wo->orderItem->getItemsInGroup()->pluck('id')
                    : [$wo->order_item_id];
                
                $stageToApprove = ($wo->status === WorkOrder::STATUS_QC_PREP)
                    ? 'QC_PERSIAPAN'
                    : ($wo->current_review_stage ?? $wo->status);

                if ($stageToApprove) {
                    \App\Models\ProductionTask::withoutGlobalScopes()
                        ->whereIn('order_item_id', $groupItemIds)
                        ->where('stage_name', $stageToApprove)
                        ->update(['qc_approved' => true, 'qc_reviewed_at' => $now]);
                }
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
                $this->advance($wo->id, true);
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

            $task->update([
                'status'      => 'pending',
                'is_revision' => true,
                'qc_approved' => false,
            ]);

            // Cukup kirim notifikasi WA ke worker
            $wo = WorkOrder::withoutGlobalScopes()
                ->where('order_item_id', $task->order_item_id)
                ->first();

            if ($wo) {
                $wo->update([
                    'reject_reason' => $reason,
                ]);

                if ($wo->status === WorkOrder::STATUS_QC_REVIEW || $wo->status === WorkOrder::STATUS_QC_AKHIR) {
                    $wo->update([
                        'status' => $task->stage_name,
                        'current_review_stage' => null,
                        'stage_entered_at' => now(),
                    ]);
                }
                $worker = \App\Models\Worker::find($task->assigned_to);
                if ($worker && $worker->phone) {
                    $link = url("/tugas?wo_id={$wo->id}&token={$wo->token}&wt={$worker->portal_token}");
                    $pesan = "⚠️ *REVISI QC — {$wo->wo_number}*\n\n"
                        . "Halo {$worker->name},\n"
                        . "Hasil kerja Anda pada tahap *{$task->stage_name}* perlu diperbaiki.\n\n"
                        . "📋 *Alasan:* {$reason}\n\n"
                        . "Silakan perbaiki dan selesaikan kembali.\n"
                        . "🔗 {$link}";
                    try {
                        \Illuminate\Support\Facades\Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5001') . '/api', [
                            'nohp'  => $worker->phone,
                            'pesan' => $pesan,
                        ]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('[WA-Bot] Gagal kirim notif revisi: ' . $e->getMessage());
                    }
                }
            }

            return $task;
        });
    }

    /**
     * QC Approve legacy: meneruskan ke divisi berikutnya dari status QC.
     * @deprecated Gunakan approveTask() untuk per-task approval
     */
    public function approveQC(int $woId): WorkOrder
    {
        return $this->advance($woId, true);
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

            // Cek jika worker punya revision task yang pending
            $groupItemIds = $wo->orderItem
                ? $wo->orderItem->getItemsInGroup()->pluck('id')
                : [$wo->order_item_id];

            $revisionTask = \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItemIds)
                ->where('assigned_to', $workerId)
                ->where('is_revision', true)
                ->where('status', 'pending')
                ->first();

            if ($revisionTask) {
                $revisionTask->update([
                    'status'      => 'in_progress',
                ]);
                $wo->refresh();
                return $wo;
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
     * QC Akhir: Approve semua task WO → advance ke COMPLETED.
     * Return: ['advanced' => bool, 'wo' => WorkOrder]
     */
    public function approveQcAkhir(int $woId): array
    {
        return DB::transaction(function () use ($woId) {
            $wo = WorkOrder::withoutGlobalScopes()->lockForUpdate()->findOrFail($woId);

            if (!$wo->isInQcAkhirStage()) {
                return ['advanced' => false, 'wo' => $wo];
            }

            $groupItemIds = $wo->orderItem
                ? $wo->orderItem->getItemsInGroup()->pluck('id')
                : [$wo->order_item_id];

            // Tandai semua task QC-Akhir-eligible sebagai qc_approved
            // (hanya stage yang tidak punya wajib_qc=true, atau semua jika tidak ada pengecualian)
            \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItemIds)
                ->where('status', 'done')
                ->whereNull('qc_approved')
                ->orWhere(function ($q) use ($groupItemIds) {
                    $q->whereIn('order_item_id', $groupItemIds)
                      ->where('status', 'done')
                      ->where('qc_approved', false);
                })
                ->update([
                    'qc_approved'    => true,
                    'qc_reviewed_at' => now(),
                ]);

            // Advance ke COMPLETED
            $wo->update([
                'status'       => WorkOrder::STATUS_COMPLETED,
                'completed_at' => now(),
                'stage_entered_at' => now(),
                'current_review_stage' => null,
            ]);
            $wo->refresh();

            $woId2 = $wo->id;
            dispatch(function () use ($woId2) {
                $wo = WorkOrder::withoutGlobalScopes()->find($woId2);
                if ($wo) NotificationHelper::notify($wo, WorkOrder::STATUS_COMPLETED);
            })->afterResponse();

        });
    }

    /**
     * QC Akhir: Approve satu task. Jika semua task eligible sudah approved → advance ke COMPLETED.
     */
    public function approveTaskQcAkhir(int $taskId): array
    {
        return DB::transaction(function () use ($taskId) {
            $task = \App\Models\ProductionTask::withoutGlobalScopes()
                ->lockForUpdate()
                ->with('orderItem')
                ->findOrFail($taskId);

            // Tandai task ini approved
            $task->update([
                'qc_approved'    => true,
                'qc_reviewed_at' => now(),
            ]);

            $wo = WorkOrder::withoutGlobalScopes()
                ->where('order_item_id', $task->order_item_id)
                ->where('status', WorkOrder::STATUS_QC_AKHIR)
                ->first();

            if (!$wo) {
                return ['advanced' => false, 'wo' => null];
            }

            $groupItemIds = $task->orderItem
                ? $task->orderItem->getItemsInGroup()->pluck('id')
                : [$task->order_item_id];

            // Cek apakah masih ada task yang belum approved (yang eligible: done, tidak punya QC sendiri)
            $stages = $wo->stage_sequence ?? [];
            $lastStage = end($stages);
            $hasQcAkhir = $wo->has_qc_selesai ?? true;

            $pendingApproval = \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItemIds)
                ->where('stage_name', '!=', 'QC_PERSIAPAN')
                ->where('status', 'done')
                ->where(function ($q) {
                    $q->where('qc_approved', false)->orWhereNull('qc_approved');
                })
                ->get()
                ->filter(function ($t) use ($lastStage, $hasQcAkhir) {
                    // Hanya task yang tidak punya QC Review sendiri yang harus di-approve di QC Akhir
                    $hasOwnQc = ($t->wajib_qc ?? false) && !($t->stage_name === $lastStage && $hasQcAkhir);
                    return !$hasOwnQc; // eligible untuk QC Akhir
                });

            // Cek juga tidak ada revisi yang pending
            $hasPendingRevision = \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItemIds)
                ->where('stage_name', '!=', 'QC_PERSIAPAN')
                ->where('status', '!=', 'done')
                ->exists();

            if ($pendingApproval->isEmpty() && !$hasPendingRevision) {
                // Semua approved → advance ke COMPLETED
                $wo->update([
                    'status'               => WorkOrder::STATUS_COMPLETED,
                    'completed_at'         => now(),
                    'stage_entered_at'     => now(),
                    'current_review_stage' => null,
                ]);
                $wo->refresh();

                $woId2 = $wo->id;
                dispatch(function () use ($woId2) {
                    $wo = WorkOrder::withoutGlobalScopes()->find($woId2);
                    if ($wo) NotificationHelper::notify($wo, WorkOrder::STATUS_COMPLETED);
                })->afterResponse();

                return ['advanced' => true, 'wo' => $wo];
            }

            return ['advanced' => false, 'wo' => $wo->fresh()];
        });
    }

    /**
     * QC Akhir Reject per-task: kirim task tertentu kembali ke stage-nya untuk revisi.
     * Hanya boleh untuk tahap yang tidak punya wajib_qc=true (tidak ada QC_REVIEW sendiri).
     */
    public function rejectTaskQcAkhir(int $taskId, string $reason): \App\Models\ProductionTask
    {
        return DB::transaction(function () use ($taskId, $reason) {
            $task = \App\Models\ProductionTask::withoutGlobalScopes()
                ->lockForUpdate()
                ->with('orderItem')
                ->findOrFail($taskId);

            // Kembalikan WO ke stage task tersebut untuk mengecek apakah ini tahap terakhir
            $wo = WorkOrder::withoutGlobalScopes()
                ->where('order_item_id', $task->order_item_id)
                ->where('status', WorkOrder::STATUS_QC_AKHIR)
                ->first();

            $isLastStage = false;
            $hasQcAkhir = false;
            if ($wo) {
                $woStages = $wo->stage_sequence ?? [];
                $isLastStage = (end($woStages) === $task->stage_name);
                $hasQcAkhir = $wo->has_qc_selesai ?? true;
            }

            // Reset task ke pending, tandai sebagai revisi dari qc_akhir
            $task->update([
                'status'          => 'pending',
                'is_revision'     => true,
                'revision_source' => 'qc_akhir',
                'qc_approved'     => false,
                'qc_reviewed_at'  => now(),
                'completed_at'    => null,
            ]);

            if ($wo) {
                $wo->update([
                    'status'               => $task->stage_name,
                    'reject_reason'        => $reason,
                    'current_review_stage' => null,
                    'stage_entered_at'     => now(),
                ]);
                // Kirim notifikasi WA ke worker
                $worker = \App\Models\Worker::find($task->assigned_to);
                if ($worker && $worker->phone) {
                    $link = url("/tugas?wo_id={$wo->id}&token={$wo->token}&wt={$worker->portal_token}");
                    $pesan = "⚠️ *REVISI QC AKHIR — {$wo->wo_number}*\n\n"
                        . "Halo {$worker->name},\n"
                        . "Hasil kerja Anda pada tahap *{$task->stage_name}* perlu diperbaiki (Revisi QC Akhir).\n\n"
                        . "📋 *Alasan:* {$reason}\n\n"
                        . "Silakan perbaiki dan selesaikan kembali.\n"
                        . "🔗 {$link}";
                    try {
                        \Illuminate\Support\Facades\Http::timeout(10)->post(config('services.wa_bot.url', 'http://localhost:5001') . '/api', [
                            'nohp'  => $worker->phone,
                            'pesan' => $pesan,
                        ]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error('[WA-Bot] Gagal kirim notif revisi QC akhir: ' . $e->getMessage());
                    }
                }
            }

            return $task->fresh();
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
