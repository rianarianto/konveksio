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
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 700;
            background: #f3f4f6;
            color: #6b7280;
            border: 1px solid #e5e7eb;
            display: inline-flex;
            align-items: center;
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
            {{-- Period Selector & Custom Range (Left-aligned & Correct Colors) --}}
            <div class="bg-white dark:bg-gray-900 p-4 rounded-2xl border border-gray-100 dark:border-white/5 mb-4 shadow-sm">
                <div class="flex flex-col md:flex-row md:items-center justify-start gap-6">
                    {{-- Judul Periode --}}
                    <div class="flex items-center gap-3">
                        <div class="p-2 bg-primary-50 dark:bg-primary-500/10 rounded-lg">
                            <svg class="w-4 h-4 text-primary-600" style="color: #8000FF;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <div class="min-w-[120px]">
                            <div class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Periode</div>
                            <div class="text-sm font-bold text-gray-800 dark:text-white leading-tight">{{ $periodoLabel }}</div>
                        </div>
                    </div>
                    
                    {{-- Button Group (Rata Kiri) --}}
                    <div class="flex flex-wrap items-center bg-gray-50 dark:bg-white/5 p-1 rounded-xl gap-1">
                        @foreach([
                            'hari_ini' => 'Hari Ini',
                            'minggu_ini' => 'Minggu Ini',
                            'bulan_ini' => 'Bulan Ini',
                            'bulan_lalu' => 'Bulan Lalu',
                            'custom' => 'Custom'
                        ] as $val => $label)
                            <button 
                                wire:click="$set('periodo', '{{ $val }}')"
                                @class([
                                    'px-3 py-1.5 rounded-lg text-[11px] font-bold transition-all duration-200',
                                    'text-white' => $periodo === $val,
                                    'text-gray-500 hover:text-gray-700 dark:hover:bg-white/5' => $periodo !== $val,
                                ])
                                @if($periodo === $val) 
                                    style="background-color: #8000FF !important; box-shadow: 0 4px 10px rgba(128, 0, 255, 0.3);" 
                                @endif
                            >
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                @if($periodo === 'custom')
                    <div class="mt-2 pt-2 border-gray-100 dark:border-white/5 flex flex-wrap items-center justify-start gap-4 animate-in fade-in slide-in-from-top-1 duration-300">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-400 uppercase">Dari:</span>
                            <input type="date" wire:model.live="dari_tgl" 
                                class="bg-gray-50 dark:bg-gray-800 border-none rounded-lg text-xs font-semibold py-1.5 px-3 focus:ring-1 focus:ring-primary-200 transition-all">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold text-gray-400 uppercase">Sampai:</span>
                            <input type="date" wire:model.live="sampai_tgl" 
                                class="bg-gray-50 dark:bg-gray-800 border-none rounded-lg text-xs font-semibold py-1.5 px-3 focus:ring-1 focus:ring-primary-200 transition-all">
                        </div>
                    </div>
                @endif
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
    </div>
</x-filament-panels::page>