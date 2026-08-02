<?php

namespace App\Livewire;

use App\Models\Worker;
use App\Models\ProductionTask;
use App\Models\CashAdvance;
use Livewire\Component;

class WorkerFinance extends Component
{
    public string $token;
    public ?Worker $worker = null;

    // Kasbon form
    public bool   $showKasbonForm = false;
    public string $kasbonAmount   = '';
    public string $kasbonNote     = '';

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
            'shop_id'               => $worker->shop_id,
            'cash_advanceable_type' => Worker::class,
            'cash_advanceable_id'   => $worker->id,
            'type'                  => 'loan',
            'status'                => 'pending',
            'amount'                => $amount,
            'note'                  => $this->kasbonNote ?: 'Pengajuan via portal keuangan',
            'date'                  => now()->toDateString(),
            'recorded_by'           => null,
        ]);

        $this->reset('kasbonAmount', 'kasbonNote', 'showKasbonForm');
        $this->loadWorker();
        session()->flash('kasbon_success', 'Pengajuan kasbon berhasil dikirim! Menunggu persetujuan Admin.');
    }

    public function render()
    {
        return view('livewire.worker-finance');
    }
}
