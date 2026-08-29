<div class="app-shell">
    {{-- ── HEADER ── --}}
    <header class="app-header">
        <div class="header-brand">
            @if(file_exists(public_path('images/logo.jpeg')))
            <img src="{{ asset('images/logo.jpeg') }}" alt="{{ $worker->shop->name ?? 'Dunia Bordir Komputer' }}" style="height: 32px; width: auto; max-width: 100px; object-fit: contain; border-radius: 6px; margin-right: 8px;">
            @else
            <div class="brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </div>
            @endif
            <span class="brand-name">{{ $worker->shop->name ?? 'Dunia Bordir Komputer' }}</span>
        </div>
        <div class="header-wo-badge">Keuangan</div>
    </header>

    @if(!$worker)
    <div class="empty-state">
        <div class="empty-icon">🔒</div>
        <h2>Link Tidak Valid</h2>
        <p>Akun tidak ditemukan atau sudah tidak aktif.</p>
    </div>
    @else

    {{-- Back Nav to Dashboard --}}
    <div class="back-nav" style="padding: 12px 16px 4px;">
        <a href="{{ route('worker.dashboard', ['token' => $worker->portal_token]) }}" class="btn-back" style="display: inline-flex; align-items: center; font-size: 13px; font-weight: 700; color: #8000FF; text-decoration: none; background: #F2E6FF; padding: 6px 12px; border-radius: 8px; transition: background 0.15s;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="margin-right:4px;">
                <line x1="19" y1="12" x2="5" y2="12"></line>
                <polyline points="12 19 5 12 12 5"></polyline>
            </svg>
            Kembali ke Dashboard
        </a>
    </div>

    {{-- ── GREETING ── --}}
    <div class="greeting-bar">
        <div>
            <div class="greeting-hello">Keuangan Saya</div>
            <div class="greeting-sub">{{ $worker->name }}</div>
        </div>
    </div>

    {{-- ── DOMPET & KASBON (Portal Keuangan Pribadi) ── --}}
    @php
        $unpaid   = (int) $worker->unpaid_wage;
        $kasbon   = (int) $worker->current_cash_advance;
        $limit    = (int) $worker->max_cash_advance;
        $sisa_limit = max(0, $limit - $kasbon);
        $activeProjection = (int) \App\Models\ProductionTask::withoutGlobalScopes()
            ->where('assigned_to', $worker->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->sum('wage_amount');
        $total = $unpaid + $activeProjection;
    @endphp

    <div class="wallet-hero" style="margin: 12px 16px 0; background: linear-gradient(135deg, #8000FF 0%, #6600CC 100%); border-radius: 20px; padding: 24px 20px; color: white; box-shadow: 0 8px 32px rgba(128,0,255,0.25);">
        <div class="wallet-label" style="font-size: 11px; font-weight: 700; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px;">Estimasi Upah Total (Termasuk WO Aktif)</div>
        <div class="wallet-total" style="font-size: 30px; font-weight: 800; letter-spacing: -1px; margin-bottom: 16px;">Rp {{ number_format($total, 0, ',', '.') }}</div>
        <div class="wallet-breakdown" style="display: flex; align-items: center; gap: 16px;">
            <div class="wallet-sub" style="flex: 1;">
                <div class="sub-value" style="font-size: 15px; font-weight: 700;">Rp {{ number_format($unpaid, 0, ',', '.') }}</div>
                <div class="sub-label" style="font-size: 11px; opacity: 0.75; margin-top: 2px;">Upah Selesai</div>
            </div>
            <div class="wallet-divider" style="width: 1px; height: 36px; background: rgba(255,255,255,0.25);"></div>
            <div class="wallet-sub" style="flex: 1;">
                <div class="sub-value" style="font-size: 15px; font-weight: 700;">Rp {{ number_format($activeProjection, 0, ',', '.') }}</div>
                <div class="sub-label" style="font-size: 11px; opacity: 0.75; margin-top: 2px;">WO Aktif (Proyeksi)</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Sisa Kasbon Sekarang</span>
                <span class="info-value {{ $kasbon > 0 ? 'text-danger' : '' }}">
                    Rp {{ number_format($kasbon, 0, ',', '.') }}
                </span>
            </div>
            <div class="info-item">
                <span class="info-label">Limit Pengajuan (70%)</span>
                <span class="info-value {{ $sisa_limit <= 0 ? 'text-danger' : 'text-purple' }}">
                    Rp {{ number_format($sisa_limit, 0, ',', '.') }}
                </span>
            </div>
        </div>

        {{-- Progress bar kasbon --}}
        @if($limit > 0)
        @php $pct = min(100, round(($kasbon / $limit) * 100)); @endphp
        <div class="progress-bar-wrap">
            <div class="progress-bar-fill" style="width: {{ $pct }}%; background: {{ $pct >= 80 ? '#ef4444' : '#8000FF' }}"></div>
        </div>
        <div class="progress-label">{{ $pct }}% dari limit kasbon terpakai</div>
        @endif
    </div>

    @if(session('kasbon_success'))
    <div class="alert-success">✅ {{ session('kasbon_success') }}</div>
    @endif

    @if(!$showKasbonForm)
    <button wire:click="$set('showKasbonForm', true)"
            class="btn btn-primary btn-full"
            style="margin: 12px 16px 0; width: calc(100% - 32px);">
        💸 Ajukan Kasbon Baru
    </button>
    @else
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-header">
            <span class="card-label">Form Pengajuan Kasbon</span>
        </div>

        <label class="form-label">Nominal <span class="required">*</span></label>
        <input type="number" wire:model="kasbonAmount"
               class="form-input"
               placeholder="Contoh: 200000"
               min="10000">
        @error('kasbonAmount') <span class="form-error">{{ $message }}</span> @enderror

        <label class="form-label mt-sm" style="margin-top: 12px;">Catatan (Opsional)</label>
        <input type="text" wire:model="kasbonNote"
               class="form-input"
               placeholder="Keperluan kasbon...">

        @error('kasbon') <span class="form-error">{{ $message }}</span> @enderror

        <div class="btn-row mt-sm" style="margin-top: 12px; display: flex; gap: 10px;">
            <button wire:click="ajukanKasbon"
                    wire:loading.attr="disabled"
                    class="btn btn-primary btn-half">
                <span wire:loading.remove wire:target="ajukanKasbon">Ajukan</span>
                <span wire:loading wire:target="ajukanKasbon">⏳...</span>
            </button>
            <button wire:click="$set('showKasbonForm', false)"
                    class="btn btn-outline btn-half">
                Batal
            </button>
        </div>
    </div>
    @endif

    {{-- ── FOOTER ── --}}
    <footer class="app-footer">
        <p>© {{ date('Y') }} Konveksio · Portal Produksi</p>
    </footer>
    @endif

<style>
/* ── Layout ── */
.app-shell {
    max-width: 480px;
    margin: 0 auto;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    padding-bottom: 32px;
}

/* ── Header ── */
.app-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px 14px;
    background: white;
    border-bottom: 1px solid #F3F4F6;
    position: sticky;
    top: 0;
    z-index: 10;
}
.header-brand { display: flex; align-items: center; gap: 10px; }
.brand-icon {
    width: 34px; height: 34px;
    background: #8000FF;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: white;
}
.brand-name { font-weight: 800; font-size: 16px; color: #111827; letter-spacing: -0.3px; }
.header-wo-badge {
    font-size: 11px; font-weight: 700;
    background: #F2E6FF; color: #6600CC;
    border: 1px solid #E6CCFF;
    padding: 4px 10px; border-radius: 20px;
}

/* ── Greeting ── */
.greeting-bar {
    display: flex; justify-content: space-between; align-items: flex-start;
    padding: 16px 20px;
    background: white;
    border-bottom: 1px solid #F3F4F6;
}
.greeting-hello { font-size: 20px; font-weight: 800; color: #111827; letter-spacing: -0.3px; }
.greeting-sub { font-size: 13px; color: #9CA3AF; margin-top: 2px; }

/* ── Cards ── */
.card {
    background: white;
    border-radius: 16px;
    padding: 18px 20px;
    margin: 12px 16px 0;
    box-shadow: 0 1px 4px rgba(0,0,0,0.05);
    border: 1px solid #F3F4F6;
}
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}
.card-label { font-size: 12px; font-weight: 700; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.5px; }

/* ── Info Grid ── */
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.info-item { display: flex; flex-direction: column; gap: 3px; }
.info-label { font-size: 11px; color: #9CA3AF; font-weight: 600; text-transform: uppercase; letter-spacing: 0.4px; }
.info-value { font-size: 14px; font-weight: 600; color: #111827; }
.text-purple { color: #8000FF; }
.text-danger { color: #EF4444; }

/* ── Buttons ── */
.btn {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    padding: 14px 20px;
    border: none;
    border-radius: 12px;
    font-family: inherit;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    text-align: center;
    white-space: nowrap;
}
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-full { width: 100%; }
.btn-primary { background: #8000FF; color: white; }
.btn-primary:not(:disabled):hover { background: #6600CC; transform: translateY(-1px); }
.btn-outline  { background: white; color: #374151; border: 1.5px solid #E5E7EB; }
.btn-outline:hover { border-color: #8000FF; color: #8000FF; }
.btn-half { flex: 1; }

/* ── Forms ── */
.form-label { display: block; font-size: 12px; font-weight: 700; color: #374151; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.4px; }
.required { color: #EF4444; }
.form-input, .form-textarea {
    width: 100%;
    padding: 11px 14px;
    border: 1.5px solid #E5E7EB;
    border-radius: 10px;
    font-family: inherit;
    font-size: 14px;
    color: #111827;
    background: white;
    transition: border-color 0.15s;
    outline: none;
}
.form-input:focus, .form-textarea:focus { border-color: #8000FF; }

/* ── Progress Bar ── */
.progress-bar-wrap { height: 6px; background: #F3F4F6; border-radius: 6px; overflow: hidden; margin-top: 12px; }
.progress-bar-fill { height: 100%; border-radius: 6px; transition: width 0.3s ease; }
.progress-label { font-size: 11px; color: #9CA3AF; margin-top: 4px; }

/* ── Alerts ── */
.alert-success { margin: 12px 16px 0; padding: 12px 16px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 10px; color: #15803D; font-size: 13px; font-weight: 600; }

/* ── Footer ── */
.app-footer { margin-top: auto; padding: 24px; text-align: center; font-size: 12px; color: #D1D5DB; }
</style>
</div>
