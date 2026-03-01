<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Shop;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function produksi(Shop $shop)
    {
        // Items yang sedang DIPROSES (ada task in_progress)
        $inProgress = OrderItem::with([
            'order.customer',
            'productionTasks.assignedTo',
        ])
            ->whereHas('order', fn($q) => $q->where('shop_id', $shop->id))
            ->whereHas('productionTasks', fn($q) => $q->where('status', 'in_progress'))
            ->get()
            ->sortByDesc(fn($item) => $item->order->is_express)
            ->sortBy(fn($item) => $item->order->deadline);

        // Items yang dalam ANTRIAN (ada tasks tapi semua masih pending)
        $antrian = OrderItem::with([
            'order.customer',
            'productionTasks',
        ])
            ->whereHas('order', fn($q) => $q->where('shop_id', $shop->id))
            ->whereHas('productionTasks')
            ->whereDoesntHave('productionTasks', fn($q) => $q->where('status', 'in_progress'))
            ->whereDoesntHave('productionTasks', fn($q) => $q->whereNot('status', 'done'))
            ->orWhereHas('productionTasks', fn($q) => $q->where('status', 'pending'))
            ->get()
            ->filter(fn($item) => $item->order->shop_id === $shop->id)
            ->filter(fn($item) => $item->productionTasks->where('status', 'in_progress')->count() === 0)
            ->filter(fn($item) => $item->productionTasks->where('status', 'done')->count() < $item->productionTasks->count())
            ->sortByDesc(fn($item) => $item->order->is_express)
            ->sortBy(fn($item) => $item->order->deadline)
            ->take(10);

        return view('monitor.produksi', compact('shop', 'inProgress', 'antrian'));
    }
}
