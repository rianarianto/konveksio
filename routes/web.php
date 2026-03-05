<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskActionController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\PDFController;

Route::get('/', function () {
    return view('welcome');
});

// Route untuk update status tugas per baris (dari tombol di modal Update Progress)
Route::get('/task-action/{task}/{action}/{item}', [TaskActionController::class, 'handle'])
    ->middleware(['web', 'auth'])
    ->name('filament.admin.resources.control-produksis.task-action');

// ─── Monitor Produksi (tanpa auth — untuk TV/monitor di ruang produksi) ───────
Route::get('/monitor/{shop}', [MonitorController::class, 'produksi'])
    ->name('monitor.produksi');

// Download Kuitansi PDF
Route::get('/orders/{order}/receipt', [PDFController::class, 'downloadReceipt'])
    ->middleware(['web', 'auth'])
    ->name('orders.receipt');

