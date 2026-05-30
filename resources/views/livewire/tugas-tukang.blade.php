<div class="app-shell">
    {{-- ── HEADER ── --}}
    <header class="app-header">
        <div class="header-brand">
            <div class="brand-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
            </div>
            <span class="brand-name">Konveksio</span>
        </div>

        @if($wo)
        <div class="header-wo-badge">{{ $wo->wo_number }}</div>
        @endif
    </header>

    @if(!$wo)
    {{-- ── ERROR STATE ── --}}
    <div class="empty-state">
        <div class="empty-icon">🔒</div>
        <h2>Link Tidak Valid</h2>
        <p>Work Order tidak ditemukan atau token sudah tidak berlaku.</p>
    </div>

    @else
    {{-- ── WORKER GREETING ── --}}
    @php
        $worker = null;
        foreach (\App\Models\WorkOrder::STATUS_WORKER_MAP as $s => $col) {
            if ($wo->$col) { $worker = $wo->$col; break; }
        }
        $workerName = $worker?->name ?? 'Pekerja';
        $isCompleted = $wo->isCompleted();
        $isQcStage = $wo->isInQcStage();
        $isWorkerStage = $wo->isInWorkerStage();
        $statusLabel = $wo->status_label;
        $orderItem = $wo->orderItem;
        $order = $orderItem?->order;
        $customer = $order?->customer;
        $sizes = $orderItem?->size_and_request_details ?? [];
        $varianUkuran = $sizes['varian_ukuran'] ?? [];
        $totalQty = $orderItem?->quantity ?? 0;
    @endphp

    <div class="greeting-bar">
        <div>
            <div class="greeting-hello">Halo, {{ $workerName }}!</div>
            <div class="greeting-sub">Portal Produksi Aktif</div>
        </div>
        <div class="greeting-status">
            <span class="status-dot {{ $isCompleted ? 'dot-green' : ($isQcStage ? 'dot-blue' : 'dot-purple') }}"></span>
            <span class="status-text">{{ $statusLabel }}</span>
        </div>
    </div>

    {{-- ── TAB NAV ── --}}
    <div class="tab-nav">
        <button wire:click="$set('activeTab', 'tugas')"
                class="tab-btn {{ $activeTab === 'tugas' ? 'tab-active' : '' }}">
            🔧 Pekerjaan
        </button>
        <button wire:click="$set('activeTab', 'akunting')"
                class="tab-btn {{ $activeTab === 'akunting' ? 'tab-active' : '' }}">
            💰 Dompet
        </button>
    </div>

    {{-- ══════════════════════════════════════════════════ --}}
    {{-- TAB: PEKERJAAN --}}
    {{-- ══════════════════════════════════════════════════ --}}
    @if($activeTab === 'tugas')

    {{-- Kartu Progress WO --}}
    <div class="card">
        <div class="card-header">
            <span class="card-label">Detail Work Order</span>
            <span class="wo-badge {{ $isCompleted ? 'badge-green' : ($isQcStage ? 'badge-blue' : 'badge-purple') }}">
                {{ $statusLabel }}
            </span>
        </div>

        <div class="info-grid">
            <div class="info-item">
                <span class="info-label">Nomor WO</span>
                <span class="info-value mono">{{ $wo->wo_number }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Produk</span>
                <span class="info-value">{{ $orderItem?->product_name ?? '-' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Pelanggan</span>
                <span class="info-value">{{ $customer?->name ?? '-' }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Total Qty</span>
                <span class="info-value font-bold">{{ $totalQty }} pcs</span>
            </div>
        </div>

        @if($orderItem?->decoration_type || ($orderItem?->size_and_request_details['sablon_jenis'] ?? null))
        <div class="decoration-tag">
            @php
                $decType = $wo->decoration_type ?? ($orderItem?->size_and_request_details['sablon_jenis'] ?? null);
                $decLabels = [
                    'bordir_reguler' => '🧵 Bordir Reguler',
                    'bordir_3d'      => '🧵 Bordir 3D',
                    'sablon_dtf'     => '🖨️ Sablon DTF',
                    'sablon_manual'  => '🖨️ Sablon Manual',
                ];
            @endphp
            <span class="decor-label">{{ $decLabels[$decType] ?? $decType }}</span>
        </div>
        @endif
    </div>

    {{-- Rincian Ukuran --}}
    @if(count($varianUkuran) > 0)
    <div class="card">
        <div class="card-header">
            <span class="card-label">Rincian Ukuran</span>
        </div>
        <div class="size-grid">
            @foreach($varianUkuran as $varian)
            <div class="size-chip">
                <span class="size-label">{{ $varian['ukuran'] ?? '?' }}</span>
                <span class="size-qty">{{ $varian['jumlah'] ?? $varian['qty'] ?? '?' }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Progress Tahapan --}}
    <div class="card">
        <div class="card-header">
            <span class="card-label">Alur Produksi</span>
        </div>
        <div class="stage-list">
            @php
                $allStatuses = array_keys(\App\Models\WorkOrder::STATUS_LABELS);
                $currentIndex = array_search($wo->status, $allStatuses);
            @endphp
            @foreach(\App\Models\WorkOrder::STATUS_LABELS as $s => $label)
            @php
                $idx = array_search($s, $allStatuses);
                $isDone = $idx < $currentIndex;
                $isCurrent = $s === $wo->status;
                $isPending = $idx > $currentIndex;
            @endphp
            <div class="stage-item {{ $isCurrent ? 'stage-current' : ($isDone ? 'stage-done' : 'stage-pending') }}">
                <div class="stage-dot">
                    @if($isDone) ✓
                    @elseif($isCurrent) ●
                    @else ○
                    @endif
                </div>
                <span class="stage-name">{{ $label }}</span>
            </div>
            @endforeach
        </div>
    </div>

    {{-- ── AKSI UTAMA ── --}}
    @if($isCompleted)
    <div class="action-banner banner-green">
        <span>✅ Work Order ini sudah selesai!</span>
    </div>

    @elseif($isQcStage)
    {{-- QC Actions --}}
    <div class="action-card">
        <div class="action-title">⚙️ Verifikasi QC — {{ $statusLabel }}</div>
        <p class="action-desc">Periksa hasil pengerjaan. Tekan Approve jika sudah sesuai standar, atau Reject jika perlu diperbaiki.</p>

        <div class="reject-info">
            @if($wo->reject_reason)
            <div class="alert-reject">
                <strong>⚠️ Catatan Penolakan Sebelumnya:</strong><br>
                {{ $wo->reject_reason }}
            </div>
            @endif
        </div>

        <button wire:click="approveQC"
                wire:loading.attr="disabled"
                class="btn btn-primary btn-full">
            <span wire:loading.remove wire:target="approveQC">✅ Approve & Lanjutkan</span>
            <span wire:loading wire:target="approveQC">⏳ Memproses...</span>
        </button>

        @if(!$showRejectForm)
        <button wire:click="$set('showRejectForm', true)"
                class="btn btn-outline btn-full mt-sm">
            ❌ Reject / Kembalikan
        </button>
        @else
        {{-- Form Reject --}}
        <div class="reject-form">
            <label class="form-label">Alasan Penolakan <span class="required">*</span></label>
            <textarea wire:model="rejectReason"
                      class="form-textarea"
                      placeholder="Jelaskan kenapa hasil ini perlu diperbaiki..."
                      rows="3"></textarea>
            @error('rejectReason') <span class="form-error">{{ $message }}</span> @enderror

            <label class="form-label mt-sm">Foto Bukti (Opsional)</label>
            <input type="file" wire:model="rejectProof" class="form-file" accept="image/*">
            @error('rejectProof') <span class="form-error">{{ $message }}</span> @enderror

            <div class="btn-row mt-sm">
                <button wire:click="rejectQC"
                        wire:loading.attr="disabled"
                        class="btn btn-danger btn-half">
                    <span wire:loading.remove wire:target="rejectQC">Kirim Reject</span>
                    <span wire:loading wire:target="rejectQC">⏳...</span>
                </button>
                <button wire:click="$set('showRejectForm', false)"
                        class="btn btn-outline btn-half">
                    Batal
                </button>
            </div>
        </div>
        @endif
    </div>

    @elseif($isWorkerStage)
    {{-- Tukang Actions --}}
    <div class="action-card">
        <div class="action-title">🔧 Tugas Anda: {{ $statusLabel }}</div>

        @if(!$wo->started_at)
        <button wire:click="mulaiKerjakan"
                wire:loading.attr="disabled"
                class="btn btn-primary btn-full btn-lg">
            <span wire:loading.remove wire:target="mulaiKerjakan">🔥 Mulai Kerjakan</span>
            <span wire:loading wire:target="mulaiKerjakan">⏳ Memulai...</span>
        </button>
        @else
        <div class="started-info">
            ⏱️ Dimulai: {{ $wo->started_at?->format('d M Y, H:i') }}
        </div>
        <button wire:click="selesaiKerjakan"
                wire:loading.attr="disabled"
                class="btn btn-success btn-full btn-lg">
            <span wire:loading.remove wire:target="selesaiKerjakan">✅ Selesai & Kirim ke QC</span>
            <span wire:loading wire:target="selesaiKerjakan">⏳ Memproses...</span>
        </button>
        @endif
    </div>

    @elseif($wo->status === 'CREATED')
    <div class="action-banner banner-gray">
        <span>⏳ Menunggu persetujuan QC Persiapan...</span>
    </div>
    @endif

    @error('aksi')
    <div class="alert-error">{{ $message }}</div>
    @enderror

    @endif {{-- end tab tugas --}}


    {{-- ══════════════════════════════════════════════════ --}}
    {{-- TAB: AKUNTING / DOMPET --}}
    {{-- ══════════════════════════════════════════════════ --}}
    @if($activeTab === 'akunting')
    @if(!$worker)
    <div class="empty-state">
        <div class="empty-icon">👷</div>
        <p>Data pekerja tidak ditemukan.</p>
    </div>
    @else
    @php
        $unpaid   = (int) $worker->unpaid_wage;
        $kasbon   = (int) $worker->current_cash_advance;
        $limit    = (int) $worker->max_cash_advance;
        $sisa_limit = max(0, $limit - $kasbon);
        // Proyeksi WO aktif: gunakan ProductionTask yang masih aktif
        $activeProjection = (int) \App\Models\ProductionTask::where('assigned_to', $worker->id)
            ->whereIn('status', ['pending', 'in_progress'])
            ->sum('wage_amount');
        $total = $unpaid + $activeProjection;
    @endphp

    <div class="wallet-hero">
        <div class="wallet-label">Estimasi Upah Total (Termasuk WO Aktif)</div>
        <div class="wallet-total">Rp {{ number_format($total, 0, ',', '.') }}</div>
        <div class="wallet-breakdown">
            <div class="wallet-sub">
                <div class="sub-value">Rp {{ number_format($unpaid, 0, ',', '.') }}</div>
                <div class="sub-label">Upah Selesai</div>
            </div>
            <div class="wallet-divider"></div>
            <div class="wallet-sub">
                <div class="sub-value">Rp {{ number_format($activeProjection, 0, ',', '.') }}</div>
                <div class="sub-label">WO Aktif (Proyeksi)</div>
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
            class="btn btn-primary btn-full">
        💸 Ajukan Kasbon Baru
    </button>
    @else
    <div class="card">
        <div class="card-header">
            <span class="card-label">Form Pengajuan Kasbon</span>
        </div>

        <label class="form-label">Nominal <span class="required">*</span></label>
        <input type="number" wire:model="kasbonAmount"
               class="form-input"
               placeholder="Contoh: 200000"
               min="10000">
        @error('kasbonAmount') <span class="form-error">{{ $message }}</span> @enderror

        <label class="form-label mt-sm">Catatan (Opsional)</label>
        <input type="text" wire:model="kasbonNote"
               class="form-input"
               placeholder="Keperluan kasbon...">

        @error('kasbon') <span class="form-error">{{ $message }}</span> @enderror

        <div class="btn-row mt-sm">
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
    @endif {{-- end $worker check --}}

    @endif {{-- end tab akunting --}}

    {{-- ── FOOTER ── --}}
    <footer class="app-footer">
        <p>© {{ date('Y') }} Konveksio · Portal Produksi</p>
    </footer>

    @endif {{-- end $wo check --}}

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
    font-family: 'Courier New', monospace;
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
.greeting-status { display: flex; align-items: center; gap: 6px; margin-top: 4px; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dot-purple { background: #8000FF; }
.dot-blue   { background: #3B82F6; }
.dot-green  { background: #22C55E; }
.status-text { font-size: 12px; font-weight: 600; color: #374151; }

/* ── Tab Nav ── */
.tab-nav {
    display: flex;
    background: white;
    padding: 10px 20px;
    gap: 8px;
    border-bottom: 2px solid #F3F4F6;
}
.tab-btn {
    flex: 1;
    padding: 9px 0;
    border: 1.5px solid #E5E7EB;
    background: white;
    color: #6B7280;
    font-family: inherit;
    font-size: 13px;
    font-weight: 600;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.15s ease;
}
.tab-active {
    background: #8000FF;
    color: white;
    border-color: #8000FF;
}

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
.info-value.mono { font-family: 'Courier New', monospace; font-size: 13px; }
.font-bold { font-weight: 700; }
.text-purple { color: #8000FF; }
.text-danger { color: #EF4444; }

/* ── WO Status Badge ── */
.wo-badge {
    font-size: 11px; font-weight: 700;
    padding: 4px 10px; border-radius: 20px;
}
.badge-purple { background: #F2E6FF; color: #6600CC; }
.badge-blue   { background: #EFF6FF; color: #1D4ED8; }
.badge-green  { background: #F0FDF4; color: #15803D; }

/* ── Decoration Tag ── */
.decoration-tag {
    margin-top: 12px;
    padding: 10px 14px;
    background: #F9F5FF;
    border-radius: 10px;
    border: 1px solid #E6CCFF;
}
.decor-label { font-size: 13px; font-weight: 700; color: #6600CC; }

/* ── Size Grid ── */
.size-grid { display: flex; flex-wrap: wrap; gap: 8px; }
.size-chip {
    display: flex; flex-direction: column; align-items: center;
    padding: 8px 14px;
    background: #F9FAFB;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    min-width: 56px;
}
.size-label { font-size: 12px; font-weight: 700; color: #374151; }
.size-qty { font-size: 20px; font-weight: 800; color: #8000FF; line-height: 1.2; }

/* ── Stage List ── */
.stage-list { display: flex; flex-direction: column; gap: 6px; }
.stage-item { display: flex; align-items: center; gap: 10px; padding: 7px 10px; border-radius: 8px; }
.stage-done    { background: #F0FDF4; }
.stage-current { background: #F2E6FF; border: 1px solid #CC99FF; }
.stage-pending { opacity: 0.4; }
.stage-dot { font-size: 14px; font-weight: 700; color: #8000FF; width: 18px; text-align: center; }
.stage-done .stage-dot { color: #22C55E; }
.stage-name { font-size: 13px; font-weight: 600; color: #374151; }
.stage-current .stage-name { color: #6600CC; font-weight: 700; }

/* ── Action Card ── */
.action-card {
    background: white;
    border-radius: 16px;
    padding: 20px;
    margin: 12px 16px 0;
    box-shadow: 0 2px 12px rgba(128,0,255,0.08);
    border: 1.5px solid #E6CCFF;
}
.action-title { font-size: 16px; font-weight: 800; color: #111827; margin-bottom: 6px; }
.action-desc  { font-size: 13px; color: #6B7280; margin-bottom: 16px; line-height: 1.5; }
.started-info { font-size: 12px; color: #6B7280; margin-bottom: 10px; }

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
.btn-lg { padding: 16px 20px; font-size: 16px; }
.btn-primary { background: #8000FF; color: white; }
.btn-primary:not(:disabled):hover { background: #6600CC; transform: translateY(-1px); }
.btn-success { background: #16A34A; color: white; }
.btn-success:not(:disabled):hover { background: #15803D; }
.btn-danger   { background: #DC2626; color: white; }
.btn-danger:not(:disabled):hover { background: #B91C1C; }
.btn-outline  { background: white; color: #374151; border: 1.5px solid #E5E7EB; }
.btn-outline:hover { border-color: #8000FF; color: #8000FF; }
.btn-half { flex: 1; }
.btn-row { display: flex; gap: 10px; }
.mt-sm { margin-top: 12px; }

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
.form-textarea { resize: vertical; min-height: 80px; }
.form-file { width: 100%; font-size: 13px; color: #6B7280; }
.form-error { display: block; font-size: 12px; color: #EF4444; margin-top: 4px; }
.reject-form { margin-top: 16px; padding-top: 16px; border-top: 1px solid #F3F4F6; }

/* ── Banners ── */
.action-banner {
    margin: 12px 16px 0;
    padding: 14px 18px;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
}
.banner-green { background: #F0FDF4; color: #15803D; border: 1px solid #BBF7D0; }
.banner-gray  { background: #F9FAFB; color: #6B7280; border: 1px solid #E5E7EB; }

/* ── Alert ── */
.alert-error   { margin: 8px 16px; padding: 12px 16px; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 10px; color: #991B1B; font-size: 13px; }
.alert-success { margin: 8px 0; padding: 12px 16px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 10px; color: #15803D; font-size: 13px; font-weight: 600; }
.alert-reject  { padding: 10px 14px; background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 10px; color: #92400E; font-size: 13px; margin-bottom: 12px; }
.reject-info { margin-bottom: 4px; }

/* ── Wallet Hero ── */
.wallet-hero {
    margin: 12px 16px 0;
    background: linear-gradient(135deg, #8000FF 0%, #6600CC 100%);
    border-radius: 20px;
    padding: 24px 20px;
    color: white;
    box-shadow: 0 8px 32px rgba(128,0,255,0.25);
}
.wallet-label { font-size: 11px; font-weight: 700; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
.wallet-total { font-size: 30px; font-weight: 800; letter-spacing: -1px; margin-bottom: 16px; }
.wallet-breakdown { display: flex; align-items: center; gap: 16px; }
.wallet-sub { flex: 1; }
.sub-value { font-size: 15px; font-weight: 700; }
.sub-label { font-size: 11px; opacity: 0.75; margin-top: 2px; }
.wallet-divider { width: 1px; height: 36px; background: rgba(255,255,255,0.25); }

/* ── Progress Bar ── */
.progress-bar-wrap { height: 6px; background: #F3F4F6; border-radius: 6px; overflow: hidden; margin-top: 12px; }
.progress-bar-fill { height: 100%; border-radius: 6px; transition: width 0.3s ease; }
.progress-label { font-size: 11px; color: #9CA3AF; margin-top: 4px; }

/* ── Empty State ── */
.empty-state {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 60px 32px; text-align: center; gap: 12px;
}
.empty-icon { font-size: 48px; }
.empty-state h2 { font-size: 20px; font-weight: 800; color: #111827; }
.empty-state p  { font-size: 14px; color: #9CA3AF; }

/* ── Footer ── */
.app-footer { margin-top: auto; padding: 24px; text-align: center; font-size: 12px; color: #D1D5DB; }
</style>
</div>
