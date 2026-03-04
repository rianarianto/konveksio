<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Worker;

class EmployeeWorkloadSidebar extends Component
{
    public $search = '';

    public function render()
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if (!$tenant) {
            $employees = collect();
        } else {
            $shopId = $tenant->id;

            $employees = Worker::where('shop_id', $shopId)
                ->where('is_active', true)
                ->when($this->search, fn($q) => $q->where('name', 'like', '%' . $this->search . '%'))
                ->withSum([
                    'productionTasks as in_progress_qty' => function ($query) {
                        $query->where('status', 'in_progress');
                    }
                ], 'quantity')
                ->withSum([
                    'productionTasks as pending_qty' => function ($query) {
                        $query->where('status', 'pending');
                    }
                ], 'quantity')
                ->get()
                ->map(function ($w) {
                    $w->active_workload = ($w->in_progress_qty ?? 0) + ($w->pending_qty ?? 0);
                    return $w;
                })
                ->filter(fn($w) => $w->active_workload > 0)
                ->sortByDesc('active_workload');
        }

        return view('livewire.employee-workload-sidebar', compact('employees'));
    }
}
