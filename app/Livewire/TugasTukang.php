<?php

namespace App\Livewire;

use App\Models\WorkOrder;
use App\Models\CashAdvance;
use App\Services\WorkOrderService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

class TugasTukang extends Component
{
    use WithFileUploads;

    // Props
    public int    $woId;
    public string $token;

    // State
    public string $activeTab    = 'tugas';    // 'tugas' | 'akunting'
    public bool   $showRejectForm = false;

    // Reject form
    public string $rejectReason = '';
    public        $rejectProof  = null;        // file upload

    // Kasbon form
    public bool   $showKasbonForm = false;
    public string $kasbonAmount   = '';
    public string $kasbonNote     = '';

    // Loaded data
    public ?WorkOrder $wo = null;

    public function mount(int $woId, string $token): void
    {
        $this->woId  = $woId;
        $this->token = $token;
        $this->loadWorkOrder();
    }

    protected function loadWorkOrder(): void
    {
        $this->wo = WorkOrder::withoutGlobalScopes()
            ->with([
                'orderItem.order.customer',
                'shop',
                'cuttingWorker',
                'sewingWorker',
                'decorationWorker',
                'buttonWorker',
                'finishingWorker',
            ])
            ->where('id', $this->woId)
            ->where('token', $this->token)
            ->first();
    }

    // ── Aksi Tukang ──────────────────────────────────────────────────────────

    public function mulaiKerjakan(): void
    {
        $this->ensureValidWo();

        if ($this->wo->status !== WorkOrder::STATUS_CREATED && !$this->wo->isInWorkerStage()) {
            $this->addError('aksi', 'Anda tidak bisa memulai tugas ini saat ini.');
            return;
        }

        app(WorkOrderService::class)->startTask($this->wo->id);
        $this->syncOrderStatus();
        $this->loadWorkOrder();
        $this->dispatch('refreshed');
    }

    public function selesaiKerjakan(): void
    {
        $this->ensureValidWo();

        if (!$this->wo->isInWorkerStage()) {
            $this->addError('aksi', 'Tugas ini tidak bisa diselesaikan saat ini.');
            return;
        }

        // Find and mark the current worker's task as done
        $worker = $this->resolveCurrentWorker();
        if ($worker) {
            $groupItemIds = $this->wo->orderItem
                ? $this->wo->orderItem->getItemsInGroup()->pluck('id')
                : [$this->wo->order_item_id];
            
            $task = \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItemIds)
                ->where('stage_name', $this->wo->status)
                ->where('assigned_to', $worker->id)
                ->first();
            
            if ($task) {
                $task->update(['status' => 'done', 'completed_at' => now()]);
            }
        }

        app(WorkOrderService::class)->advance($this->wo->id);
        $this->syncOrderStatus();
        $this->loadWorkOrder();
        $this->dispatch('refreshed');
    }

    // ── Aksi QC ──────────────────────────────────────────────────────────────

    public function approveQC(): void
    {
        $this->ensureValidWo();

        if (!$this->wo->isInQcStage()) {
            $this->addError('aksi', 'Tidak dalam tahap QC.');
            return;
        }

        app(WorkOrderService::class)->approveQC($this->wo->id);
        $this->syncOrderStatus();
        $this->loadWorkOrder();
        $this->dispatch('refreshed');
    }

    public function rejectQC(): void
    {
        $this->validate([
            'rejectReason' => 'required|string|min:5',
            'rejectProof'  => 'nullable|image|max:5120',
        ], [
            'rejectReason.required' => 'Alasan penolakan wajib diisi.',
            'rejectReason.min'      => 'Alasan minimal 5 karakter.',
        ]);

        $this->ensureValidWo();

        $proofPath = null;
        if ($this->rejectProof) {
            $proofPath = $this->rejectProof->store('wo-rejects', 'public');
        }

        app(WorkOrderService::class)->rejectQC(
            $this->wo->id,
            $this->rejectReason,
            $proofPath
        );

        $this->syncOrderStatus();
        $this->reset('rejectReason', 'rejectProof', 'showRejectForm');
        $this->loadWorkOrder();
        $this->dispatch('refreshed');
    }

    // ── Aksi Kasbon ──────────────────────────────────────────────────────────

    public function ajukanKasbon(): void
    {
        $this->validate([
            'kasbonAmount' => 'required|numeric|min:10000',
            'kasbonNote'   => 'nullable|string|max:255',
        ], [
            'kasbonAmount.required' => 'Nominal kasbon wajib diisi.',
            'kasbonAmount.min'      => 'Minimal kasbon Rp 10.000.',
        ]);

        // Tentukan pekerja dari WO
        $worker = $this->resolveCurrentWorker();

        if (!$worker) {
            $this->addError('kasbon', 'Data pekerja tidak ditemukan.');
            return;
        }

        $amount = (int) str_replace(['.', ','], '', $this->kasbonAmount);
        $limit  = (int) $worker->max_cash_advance - (int) $worker->current_cash_advance;

        if ($amount > $limit) {
            $this->addError('kasbon', 'Nominal melebihi limit kasbon Anda (Rp ' . number_format($limit, 0, ',', '.') . ').');
            return;
        }

        CashAdvance::create([
            'cash_advanceable_type' => \App\Models\Worker::class,
            'cash_advanceable_id'   => $worker->id,
            'type'                  => 'pinjaman',
            'amount'                => $amount,
            'note'                  => $this->kasbonNote ?: 'Pengajuan via portal tugas',
            'date'                  => now()->toDateString(),
            'recorded_by'           => null,
        ]);

        // Update current_cash_advance
        $worker->increment('current_cash_advance', $amount);

        $this->reset('kasbonAmount', 'kasbonNote', 'showKasbonForm');
        $this->loadWorkOrder();
        session()->flash('kasbon_success', 'Kasbon berhasil diajukan!');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    protected function ensureValidWo(): void
    {
        if (!$this->wo) {
            abort(403, 'Work Order tidak valid.');
        }
    }

    /**
     * Sync Order status berdasarkan status WO.
     */
    protected function syncOrderStatus(): void
    {
        $order = $this->wo->orderItem?->order;
        if (!$order) return;

        $groupItemIds = $order->orderItems()->pluck('id');
        $wos = WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->get();

        if ($wos->isEmpty()) return;

        $allCompleted = $wos->every(fn($wo) => $wo->isCompleted());
        $anyInProgress = $wos->some(fn($wo) => !$wo->isCompleted() && $wo->status !== WorkOrder::STATUS_CREATED);

        $newStatus = 'antrian';
        if ($allCompleted) $newStatus = 'selesai';
        elseif ($anyInProgress) $newStatus = 'diproses';

        if ($order->status !== $newStatus) {
            $order->update(['status' => $newStatus]);
        }
    }

    protected function resolveCurrentWorker(): ?\App\Models\Worker
    {
        if (!$this->wo) return null;

        // Cari worker dari salah satu relasi yang ada
        $workerCols = [
            'cutting_worker_id', 'sewing_worker_id', 'decoration_worker_id',
            'button_worker_id', 'finishing_worker_id',
        ];
        foreach ($workerCols as $col) {
            $workerId = $this->wo->$col;
            if ($workerId) {
                $worker = \App\Models\Worker::find($workerId);
                if ($worker) return $worker;
            }
        }

        return null;
    }

    public function getWorkerAttribute(): ?\App\Models\Worker
    {
        return $this->resolveCurrentWorker();
    }

    public function render()
    {
        return view('livewire.tugas-tukang');
    }
}
