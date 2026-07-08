<?php

namespace App\Livewire;

use App\Models\Worker;
use App\Models\ProductionTask;
use App\Models\WorkOrder;
use App\Services\WorkOrderService;
use Livewire\Component;

class WorkerDashboard extends Component
{
    public string $token;
    public ?Worker $worker = null;

    // Selected task for detail view
    public ?int $selectedTaskId = null;

    // QC Review state
    public ?int $reviewWoId = null;
    public string $rejectReason = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->loadWorker();
    }

    protected function loadWorker(): void
    {
        $this->worker = Worker::withoutGlobalScopes()
            ->where('portal_token', $this->token)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get all tasks for this worker, grouped by status.
     * Sort: Express first, then FIFO (created_at ASC).
     */
    public function getTasksProperty(): array
    {
        if (!$this->worker) {
            return ['in_progress' => collect(), 'pending' => collect(), 'done' => collect()];
        }

        $tasks = ProductionTask::withoutGlobalScopes()
            ->where('assigned_to', $this->worker->id)
            ->with(['orderItem.order.customer', 'orderItem.order', 'workOrder'])
            ->orderBy('created_at', 'asc')
            ->get();

        // Sort: express first, then by created_at (already sorted)
        $sortedTasks = $tasks->sortByDesc(fn($t) => $t->orderItem?->order?->is_express ?? false);

        return [
            'in_progress' => $sortedTasks->where('status', 'in_progress')->values(),
            'pending' => $sortedTasks->where('status', 'pending')->values(),
            'done' => $sortedTasks->where('status', 'done')->take(20)->values(), // Limit history
        ];
    }

    /**
     * Check if a task can be started (sequential dependency).
     * Task bisa dimulai kalau WO lagi di stage yang SAMA dengan task ini.
     */
    public function canStartTask(ProductionTask $task): bool
    {
        $wo = $this->getWorkOrderForTask($task);
        if (!$wo) {
            return false;
        }

        // Special case: QC_PREP status matches QC_PERSIAPAN task
        if ($wo->status === WorkOrder::STATUS_QC_PREP && $task->stage_name === 'QC_PERSIAPAN') {
            return true;
        }

        // Special case: CREATED status - first stage is unlockable
        if ($wo->status === WorkOrder::STATUS_CREATED) {
            $stages = $wo->stage_sequence ?? [];
            if (!empty($stages) && $stages[0] === $task->stage_name) {
                return true;
            }
        }

        // WO harus di stage yang sama dengan task
        if ($wo->status === $task->stage_name) {
            return true;
        }

        // Kalau WO udah LEWAT task ini, task harusnya udah selesai
        // Tapi kalau masih pending, tandanya ada masalah data - anggap bisa dikerjakan
        $stages = $wo->stage_sequence ?? [];
        $currentIdx = array_search($wo->status, $stages);
        $taskIdx = array_search($task->stage_name, $stages);

        // Handle QC_PREP: treat it as being at QC_PERSIAPAN stage
        if ($wo->status === WorkOrder::STATUS_QC_PREP) {
            $currentIdx = array_search('QC_PERSIAPAN', $stages);
        }

        if ($taskIdx !== false && $currentIdx !== false && $taskIdx < $currentIdx) {
            // WO udah lewat tapi task masih pending - bisa dikerjakan
            return true;
        }

        return false;
    }

    /**
     * Get WorkOrder for a task.
     */
    protected function getWorkOrderForTask(ProductionTask $task): ?WorkOrder
    {
        $groupItemIds = $task->orderItem
            ? $task->orderItem->getItemsInGroup()->pluck('id')
            : [$task->order_item_id];

        return WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->first();
    }

    /**
     * Get the blocking stage (what needs to finish first).
     * Cek stage apa yang menghalangi task ini dimulai.
     */
    public function getBlockingStage(ProductionTask $task): ?string
    {
        $wo = $this->getWorkOrderForTask($task);
        if (!$wo) {
            return null;
        }

        $stages = $wo->stage_sequence ?? [];
        $currentIdx = array_search($wo->status, $stages);
        $taskIdx = array_search($task->stage_name, $stages);

        // Handle QC_PREP: treat it as being at QC_PERSIAPAN stage
        if ($wo->status === WorkOrder::STATUS_QC_PREP) {
            $currentIdx = array_search('QC_PERSIAPAN', $stages);
        }

        // Handle CREATED: treat it as being before the first stage (index -1)
        if ($wo->status === WorkOrder::STATUS_CREATED) {
            $currentIdx = -1;
        }

        if ($taskIdx === false || $currentIdx === false) {
            return null;
        }

        // Kalau WO udah lewat task ini, ga ada yang blocking
        if ($taskIdx <= $currentIdx) {
            return null;
        }

        // Task di DEPAN WO → yang blocking adalah stage SEBELUM task
        return $stages[$taskIdx - 1] ?? null;
    }

    /**
     * Start working on a task.
     */
    public function mulaiKerjakan(int $taskId): void
    {
        $task = ProductionTask::withoutGlobalScopes()->find($taskId);
        if (!$task || $task->assigned_to !== $this->worker?->id) {
            $this->addError('aksi', 'Tugas tidak valid.');
            return;
        }

        $wo = $this->getWorkOrderForTask($task);
        if (!$wo) {
            $this->addError('aksi', 'Work Order tidak ditemukan.');
            return;
        }

        // Check sequential dependency using canStartTask
        if (!$this->canStartTask($task)) {
            $blocking = $this->getBlockingStage($task);
            $blockingLabel = $blocking ? str_replace('_', ' ', $blocking) : 'sebelumnya';
            $this->addError('aksi', "Belum bisa dimulai. Menunggu tahap {$blockingLabel} selesai.");
            return;
        }

        // Auto-advance WO if at CREATED (before first stage)
        if ($wo->status === WorkOrder::STATUS_CREATED) {
            app(WorkOrderService::class)->advance($wo->id);
            $wo = $wo->fresh();
        }

        // Set WO started_at if not yet
        if (!$wo->started_at) {
            $wo->update(['started_at' => now()]);
        }

        // Directly update THIS specific task to in_progress
        // (avoids bug where startTask() picks wrong worker's task in multi-worker stage)
        if ($task->status === 'pending') {
            $task->update(['status' => 'in_progress']);
        }

        $this->syncOrderStatus($wo);
        session()->flash('success', 'Tugas dimulai!');
    }

    /**
     * Complete a task.
     */
    public function selesaiKerjakan(int $taskId): void
    {
        $task = ProductionTask::withoutGlobalScopes()->find($taskId);
        if (!$task || $task->assigned_to !== $this->worker?->id) {
            $this->addError('aksi', 'Tugas tidak valid.');
            return;
        }

        $wo = $this->getWorkOrderForTask($task);
        if (!$wo) {
            $this->addError('aksi', 'Work Order tidak ditemukan.');
            return;
        }

        // Check if task is in progress
        if ($task->status !== 'in_progress') {
            $this->addError('aksi', 'Tugas belum dimulai.');
            return;
        }

        // Mark this task as done
        $task->update(['status' => 'done', 'completed_at' => now()]);

        // Try to advance WO (will only advance if ALL tasks for this stage are done)
        $woBefore = $wo->fresh();
        app(WorkOrderService::class)->advance($wo->id);
        $woAfter = $wo->fresh();

        $this->syncOrderStatus($wo);
        
        // Different message based on whether WO advanced or not
        if ($woBefore->status !== $woAfter->status) {
            session()->flash('success', 'Tugas selesai! Pesanan lanjut ke tahap berikutnya.');
        } else {
            session()->flash('success', 'Tugas selesai! Menunggu rekan lain menyelesaikan tugas mereka.');
        }
    }

    /**
     * Select a task to view details.
     */
    public function selectTask(int $taskId): void
    {
        $this->selectedTaskId = $taskId;
    }

    /**
     * Close task detail.
     */
    public function closeDetail(): void
    {
        $this->selectedTaskId = null;
    }

    /**
     * Get selected task with relations.
     */
    public function getSelectedTaskProperty(): ?ProductionTask
    {
        if (!$this->selectedTaskId) {
            return null;
        }

        return ProductionTask::withoutGlobalScopes()
            ->with(['orderItem.order.customer'])
            ->find($this->selectedTaskId);
    }

    /**
     * Get stage progress for a task's WorkOrder.
     */
    public function getStageProgress(ProductionTask $task): array
    {
        $wo = $this->getWorkOrderForTask($task);
        if (!$wo) {
            return [];
        }

        $stages = $wo->stage_sequence ?? [];
        $currentIdx = array_search($wo->status, $stages);
        $taskIdx = array_search($task->stage_name, $stages);

        $progress = [];
        foreach ($stages as $idx => $stage) {
            $status = 'pending';
            if ($idx < $currentIdx) {
                $status = 'done';
            } elseif ($idx === $currentIdx) {
                $status = 'current';
            }

            $progress[] = [
                'name' => $stage,
                'status' => $status,
                'is_task_stage' => ($idx === $taskIdx),
            ];
        }

        return $progress;
    }

    /**
     * Sync Order status based on WorkOrder.
     */
    protected function syncOrderStatus(WorkOrder $wo): void
    {
        $order = $wo->orderItem?->order;
        if (!$order) return;

        $groupItemIds = $order->orderItems()->pluck('id');
        $wos = WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->get();

        if ($wos->isEmpty()) return;

        $allCompleted = $wos->every(fn($w) => $w->isCompleted());
        $anyInProgress = $wos->some(fn($w) => !$w->isCompleted() && $w->status !== WorkOrder::STATUS_CREATED);

        $newStatus = 'antrian';
        if ($allCompleted) $newStatus = 'selesai';
        elseif ($anyInProgress) $newStatus = 'diproses';

        if ($order->status !== $newStatus) {
            $order->update(['status' => $newStatus]);
        }
    }

    public function render()
    {
        return view('livewire.worker-dashboard', [
            'tasks' => $this->tasks,
            'qcReviews' => $this->qcReviews,
        ]);
    }

    /**
     * Get WOs that need QC Review (assigned to this worker as QC).
     */
    public function getQcReviewsProperty(): \Illuminate\Support\Collection
    {
        if (!$this->worker) {
            return collect();
        }

        return WorkOrder::withoutGlobalScopes()
            ->where('status', WorkOrder::STATUS_QC_REVIEW)
            ->where('qc_worker_id', $this->worker->id)
            ->with(['orderItem.order.customer', 'orderItem.productionTasks'])
            ->orderBy('stage_entered_at', 'asc')
            ->get();
    }

    /**
     * Approve QC Review - advance to next stage.
     */
    public function approveQCReview(int $woId): void
    {
        $wo = WorkOrder::withoutGlobalScopes()->find($woId);
        if (!$wo || $wo->qc_worker_id !== $this->worker?->id) {
            $this->addError('qc', 'Tidak memiliki akses.');
            return;
        }

        app(WorkOrderService::class)->approveQC($wo->id);
        $this->syncOrderStatus($wo);
        
        session()->flash('success', '✅ QC Approved! Pesanan lanjut ke tahap berikutnya.');
        $this->reviewWoId = null;
    }

    /**
     * Reject QC Review - return to previous stage with reason.
     */
    public function rejectQCReview(int $woId): void
    {
        if (empty(trim($this->rejectReason))) {
            $this->addError('rejectReason', 'Alasan revisi wajib diisi.');
            return;
        }

        $wo = WorkOrder::withoutGlobalScopes()->find($woId);
        if (!$wo || $wo->qc_worker_id !== $this->worker?->id) {
            $this->addError('qc', 'Tidak memiliki akses.');
            return;
        }

        app(WorkOrderService::class)->rejectQC($wo->id, $this->rejectReason);
        
        // Mark task back to pending for revision (worker needs to click "Mulai Perbaikan")
        $prevStage = $wo->current_review_stage;
        if ($prevStage) {
            $task = ProductionTask::withoutGlobalScopes()
                ->where('order_item_id', $wo->order_item_id)
                ->where('stage_name', $prevStage)
                ->first();
            if ($task) {
                $task->update([
                    'status' => 'pending',
                    'completed_at' => null,
                    'is_revision' => true,
                ]);
            }
        }
        
        $this->syncOrderStatus($wo);
        session()->flash('success', '🔧 Revisi dikirim. Pekerja akan memperbaiki pekerjaan.');
        $this->reviewWoId = null;
        $this->rejectReason = '';
    }

    /**
     * Open review modal for a WO.
     */
    public function openReview(int $woId): void
    {
        $this->reviewWoId = $woId;
        $this->rejectReason = '';
        $this->resetErrorBag();
    }

    /**
     * Close review modal.
     */
    public function closeReview(): void
    {
        $this->reviewWoId = null;
        $this->rejectReason = '';
        $this->resetErrorBag();
    }

    /**
     * Get review stage name for display.
     */
    public function getReviewStageLabel(WorkOrder $wo): string
    {
        return $wo->current_review_stage ?? 'Unknown';
    }
}
