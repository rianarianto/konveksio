<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskActionController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\PDFController;
use App\Http\Controllers\WorkTaskController;

Route::get('/', function () {
    return redirect('/app');
});

// Route untuk update status tugas per baris (dari tombol di modal Update Progress)
Route::get('/task-action/{task}/{action}/{item}', [TaskActionController::class, 'handle'])
    ->middleware(['web', 'auth'])
    ->name('filament.admin.resources.control-produksis.task-action');

// Route untuk WorkOrder actions (approve QC, advance dari QC_PREP)
Route::get('/wo-action/{action}/{item}', [TaskActionController::class, 'handleWoAction'])
    ->middleware(['web', 'auth'])
    ->name('filament.admin.resources.control-produksis.wo-action');

// ─── Monitor Produksi (tanpa auth — untuk TV/monitor di ruang produksi) ───────
Route::get('/monitor/{shop}', [MonitorController::class, 'produksi'])
    ->name('monitor.produksi');

// Download Kuitansi PDF
Route::get('/orders/{order}/receipt', [PDFController::class, 'downloadReceipt'])
    ->middleware(['web', 'auth'])
    ->name('orders.receipt');

// Temporary route to seed database on Railway
Route::get('/setup-database', function() {
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
    return 'Database berhasil diisi dengan akun Owner! Silakan kembali ke halaman awal /app untuk login.';
});

// Download Slip Upah PDF
Route::get('/payroll/{payroll}/print', [\App\Http\Controllers\PayrollController::class, 'print'])
    ->middleware(['web', 'auth'])
    ->name('payroll.print');

// ─── Download SPK Produksi (akses publik untuk pekerja) ──────────────────────
Route::get('/tugas/{wo}/spk', [\App\Http\Controllers\PDFController::class, 'downloadSpk'])
    ->name('work.task.spk');

// ─── Halaman Tugas Tukang (akses publik via token, tanpa login) ──────────────
Route::get('/tugas', [WorkTaskController::class, 'index'])
    ->name('work.task');

// ─── Dashboard Worker (list semua tugas worker) ──────────────────────────────
Route::get('/worker/{token}', function (string $token) {
    return view('worker-dashboard', ['token' => $token]);
})->name('worker.dashboard');

// ─── Keuangan Worker (Dompet & Pengajuan Kasbon) ─────────────────────────────
Route::get('/worker/{token}/keuangan', function (string $token) {
    return view('worker-finance', ['token' => $token]);
})->name('worker.finance');
