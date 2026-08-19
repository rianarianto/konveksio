<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reset-transactions {--force : Paksa eksekusi tanpa konfirmasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mereset & mengosongkan seluruh data transaksi dummy (Order, WorkOrder, Retur, Payroll, Notifikasi) tanpa menghapus Master Data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (!$this->option('force')) {
            if (!$this->confirm('⚠️ PERINGATAN: Seluruh data transaksi (Order, WorkOrder, Retur, Pembayaran, Payroll) akan DIHAPUS. Lanjutkan?')) {
                $this->info('Proses reset dibatalkan.');
                return 0;
            }
        }

        $this->info('🔄 Memulai pembersihan data transaksi...');

        Schema::disableForeignKeyConstraints();

        $transactionTables = [
            'production_tasks',
            'work_orders',
            'order_returns',
            'payments',
            'worker_payrolls',
            'order_items',
            'orders',
            'notifications',
        ];

        foreach ($transactionTables as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->truncate();
                $this->line("  ✓ Table [{$table}] berhasil di-truncate (Auto-increment di-reset).");
            }
        }

        Schema::enableForeignKeyConstraints();

        $this->info('✅ SELURUH DATA TRANSAKSI DUMMY BERHASIL DIBERSIHKAN!');
        $this->info('💡 Master data (Users, Shops, Workers, Produk/Kain) tetap aman & utuh.');

        return 0;
    }
}
