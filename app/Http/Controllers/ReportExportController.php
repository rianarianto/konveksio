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
}
