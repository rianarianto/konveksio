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
        <div class="header-wo-badge">Portal Pekerja</div>
    </header>

    @if(!$worker)
    <div class="empty-state">
        <div class="empty-icon">🔒</div>
        <h2>Link Tidak Valid</h2>
        <p>Akun tidak ditemukan atau sudah tidak aktif.</p>
    </div>
    @else

    {{-- ── GREETING ── --}}
    <div class="greeting-bar">
        <div>
            <div class="greeting-hello">Halo, {{ $worker->name }}!</div>
            <div class="greeting-sub">Dashboard & Keuangan Produksi</div>
        </div>
        <div class="greeting-status">
            <span class="status-dot dot-purple"></span>
            <span class="status-text">{{ $tasks['in_progress']->count() + $tasks['pending']->count() }} tugas aktif</span>
        </div>
    </div>

    {{-- ── TOP NAVIGATION TABS ── --}}
    <div class="main-tabs-wrap">
        <button wire:click="$set('activeTab', 'tugas')" class="tab-btn {{ $activeTab === 'tugas' ? 'active' : '' }}">
            📋 Tugas
            @if($tasks['in_progress']->count() + $tasks['pending']->count() > 0)
            <span class="tab-badge">{{ $tasks['in_progress']->count() + $tasks['pending']->count() }}</span>
            @endif
        </button>
        <button wire:click="$set('activeTab', 'riwayat')" class="tab-btn {{ $activeTab === 'riwayat' ? 'active' : '' }}">
            📜 Riwayat
        </button>
        <button wire:click="$set('activeTab', 'keuangan')" class="tab-btn {{ $activeTab === 'keuangan' ? 'active' : '' }}">
            💰 Keuangan
        </button>
    </div>

    {{-- Flash Messages --}}
    @if(session()->has('success'))
    <div class="alert-success">✅ {{ session('success') }}</div>
    @endif
    @if(session()->has('kasbon_success'))
    <div class="alert-success">✅ {{ session('kasbon_success') }}</div>
    @endif
    @if(session()->has('kasbon_error'))
    <div class="alert-error">⚠️ {{ session('kasbon_error') }}</div>
    @endif
    @if(isset($errors) && $errors->has('aksi'))
    <div class="alert-error">{{ $errors->first('aksi') }}</div>
    @endif

    {{-- ========================================================================= --}}
    {{-- TAB 1: TUGAS PRODUKSI --}}
    {{-- ========================================================================= --}}
    @if($activeTab === 'tugas')

        {{-- Status Filter Sub-Pills --}}
        <div class="sub-filter-wrap">
            <button wire:click="$set('statusFilter', 'semua')" class="sub-pill {{ $statusFilter === 'semua' ? 'active' : '' }}">
                Semua
            </button>
            <button wire:click="$set('statusFilter', 'in_progress')" class="sub-pill {{ $statusFilter === 'in_progress' ? 'active' : '' }}">
                ⚡ Dikerjakan ({{ $tasks['in_progress']->count() }})
            </button>
            <button wire:click="$set('statusFilter', 'pending')" class="sub-pill {{ $statusFilter === 'pending' ? 'active' : '' }}">
                ⏳ Antrian ({{ $tasks['pending']->count() }})
            </button>
            <button wire:click="$set('statusFilter', 'done')" class="sub-pill {{ $statusFilter === 'done' ? 'active' : '' }}">
                ✅ Selesai ({{ $tasks['done']->count() }})
            </button>
        </div>

        {{-- ── QC REVIEW (for QC workers only) ── --}}
        @if(in_array($statusFilter, ['semua', 'in_progress']) && $qcReviews->count() > 0)
        <div class="section-header">
            <span class="section-dot dot-orange"></span>
            <span class="section-title">Perlu QC Review</span>
            <span class="section-count">{{ $qcReviews->count() }}</span>
        </div>

        @foreach($qcReviews as $wo)
        <div class="card task-card task-review" style="border:1.5px solid #fbbf24;">
            <a href="{{ route('work.task', ['wo_id' => $wo->id, 'token' => $wo->token, 'wt' => $worker->portal_token]) }}"
               style="text-decoration:none;color:inherit;display:block;">
                <div class="task-header" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                    <div class="task-info" style="flex:1;">
                        <div class="task-stage task-stage-review">Review: {{ str_replace('_', ' ', $wo->current_review_stage ?? '') }}</div>
                        <div class="task-product">{{ $wo->orderItem?->product_name ?? '-' }}</div>
                        <div class="task-customer">
                            <span class="mono" style="font-weight:600;color:#475569;">{{ $wo->orderItem?->order?->order_number ?? '-' }}</span> •
                            {{ $wo->orderItem?->order?->customer?->name ?? '-' }} •
                            {{ $wo->orderItem ? $wo->orderItem->getItemsInGroup()->sum('quantity') : 0 }} pcs
                            @if($wo->orderItem?->order?->is_express)
                            <span class="express-badge">⚡ Express</span>
                            @endif
                        </div>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0;">
                        <span style="font-size:11px; font-weight:700; color:#d97706; background:#fef3c7; border-radius:20px; padding:4px 10px; white-space:nowrap;">
                            🔍 Perlu Review
                        </span>
                        <span class="btn btn-warning btn-sm" style="font-size:11px; padding:6px 12px; border-radius:8px; background:#f59e0b; color:white; font-weight:600; cursor:pointer;">
                            Buka Detail
                        </span>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
        @endif

        {{-- ── QC AKHIR ── --}}
        @if(in_array($statusFilter, ['semua', 'in_progress']) && $qcAkhir->count() > 0)
        <div class="section-header">
            <span class="section-dot" style="background:#7C3AED;"></span>
            <span class="section-title">QC Akhir — Verifikasi Final</span>
            <span class="section-count">{{ $qcAkhir->count() }}</span>
        </div>

        @foreach($qcAkhir as $qaWo)
        <div class="card task-card" style="border:1.5px solid #C084FC;">
            <a href="{{ route('work.task', ['wo_id' => $qaWo->id, 'token' => $qaWo->token, 'wt' => $worker->portal_token]) }}"
               style="text-decoration:none;color:inherit;display:block;">
                <div class="task-header" style="display:flex; justify-space-between; align-items:center; gap:12px;">
                    <div class="task-info" style="flex:1;">
                        <div class="task-stage" style="background:#F3E8FF;color:#7C3AED; display:inline-block; padding:2px 8px; border-radius:6px; font-weight:700; font-size:12px; margin-bottom:4px;">QC Akhir</div>
                        <div class="task-product">{{ $qaWo->orderItem?->product_name ?? '-' }}</div>
                        <div class="task-customer">
                            <span class="mono" style="font-weight:600;color:#475569;">{{ $qaWo->orderItem?->order?->order_number ?? '-' }}</span> •
                            {{ $qaWo->orderItem?->order?->customer?->name ?? '-' }} •
                            {{ $qaWo->orderItem ? $qaWo->orderItem->getItemsInGroup()->sum('quantity') : 0 }} pcs
                            @if($qaWo->orderItem?->order?->is_express)
                            <span class="express-badge">⚡ Express</span>
                            @endif
                        </div>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0;">
                        <span style="font-size:11px; font-weight:700; color:#7c3aed; background:#f3e8ff; border-radius:20px; padding:4px 10px; white-space:nowrap;">
                            🏁 QC Akhir
                        </span>
                        <span class="btn btn-sm" style="font-size:11px; padding:6px 12px; border-radius:8px; background:#7c3aed; color:white; font-weight:600; cursor:pointer;">
                            Buka Detail
                        </span>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
        @endif

        {{-- ── SEDANG DIKERJAKAN ── --}}
        @if(in_array($statusFilter, ['semua', 'in_progress']) && $tasks['in_progress']->count() > 0)
        <div class="section-header">
            <span class="section-dot dot-blue"></span>
            <span class="section-title">Sedang Dikerjakan</span>
            <span class="section-count">{{ $tasks['in_progress']->count() }}</span>
        </div>

        @foreach($tasks['in_progress'] as $task)
        @php $wo = $task->workOrder; @endphp
        @if($wo)<a href="{{ route('work.task', ['wo_id' => $wo->id, 'token' => $wo->token, 'wt' => $worker->portal_token]) }}" style="text-decoration:none;color:inherit;display:block;">@endif
        <div class="card task-card task-working">
            <div class="task-header" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <div class="task-info" style="flex:1;">
                    <div class="task-stage" style="color:#059669; font-weight:700;">{{ str_replace('_', ' ', $task->stage_name) }}</div>
                    <div class="task-product">{{ $task->orderItem?->product_name ?? '-' }}</div>
                    <div class="task-customer">
                        <span class="mono" style="font-weight:600;color:#475569;">{{ $task->orderItem?->order?->order_number ?? '-' }}</span> •
                        {{ $task->orderItem?->order?->customer?->name ?? '-' }} • 
                        {{ $task->orderItem ? $task->orderItem->getItemsInGroup()->sum('quantity') : $task->quantity }} pcs
                        @if($task->orderItem?->order?->is_express)
                        <span class="express-badge">⚡ Express</span>
                        @endif
                    </div>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0;">
                    <span style="font-size:11px;font-weight:700;color:#047857;background:#d1fae5;border-radius:20px;padding:4px 10px;white-space:nowrap;">
                        ⚡ Dikerjakan
                    </span>
                    <span class="btn btn-primary btn-sm" style="font-size:11px; padding:6px 12px; border-radius:8px; font-weight:600; cursor:pointer;">
                        Buka Detail
                    </span>
                </div>
            </div>

            @php $progress = $this->getStageProgress($task); @endphp
            @if(count($progress) > 0)
            <div class="stage-progress">
                @foreach($progress as $stage)
                <span class="stage-tag @if($stage['status'] === 'done') stage-done @elseif($stage['status'] === 'current') stage-current @else stage-pending @endif @if($stage['is_task_stage']) stage-highlight @endif">
                    {{ str_replace('_', ' ', $stage['name']) }}
                </span>
                @endforeach
            </div>
            @endif
        </div>
        @if($wo)</a>@endif
        @endforeach
        @endif

        {{-- ── ANTRIAN PEKERJAAN ── --}}
        @if(in_array($statusFilter, ['semua', 'pending']) && $tasks['pending']->count() > 0)
        <div class="section-header">
            <span class="section-dot dot-yellow"></span>
            <span class="section-title">Antrian Pekerjaan</span>
            <span class="section-count">{{ $tasks['pending']->count() }}</span>
        </div>

        @foreach($tasks['pending'] as $task)
        @php
            $canStart = $this->canStartTask($task);
            $blocking = $canStart ? null : $this->getBlockingStage($task);
            $progress = $this->getStageProgress($task);
            $isRevision = $task->is_revision ?? false;
            $wo = $task->workOrder;
        @endphp
        @if($wo)<a href="{{ route('work.task', ['wo_id' => $wo->id, 'token' => $wo->token, 'wt' => $worker->portal_token]) }}" style="text-decoration:none;color:inherit;display:block;">@endif
        <div class="card task-card {{ $canStart ? ($isRevision ? 'task-revision' : 'task-ready') : '' }}">
            <div class="task-header" style="display:flex; justify-content:space-between; align-items:center; gap:12px;">
                <div class="task-info" style="flex:1;">
                    @if($isRevision)
                    <div style="background:#fee2e2;color:#b91c1c;font-weight:700;font-size:12px;padding:2px 10px;border-radius:20px;display:inline-block;margin-bottom:6px;">
                        🔄 RETUR PERBAIKAN — {{ str_replace('_', ' ', $task->stage_name) }}
                    </div>
                    @else
                    <div class="task-stage">{{ str_replace('_', ' ', $task->stage_name) }}</div>
                    @endif
                    <div class="task-product">{{ $task->orderItem?->product_name ?? '-' }}</div>
                    <div class="task-customer">
                        <span class="mono" style="font-weight:600;color:#475569;">{{ $task->orderItem?->order?->order_number ?? '-' }}</span> •
                        {{ $task->orderItem?->order?->customer?->name ?? '-' }} • 
                        @if($isRevision)
                        @php
                            $hasReturnRec = \App\Models\OrderReturn::where('order_item_id', $task->order_item_id)->whereIn('status', ['pending', 'diproses'])->exists();
                        @endphp
                        <b style="color:#b91c1c;">{{ $task->quantity }} pcs {{ $hasReturnRec ? 'retur' : 'revisi' }}</b>
                        @else
                        {{ $task->orderItem ? $task->orderItem->getItemsInGroup()->sum('quantity') : $task->quantity }} pcs
                        @endif
                        @if($task->orderItem?->order?->is_express)
                        <span class="express-badge">⚡ Express</span>
                        @endif
                    </div>
                </div>
                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px; flex-shrink:0;">
                    @if($canStart)
                        <span class="btn btn-primary btn-sm" style="font-size:11px; padding:6px 12px; border-radius:8px; font-weight:800; cursor:pointer; {{ $isRevision ? 'background:#dc2626; border-color:#dc2626; color:#ffffff !important;' : 'color:#ffffff !important;' }}">
                            {{ $isRevision ? '🔧 Perbaiki Sekarang' : 'Buka Detail' }}
                        </span>
                    @else
                        <span style="font-size:11px;font-weight:700;color:#6b7280;background:#f3f4f6;border-radius:20px;padding:4px 10px;white-space:nowrap;">
                            🔒 Terkunci
                        </span>
                    @endif
                </div>
            </div>

            @if(count($progress) > 0)
            <div class="stage-progress">
                @foreach($progress as $stage)
                <span class="stage-tag @if($stage['status'] === 'done') stage-done @elseif($stage['status'] === 'current') stage-current @else stage-pending @endif @if($stage['is_task_stage']) stage-highlight @endif">
                    {{ str_replace('_', ' ', $stage['name']) }}
                </span>
                @endforeach
            </div>
            @endif

            @if(!$canStart && $blocking)
            <div class="blocking-info">⏳ Menunggu: {{ str_replace('_', ' ', $blocking) }}</div>
            @endif
        </div>
        @if($wo)</a>@endif
        @endforeach
        @endif

        {{-- ── PEKERJAAN SELESAI ── --}}
        @if(in_array($statusFilter, ['semua', 'done']) && $tasks['done']->count() > 0)
        <div class="section-header">
            <span class="section-dot dot-green"></span>
            <span class="section-title">Pekerjaan Selesai</span>
            <span class="section-count">{{ $tasks['done_total'] }}</span>
        </div>

        @foreach($tasks['done'] as $task)
        @php $wo = $task->workOrder; @endphp
        @if($wo)<a href="{{ route('work.task', ['wo_id' => $wo->id, 'token' => $wo->token, 'wt' => $worker->portal_token]) }}" style="text-decoration:none;color:inherit;display:block;">@endif
        <div class="card task-card task-done">
            <div class="task-header">
                <div class="task-info">
                    <div class="task-stage">{{ str_replace('_', ' ', $task->stage_name) }}</div>
                    <div class="task-product">{{ $task->orderItem?->product_name ?? '-' }}</div>
                    <div class="task-customer">
                        <span class="mono" style="font-weight:600;color:#475569;">{{ $task->orderItem?->order?->order_number ?? '-' }}</span> •
                        {{ $task->orderItem?->order?->customer?->name ?? '-' }} • 
                        {{ $task->orderItem ? $task->orderItem->getItemsInGroup()->sum('quantity') : $task->quantity }} pcs
                        @if($task->completed_at)
                        <span class="task-date">• {{ $task->completed_at->format('d M') }}</span>
                        @endif
                    </div>
                </div>
                <span class="done-check">✓</span>
            </div>
        </div>
        @if($wo)</a>@endif
        @endforeach

        @if($tasks['done_pages'] > 1)
        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:12px; padding:10px 14px; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
            <button wire:click="prevPage('done')" @if($pageDone <= 1) disabled @endif style="padding:6px 14px; font-size:12px; font-weight:700; border-radius:8px; border:1px solid #cbd5e1; background:{{ $pageDone <= 1 ? '#f1f5f9' : '#ffffff' }}; color:{{ $pageDone <= 1 ? '#94a3b8' : '#1e293b' }}; cursor:{{ $pageDone <= 1 ? 'not-allowed' : 'pointer' }};">
                ◀ Sebelum
            </button>
            <div style="font-size:12px; font-weight:800; color:#334155;">
                Halaman <span style="color:#8000ff;">{{ $pageDone }}</span> dari {{ $tasks['done_pages'] }}
            </div>
            <button wire:click="nextPage('done')" @if($pageDone >= $tasks['done_pages']) disabled @endif style="padding:6px 12px; font-size:12px; font-weight:700; border-radius:8px; border:1px solid #cbd5e1; background:{{ $pageDone >= $tasks['done_pages'] ? '#f1f5f9' : '#ffffff' }}; color:{{ $pageDone >= $tasks['done_pages'] ? '#94a3b8' : '#1e293b' }}; cursor:{{ $pageDone >= $tasks['done_pages'] ? 'not-allowed' : 'pointer' }};">
                Selanjutnya ▶
            </button>
        </div>
        @endif
        @endif

        {{-- Empty State --}}
        @if($tasks['in_progress']->count() === 0 && $tasks['pending']->count() === 0 && $tasks['done']->count() === 0 && $qcReviews->count() === 0)
        <div class="empty-state">
            <div class="empty-icon">📋</div>
            <h2>Belum Ada Tugas</h2>
            <p>Anda belum mendapat penugasan produksi.</p>
        </div>
        @endif

    @endif

    {{-- ========================================================================= --}}
    {{-- TAB 2: RIWAYAT PEKERJAAN (DATE RANGE FILTER) --}}
    {{-- ========================================================================= --}}
    @if($activeTab === 'riwayat')
    <div class="card" style="padding:16px;">
        <div style="font-size:14px; font-weight:800; color:#111827; margin-bottom:12px;">🗓️ Filter Periode Tanggal Selesai</div>
        
        {{-- Quick Date Presets --}}
        <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:14px;">
            <button wire:click="setQuickDate('today')" class="preset-btn">Hari Ini</button>
            <button wire:click="setQuickDate('7days')" class="preset-btn">7 Hari Terakhir</button>
            <button wire:click="setQuickDate('this_month')" class="preset-btn">Bulan Ini</button>
            <button wire:click="setQuickDate('last_month')" class="preset-btn">Bulan Lalu</button>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
            <div>
                <label style="font-size:11px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Dari Tanggal</label>
                <input type="date" wire:model.live="startDate" class="date-input">
            </div>
            <div>
                <label style="font-size:11px; font-weight:700; color:#6b7280; display:block; margin-bottom:4px;">Sampai Tanggal</label>
                <input type="date" wire:model.live="endDate" class="date-input">
            </div>
        </div>
    </div>

    {{-- Summary Cards --}}
    @php $stats = $this->historyStats; @endphp
    <div style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap:10px; margin-top:12px;">
        <div class="summary-card" style="background:#f0fdf4; border:1px solid #bbf7d0;">
            <div class="summary-label" style="color:#166534;">Pcs Selesai</div>
            <div class="summary-val" style="color:#15803d;">{{ number_format($stats['total_pcs']) }}</div>
        </div>
        <div class="summary-card" style="background:#f3e8ff; border:1px solid #e9d5ff;">
            <div class="summary-label" style="color:#6b21a8;">Total Upah</div>
            <div class="summary-val" style="color:#7e22ce;">Rp {{ number_format($stats['total_upah'], 0, ',', '.') }}</div>
        </div>
        <div class="summary-card" style="background:#eff6ff; border:1px solid #bfdbfe;">
            <div class="summary-label" style="color:#1e40af;">Total Tugas</div>
            <div class="summary-val" style="color:#1d4ed8;">{{ number_format($stats['total_tugas']) }}</div>
        </div>
    </div>

    @php $histData = $this->paginatedHistory; @endphp
    {{-- Detailed History List --}}
    <div style="margin-top:16px;">
        <div class="section-header" style="margin-bottom:8px;">
            <span class="section-dot dot-green"></span>
            <span class="section-title">Detail Pekerjaan Selesai</span>
            <span class="section-count">{{ $histData['total'] }}</span>
        </div>

        @forelse($histData['items'] as $task)
        <div class="card task-card" style="margin-bottom:8px; padding:12px 14px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:10px;">
                <div style="flex:1;">
                    <div style="font-size:11px; font-weight:800; color:#8000FF; text-transform:uppercase;">
                        {{ str_replace('_', ' ', $task->stage_name) }}
                    </div>
                    <div style="font-size:14px; font-weight:800; color:#0f172a; margin-top:2px;">
                        {{ $task->orderItem?->product_name ?? '-' }}
                    </div>
                    <div style="font-size:12px; color:#64748b; margin-top:2px;">
                        <span class="mono" style="font-weight:600;">{{ $task->orderItem?->order?->order_number ?? '-' }}</span> • 
                        {{ $task->orderItem?->order?->customer?->name ?? '-' }}
                    </div>
                    @if($task->completed_at)
                    <div style="font-size:11px; color:#94a3b8; margin-top:4px;">
                        📅 Selesai: {{ $task->completed_at->format('d M Y H:i') }}
                    </div>
                    @endif
                </div>

                <div style="text-align:right; flex-shrink:0;">
                    <div style="font-size:12px; font-weight:700; color:#1e293b;">
                        {{ $task->quantity }} pcs
                    </div>
                    <div style="font-size:13px; font-weight:800; color:#16a34a; margin-top:2px;">
                        Rp {{ number_format($task->wage_amount, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="empty-state" style="padding:24px 16px;">
            <div class="empty-icon">📂</div>
            <h3>Tidak Ada Riwayat</h3>
            <p>Tidak ditemukan pekerjaan selesai pada periode tanggal yang dipilih.</p>
        </div>
        @endforelse

        @if($histData['total_pages'] > 1)
        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:12px; padding:10px 14px; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
            <button wire:click="prevPage('history')" @if($pageHistory <= 1) disabled @endif style="padding:6px 14px; font-size:12px; font-weight:700; border-radius:8px; border:1px solid #cbd5e1; background:{{ $pageHistory <= 1 ? '#f1f5f9' : '#ffffff' }}; color:{{ $pageHistory <= 1 ? '#94a3b8' : '#1e293b' }}; cursor:{{ $pageHistory <= 1 ? 'not-allowed' : 'pointer' }};">
                ◀ Sebelum
            </button>
            <div style="font-size:12px; font-weight:800; color:#334155;">
                Halaman <span style="color:#8000ff;">{{ $pageHistory }}</span> dari {{ $histData['total_pages'] }}
            </div>
            <button wire:click="nextPage('history')" @if($pageHistory >= $histData['total_pages']) disabled @endif style="padding:6px 14px; font-size:12px; font-weight:700; border-radius:8px; border:1px solid #cbd5e1; background:{{ $pageHistory >= $histData['total_pages'] ? '#f1f5f9' : '#ffffff' }}; color:{{ $pageHistory >= $histData['total_pages'] ? '#94a3b8' : '#1e293b' }}; cursor:{{ $pageHistory >= $histData['total_pages'] ? 'not-allowed' : 'pointer' }};">
                Selanjutnya ▶
            </button>
        </div>
        @endif
    </div>
    @endif

    {{-- ========================================================================= --}}
    {{-- TAB 3: KEUANGAN & KASBON WORKER --}}
    {{-- ========================================================================= --}}
    @if($activeTab === 'keuangan')
    @php
        $totalEarned = (int) $worker->total_earned;
        $kasbon = (int) $worker->current_cash_advance;
        $limit = (int) $worker->max_cash_advance;
        $sisaLimit = max(0, $limit - $kasbon);
        $netEarned = max(0, $totalEarned - $kasbon);
        $unpaid = (int) $worker->unpaid_wage;
    @endphp

    {{-- Hero Keuangan --}}
    <div class="wallet-hero" style="margin: 12px 0 0; background: linear-gradient(135deg, #8000FF 0%, #5B00B7 100%); border-radius: 20px; padding: 24px 20px; color: white; box-shadow: 0 8px 32px rgba(128,0,255,0.25);">
        <div class="wallet-label" style="font-size: 11px; font-weight: 700; opacity: 0.85; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Pendapatan Bersih Saat Ini</div>
        <div class="wallet-total" style="font-size: 32px; font-weight: 800; letter-spacing: -1px; margin-bottom: 16px;">Rp {{ number_format($netEarned, 0, ',', '.') }}</div>
        
        <div class="wallet-breakdown" style="display: flex; align-items: center; gap: 16px; background: rgba(255,255,255,0.12); padding:12px 14px; border-radius:12px;">
            <div class="wallet-sub" style="flex: 1;">
                <div class="sub-label" style="font-size: 10px; opacity: 0.8;">Pendapatan Kotor</div>
                <div class="sub-value" style="font-size: 14px; font-weight: 800;">Rp {{ number_format($totalEarned, 0, ',', '.') }}</div>
            </div>
            <div class="wallet-divider" style="width: 1px; height: 32px; background: rgba(255,255,255,0.25);"></div>
            <div class="wallet-sub" style="flex: 1;">
                <div class="sub-label" style="font-size: 10px; opacity: 0.8;">Kasbon Aktif</div>
                <div class="sub-value" style="font-size: 14px; font-weight: 800; color: #fca5a5;">Rp {{ number_format($kasbon, 0, ',', '.') }}</div>
            </div>
        </div>
    </div>

    {{-- Limit & Action Ajukan Kasbon --}}
    <div class="card" style="margin-top:12px; padding:16px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
            <div style="font-size:13px; font-weight:800; color:#1e293b;">💳 Limit Kasbon Pekerja</div>
            <span style="font-size:12px; font-weight:800; color:{{ $sisaLimit > 0 ? '#16a34a' : '#dc2626' }}; background:{{ $sisaLimit > 0 ? '#f0fdf4' : '#fef2f2' }}; padding:2px 8px; border-radius:12px;">
                Sisa Limit: Rp {{ number_format($sisaLimit, 0, ',', '.') }}
            </span>
        </div>

        @if($limit > 0)
        @php $pct = min(100, round(($kasbon / $limit) * 100)); @endphp
        <div class="progress-bar-wrap" style="height:8px; background:#e5e7eb; border-radius:4px; overflow:hidden; margin-bottom:8px;">
            <div class="progress-bar-fill" style="width: {{ $pct }}%; height:100%; background: {{ $pct >= 80 ? '#ef4444' : '#8000FF' }}"></div>
        </div>
        <div style="font-size:11px; color:#6b7280; display:flex; justify-content:space-between; margin-bottom:12px;">
            <span>Terpakai: {{ $pct }}%</span>
            <span>Max Limit: Rp {{ number_format($limit, 0, ',', '.') }}</span>
        </div>
        @endif

        <button wire:click="$toggle('showKasbonModal')" class="btn btn-primary btn-sm" style="width:100%; padding:10px; font-size:13px; font-weight:700; border-radius:10px; background:#8000FF; color:white; border:none; cursor:pointer;">
            ➕ {{ $showKasbonModal ? 'Tutup Form Pengajuan' : 'Ajukan Kasbon Baru' }}
        </button>
    </div>

    {{-- Form Ajukan Kasbon --}}
    @if($showKasbonModal)
    <div class="card" style="margin-top:12px; padding:16px; border:2px solid #c4b5fd; background:#faf5ff;">
        <div style="font-size:14px; font-weight:800; color:#5b21b6; margin-bottom:12px;">💸 Form Pengajuan Kasbon</div>
        
        <form wire:submit.prevent="ajukanKasbon">
            <div style="margin-bottom:12px;">
                <label style="font-size:12px; font-weight:700; color:#4c1d95; display:block; margin-bottom:4px;">Nominal Kasbon (Rp)</label>
                <input type="number" wire:model="kasbonAmount" placeholder="Contoh: 50000" class="date-input" style="font-size:14px; font-weight:700;">
                @error('kasbonAmount') <span style="font-size:11px; color:#dc2626; font-weight:600;">{{ $message }}</span> @enderror
            </div>

            <div style="margin-bottom:14px;">
                <label style="font-size:12px; font-weight:700; color:#4c1d95; display:block; margin-bottom:4px;">Catatan / Keperluan (Opsional)</label>
                <input type="text" wire:model="kasbonNote" placeholder="misal: Kebutuhan harian / keperluan mendesak" class="date-input">
                @error('kasbonNote') <span style="font-size:11px; color:#dc2626; font-weight:600;">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn btn-primary" style="width:100%; padding:12px; font-size:13px; font-weight:800; background:#7c3aed; color:white; border-radius:10px; border:none; cursor:pointer;">
                🚀 Kirim Pengajuan Kasbon
            </button>
        </form>
    </div>
    @endif

    @php $kasData = $this->paginatedCashAdvance; @endphp
    {{-- Riwayat Transaksi Kasbon --}}
    <div style="margin-top:16px;">
        <div class="section-header" style="margin-bottom:8px;">
            <span class="section-dot dot-purple"></span>
            <span class="section-title">Riwayat Kasbon & Pembayaran</span>
            <span class="section-count">{{ $kasData['total'] }}</span>
        </div>

        @forelse($kasData['items'] as $ca)
        <div class="card" style="margin-bottom:8px; padding:12px 14px;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    @php 
                        $isLoan = in_array($ca->type, ['loan', 'pinjaman']);
                        $status = $ca->status ?? 'approved';
                    @endphp
                    <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                        <span style="font-size:11px; font-weight:800; padding:2px 8px; border-radius:6px; {{ $isLoan ? 'background:#fef2f2; color:#dc2626;' : 'background:#f0fdf4; color:#16a34a;' }}">
                            {{ $isLoan ? 'PINJAMAN (KASBON)' : 'PELUNASAN' }}
                        </span>
                        @if($status === 'pending')
                        <span style="font-size:10px; font-weight:700; padding:2px 8px; border-radius:6px; background:#fef3c7; color:#b45309;">
                            ⏳ Menunggu Persetujuan
                        </span>
                        @elseif($status === 'rejected')
                        <span style="font-size:10px; font-weight:700; padding:2px 8px; border-radius:6px; background:#fee2e2; color:#b91c1c;">
                            ❌ Ditolak
                        </span>
                        @else
                        <span style="font-size:10px; font-weight:700; padding:2px 8px; border-radius:6px; background:#dcfce7; color:#15803d;">
                            ✅ Cair
                        </span>
                        @endif
                    </div>
                    <div style="font-size:12px; font-weight:600; color:#334155; margin-top:4px;">
                        {{ $ca->note ?? 'Pinjaman Kasbon' }}
                    </div>
                    @if($status === 'rejected' && $ca->rejection_reason)
                    <div style="font-size:11px; font-weight:600; color:#dc2626; margin-top:2px;">
                        Alasan penolakan: {{ $ca->rejection_reason }}
                    </div>
                    @endif
                    <div style="font-size:10px; color:#94a3b8; margin-top:2px;">
                        {{ $ca->date ? $ca->date->format('d M Y') : '-' }}
                    </div>
                </div>

                <div style="font-size:14px; font-weight:800; color:{{ $isLoan ? '#dc2626' : '#16a34a' }};">
                    {{ $isLoan ? '-' : '+' }} Rp {{ number_format($ca->amount, 0, ',', '.') }}
                </div>
            </div>
        </div>
        @empty
        <div class="empty-state" style="padding:24px 16px;">
            <div class="empty-icon">💳</div>
            <h3>Belum Ada Riwayat Kasbon</h3>
            <p>Anda belum pernah mengajukan pinjaman atau transaksi kasbon.</p>
        </div>
        @endforelse

        @if($kasData['total_pages'] > 1)
        <div style="display:flex; align-items:center; justify-content:space-between; margin-top:12px; padding:10px 14px; background:#ffffff; border-radius:12px; border:1px solid #e2e8f0; box-shadow:0 1px 3px rgba(0,0,0,0.04);">
            <button wire:click="prevPage('kasbon')" @if($pageKasbon <= 1) disabled @endif style="padding:6px 14px; font-size:12px; font-weight:700; border-radius:8px; border:1px solid #cbd5e1; background:{{ $pageKasbon <= 1 ? '#f1f5f9' : '#ffffff' }}; color:{{ $pageKasbon <= 1 ? '#94a3b8' : '#1e293b' }}; cursor:{{ $pageKasbon <= 1 ? 'not-allowed' : 'pointer' }};">
                ◀ Sebelum
            </button>
            <div style="font-size:12px; font-weight:800; color:#334155;">
                Halaman <span style="color:#8000ff;">{{ $pageKasbon }}</span> dari {{ $kasData['total_pages'] }}
            </div>
            <button wire:click="nextPage('kasbon')" @if($pageKasbon >= $kasData['total_pages']) disabled @endif style="padding:6px 14px; font-size:12px; font-weight:700; border-radius:8px; border:1px solid #cbd5e1; background:{{ $pageKasbon >= $kasData['total_pages'] ? '#f1f5f9' : '#ffffff' }}; color:{{ $pageKasbon >= $kasData['total_pages'] ? '#94a3b8' : '#1e293b' }}; cursor:{{ $pageKasbon >= $kasData['total_pages'] ? 'not-allowed' : 'pointer' }};">
                Selanjutnya ▶
            </button>
        </div>
        @endif
    </div>
    @endif

    {{-- ── FOOTER ── --}}
    <footer class="app-footer" style="margin-top:24px;">
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
    padding: 0 16px 32px;
    background: #F9FAFB;
    font-family: inherit;
}

/* ── Header ── */
.app-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 0 14px;
    background: #F9FAFB;
    border-bottom: 1px solid #E5E7EB;
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
    padding: 14px 0;
}
.greeting-hello { font-size: 20px; font-weight: 800; color: #111827; letter-spacing: -0.3px; }
.greeting-sub { font-size: 13px; color: #6B7280; margin-top: 2px; }
.greeting-status { display: flex; align-items: center; gap: 6px; margin-top: 4px; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dot-purple { background: #8000FF; }
.dot-blue { background: #3B82F6; }
.dot-yellow { background: #F59E0B; }
.dot-orange { background: #F97316; }
.dot-green { background: #10B981; }
.status-text { font-size: 12px; font-weight: 600; color: #4B5563; }

/* ── Main Tabs ── */
.main-tabs-wrap {
    display: flex;
    background: #E5E7EB;
    padding: 4px;
    border-radius: 12px;
    gap: 4px;
    margin-bottom: 14px;
}
.tab-btn {
    flex: 1;
    padding: 10px 6px;
    border: none;
    background: transparent;
    font-size: 13px;
    font-weight: 700;
    color: #4B5563;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.15s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}
.tab-btn.active {
    background: white;
    color: #8000FF;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}
.tab-badge {
    background: #8000FF;
    color: white;
    font-size: 10px;
    font-weight: 800;
    padding: 1px 6px;
    border-radius: 10px;
}

/* ── Sub Filter Pills ── */
.sub-filter-wrap {
    display: flex;
    gap: 6px;
    overflow-x: auto;
    padding-bottom: 8px;
    margin-bottom: 12px;
    -webkit-overflow-scrolling: touch;
}
.sub-pill {
    white-space: nowrap;
    padding: 6px 12px;
    font-size: 11px;
    font-weight: 700;
    border-radius: 20px;
    background: #F3F4F6;
    color: #6B7280;
    border: 1px solid #E5E7EB;
    cursor: pointer;
    transition: all 0.15s ease;
}
.sub-pill.active {
    background: #8000FF;
    color: white;
    border-color: #8000FF;
}

/* ── Cards & Tasks ── */
.card {
    background: white;
    border-radius: 14px;
    border: 1px solid #E5E7EB;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
}
.task-card {
    padding: 14px 16px;
    margin-bottom: 10px;
    transition: transform 0.15s ease;
}
.task-working { border-left: 4px solid #10B981; }
.task-ready { border-left: 4px solid #8000FF; }
.task-revision { border-left: 4px solid #EF4444; background: #FEF2F2; }
.task-done { border-left: 4px solid #9CA3AF; opacity: 0.95; }

.task-stage { font-size: 12px; font-weight: 800; color: #8000FF; text-transform: uppercase; letter-spacing: 0.3px; }
.task-product { font-size: 15px; font-weight: 800; color: #111827; margin-top: 2px; }
.task-customer { font-size: 12px; color: #6B7280; margin-top: 2px; }
.express-badge { background: #FEF2F2; color: #DC2626; font-size: 10px; font-weight: 800; padding: 2px 6px; border-radius: 4px; }
.done-check { font-size: 18px; font-weight: 900; color: #10B981; }

.stage-progress { display: flex; gap: 4px; flex-wrap: wrap; margin-top: 10px; }
.stage-tag { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 4px; background: #F3F4F6; color: #9CA3AF; }
.stage-done { background: #D1FAE5; color: #065F46; }
.stage-current { background: #DBEAFE; color: #1E40AF; }
.stage-highlight { ring: 2px solid #8000FF; }
.blocking-info { font-size: 11px; font-weight: 700; color: #D97706; margin-top: 8px; }

.section-header { display: flex; align-items: center; gap: 8px; margin: 16px 0 10px; }
.section-dot { width: 8px; height: 8px; border-radius: 50%; }
.section-title { font-size: 13px; font-weight: 800; color: #374151; letter-spacing: 0.2px; text-transform: uppercase; }
.section-count { font-size: 11px; font-weight: 800; background: #E5E7EB; color: #374151; padding: 2px 8px; border-radius: 10px; }

/* ── Date Filter & Inputs ── */
.date-input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 12px;
    color: #111827;
    background: white;
}
.preset-btn {
    padding: 4px 10px;
    font-size: 11px;
    font-weight: 700;
    background: #F3F4F6;
    color: #4B5563;
    border: 1px solid #D1D5DB;
    border-radius: 6px;
    cursor: pointer;
}
.preset-btn:hover { background: #E5E7EB; }

/* ── Summary Card ── */
.summary-card {
    padding: 10px 12px;
    border-radius: 10px;
    text-align: center;
}
.summary-label { font-size: 10px; font-weight: 800; text-transform: uppercase; }
.summary-val { font-size: 15px; font-weight: 800; margin-top: 2px; }

/* ── Alerts & Empty ── */
.alert-success { background: #D1FAE5; color: #065F46; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 12px; }
.alert-error { background: #FEE2E2; color: #991B1B; padding: 12px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 12px; }

.empty-state { text-align: center; padding: 36px 16px; background: white; border-radius: 16px; border: 1px solid #E5E7EB; margin-top: 12px; }
.empty-icon { font-size: 36px; margin-bottom: 8px; }
.empty-state h2, .empty-state h3 { font-size: 16px; font-weight: 800; color: #111827; }
.empty-state p { font-size: 13px; color: #6B7280; margin-top: 4px; }

.app-footer { text-align: center; font-size: 11px; color: #9CA3AF; padding: 16px 0; }
</style>
</div>
