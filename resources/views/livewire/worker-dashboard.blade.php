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
        <div class="header-wo-badge">Dashboard</div>
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
            <div class="greeting-sub">Dashboard Tugas Produksi</div>
        </div>
        <div class="greeting-status">
            <span class="status-dot dot-purple"></span>
            <span class="status-text">{{ $tasks['in_progress']->count() + $tasks['pending']->count() }} tugas aktif</span>
        </div>
    </div>

    {{-- Flash Message --}}
    @if(session()->has('success'))
    <div class="alert-success">✅ {{ session('success') }}</div>
    @endif

    {{-- Error Message --}}
    @if($errors->has('aksi'))
    <div class="alert-error">{{ $errors->first('aksi') }}</div>
    @endif
    @if($errors->has('qc'))
    <div class="alert-error">{{ $errors->first('qc') }}</div>
    @endif

    {{-- ── QC REVIEW (for QC workers only) ── --}}
    @if($qcReviews->count() > 0)
    <div class="section-header">
        <span class="section-dot dot-orange"></span>
        <span class="section-title">Perlu QC Review</span>
        <span class="section-count">{{ $qcReviews->count() }}</span>
    </div>

    @foreach($qcReviews as $wo)
    <div class="card task-card task-review" style="border:1.5px solid #fbbf24;">
        <a href="{{ route('work.task', ['wo_id' => $wo->id, 'token' => $wo->token, 'wt' => $worker->portal_token]) }}"
           style="text-decoration:none;color:inherit;display:block;">
            <div class="task-header" style="margin-bottom:0;">
                <div class="task-info">
                    <div class="task-stage task-stage-review">Review: {{ str_replace('_', ' ', $wo->current_review_stage ?? '') }}</div>
                    <div class="task-product">{{ $wo->orderItem?->product_name ?? '-' }}</div>
                    <div class="task-customer">
                        {{ $wo->orderItem?->order?->customer?->name ?? '-' }} •
                        {{ $wo->orderItem ? $wo->orderItem->getItemsInGroup()->sum('quantity') : 0 }} pcs
                        @if($wo->orderItem?->order?->is_express)
                        <span class="express-badge">⚡ Express</span>
                        @endif
                    </div>
                </div>
            </div>
            <div style="margin-top:12px;text-align:center;">
                <span style="font-size:11px;font-weight:600;color:#3b82f6;">👆 Buka Detail untuk Proses QC</span>
            </div>
        </a>
    </div>
    @endforeach
    @endif




    {{-- ── QC AKHIR (untuk QC workers) ── --}}
    @if($qcAkhir->count() > 0)
    <div class="section-header">
        <span class="section-dot" style="background:#7C3AED;"></span>
        <span class="section-title">QC Akhir — Verifikasi Final</span>
        <span class="section-count">{{ $qcAkhir->count() }}</span>
    </div>

    @foreach($qcAkhir as $qaWo)
    <div class="card task-card" style="border:1.5px solid #C084FC;">
        <a href="{{ route('work.task', ['wo_id' => $qaWo->id, 'token' => $qaWo->token, 'wt' => $worker->portal_token]) }}"
           style="text-decoration:none;color:inherit;display:block;">
            <div class="task-header" style="margin-bottom:0;">
                <div class="task-info">
                    <div class="task-stage" style="background:#F3E8FF;color:#7C3AED;">QC Akhir</div>
                    <div class="task-product">{{ $qaWo->orderItem?->product_name ?? '-' }}</div>
                    <div class="task-customer">
                        {{ $qaWo->orderItem?->order?->customer?->name ?? '-' }} •
                        {{ $qaWo->orderItem ? $qaWo->orderItem->getItemsInGroup()->sum('quantity') : 0 }} pcs
                    </div>
                </div>
            </div>
            <div style="margin-top:12px;text-align:center;">
                <span style="font-size:11px;font-weight:600;color:#7C3AED;">👆 Buka Detail untuk Proses QC Akhir</span>
            </div>
        </a>
    </div>
    @endforeach
    @endif

    {{-- ── SEDANG DIKERJAKAN ── --}}
    @if($tasks['in_progress']->count() > 0)
    <div class="section-header">
        <span class="section-dot dot-blue"></span>
        <span class="section-title">Sedang Dikerjakan</span>
        <span class="section-count">{{ $tasks['in_progress']->count() }}</span>
    </div>

    @foreach($tasks['in_progress'] as $task)
    @php
        $wo = $task->workOrder;
    @endphp
    @if($wo)
    <a href="{{ route('work.task', ['wo_id' => $wo->id, 'token' => $wo->token, 'wt' => $worker->portal_token]) }}" style="text-decoration:none;color:inherit;display:block;">
    @endif
    <div class="card task-card" style="cursor:pointer;">
        <div class="task-header" style="justify-content: space-between; align-items: center;">
            <div class="task-info">
                <div class="task-stage">{{ str_replace('_', ' ', $task->stage_name) }}</div>
                <div class="task-product">{{ $task->orderItem?->product_name ?? '-' }}</div>
                <div class="task-customer">{{ $task->orderItem?->order?->customer?->name ?? '-' }} • {{ $task->orderItem ? $task->orderItem->getItemsInGroup()->sum('quantity') : $task->quantity }} pcs</div>
            </div>
            <div style="font-size:11px;font-weight:600;color:#22c55e;background:#dcfce7;border-radius:20px;padding:4px 10px;white-space:nowrap;">
                ⚡ Sedang Dikerjakan
            </div>
        </div>
    </div>
    @if($wo)</a>@endif
    @endforeach
    @endif

    {{-- ── ANTRIAN ── --}}
    @if($tasks['pending']->count() > 0)
    <div class="section-header">
        <span class="section-dot dot-yellow"></span>
        <span class="section-title">Antrian</span>
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
    @if($wo)
    <a href="{{ route('work.task', ['wo_id' => $wo->id, 'token' => $wo->token, 'wt' => $worker->portal_token]) }}" style="text-decoration:none;color:inherit;display:block;">
    @endif
    <div class="card task-card {{ $canStart ? ($isRevision ? 'task-revision' : 'task-ready') : '' }}" style="cursor:pointer;">
        <div class="task-header" style="justify-content: space-between; align-items: center;">
            <div class="task-info">
                <div class="task-stage {{ $isRevision ? 'task-stage-revision' : '' }}">
                    {{ str_replace('_', ' ', $task->stage_name) }}
                    @if($isRevision)
                    <span class="revision-badge">REVISI</span>
                    @endif
                </div>
                <div class="task-product">{{ $task->orderItem?->product_name ?? '-' }}</div>
                <div class="task-customer">
                    {{ $task->orderItem?->order?->customer?->name ?? '-' }} • {{ $task->orderItem ? $task->orderItem->getItemsInGroup()->sum('quantity') : $task->quantity }} pcs
                    @if($task->orderItem?->order?->is_express)
                    <span class="express-badge">⚡ Express</span>
                    @endif
                </div>
            </div>
            <div>
                @if($canStart)
                    <span style="font-size:11px;font-weight:600;color:{{ $isRevision ? '#d97706' : '#2563eb' }};background:{{ $isRevision ? '#fef3c7' : '#dbeafe' }};border-radius:20px;padding:4px 10px;white-space:nowrap;">
                        {{ $isRevision ? '🔧 Perbaikan' : '👉 Siap' }}
                    </span>
                @else
                    <span style="font-size:11px;font-weight:600;color:#94a3b8;background:#f1f5f9;border-radius:20px;padding:4px 10px;white-space:nowrap;">
                        🔒 Terkunci
                    </span>
                @endif
            </div>
        </div>

        {{-- Stage Progress --}}
        @if(count($progress) > 0)
        <div class="stage-progress">
            @foreach($progress as $stage)
            <span class="stage-tag
                @if($stage['status'] === 'done') stage-done
                @elseif($stage['status'] === 'current') stage-current
                @else stage-pending
                @endif
                @if($stage['is_task_stage']) stage-highlight
                @endif">
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

    {{-- ── SELESAI ── --}}
    @if($tasks['done']->count() > 0)
    <div class="section-header">
        <span class="section-dot dot-green"></span>
        <span class="section-title">Selesai</span>
        <span class="section-count">{{ $tasks['done']->count() }}</span>
    </div>

    @foreach($tasks['done'] as $task)
    @php
        $wo = $task->workOrder;
    @endphp
    @if($wo)
    <a href="{{ route('work.task', ['wo_id' => $wo->id, 'token' => $wo->token, 'wt' => $worker->portal_token]) }}" style="text-decoration:none;color:inherit;display:block;">
    @endif
    <div class="card task-card task-done" style="cursor:pointer;">
        <div class="task-header">
            <div class="task-info">
                <div class="task-stage">{{ str_replace('_', ' ', $task->stage_name) }}</div>
                <div class="task-product">{{ $task->orderItem?->product_name ?? '-' }}</div>
                <div class="task-customer">
                    {{ $task->orderItem?->order?->customer?->name ?? '-' }} • {{ $task->orderItem ? $task->orderItem->getItemsInGroup()->sum('quantity') : $task->quantity }} pcs
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
    @endif

    {{-- Empty State --}}
    @if($tasks['in_progress']->count() === 0 && $tasks['pending']->count() === 0 && $tasks['done']->count() === 0 && $qcReviews->count() === 0)
    <div class="empty-state">
        <div class="empty-icon">📋</div>
        <h2>Belum Ada Tugas</h2>
        <p>Anda belum mendapat penugasan produksi.</p>
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
    background: #F9FAFB;
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
.greeting-status { display: flex; align-items: center; gap: 6px; margin-top: 4px; }
.status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.dot-purple { background: #8000FF; }
.dot-blue { background: #3B82F6; }
.dot-green { background: #22C55E; }
.dot-yellow { background: #F59E0B; }
.status-text { font-size: 12px; font-weight: 600; color: #374151; }

/* ── Section Header ── */
.section-header {
    display: flex; align-items: center; gap: 8px;
    padding: 16px 20px 8px;
}
.section-title { font-size: 13px; font-weight: 700; color: #374151; }
.section-count {
    font-size: 11px; font-weight: 700;
    background: #F3F4F6; color: #6B7280;
    padding: 2px 8px; border-radius: 10px;
}

/* ── Cards ── */
.card {
    background: white;
    border-radius: 14px;
    padding: 14px 16px;
    margin: 8px 16px 0;
    box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    border: 1px solid #F3F4F6;
}
.task-card { transition: all 0.15s ease; }
.task-ready { border-color: #BBF7D0; background: #F0FDF4; }
.task-done { opacity: 0.6; }

/* ── Task Layout ── */
.task-header { display: flex; justify-content: space-between; align-items: center; gap: 12px; }
.task-info { flex: 1; min-width: 0; }
.task-stage { font-size: 14px; font-weight: 700; color: #111827; }
.task-product { font-size: 13px; color: #374151; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.task-customer { font-size: 12px; color: #9CA3AF; margin-top: 1px; }
.task-date { color: #D1D5DB; }
.done-check { font-size: 20px; font-weight: 700; color: #22C55E; }

/* ── Stage Progress ── */
.stage-progress {
    display: flex; flex-wrap: wrap; gap: 4px;
    margin-top: 10px; padding-top: 10px;
    border-top: 1px solid #F3F4F6;
}
.stage-tag {
    font-size: 10px; font-weight: 600;
    padding: 3px 7px; border-radius: 6px;
}
.stage-tag.stage-done { background: #DCFCE7; color: #166534; }
.stage-tag.stage-current { background: #DBEAFE; color: #1E40AF; font-weight: 700; }
.stage-tag.stage-pending { background: #F3F4F6; color: #9CA3AF; }
.stage-tag.stage-highlight { box-shadow: 0 0 0 2px #8000FF; }

/* ── Blocking Info ── */
.blocking-info {
    margin-top: 8px;
    font-size: 11px; color: #D97706;
    padding: 6px 10px;
    background: #FFFBEB;
    border-radius: 8px;
}

/* ── Buttons ── */
.btn {
    display: flex; align-items: center; justify-content: center; gap: 4px;
    padding: 10px 14px;
    border: none;
    border-radius: 10px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.btn:disabled { opacity: 0.6; cursor: not-allowed; }
.btn-sm { padding: 8px 12px; font-size: 12px; }
.btn-primary { background: #8000FF; color: white; }
.btn-primary:not(:disabled):hover { background: #6600CC; }
.btn-success { background: #16A34A; color: white; }
.btn-success:not(:disabled):hover { background: #15803D; }
.btn-locked { background: #F3F4F6; color: #9CA3AF; cursor: not-allowed; }
.btn-warning { background: #F59E0B; color: white; }
.btn-warning:not(:disabled):hover { background: #D97706; }
.btn-danger { background: #DC2626; color: white; }
.btn-danger:not(:disabled):hover { background: #B91C1C; }
.btn-full { width: 100%; padding: 14px; font-size: 14px; }

/* ── QC Review ── */
.dot-orange { background: #F59E0B; }
.task-review { border-color: #FDE68A; background: #FFFBEB; }
.task-stage-review { color: #D97706; font-weight: 700; }
.review-actions-inline {
    display: flex;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #FDE68A;
}
.review-actions-inline .btn {
    flex: 1;
    justify-content: center;
}

/* ── Modal ── */
.modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    display: flex; align-items: center; justify-content: center;
    z-index: 1000;
    padding: 16px;
}
.modal-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 400px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 20px;
    border-bottom: 1px solid #E5E7EB;
}
.modal-header h3 { font-size: 18px; font-weight: 800; margin: 0; }
.modal-close {
    background: none; border: none; font-size: 24px;
    color: #9CA3AF; cursor: pointer; padding: 0; line-height: 1;
}
.modal-body { padding: 20px; }
.review-info {
    background: #F9FAFB;
    padding: 16px;
    border-radius: 12px;
    margin-bottom: 16px;
}
.review-stage { font-weight: 700; color: #D97706; font-size: 14px; }
.review-product { font-weight: 600; color: #111827; font-size: 15px; margin-top: 4px; }
.review-customer { font-size: 12px; color: #6B7280; margin-top: 2px; }
.review-actions { display: flex; flex-direction: column; gap: 12px; }
.reject-section { margin-top: 8px; padding-top: 16px; border-top: 1px solid #E5E7EB; }
.reject-label { font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 8px; }
.reject-textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #D1D5DB;
    border-radius: 10px;
    font-family: inherit;
    font-size: 14px;
    resize: none;
    margin-bottom: 12px;
}
.reject-textarea:focus { outline: none; border-color: #8000FF; }
.field-error { font-size: 11px; color: #DC2626; display: block; margin-top: -8px; margin-bottom: 8px; }

/* ── Revision ── */
.task-revision { border-color: #FCA5A5; background: #FEF2F2; }
.task-stage-revision { color: #DC2626; }
.revision-badge {
    display: inline-block;
    font-size: 9px;
    font-weight: 700;
    background: #DC2626;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 6px;
    vertical-align: middle;
}
.express-badge {
    display: inline-block;
    font-size: 9px;
    font-weight: 700;
    background: #F59E0B;
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    margin-left: 4px;
    vertical-align: middle;
}

/* ── Alerts ── */
.alert-success { margin: 12px 16px 0; padding: 12px 16px; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 10px; color: #15803D; font-size: 13px; font-weight: 600; }
.alert-error { margin: 12px 16px 0; padding: 12px 16px; background: #FEF2F2; border: 1px solid #FECACA; border-radius: 10px; color: #991B1B; font-size: 13px; }

/* ── Empty State ── */
.empty-state {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    padding: 60px 32px; text-align: center; gap: 12px;
}
.empty-icon { font-size: 48px; }
.empty-state h2 { font-size: 20px; font-weight: 800; color: #111827; }
.empty-state p { font-size: 14px; color: #9CA3AF; }

/* ── Footer ── */
.app-footer { margin-top: auto; padding: 24px; text-align: center; font-size: 12px; color: #D1D5DB; }
</style>
</div>
