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

    public string $activeTab = 'tugas'; // 'tugas', 'riwayat', 'keuangan'
    public string $statusFilter = 'semua'; // 'semua', 'in_progress', 'pending', 'done'

    // History Date Filter
    public string $startDate = '';
    public string $endDate = '';

    // Kasbon form
    public string $kasbonAmount = '';
    public string $kasbonNote = '';
    public bool $showKasbonModal = false;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->toDateString();
        $this->loadWorker();
    }

    protected function loadWorker(): void
    {
        $this->worker = Worker::withoutGlobalScopes()
            ->where('portal_token', $this->token)
            ->where('is_active', true)
            ->first();
    }

    public function setQuickDate(string $preset): void
    {
        if ($preset === 'today') {
            $this->startDate = now()->toDateString();
            $this->endDate = now()->toDateString();
        } elseif ($preset === '7days') {
            $this->startDate = now()->subDays(6)->toDateString();
            $this->endDate = now()->toDateString();
        } elseif ($preset === 'this_month') {
            $this->startDate = now()->startOfMonth()->toDateString();
            $this->endDate = now()->toDateString();
        } elseif ($preset === 'last_month') {
            $this->startDate = now()->subMonth()->startOfMonth()->toDateString();
            $this->endDate = now()->subMonth()->endOfMonth()->toDateString();
        }
    }

    public function getFilteredHistoryProperty()
    {
        if (!$this->worker) return collect();

        $query = ProductionTask::withoutGlobalScopes()
            ->where('assigned_to', $this->worker->id)
            ->where('status', 'done')
            ->with(['orderItem.order.customer', 'orderItem.order', 'workOrder']);

        if (!empty($this->startDate)) {
            $query->whereDate('completed_at', '>=', $this->startDate);
        }
        if (!empty($this->endDate)) {
            $query->whereDate('completed_at', '<=', $this->endDate);
        }

        return $query->orderBy('completed_at', 'desc')->get();
    }

    public function getHistoryStatsProperty(): array
    {
        $history = $this->filteredHistory;
        return [
            'total_pcs' => $history->sum('quantity'),
            'total_upah' => $history->sum('wage_amount'),
            'total_tugas' => $history->count(),
        ];
    }

    public function ajukanKasbon(): void
    {
        $this->validate([
            'kasbonAmount' => 'required|numeric|min:10000',
            'kasbonNote'   => 'nullable|string|max:255',
        ], [
            'kasbonAmount.required' => 'Nominal kasbon wajib diisi.',
            'kasbonAmount.min'      => 'Minimal kasbon Rp 10.000.',
        ]);

        $worker = $this->worker;
        if (!$worker) {
            session()->flash('kasbon_error', 'Data pekerja tidak ditemukan.');
            return;
        }

        $amount = (int) str_replace(['.', ','], '', $this->kasbonAmount);
        $limit  = max(0, (int) $worker->max_cash_advance - (int) $worker->current_cash_advance);

        if ($amount > $limit) {
            session()->flash('kasbon_error', 'Nominal melebihi sisa limit kasbon Anda (Rp ' . number_format($limit, 0, ',', '.') . ').');
            return;
        }

        \App\Models\CashAdvance::create([
            'shop_id'               => $worker->shop_id,
            'cash_advanceable_type' => Worker::class,
            'cash_advanceable_id'   => $worker->id,
            'type'                  => 'pinjaman',
            'amount'                => $amount,
            'note'                  => $this->kasbonNote ?: 'Pengajuan kasbon via portal worker',
            'date'                  => now()->toDateString(),
            'recorded_by'           => null,
        ]);

        $worker->increment('current_cash_advance', $amount);

        $this->reset('kasbonAmount', 'kasbonNote', 'showKasbonModal');
        $this->loadWorker();
        session()->flash('kasbon_success', 'Pengajuan kasbon sebesar Rp ' . number_format($amount, 0, ',', '.') . ' berhasil!');
    }

    public function getCashAdvanceHistoryProperty()
    {
        if (!$this->worker) return collect();

        return \App\Models\CashAdvance::withoutGlobalScopes()
            ->where('cash_advanceable_type', Worker::class)
            ->where('cash_advanceable_id', $this->worker->id)
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->take(20)
            ->get();
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

        // Special case: task is a revision - always startable
        if ($task->is_revision && $task->status === 'pending') {
            return true;
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
            // Pengaman: Jika ini tahap pertama dan WO memiliki QC Persiapan,
            // pastikan task QC_PERSIAPAN sudah berstatus 'done'.
            if ($wo->has_qc_prep) {
                $stages = $wo->stage_sequence ?? [];
                if (!empty($stages) && $stages[0] === $task->stage_name) {
                    $qcTask = \App\Models\ProductionTask::withoutGlobalScopes()
                        ->where('order_item_id', $task->order_item_id)
                        ->where('stage_name', 'QC_PERSIAPAN')
                        ->first();
                    if ($qcTask && $qcTask->status !== 'done') {
                        return false;
                    }
                }
            }
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

        // Auto-advance WO if at CREATED (before first stage), tanpa notifikasi
        if ($wo->status === WorkOrder::STATUS_CREATED) {
            app(WorkOrderService::class)->advanceSilent($wo->id);
            $wo = $wo->fresh();
        }

        // Set WO started_at if not yet
        if (!$wo->started_at) {
            $wo->update(['started_at' => now()]);
        }

        if ($task->status === 'pending') {
            $task->update(['status' => 'in_progress']);
        }

        // Jika ini QC_PERSIAPAN, pastikan status WO diset ke QC_PREP
        if ($task->stage_name === 'QC_PERSIAPAN' && $wo->status !== WorkOrder::STATUS_QC_PREP) {
            $wo->update([
                'status' => WorkOrder::STATUS_QC_PREP,
                'stage_entered_at' => now(),
            ]);
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
        if ($wo->status === WorkOrder::STATUS_COMPLETED) {
            $currentIdx = count($stages);
        }
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
        if ($allCompleted) $newStatus = 'siap_diambil';
        elseif ($anyInProgress) $newStatus = 'diproses';

        if ($order->status !== $newStatus) {
            $order->update(['status' => $newStatus]);
        }
    }

    public function render()
    {
        return view('livewire.worker-dashboard', [
            'tasks'     => $this->tasks,
            'qcReviews' => $this->qcReviews,
            'qcAkhir'   => $this->qcAkhir,
        ]);
    }

    /**
     * Get WOs yang perlu QC Review (assigned to this worker as QC).
     * Hanya tampilkan WO yang masih ada task belum di-approve.
     */
    public function getQcReviewsProperty(): \Illuminate\Support\Collection
    {
        if (!$this->worker) {
            return collect();
        }

        $wos = WorkOrder::withoutGlobalScopes()
            ->where('status', WorkOrder::STATUS_QC_REVIEW)
            ->where('qc_worker_id', $this->worker->id)
            ->with(['orderItem.order.customer'])
            ->orderBy('stage_entered_at', 'asc')
            ->get();

        // Attach tasks per WO untuk ditampilkan di view
        foreach ($wos as $wo) {
            $wo->setRelation('reviewTasks', $this->getReviewTasksForWo($wo));
        }

        return $wos;
    }

    /**
     * Ambil semua task di stage yang sedang di-review untuk WO ini.
     */
    public function getReviewTasksForWo(WorkOrder $wo): \Illuminate\Support\Collection
    {
        $groupItemIds = $wo->orderItem
            ? $wo->orderItem->getItemsInGroup()->pluck('id')
            : [$wo->order_item_id];

        return ProductionTask::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->where('stage_name', $wo->current_review_stage)
            ->with('assignedTo')
            ->get();
    }

    /**
     * Get WOs in QC_AKHIR status for this QC worker.
     */
    public function getQcAkhirProperty(): \Illuminate\Support\Collection
    {
        if (!$this->worker) return collect();

        return WorkOrder::withoutGlobalScopes()
            ->where('status', WorkOrder::STATUS_QC_AKHIR)
            ->where('qc_worker_id', $this->worker->id)
            ->with(['orderItem.order.customer'])
            ->orderBy('stage_entered_at', 'asc')
            ->get()
            ->each(function ($wo) {
                $groupItemIds = $wo->orderItem
                    ? $wo->orderItem->getItemsInGroup()->pluck('id')
                    : collect([$wo->order_item_id]);
                $wo->setRelation('allTasks', ProductionTask::withoutGlobalScopes()
                    ->whereIn('order_item_id', $groupItemIds)
                    ->with('assignedTo')
                    ->get()
                    ->groupBy('stage_name'));
            });
    }
}
