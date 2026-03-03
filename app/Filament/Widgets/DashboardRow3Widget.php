<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Filament\Facades\Filament;
use Livewire\WithPagination;

class DashboardRow3Widget extends Widget
{
    use WithPagination;

    protected string $view = 'filament.widgets.dashboard-row3-widget';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 4;

    public $search = '';
    public $statusFilter = '';
    public $deadlineFilter = '';
    public $perPage = 5;

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['search', 'statusFilter', 'deadlineFilter', 'perPage'])) {
            $this->resetPage();
        }
    }

    protected function getOrders()
    {
        $tenantId = Filament::getTenant()?->id;

        $query = Order::query()
            ->where('shop_id', $tenantId)
            ->with(['customer', 'orderItems.productionTasks']);

        if (!empty($this->search)) {
            $query->where(function ($q) {
                $q->where('order_number', 'like', '%' . $this->search . '%')
                  ->orWhereHas('customer', function ($cq) {
                      $cq->where('name', 'like', '%' . $this->search . '%')
                         ->orWhere('phone', 'like', '%' . $this->search . '%');
                  });
            });
        }

        if (!empty($this->statusFilter)) {
            $query->where('status', $this->statusFilter);
        }

        if (!empty($this->deadlineFilter)) {
            $query->whereDate('deadline', $this->deadlineFilter);
        }

        // Default order by deadline so urgent ones are on top
        return $query->orderByRaw('is_express DESC')->orderBy('deadline', 'asc')->paginate($this->perPage);
    }

    public function getViewData(): array
    {
        return [
            'orders' => $this->getOrders(),
        ];
    }
}
