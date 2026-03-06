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

        .pgl-table-wrap {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid rgba(0, 0, 0, 0.06);
            background: #fff;
        }

        .pgl-table {
            width: 100%;
            border-collapse: collapse;
        }

        .pgl-table th {
            padding: 10px 16px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b7280;
            font-weight: 700;
            background: #f9fafb;
            border-bottom: 2px solid #e5e7eb;
        }

        .pgl-table td {
            padding: 10px 16px;
            font-size: 13px;
            color: #374151;
            border-bottom: 1px solid #f3f4f6;
        }

        .pgl-table tr:last-child td {
            border-bottom: none;
        }

        .pgl-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
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

        .dark .pgl-table-wrap {
            background: rgba(30, 20, 50, 0.7);
            border-color: rgba(255, 255, 255, 0.08);
        }

        .dark .pgl-table th {
            background: rgba(255, 255, 255, 0.04);
            color: #9ca3af;
            border-color: rgba(255, 255, 255, 0.08);
        }

        .dark .pgl-table td {
            color: #e5e7eb;
            border-color: rgba(255, 255, 255, 0.04);
        }

        .dark .pgl-chip {
            background: rgba(255, 255, 255, 0.06);
            color: #d1d5db;
            border-color: rgba(255, 255, 255, 0.1);
        }
    </style>

    {{-- Header --}}
    <div style="margin-bottom: 1.5rem;">
        <h2 style="font-size: 18px; font-weight: 800; color: var(--fi-foreground); margin: 0;">📊 Rekap Pengeluaran —
            {{ $periodoLabel }}</h2>
        <p style="font-size: 13px; color: #9ca3af; margin-top: 4px;">Ringkasan semua uang keluar dari kas konveksi.</p>
    </div>

    {{-- Stat Cards --}}
    <div class="pgl-grid">
        <div class="pgl-card">
            <div class="pgl-card-label">Grand Total Keluar</div>
            <div class="pgl-card-value" style="color: #dc2626;">Rp {{ number_format($grandTotal, 0, ',', '.') }}</div>
            <div class="pgl-card-sub">Operasional + Upah Borongan</div>
        </div>
        <div class="pgl-card">
            <div class="pgl-card-label">Operasional</div>
            <div class="pgl-card-value" style="color: #f59e0b;">Rp {{ number_format($totalOperasional, 0, ',', '.') }}
            </div>
            <div class="pgl-card-sub">Bahan, transport, alat, dll</div>
        </div>
        <div class="pgl-card">
            <div class="pgl-card-label">Upah Borongan Tukang</div>
            <div class="pgl-card-value" style="color: #8b5cf6;">Rp {{ number_format($totalUpahBorongan, 0, ',', '.') }}
            </div>
            <div class="pgl-card-sub">Total dari pekerjaan selesai</div>
        </div>
        <div class="pgl-card">
            <div class="pgl-card-label">Kasbon Cair</div>
            <div class="pgl-card-value" style="color: #ef4444;">Rp {{ number_format($totalKasbonExpense, 0, ',', '.') }}
            </div>
            <div class="pgl-card-sub">Kasbon yang sudah dicairkan</div>
        </div>
        <div class="pgl-card">
            <div class="pgl-card-label">Total Kasbon Belum Lunas</div>
            <div class="pgl-card-value" style="color: {{ $totalKasbonBelumLunas > 0 ? '#dc2626' : '#10b981' }};">Rp
                {{ number_format($totalKasbonBelumLunas, 0, ',', '.') }}</div>
            <div class="pgl-card-sub">Hutang aktif semua karyawan</div>
        </div>
    </div>

    {{-- Breakdown per Kategori --}}
    @if($breakdown->isNotEmpty())
        <div
            style="margin-bottom: 0.5rem; font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em;">
            Breakdown per Kategori</div>
        <div class="pgl-breakdown">
            @foreach($breakdown as $kategori => $total)
                <div class="pgl-chip">{{ $kategori }}: <strong>Rp {{ number_format($total, 0, ',', '.') }}</strong></div>
            @endforeach
        </div>
    @endif

    {{-- Detail Table (Filament Widget) --}}
    <div style="margin-top: 1rem;">
        @livewire(\App\Filament\Resources\Keuangans\Widgets\PengeluaranTableWidget::class)
    </div>
</x-filament-panels::page>