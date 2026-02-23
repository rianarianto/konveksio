<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\User;

class EmployeeWorkloadSidebar extends Component
{
    public function render()
    {
        $tenant = \Filament\Facades\Filament::getTenant();
        if (!$tenant) {
            $employees = collect();
        } else {
            $shopId = $tenant->id;
            
            $employees = User::where('shop_id', $shopId)
                ->withSum(['assignedProductionTasks as active_workload' => function($query) {
                    $query->whereIn('status', ['pending', 'in_progress']);
                }], 'quantity')
                ->get()
                ->filter(fn ($u) => ($u->active_workload ?? 0) > 0)
                ->sortByDesc('active_workload');
        }

        return view('livewire.employee-workload-sidebar', compact('employees'));
    }
}
