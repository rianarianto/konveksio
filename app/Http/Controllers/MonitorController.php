<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Shop;
use Illuminate\Http\Request;

class MonitorController extends Controller
{
    public function produksi(Shop $shop)
    {
        // 1. Items yang sedang DIPROSES (Sudah ada yang dimulai/selesai, tapi belum selesai semua)
        $inProgress = OrderItem::with(['order.customer', 'productionTasks.assignedTo'])
            ->whereHas('order', fn($q) => $q->where('shop_id', $shop->id))
            ->whereHas('productionTasks', fn($q) => $q->whereIn('status', ['in_progress', 'done']))
            ->whereHas('productionTasks', fn($q) => $q->where('status', '!=', 'done'))
            ->get()
            ->sortBy(function($item) {
                // Urutkan: Express dulu (0), baru deadline terdekat
                return ($item->order->is_express ? '0' : '1') . '_' . $item->order->deadline;
            });

        // 2. Items yang dalam ANTRIAN (BELUM ada tugas yang dimulai sama sekali)
        $antrian = OrderItem::with(['order.customer', 'productionTasks'])
            ->whereHas('order', fn($q) => $q->where('shop_id', $shop->id))
            ->whereHas('productionTasks') // Pastikan sudah diatur tugasnya
            ->whereDoesntHave('productionTasks', fn($q) => $q->whereIn('status', ['in_progress', 'done']))
            ->get()
            ->sortBy(function($item) {
                return ($item->order->is_express ? '0' : '1') . '_' . $item->order->deadline;
            })
            ->take(15);

        return view('monitor.produksi', compact('shop', 'inProgress', 'antrian'));
    }
}
