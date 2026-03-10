<x-filament-panels::page>
    <style>
        .pgl-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .pgl-card {
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            background: rgba(255, 255, 255, 0.95);
            border: 1px solid rgba(0, 0, 0, 0.06);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .pgl-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .pgl-card-label {
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .pgl-card-value {
            font-size: 22px;
            font-weight: 800;
            line-height: 1.2;
        }

        .pgl-card-sub {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 4px;
        }

        .pgl-breakdown {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 1.5rem;
        }

        .pgl-chip {
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb;
        }

        .dark .pgl-card {
            background: rgba(30, 20, 50, 0.7);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .dark .pgl-card-label {
            color: #9ca3af;
        }

        .dark .pgl-card-sub {
            color: #6b7280;
        }

        .dark .pgl-chip {
            background: rgba(255, 255, 255, 0.06);
            color: #d1d5db;
            border-color: rgba(255, 255, 255, 0.1);
        }
    </style>

    <div class="space-y-6">
        {{-- Section 1: Statistik & Ringkasan --}}
        <div>
            {{-- Period Selector --}}
            <div
                class="flex items-center gap-4 bg-white dark:bg-gray-800 p-4 rounded-xl border border-gray-100 dark:border-white/5 mb-4">
                <span class="text-sm font-semibold text-gray-500">Periode Statistik:</span>
                <select wire:model.live="periodo"
                    class="text-sm border-none bg-transparent focus:ring-0 font-bold text-primary-600">
                    <option value="hari_ini">Hari Ini</option>
                    <option value="minggu_ini">Minggu Ini</option>
                    <option value="bulan_ini">Bulan Ini</option>
                    <option value="bulan_lalu">Bulan Lalu</option>
                </select>
            </div>

            {{-- Stat Cards --}}
            <div class="pgl-grid">
                <div class="pgl-card">
                    <div class="pgl-card-label">Grand Total Keluar</div>
                    <div class="pgl-card-value text-red-600">Rp {{ number_format($grandTotal, 0, ',', '.') }}</div>
                    <div class="pgl-card-sub">Operasional + Upah Borongan</div>
                </div>
                <div class="pgl-card">
                    <div class="pgl-card-label">Operasional</div>
                    <div class="pgl-card-value text-amber-500">Rp
                        {{ number_format($totalOperasional, 0, ',', '.') }}
                    </div>
                    <div class="pgl-card-sub">Bahan, transport, alat, dll</div>
                </div>
                <div class="pgl-card">
                    <div class="pgl-card-label">Upah Borongan</div>
                    <div class="pgl-card-value text-primary-600">Rp
                        {{ number_format($totalUpahBorongan, 0, ',', '.') }}
                    </div>
                    <div class="pgl-card-sub">Total dari pekerjaan selesai</div>
                </div>
                <div class="pgl-card">
                    <div class="pgl-card-label">Kasbon Cair</div>
                    <div class="pgl-card-value text-red-500">Rp
                        {{ number_format($totalKasbonExpense, 0, ',', '.') }}
                    </div>
                    <div class="pgl-card-sub">Kasbon yang sudah dicairkan</div>
                </div>
                <div class="pgl-card">
                    <div class="pgl-card-label">Sisa Kasbon Aktif</div>
                    <div class="pgl-card-value {{ $totalKasbonBelumLunas > 0 ? 'text-red-600' : 'text-green-600' }}">
                        Rp {{ number_format($totalKasbonBelumLunas, 0, ',', '.') }}</div>
                    <div class="pgl-card-sub">Hutang belum lunas (Semua Karyawan)</div>
                </div>
            </div>

            {{-- Breakdown per Kategori --}}
            @if($breakdown->isNotEmpty())
                <div class="text-[11px] font-bold text-gray-500 uppercase tracking-wider mb-2">Breakdown per Kategori
                </div>
                <div class="pgl-breakdown">
                    @foreach($breakdown as $kategori => $total)
                        <div class="pgl-chip">{{ $kategori }}: <strong>Rp {{ number_format($total, 0, ',', '.') }}</strong>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <hr class="border-gray-100 dark:border-white/5">

        {{-- Section 2: Jurnal Kas Keluar (Tabel) --}}
        <div>
            <div class="text-sm font-bold text-gray-500 uppercase tracking-wider mb-4">Catatan Pengeluaran (Jurnal)
            </div>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>