<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Filament\Facades\Filament;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Builder;
use Barryvdh\DomPDF\Facade\Pdf;

class DashboardRow3Widget extends Widget
{
    use WithPagination;

    protected string $view = 'filament.widgets.dashboard-row3-widget';
    protected int|string|array $columnSpan = 'full';
    protected static ?int $sort = 4;

    // Sembunyikan dari Owner — mereka punya widget sendiri
    public static function canView(): bool
    {
        return auth()->check() && in_array(auth()->user()->role, ['admin', 'owner']);
    }

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

        if (auth()->user()->role === 'owner') {
            $query->whereNotIn('status', ['selesai', 'diambil', 'batal'])
                ->where(function (Builder $query) {
                    $query->whereDate('deadline', '<=', now()->addDays(3))
                        ->orWhereRaw('total_price > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.order_id = orders.id)');
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

    public function downloadReceipt($orderId)
    {
        $order = Order::with(['customer', 'shop', 'orderItems', 'payments'])->findOrFail($orderId);

        $filename = 'Kuitansi-' . str_replace('#', '', $order->order_number) . '.pdf';

        return response()->streamDownload(function () use ($order) {
            echo Pdf::loadView('pdf.receipt', ['order' => $order])->output();
        }, $filename);
    }

    public function deleteOrder($orderId)
    {
        $order = Order::find($orderId);
        if ($order) {
            $order->delete();
        }
    }

    public function getViewData(): array
    {
        return [
            'orders' => $this->getOrders(),
        ];
    }
}
