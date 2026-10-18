<?php

namespace App\Http\Controllers;

use App\Models\CashAdvance;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\ProductionTask;
use App\Models\Shop;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ReportExportController extends Controller
{
    /**
     * Export Laporan Pemasukan (Kas Masuk) ke CSV / Excel
     */
    public function exportPemasukan(Request $request)
    {
        $shopId = Filament::getTenant()?->id ?? auth()->user()->shop_id;
        $from = $request->query('from');
        $until = $request->query('until');

        $query = Payment::withoutGlobalScopes()
            ->with(['order.customer', 'recorder'])
            ->where(function ($q) use ($shopId) {
                $q->where('payments.shop_id', $shopId)
                  ->orWhereHas('order', fn($oq) => $oq->withoutGlobalScopes()->where('shop_id', $shopId));
            });

        if ($from) {
            $query->whereDate('payment_date', '>=', $from);
        }
        if ($until) {
            $query->whereDate('payment_date', '<=', $until);
        }

        $payments = $query->orderBy('payment_date', 'asc')->get();

        $filename = 'Laporan-Kas-Masuk-' . date('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($payments) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 for Excel compatibility
            fputs($file, "\xEF\xBB\xBF");

            // Header kolom
            fputcsv($file, [
                'No',
                'Tanggal',
                'Tipe / Pesanan',
                'Pelanggan / Keterangan',
                'Metode Pembayaran',
                'Nominal (Rp)',
                'Dicatat Oleh',
            ]);

            $no = 1;
            $total = 0;
            foreach ($payments as $p) {
                $isModal = $p->type === 'modal_awal' || !$p->order_id;
                $ref = $isModal ? 'MODAL KAS KECIL' : ($p->order->order_number ?? '-');
                $keterangan = $isModal ? ($p->note ?: 'Modal Harian') : (($p->order->customer->name ?? '-') . ($p->note ? ' (' . $p->note . ')' : ''));
                $total += $p->amount;

                fputcsv($file, [
                    $no++,
                    Carbon::parse($p->payment_date)->format('d/m/Y'),
                    $ref,
                    $keterangan,
                    $p->methodLabel(),
                    $p->amount,
                    $p->recorder->name ?? '-',
                ]);
            }

            // Summary row
            fputcsv($file, ['TOTAL PEMASUKAN', '', '', '', '', $total, '']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Laporan Pengeluaran (Kas Keluar) ke CSV / Excel
     */
    public function exportPengeluaran(Request $request)
    {
        $shopId = Filament::getTenant()?->id ?? auth()->user()->shop_id;
        $from = $request->query('from');
        $until = $request->query('until');

        $query = Expense::withoutGlobalScopes()
            ->with('recorder')
            ->where('shop_id', $shopId);

        if ($from) {
            $query->whereDate('expense_date', '>=', $from);
        }
        if ($until) {
            $query->whereDate('expense_date', '<=', $until);
        }

        $expenses = $query->orderBy('expense_date', 'asc')->get();

        $filename = 'Laporan-Pengeluaran-' . date('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($expenses) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 for Excel compatibility
            fputs($file, "\xEF\xBB\xBF");

            // Header kolom
            fputcsv($file, [
                'No',
                'Tanggal',
                'Keperluan',
                'Kategori',
                'Nominal (Rp)',
                'Dicatat Oleh',
            ]);

            $no = 1;
            $total = 0;
            foreach ($expenses as $e) {
                $total += $e->amount;
                fputcsv($file, [
                    $no++,
                    Carbon::parse($e->expense_date)->format('d/m/Y'),
                    $e->keperluan,
                    $e->note ?: 'Lainnya',
                    $e->amount,
                    $e->recorder->name ?? '-',
                ]);
            }

            // Summary row
            fputcsv($file, ['TOTAL PENGELUARAN', '', '', '', $total, '']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export Laporan Arus Kas Lengkap (Pemasukan + Pengeluaran + Saldo Akhir) PDF / Cetak
     */
    public function downloadPdfSummary(Request $request)
    {
        $shopId = Filament::getTenant()?->id ?? auth()->user()->shop_id;
        $shop = Shop::find($shopId);
        $from = $request->query('from') ?: Carbon::now()->startOfMonth()->toDateString();
        $until = $request->query('until') ?: Carbon::now()->endOfMonth()->toDateString();

        $payments = Payment::withoutGlobalScopes()
            ->with(['order.customer', 'recorder'])
            ->where(function ($q) use ($shopId) {
                $q->where('payments.shop_id', $shopId)
                  ->orWhereHas('order', fn($oq) => $oq->withoutGlobalScopes()->where('shop_id', $shopId));
            })
            ->whereDate('payment_date', '>=', $from)
            ->whereDate('payment_date', '<=', $until)
            ->orderBy('payment_date', 'asc')
            ->get();

        $expenses = Expense::withoutGlobalScopes()
            ->with('recorder')
            ->where('shop_id', $shopId)
            ->whereDate('expense_date', '>=', $from)
            ->whereDate('expense_date', '<=', $until)
            ->orderBy('expense_date', 'asc')
            ->get();

        $totalPemasukan = $payments->sum('amount');
        $totalPengeluaran = $expenses->sum('amount');
        $saldoPeriode = $totalPemasukan - $totalPengeluaran;

        $pdf = app('dompdf.wrapper')->loadView('pdf.laporan-keuangan', [
            'shop' => $shop,
            'from' => $from,
            'until' => $until,
            'payments' => $payments,
            'expenses' => $expenses,
            'totalPemasukan' => $totalPemasukan,
            'totalPengeluaran' => $totalPengeluaran,
            'saldoPeriode' => $saldoPeriode,
        ]);

        $filename = 'Laporan-Keuangan-' . Carbon::parse($from)->format('dMy') . '-' . Carbon::parse($until)->format('dMy') . '.pdf';

        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
        $pdf->save($tempPath);
        return response()->file($tempPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export Laporan Kasbon ke CSV / Excel
     */
    public function exportKasbon(Request $request)
    {
        $shopId = $request->query('shop_id') ?: (Filament::getTenant()?->id ?? auth()->user()->shop_id);
        $from = $request->query('from');
        $until = $request->query('until');

        $query = CashAdvance::withoutGlobalScopes()
            ->with(['cashAdvanceable', 'recorder'])
            ->where('shop_id', $shopId);

        if ($from) {
            $query->whereDate('date', '>=', $from);
        }
        if ($until) {
            $query->whereDate('date', '<=', $until);
        }

        $kasbons = $query->orderBy('date', 'asc')->get();

        $filename = 'Laporan-Kasbon-' . date('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($kasbons) {
            $file = fopen('php://output', 'w');
            // BOM UTF-8 for Excel compatibility
            fputs($file, "\xEF\xBB\xBF");

            // Header kolom
            fputcsv($file, [
                'No',
                'Tanggal',
                'Nama Karyawan / Tukang',
                'Tipe',
                'Status',
                'Nominal (Rp)',
                'Catatan / Keperluan',
                'Dicatat Oleh',
            ]);

            $no = 1;
            $totalPinjaman = 0;
            $totalPelunasan = 0;

            foreach ($kasbons as $k) {
                $isLoan = in_array($k->type, ['loan', 'pinjaman']);
                $personName = $k->cashAdvanceable?->name ?? '-';
                $tipeLabel = $isLoan ? 'Pinjaman (Kasbon)' : 'Pelunasan';
                $statusLabel = match ($k->status) {
                    'approved' => 'Disetujui',
                    'pending'  => 'Pending',
                    'rejected' => 'Ditolak',
                    default    => ucfirst($k->status ?? '-'),
                };

                if ($k->status === 'approved') {
                    if ($isLoan) {
                        $totalPinjaman += $k->amount;
                    } else {
                        $totalPelunasan += $k->amount;
                    }
                }

                fputcsv($file, [
                    $no++,
                    Carbon::parse($k->date)->format('d/m/Y'),
                    $personName,
                    $tipeLabel,
                    $statusLabel,
                    $k->amount,
                    $k->note ?: '-',
                    $k->recorder?->name ?? '-',
                ]);
            }

            // Summary row
            fputcsv($file, []);
            fputcsv($file, ['RINGKASAN KASBON DISETUJUI', '', '', '', '', '', '', '']);
            fputcsv($file, ['Total Pinjaman Dicairkan (Kasbon Keluar)', '', '', '', '', $totalPinjaman, '', '']);
            fputcsv($file, ['Total Pelunasan Diterima (Kas Masuk)', '', '', '', '', $totalPelunasan, '', '']);
            fputcsv($file, ['Sisa Kasbon Belum Lunas Periode Ini', '', '', '', '', ($totalPinjaman - $totalPelunasan), '', '']);

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Cetak Laporan Kasbon PDF
     */
    public function downloadPdfKasbon(Request $request)
    {
        $shopId = $request->query('shop_id') ?: (Filament::getTenant()?->id ?? auth()->user()->shop_id);
        $shop = Shop::find($shopId);
        $from = $request->query('from') ?: Carbon::now()->startOfMonth()->toDateString();
        $until = $request->query('until') ?: Carbon::now()->endOfMonth()->toDateString();

        $kasbons = CashAdvance::withoutGlobalScopes()
            ->with(['cashAdvanceable', 'recorder'])
            ->where('shop_id', $shopId)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $until)
            ->orderBy('date', 'asc')
            ->get();

        $totalPinjaman = $kasbons->where('status', 'approved')->whereIn('type', ['loan', 'pinjaman'])->sum('amount');
        $totalPelunasan = $kasbons->where('status', 'approved')->whereIn('type', ['repayment', 'pelunasan'])->sum('amount');
        $sisaKasbon = $totalPinjaman - $totalPelunasan;

        $pdf = app('dompdf.wrapper')->loadView('pdf.laporan-kasbon', [
            'shop' => $shop,
            'from' => $from,
            'until' => $until,
            'kasbons' => $kasbons,
            'totalPinjaman' => $totalPinjaman,
            'totalPelunasan' => $totalPelunasan,
            'sisaKasbon' => $sisaKasbon,
        ]);

        $filename = 'Laporan-Kasbon-' . Carbon::parse($from)->format('dMy') . '-' . Carbon::parse($until)->format('dMy') . '.pdf';

        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
        $pdf->save($tempPath);
        return response()->file($tempPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export Tabel Bayar Gaji & Upah ke CSV
     */
    public function exportPayrollsCsv(Request $request)
    {
        $shopId = $request->query('shop_id') ?: (Filament::getTenant()?->id ?? auth()->user()->shop_id);
        $tab = $request->query('tab', 'borongan');
        $from = $request->query('from');
        $until = $request->query('until');

        $query = \App\Models\Worker::withoutGlobalScopes()
            ->where('shop_id', $shopId);

        if ($tab === 'bulanan') {
            $query->where('wage_type', 'monthly');
        } else {
            $query->where('wage_type', 'piece_rate')
                ->whereHas('productionTasks', function ($q) use ($from, $until) {
                    $q->where('status', 'done')->where('is_paid', false);
                    if ($from) {
                        $q->whereDate('completed_at', '>=', $from);
                    }
                    if ($until) {
                        $q->whereDate('completed_at', '<=', $until);
                    }
                });
        }

        $workers = $query->with(['productionTasks' => function ($q) use ($from, $until) {
            $q->where('status', 'done')->where('is_paid', false)->with('orderItem');
            if ($from) {
                $q->whereDate('completed_at', '>=', $from);
            }
            if ($until) {
                $q->whereDate('completed_at', '<=', $until);
            }
        }])->orderBy('name')->get();

        $filename = ($tab === 'bulanan' ? 'Laporan-Gaji-Bulanan-' : 'Laporan-Upah-Borongan-') . date('Ymd-His') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"$filename\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $callback = function () use ($workers, $tab) {
            $file = fopen('php://output', 'w');
            fputs($file, "\xEF\xBB\xBF");

            if ($tab === 'bulanan') {
                fputcsv($file, [
                    'No',
                    'Nama Karyawan',
                    'No HP',
                    'Gaji Pokok Kotor (Rp)',
                    'Sisa Kasbon (Rp)',
                    'Gaji Bersih Diterima (Rp)',
                ]);

                $no = 1;
                $totalKotor = 0;
                $totalKasbon = 0;
                $totalBersih = 0;

                foreach ($workers as $w) {
                    $kotor = (int) $w->base_salary;
                    $kasbon = (int) $w->current_cash_advance;
                    $bersih = max(0, $kotor - $kasbon);

                    $totalKotor += $kotor;
                    $totalKasbon += $kasbon;
                    $totalBersih += $bersih;

                    fputcsv($file, [
                        $no++,
                        $w->name,
                        $w->phone ?: '-',
                        $kotor,
                        $kasbon,
                        $bersih,
                    ]);
                }

                fputcsv($file, []);
                fputcsv($file, ['TOTAL', '', '', $totalKotor, $totalKasbon, $totalBersih]);
            } else {
                fputcsv($file, [
                    'No',
                    'Nama Tukang / Pekerja',
                    'Periode Tugas',
                    'Jml Pesanan',
                    'Jml Item',
                    'Total Pcs',
                    'Total Upah Kotor (Rp)',
                    'Sisa Kasbon (Rp)',
                    'Upah Bersih Diterima (Rp)',
                ]);

                $no = 1;
                $totalPcs = 0;
                $totalKotor = 0;
                $totalKasbon = 0;
                $totalBersih = 0;

                foreach ($workers as $w) {
                    $tasks = $w->productionTasks;
                    $minDate = $tasks->min('completed_at');
                    $maxDate = $tasks->max('completed_at');
                    $period = '-';
                    if ($minDate && $maxDate) {
                        $f = Carbon::parse($minDate)->format('d/m/Y');
                        $t = Carbon::parse($maxDate)->format('d/m/Y');
                        $period = $f === $t ? $f : "{$f} - {$t}";
                    }

                    $pesananCount = $tasks->pluck('orderItem.order_id')->filter()->unique()->count();
                    $itemsCount = $tasks->pluck('order_item_id')->filter()->unique()->count();
                    $pcs = (int) $tasks->sum('quantity');
                    $kotor = (int) $tasks->sum('wage_amount');
                    $kasbon = (int) $w->current_cash_advance;
                    $bersih = max(0, $kotor - $kasbon);

                    $totalPcs += $pcs;
                    $totalKotor += $kotor;
                    $totalKasbon += $kasbon;
                    $totalBersih += $bersih;

                    fputcsv($file, [
                        $no++,
                        $w->name,
                        $period,
                        $pesananCount,
                        $itemsCount,
                        $pcs,
                        $kotor,
                        $kasbon,
                        $bersih,
                    ]);
                }

                fputcsv($file, []);
                fputcsv($file, ['TOTAL', '', '', '', '', $totalPcs, $totalKotor, $totalKasbon, $totalBersih]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Download PDF Tabel Bayar Gaji & Upah
     */
    public function downloadPdfPayrolls(Request $request)
    {
        $shopId = $request->query('shop_id') ?: (Filament::getTenant()?->id ?? auth()->user()->shop_id);
        $shop = Shop::find($shopId);
        $tab = $request->query('tab', 'borongan');
        $from = $request->query('from');
        $until = $request->query('until');

        $query = \App\Models\Worker::withoutGlobalScopes()
            ->where('shop_id', $shopId);

        if ($tab === 'bulanan') {
            $query->where('wage_type', 'monthly');
        } else {
            $query->where('wage_type', 'piece_rate')
                ->whereHas('productionTasks', function ($q) use ($from, $until) {
                    $q->where('status', 'done')->where('is_paid', false);
                    if ($from) {
                        $q->whereDate('completed_at', '>=', $from);
                    }
                    if ($until) {
                        $q->whereDate('completed_at', '<=', $until);
                    }
                });
        }

        $workers = $query->with(['productionTasks' => function ($q) use ($from, $until) {
            $q->where('status', 'done')->where('is_paid', false)->with('orderItem');
            if ($from) {
                $q->whereDate('completed_at', '>=', $from);
            }
            if ($until) {
                $q->whereDate('completed_at', '<=', $until);
            }
        }])->orderBy('name')->get();

        $pdf = app('dompdf.wrapper')->loadView('pdf.laporan-payrolls', [
            'shop' => $shop,
            'tab' => $tab,
            'from' => $from,
            'until' => $until,
            'workers' => $workers,
        ]);

        $filename = ($tab === 'bulanan' ? 'Laporan-Gaji-Bulanan-' : 'Laporan-Upah-Borongan-') . date('Ymd-His') . '.pdf';

        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
        $pdf->save($tempPath);
        return response()->file($tempPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    /**
     * Export Detail Rincian Karyawan (Profil + Ringkasan Upah + Riwayat Tugas + Riwayat Kasbon)
     */
    public function exportWorkerDetail(Request $request)
    {
        $workerId = $request->query('worker_id');
        $type = $request->query('type', 'pdf'); // pdf or csv

        $worker = \App\Models\Worker::withoutGlobalScopes()
            ->with([
                'shop',
                'productionTasks.orderItem.order',
                'cashAdvances' => fn($q) => $q->orderBy('date', 'desc'),
                'payrolls' => fn($q) => $q->orderBy('created_at', 'desc'),
            ])
            ->findOrFail($workerId);

        if ($type === 'csv') {
            $filename = 'Rincian-Karyawan-' . \Illuminate\Support\Str::slug($worker->name) . '-' . date('Ymd-His') . '.csv';

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"$filename\"",
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            $callback = function () use ($worker) {
                $file = fopen('php://output', 'w');
                fputs($file, "\xEF\xBB\xBF");

                // Info Karyawan
                fputcsv($file, ['PROFIL KARYAWAN']);
                fputcsv($file, ['Nama', $worker->name]);
                fputcsv($file, ['No HP', $worker->phone ?: '-']);
                fputcsv($file, ['Tipe Upah', $worker->wage_type === 'monthly' ? 'Gaji Bulanan' : 'Upah Borongan']);
                fputcsv($file, ['Status', $worker->is_active ? 'Aktif' : 'Nonaktif']);
                fputcsv($file, ['Sisa Kasbon Saat Ini', $worker->current_cash_advance]);
                fputcsv($file, ['Limit Kasbon', $worker->max_cash_advance]);
                fputcsv($file, []);

                // Ringkasan Upah
                fputcsv($file, ['RINGKASAN UPAH']);
                fputcsv($file, ['Upah Belum Dibayar (Unpaid)', $worker->unpaid_wage]);
                fputcsv($file, ['Upah Bulan Ini', $worker->monthly_earned]);
                fputcsv($file, ['Total Upah Keseluruhan', $worker->total_earned]);
                fputcsv($file, []);

                // Riwayat Pekerjaan
                fputcsv($file, ['RIWAYAT PEKERJAAN & UPAH']);
                fputcsv($file, ['No', 'No Pesanan', 'Produk', 'Tahap', 'Qty', 'Upah (Rp)', 'Status Kerja', 'Status Pembayaran', 'Tanggal Selesai']);
                $no = 1;
                foreach ($worker->productionTasks as $task) {
                    fputcsv($file, [
                        $no++,
                        $task->orderItem?->order?->order_number ?? '-',
                        $task->orderItem?->product_name ?? '-',
                        $task->stage_name,
                        $task->quantity,
                        $task->wage_amount,
                        $task->status,
                        $task->is_paid ? 'Lunas' : 'Belum Dibayar',
                        $task->completed_at ? Carbon::parse($task->completed_at)->format('d/m/Y H:i') : '-',
                    ]);
                }
                fputcsv($file, []);

                // Riwayat Kasbon
                fputcsv($file, ['RIWAYAT KASBON & PELUNASAN']);
                fputcsv($file, ['No', 'Tanggal', 'Jenis', 'Nominal (Rp)', 'Status', 'Catatan']);
                $noK = 1;
                foreach ($worker->cashAdvances as $kasbon) {
                    fputcsv($file, [
                        $noK++,
                        Carbon::parse($kasbon->date)->format('d/m/Y'),
                        $kasbon->type === 'loan' ? 'Pinjaman (Kasbon)' : 'Pelunasan',
                        $kasbon->amount,
                        $kasbon->status,
                        $kasbon->note ?: '-',
                    ]);
                }

                fclose($file);
            };

            return response()->stream($callback, 200, $headers);
        }

        // PDF Generation
        $pdf = app('dompdf.wrapper')->loadView('pdf.worker-detail-report', [
            'worker' => $worker,
            'shop' => $worker->shop,
        ]);

        $filename = 'Rincian-Karyawan-' . \Illuminate\Support\Str::slug($worker->name) . '-' . date('Ymd-His') . '.pdf';

        $tempPath = tempnam(sys_get_temp_dir(), 'pdf_');
        $pdf->save($tempPath);
        return response()->file($tempPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}

