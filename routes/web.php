<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TaskActionController;

Route::get('/', function () {
    return view('welcome');
});

// Route untuk update status tugas per baris (dari tombol di modal Update Progress)
Route::get('/task-action/{task}/{action}/{item}', [TaskActionController::class, 'handle'])
    ->middleware(['web', 'auth'])
    ->name('filament.admin.resources.control-produksis.task-action');
