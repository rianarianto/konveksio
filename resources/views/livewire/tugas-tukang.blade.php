<div class="app-shell">
    {{-- ── HEADER ── --}}
    <header class="app-header">
        <div class="header-left">
            @if($this->wt)
            <a href="{{ route('worker.dashboard', ['token' => $this->wt]) }}" class="back-btn" style="margin-right: 12px; padding: 6px; display: inline-flex; align-items: center; justify-content: center; background: #f3f4f6; border-radius: 50%; width: 32px; height: 32px; color: #374151;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M19 12H5M12 5l-7 7 7 7"/>
                </svg>
            </a>
            @endif
            <div class="header-brand">
                <div class="brand-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                </div>
                <span class="brand-name">Konveksio</span>
            </div>
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
        $worker = $this->worker;
        $workerName = $worker?->name ?? 'Pekerja';
        $isCompleted = $wo->isCompleted();
        $isQcStage = $wo->isInQcStage();
        $isWorkerStage = $wo->isInWorkerStage();
        $statusLabel = $wo->status_label;
        $orderItem = $wo->orderItem;
        $order = $orderItem?->order;
        $customer = $order?->customer;

        // Agregasi total qty dari seluruh group items
        $groupItems = $orderItem ? $orderItem->getItemsInGroup() : collect();
        $totalQty = $groupItems->sum('quantity');

        // Info bahan & deadline
        $bahan = $orderItem?->bahan;
        $bahanLabel = $bahan ? ($bahan->material?->name . ' - ' . $bahan->color_name) : null;
        $deadline = $order?->deadline ? \Carbon\Carbon::parse($order->deadline) : null;
        $catatanUmum = $order?->notes ?? null;

        // Gambar desain (dari orderItem grup, ambil yang pertama punya gambar)
        $designImage = null;
        foreach ($groupItems as $gi) {
            if ($gi->design_image && file_exists(public_path('storage/' . $gi->design_image))) {
                $designImage = asset('storage/' . $gi->design_image);
                break;
            }
        }

        // Build specGroups (sama persis dengan PDFController)
        $specGroups = [];
        foreach ($groupItems as $gi) {
            $d = $gi->size_and_request_details ?? [];
            $isProduksi = in_array($gi->production_category ?? 'produksi', ['produksi', 'custom']);
            if (!$isProduksi) {
                $gen = 'UMUM'; $slv = '-'; $pck = '-'; $btn = '-'; $tun = '-'; $clr = '-';
            } else {
                $gen = $d['gender'] ?? 'L';
                $slv = strtoupper($d['sleeve_model'] ?? 'PENDEK');
                $pck = strtoupper(str_replace('_', ' ', $d['pocket_model'] ?? 'TANPA SAKU'));
                $btn = strtoupper($d['button_model'] ?? 'BIASA');
                $tun = !empty($d['is_tunic']) ? 'TUNIK' : 'STANDAR';
                $clr = strtoupper($d['collar_model'] ?? 'BIASA');
            }
            $sbList = [];
            if (!empty($d['sablon_bordir'])) {
                foreach ($d['sablon_bordir'] as $sb) {
                    $ket = !empty($sb['keterangan']) ? ' - ' . $sb['keterangan'] : '';
                    $sbList[] = ($sb['jenis'] ?? '') . ' (' . ($sb['lokasi'] ?? '') . $ket . ')';
                }
            } elseif (!empty($d['sablon_jenis'])) {
                $ket = !empty($d['sablon_keterangan']) ? ' - ' . $d['sablon_keterangan'] : '';
                $sbList[] = ($d['sablon_jenis'] ?? '') . ' (' . ($d['sablon_lokasi'] ?? '-') . $ket . ')';
            }
            $reqList = [];
            if (!empty($d['request_tambahan']) && is_array($d['request_tambahan'])) {
                foreach ($d['request_tambahan'] as $rt) {
                    $reqList[] = ($rt['jenis'] ?? '') . ': ' . ($rt['keterangan'] ?? '');
                }
            }
            $groupKey = "{$gen}|{$slv}|{$pck}|{$btn}|{$tun}|{$clr}|" . implode('|', $sbList);
            if (!isset($specGroups[$groupKey])) {
                $specGroups[$groupKey] = [
                    'gender'       => $gen === 'UMUM' ? 'UMUM' : ($gen === 'L' ? 'LAKI-LAKI' : 'PEREMPUAN'),
                    'is_produksi'  => $isProduksi,
                    'sleeve'       => $slv,
                    'pocket'       => $pck,
                    'button'       => $btn,
                    'tunic'        => $tun,
                    'collar'       => $clr,
                    'sablon_bordir'=> $sbList,
                    'requests'     => $reqList,
                    'total_qty'    => 0,
                    'sizes'        => [],
                    'recipients'   => ['standard' => [], 'custom' => []],
                ];
            }
            $specGroups[$groupKey]['total_qty'] += $gi->quantity;
            $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
            $specGroups[$groupKey]['sizes'][$sz] = ($specGroups[$groupKey]['sizes'][$sz] ?? 0) + $gi->quantity;
            if (!empty($d['detail_custom'])) {
                foreach ($d['detail_custom'] as $u) {
                    $specGroups[$groupKey]['recipients']['custom'][] = [
                        'nama' => $u['nama'] ?? '-',
                        'size' => $u['ukuran'] ?? 'Custom',
                        'ukuran_badan' => !empty($u['LD']) ? "LD:{$u['LD']} PB:{$u['PB']} LP:{$u['LP']}" : '',
                    ];
                }
            } elseif ($sz === 'CUSTOM') {
                $mArr = [];
                foreach (['LD','PB','PL','LB','LP','LPh'] as $mk) {
                    if (!empty($d[$mk])) $mArr[] = "$mk:{$d[$mk]}";
                }
                $specGroups[$groupKey]['recipients']['custom'][] = [
                    'nama' => $gi->recipient_name ?? '-',
                    'size' => 'Custom',
                    'ukuran_badan' => implode(' ', $mArr),
                ];
            } elseif (!empty($gi->recipient_name)) {
                $specGroups[$groupKey]['recipients']['standard'][$sz][] = $gi->recipient_name;
            }
        }

        // Cek tahap sekarang
        $expectedStage = $wo->status;
        if ($wo->status === \App\Models\WorkOrder::STATUS_QC_PREP) {
            $expectedStage = 'QC_PERSIAPAN';
        } elseif ($wo->status === \App\Models\WorkOrder::STATUS_QC_REVIEW) {
            $expectedStage = $wo->current_review_stage;
        }

        $myActiveTask = null;
        if ($worker) {
            // 1. Cari task yang sesuai dengan stage aktif WO saat ini
            $myActiveTask = \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItems->pluck('id'))
                ->where('stage_name', $expectedStage)
                ->where('assigned_to', $worker->id)
                ->first();

            // 2. Jika tidak ada, cari apakah ada task revisi yang aktif untuk worker ini
            if (!$myActiveTask) {
                $myActiveTask = \App\Models\ProductionTask::withoutGlobalScopes()
                    ->whereIn('order_item_id', $groupItems->pluck('id'))
                    ->where('assigned_to', $worker->id)
                    ->where('is_revision', true)
                    ->whereIn('status', ['pending', 'in_progress'])
                    ->first();
            }
            
            // 3. Buat virtual task dinamis untuk Petugas QC saat di tahap QC_REVIEW atau QC_AKHIR
            if (!$myActiveTask && $wo->qc_worker_id === $worker->id) {
                if ($wo->status === \App\Models\WorkOrder::STATUS_QC_REVIEW) {
                    $myActiveTask = new \App\Models\ProductionTask([
                        'stage_name'  => 'QC_' . $wo->current_review_stage,
                        'quantity'    => $totalQty,
                        'description' => 'Periksa hasil pengerjaan tahap ' . str_replace('_', ' ', $wo->current_review_stage) . '. Berikan persetujuan per-pekerja di panel QC Review bawah atau kirim revisi jika ada kesalahan.',
                        'status'      => 'in_progress',
                    ]);
                } elseif ($wo->status === \App\Models\WorkOrder::STATUS_QC_AKHIR) {
                    $myActiveTask = new \App\Models\ProductionTask([
                        'stage_name'  => 'QC_AKHIR',
                        'quantity'    => $totalQty,
                        'description' => 'Lakukan verifikasi final untuk seluruh tahapan produksi. Berikan persetujuan pada panel QC Akhir di bawah atau kirim revisi jika diperlukan.',
                        'status'      => 'in_progress',
                    ]);
                }
            }

            // 4. Jika tidak ada, cari task terakhir milik worker ini yang sudah selesai (untuk menampilkan riwayat tugas selesai)
            if (!$myActiveTask) {
                $myActiveTask = \App\Models\ProductionTask::withoutGlobalScopes()
                    ->whereIn('order_item_id', $groupItems->pluck('id'))
                    ->where('assigned_to', $worker->id)
                    ->where('status', 'done')
                    ->orderBy('id', 'desc')
                    ->first();
            }
        }

        $futureTask = null;
        if (!$myActiveTask && $worker) {
            $futureTask = \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItems->pluck('id'))
                ->where('assigned_to', $worker->id)
                ->where('status', 'pending')
                ->first();
        }

        $isMyActiveStage = $myActiveTask ? true : false;
        $myTask = $myActiveTask ?: $futureTask;

        $nextStageLabel = 'selesai dikerjakan';
        if ($myTask) {
            if ($myTask->status === 'done') {
                if ($wo->status === \App\Models\WorkOrder::STATUS_COMPLETED) {
                    $nextStageLabel = 'produksi telah selesai';
                } else {
                    $nextStageLabel = 'saat ini tahap ' . str_replace('_', ' ', $wo->status_label);
                }
            } else {
                if ($myTask->wajib_qc) {
                    $nextStageLabel = 'masuk tahap QC';
                } else {
                    $stages = $wo->stage_sequence ?? [];
                    $currentIdx = array_search($myTask->stage_name, $stages);
                    if ($currentIdx !== false && isset($stages[$currentIdx + 1])) {
                        $nextStageLabel = 'masuk tahap ' . str_replace('_', ' ', $stages[$currentIdx + 1]);
                    }
                }
            }
        }
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



    {{-- ── DETAIL PEKERJAAN ── --}}

    {{-- Kartu Progress WO --}}
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span class="card-label" style="margin-bottom: 0;">Detail Work Order</span>
                <a href="{{ route('work.task.spk', $wo->id) }}" target="_blank" class="download-spk-link" title="Download SPK PDF">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
                    </svg>
                    <span>SPK</span>
                </a>
            </div>
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
                    'Bordir'         => '🧵 Bordir',
                    'Sablon'         => '🖨️ Sablon',
                ];
            @endphp
            <span class="decor-label">{{ $decLabels[$decType] ?? ($decLabels[ucfirst(strtolower($decType))] ?? $decType) }}</span>
        </div>
        @endif
    </div>


    {{-- ── SECTION: TUGAS SAYA ── --}}
    @if($myTask)
    @php
        $isQcTask = in_array(strtoupper($myTask->stage_name ?? ''), ['QC_PREP', 'QC_PERSIAPAN', 'QC_REVIEW', 'QC_FINISHING', 'QC_AKHIR']);
        $displayTaskQty = $isQcTask ? $totalQty : $myTask->quantity;
        
        // Buat default instruksi per tahapan jika deskripsi kosong
        $defaultInstructions = '';
        if (empty($myTask->description)) {
            $stageUpper = strtoupper($myTask->stage_name ?? '');
            if (str_contains($stageUpper, 'PREP') || str_contains($stageUpper, 'PERSIAPAN')) {
                $defaultInstructions = 'Periksa kesiapan bahan utama, kesesuaian warna, dan kelengkapan aksesoris pendukung (kancing/saku/kerah) sebelum produksi dimulai.';
            } elseif (str_contains($stageUpper, 'POTONG') || str_contains($stageUpper, 'CUTTING')) {
                $defaultInstructions = 'Potong bahan utama sesuai pola pola spesifikasi gender, panjang lengan, dan model potongan (tunik/standar). Pastikan toleransi jahitan aman.';
            } elseif (str_contains($stageUpper, 'SABLON') || str_contains($stageUpper, 'BORDIR') || str_contains($stageUpper, 'DECORATION')) {
                $defaultInstructions = 'Kerjakan aplikasi bordir/sablon pada titik lokasi yang telah ditentukan (dada/lengan/punggung) sesuai teknik aplikasi yang diminta.';
            } elseif (str_contains($stageUpper, 'JAHIT') || str_contains($stageUpper, 'SEWING')) {
                $defaultInstructions = 'Jahit potongan bahan dengan rapi. Pasang saku, kancing, atau kerah sesuai detail model spesifikasi. Gunakan benang warna senada.';
            } elseif (str_contains($stageUpper, 'KANCING') || str_contains($stageUpper, 'BUTTON')) {
                $defaultInstructions = 'Pasang kancing atau pelubang kancing pada posisi tengah yang simetris sesuai spesifikasi model kancing.';
            } elseif (str_contains($stageUpper, 'FINISHING')) {
                $defaultInstructions = 'Lakukan pembersihan sisa benang, penyetrikaan uap, lipat rapi, dan packing plastik bening sesuai standar pengiriman.';
            } else {
                $defaultInstructions = 'Kerjakan pengerjaan tahapan ini sesuai dengan rincian model spesifikasi pesanan di bawah.';
            }
        }
    @endphp

    <div class="section-title-bar">🔧 TUGAS KAMU</div>
    <div class="card card-task-highlight">
        <div class="task-detail-row">
            <div class="task-detail-label">Tahap</div>
            <div class="task-detail-value">{{ strtoupper(str_replace('_', ' ', $myTask->stage_name)) }}</div>
        </div>
        <div class="task-detail-row">
            <div class="task-detail-label">Qty Tugas</div>
            <div class="task-detail-value text-purple"><strong>{{ $displayTaskQty }} pcs</strong></div>
        </div>
        @php $taskCustomRecipients = []; $taskStandardRecipients = []; @endphp
        @if(!$isQcTask && !empty($myTask->size_quantities))
        <div class="task-detail-row">
            <div class="task-detail-label">Rincian Ukuran</div>
            <div class="task-detail-value">
                <div class="size-grid mt-0">
                    @foreach($myTask->size_quantities as $sz => $qty)
                        @if(!str_starts_with($sz, '_') && $qty > 0)
                        <div class="size-chip size-chip-sm">
                            <span class="size-label">{{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}</span>
                            <span class="size-qty">{{ $qty }}</span>
                        </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        
        {{-- Cocokkan siapa saja penerima/ukuran custom khusus untuk tugas ini --}}
        @php
            $taskCustomRecipients = [];
            $taskStandardRecipients = [];
            foreach ($groupItems as $gi) {
                $giSize = strtoupper($gi->size ?? 'TANPA_UKURAN');
                $taskQtyForSize = $myTask->size_quantities[$giSize] ?? 0;
                
                if ($taskQtyForSize > 0) {
                    $d = $gi->size_and_request_details ?? [];
                    if (!empty($d['detail_custom'])) {
                        foreach ($d['detail_custom'] as $u) {
                            $taskCustomRecipients[] = [
                                'nama' => $u['nama'] ?? '-',
                                'size' => $u['ukuran'] ?? 'Custom',
                                'ukuran_badan' => !empty($u['LD']) ? "LD:{$u['LD']} PB:{$u['PB']} LP:{$u['LP']}" : '',
                            ];
                        }
                    } elseif ($giSize === 'CUSTOM') {
                        $mArr = [];
                        foreach (['LD','PB','PL','LB','LP','LPh'] as $mk) {
                            if (!empty($d[$mk])) $mArr[] = "$mk:{$d[$mk]}";
                        }
                        $taskCustomRecipients[] = [
                            'nama' => $gi->recipient_name ?? '-',
                            'size' => 'Custom',
                            'ukuran_badan' => implode(' ', $mArr),
                        ];
                    } elseif (!empty($gi->recipient_name)) {
                        $taskStandardRecipients[$giSize][] = $gi->recipient_name;
                    }
                }
            }
        @endphp

        @endif

        @php
            $rawText = $myTask->description ?: $defaultInstructions;
            $hasCustom = !empty($taskCustomRecipients);

            $isQcTask = in_array(strtoupper($myTask->stage_name ?? ''), ['QC_PREP', 'QC_PERSIAPAN', 'QC_AKHIR']) || str_starts_with(strtoupper($myTask->stage_name ?? ''), 'QC_');

            // Pisahkan per baris: admin note vs baris "Pembagian Ukuran:"
            $allLines = explode("\n", $rawText);
            $adminNoteLines = [];
            $sizeBreakdownLine = '';
            foreach ($allLines as $line) {
                if (!$isQcTask && str_starts_with(trim($line), 'Pembagian Ukuran:')) {
                    $sizeBreakdownLine = trim($line);
                } else {
                    $adminNoteLines[] = $line;
                }
            }
            $adminNote = $isQcTask ? '' : trim(implode("\n", $adminNoteLines));

            // Parse items dari baris "Pembagian Ukuran: S (...) | L (...)"
            $hasPipe = !empty($sizeBreakdownLine) && str_contains($sizeBreakdownLine, '|');
            $parsedTitle = '';
            $parsedItems = [];
            if (!empty($sizeBreakdownLine)) {
                $colonPos = strpos($sizeBreakdownLine, ':');
                $parsedTitle = trim(substr($sizeBreakdownLine, 0, $colonPos + 1));
                $afterColon = trim(substr($sizeBreakdownLine, $colonPos + 1));
                $parts = array_map('trim', explode('|', $afterColon));
                foreach ($parts as $part) {
                    if (trim($part)) $parsedItems[] = trim($part);
                }
                if ($hasCustom) {
                    $parsedItems = array_filter($parsedItems, fn($item) => !str_contains($item, 'CUSTOM'));
                }
            }
        @endphp

        @if(!empty($adminNote))
        <div class="task-detail-row">
            <div class="task-detail-label">Catatan Tambahan</div>
            <div class="task-detail-value font-bold" style="color: #374151;">
                {!! nl2br(e($adminNote)) !!}
            </div>
        </div>
        @endif

        <div class="task-detail-row" style="flex-direction: column; align-items: flex-start; text-align: left; width: 100%;">
            <div class="task-detail-label" style="margin-bottom: 6px;">Instruksi Kerja</div>
            <div class="task-detail-value text-note" style="text-align: left; width: 100%;">
                {{-- List ukuran dari rawText (Pembagian Ukuran / Size Toko) --}}
                @if(!empty($parsedItems))
                    @if(!empty($parsedTitle))
                        <div style="font-weight: 700; color: #4B5563; font-size: 13px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                            {{ $parsedTitle }}
                        </div>
                    @endif
                    <ul class="instruction-list">
                        @foreach($parsedItems as $item)
                        <li class="instruction-item">
                            <span class="instruction-pin" style="color:#8000FF;font-weight:900;font-size:16px;line-height:1;">•</span>
                            {!! nl2br(e($item)) !!}
                        </li>
                        @endforeach
                    </ul>
                @else
                    {!! nl2br(e($rawText)) !!}
                @endif

                {{-- Detail Size Custom: hanya jika ada custom recipients --}}
                @if($hasCustom)
                    <div style="font-weight: 700; color: #4B5563; font-size: 13px; margin-top: {{ ($hasPipe && !empty($parsedItems)) ? '14px' : '0' }}; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px;">
                        Detail Size Custom :
                    </div>
                    <ul class="instruction-list">
                        @foreach($taskCustomRecipients as $c)
                        <li class="instruction-item">
                            <span class="instruction-pin" style="color:#8000FF;font-weight:900;font-size:16px;line-height:1;">•</span>
                            <span style="font-weight: 600; color: #1e293b;">{{ $c['nama'] }}</span>
                            @if($c['ukuran_badan'])
                            <div style="font-size: 13px; color: #475569; margin-top: 2px;">({{ $c['ukuran_badan'] }})</div>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>


        
        @if($myTask->is_revision)
        <div class="revision-notice" style="background: #FEF2F2; border: 1.5px solid #FCA5A5; border-radius: 12px; padding: 12px; margin-top: 12px;">
            <div style="font-weight: 700; color: #991B1B; display: flex; align-items: center; gap: 6px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">
                <span>🔧</span> Tugas Revisi
            </div>
            @if($wo->reject_reason)
            <div style="margin-top: 6px; font-size: 13px; color: #7F1D1D; font-weight: 500; line-height: 1.4; background: #FFF; padding: 8px 10px; border-radius: 8px; border: 1px solid #FEE2E2;">
                <strong>Catatan QC:</strong> {{ $wo->reject_reason }}
            </div>
            @else
            <div style="margin-top: 4px; font-size: 12px; color: #7F1D1D;">Perbaiki pengerjaan sesuai arahan QC.</div>
            @endif
        </div>
        @endif
    </div>
    @endif

    {{-- ── SECTION: INFO BAHAN & DEADLINE ── --}}
    @if($bahanLabel || $deadline || $catatanUmum)
    <div class="section-title-bar">📦 INFORMASI BAHAN</div>
    <div class="card">
        @if($bahanLabel)
        <div class="bahan-name">{{ $bahanLabel }}</div>
        @endif
        @if($deadline)
        <div class="info-row">
            <span class="info-label">Deadline</span>
            <span class="info-value {{ $deadline->isPast() ? 'text-danger' : ($deadline->diffInDays(now()) <= 3 ? 'text-warning' : 'text-green') }}">
                {{ $deadline->format('d F Y') }}
                @if(!$deadline->isPast())
                <span style="font-size:11px;font-weight:400;">({{ $deadline->diffForHumans() }})</span>
                @else
                <span style="font-size:11px;font-weight:400;"> — Lewat deadline!</span>
                @endif
            </span>
        </div>
        @endif
        @if($catatanUmum)
        <div class="catatan-box">
            <div class="catatan-label">⚠️ CATATAN UMUM PESANAN</div>
            <div class="catatan-text">{{ $catatanUmum }}</div>
        </div>
        @endif
    </div>
    @endif

    {{-- ── SECTION: GAMBAR DESAIN ── --}}
    @if($designImage)
    <div class="section-title-bar">🎨 REFERENSI DESAIN</div>
    <div class="card" style="padding:12px;">
        <img src="{{ $designImage }}" alt="Gambar Desain" style="width:100%;border-radius:10px;border:1px solid #E5E7EB;">
    </div>
    @endif

    {{-- ── SECTION: RINCIAN PRODUKSI (per grup spesifikasi) ── --}}
    @if(!empty($specGroups))
    <div class="section-title-bar">📋 RINCIAN PRODUKSI</div>
    @foreach($specGroups as $group)
    <div class="card spec-group-card">
        <div class="spec-group-header">
            <span class="spec-gender">{{ $group['gender'] }}</span>
            <span class="spec-qty-badge">{{ $group['total_qty'] }} pcs</span>
        </div>

        @if($group['is_produksi'] && $group['gender'] !== 'UMUM')
        <div class="spec-model-row">
            <span class="spec-tag-item">LENGAN: <strong>{{ $group['sleeve'] }}</strong></span>
            <span class="spec-sep">|</span>
            <span class="spec-tag-item">SAKU: <strong>{{ $group['pocket'] }}</strong></span>
            <span class="spec-sep">|</span>
            <span class="spec-tag-item">KANCING: <strong>{{ $group['button'] }}</strong></span>
        </div>
        @if(!empty($group['collar']) && $group['collar'] !== 'BIASA')
        <div class="spec-model-row" style="margin-top:4px;">
            <span class="spec-tag-item">KERAH: <strong>{{ $group['collar'] }}</strong></span>
            @if($group['tunic'] === 'TUNIK')
            <span class="spec-sep">|</span>
            <span class="spec-tag-item">MODEL: <strong>TUNIK</strong></span>
            @endif
        </div>
        @endif
        @endif

        @if(!empty($group['requests']))
        <div class="request-row">
            📌 {{ implode(' | ', $group['requests']) }}
        </div>
        @endif

        {{-- Sablon / Bordir --}}
        @if(!empty($group['sablon_bordir']))
        <div class="sablon-section">
            <div class="sablon-title">🎨 DETAIL APLIKASI (SABLON / BORDIR)</div>
            @foreach($group['sablon_bordir'] as $sb)
            <div class="sablon-item">{{ $sb }}</div>
            @endforeach
        </div>
        @endif

        {{-- Ukuran & Qty --}}
        @if(!empty($group['sizes']))
        <div class="size-grid">
            @foreach($group['sizes'] as $sz => $qty)
            <div class="size-chip">
                <span class="size-label">{{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}</span>
                <span class="size-qty">{{ $qty }}</span>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Penerima Standar --}}
        @if(!empty($group['recipients']['standard']))
        <div class="recipients-section">
            <div class="recipients-title">👤 Nama Penerima</div>
            @foreach($group['recipients']['standard'] as $sz => $names)
            <div class="recipient-row">
                <span class="recipient-size">{{ $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz }}</span>
                <span class="recipient-names">{{ implode(', ', $names) }}</span>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Custom Ukuran Badan --}}
        @if(!empty($group['recipients']['custom']))
        <div class="recipients-section">
            <div class="recipients-title">📐 Ukuran Custom</div>
            @foreach($group['recipients']['custom'] as $c)
            <div class="recipient-custom-row">
                <span class="recipient-nama">{{ $c['nama'] }}</span>
                @if($c['ukuran_badan'])
                <span class="recipient-ukuran">{{ $c['ukuran_badan'] }}</span>
                @endif
            </div>
            @endforeach
        </div>
        @endif
    </div>
    @endforeach
    @endif

    {{-- Progress Tahapan --}}
    <div class="card">
        <div class="card-header">
            <span class="card-label">Alur Produksi</span>
        </div>
        <div class="stage-list">
            @php
                // Bangun urutan tahap: CREATED → (QC_PREP opsional) → stages → COMPLETED
                $stages = $wo->stage_sequence ?? [];
                
                // Normalisasi dan hilangkan duplikasi QC_PERSIAPAN / QC_PREP di $stages
                $normalizedStages = [];
                $hasPrep = false;
                foreach ($stages as $stg) {
                    $stgUpper = strtoupper($stg);
                    if ($stgUpper === 'QC_PREP' || $stgUpper === 'QC_PERSIAPAN') {
                        if ($hasPrep) { continue; } // Lewati jika duplikat
                        $normalizedStages[] = 'QC_PREP';
                        $hasPrep = true;
                    } else {
                        $normalizedStages[] = $stg;
                    }
                }
                
                $allStages = ['CREATED'];
                if ($wo->has_qc_prep && !$hasPrep) {
                    $allStages[] = 'QC_PREP';
                }
                $allStages = array_merge($allStages, $normalizedStages, ['COMPLETED']);
                
                // Normalisasi status saat ini untuk pencocokan indeks
                $currentStatus = $wo->status;
                if ($currentStatus === 'QC_PERSIAPAN') { $currentStatus = 'QC_PREP'; }
                if ($currentStatus === 'QC_REVIEW') {
                    $currentStatus = $wo->current_review_stage;
                    if ($currentStatus === 'QC_PERSIAPAN') { $currentStatus = 'QC_PREP'; }
                }
                if ($currentStatus === 'QC_AKHIR') {
                    $currentStatus = end($normalizedStages) ?: 'COMPLETED';
                }
                
                $currentIdx = array_search($currentStatus, $allStages);
                if ($currentIdx === false) { $currentIdx = 1; } // fallback ke tahap awal jika tidak cocok
                
                $stageLabels = [
                    'CREATED'       => 'Dibuat',
                    'QC_PREP'       => 'QC Persiapan',
                    'QC_PERSIAPAN'  => 'QC Persiapan',
                    'QC_REVIEW'     => 'QC Review',
                    'COMPLETED'     => 'Selesai',
                ];
            @endphp
            @foreach($allStages as $s)
            @php
                $idx = array_search($s, $allStages);
                $isDone = $idx < $currentIdx;
                $isCurrent = $idx === $currentIdx;
                $label = $stageLabels[$s] ?? str_replace('_', ' ', $s);
                if ($wo->status === 'QC_REVIEW' && $s === $wo->current_review_stage) {
                    $isCurrent = true;
                    $label = 'QC ' . str_replace('_', ' ', $s);
                }
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
    @error('aksi')
    <div class="alert-error" style="margin: 12px 16px; padding: 12px; background-color: #fee2e2; border: 1px solid #fca5a5; color: #b91c1c; border-radius: 8px; font-size: 14px; font-weight: 500;">
        ⚠️ {{ $message }}
    </div>
    @enderror

    @if($isCompleted)
    <div class="action-banner banner-green">
        <span>✅ Work Order ini sudah selesai!</span>
    </div>

    @elseif($isQcStage)
    {{-- QC Actions --}}
    @if($worker?->id === $wo->qc_worker_id && $isMyActiveStage && $wo->status === 'QC_PREP')
    <div class="action-card">
        {{-- QC Persiapan: hanya approve (tidak ada reject) --}}
        <div class="action-title">📋 QC Persiapan Bahan & Peralatan</div>
        <p class="action-desc">Pastikan semua bahan dan peralatan sudah siap sebelum produksi dimulai. Tekan Approve untuk melanjutkan ke tahap produksi.</p>

        <button wire:click="approveQC"
                wire:loading.attr="disabled"
                class="btn btn-primary btn-full">
            <span wire:loading.remove wire:target="approveQC">✅ Approve & Mulai Produksi</span>
            <span wire:loading wire:target="approveQC">⏳ Memproses...</span>
        </button>
    </div>
    @elseif($worker?->id === $wo->qc_worker_id && $wo->status === \App\Models\WorkOrder::STATUS_QC_REVIEW)
        {{-- Sembunyikan panel bulk approve di atas karena sudah digantikan per-worker review panel di bawah --}}
    @elseif($myActiveTask && ($myActiveTask->is_revision ?? false) && in_array($myActiveTask->status, ['pending', 'in_progress']))
    {{-- Worker punya revision task — tampilkan action card meski WO sedang di tahap QC --}}
    <div class="action-card" style="margin-top:16px;">
        <div class="action-title">🔧 Tugas Anda: {{ $myActiveTask->is_revision ? 'Perlu Perbaikan' : $statusLabel }}</div>
        <div class="action-desc" style="margin-bottom:12px;font-size:13px;color:#64748b;">
            Tahap: <strong>{{ str_replace('_', ' ', $myActiveTask->stage_name) }}</strong> — {{ $myActiveTask->quantity }} pcs
        </div>
        @if($myActiveTask->status === 'pending')
        <button wire:click="mulaiKerjakan" wire:loading.attr="disabled" class="btn btn-warning btn-full btn-lg">
            <span wire:loading.remove wire:target="mulaiKerjakan">🔧 Mulai Perbaikan</span>
            <span wire:loading wire:target="mulaiKerjakan">⏳ Memulai...</span>
        </button>
        @else
        <div class="started-info">⏱️ Dimulai: {{ $myActiveTask->updated_at?->format('d M Y, H:i') }}</div>
        <button wire:click="selesaiKerjakan" wire:loading.attr="disabled" class="btn btn-success btn-full btn-lg">
            <span wire:loading.remove wire:target="selesaiKerjakan">✅ Selesai Perbaikan</span>
            <span wire:loading wire:target="selesaiKerjakan">⏳ Memproses...</span>
        </button>
        @endif
    </div>
    @else
    <div class="action-banner banner-green">
        @if($myTask && $myTask->status === 'done')
            <span>✅ Tugas {{ str_replace('_', ' ', $myTask->stage_name) }} selesai & {{ $nextStageLabel }}</span>
        @elseif($wo->status === \App\Models\WorkOrder::STATUS_QC_REVIEW)
            <span>🔍 Sedang dalam verifikasi hasil pekerjaan oleh Petugas QC...</span>
        @else
            <span>🔍 Menunggu verifikasi dari Petugas QC...</span>
        @endif
    </div>
    @endif


    @elseif($isWorkerStage)
    {{-- Tukang Actions --}}
    @if($isMyActiveStage)
        @if($myActiveTask && $myActiveTask->status === 'done')
        <div class="action-banner banner-green">
            <span>✅ Tugas {{ str_replace('_', ' ', $myActiveTask->stage_name) }} selesai & {{ $nextStageLabel }}</span>
        </div>
        @else
        <div class="action-card">
            <div class="action-title">🔧 Tugas Anda: {{ $statusLabel }}</div>

            @if($myActiveTask && $myActiveTask->status === 'pending')
            @php $isRevision = $myActiveTask->is_revision ?? false; @endphp
            <button wire:click="mulaiKerjakan"
                    wire:loading.attr="disabled"
                    class="btn {{ $isRevision ? 'btn-warning' : 'btn-primary' }} btn-full btn-lg">
                 <span wire:loading.remove wire:target="mulaiKerjakan">
                    @php
                        $stageName = strtoupper($myActiveTask->stage_name ?? '');
                        $isQcPrep = in_array($stageName, ['QC_PREP', 'QC_PERSIAPAN']);
                    @endphp
                    @if($isQcPrep)
                        🔥 Mulai QC Persiapan
                    @elseif($isRevision)
                        🔧 Mulai Perbaikan
                    @else
                        🔥 Mulai Kerjakan
                    @endif
                </span>
                <span wire:loading wire:target="mulaiKerjakan">⏳ Memulai...</span>
            </button>
            @else
            <div class="started-info">
                ⏱️ Dimulai: {{ $myActiveTask?->updated_at?->format('d M Y, H:i') }}
            </div>
            <button wire:click="selesaiKerjakan"
                    wire:loading.attr="disabled"
                    class="btn btn-success btn-full btn-lg">
                <span wire:loading.remove wire:target="selesaiKerjakan">
                    @php
                        $nextStatus = $wo->getNextStatus();
                        $isActiveTaskQcPrep = in_array(strtoupper($myActiveTask->stage_name ?? ''), ['QC_PREP', 'QC_PERSIAPAN']);
                        if ($isActiveTaskQcPrep) {
                            $btnLabel = '✅ Selesai & Lanjutkan';
                        } elseif ($nextStatus === \App\Models\WorkOrder::STATUS_QC_REVIEW) {
                            $btnLabel = '✅ Selesai & Kirim ke QC';
                        } elseif ($nextStatus === \App\Models\WorkOrder::STATUS_COMPLETED) {
                            $btnLabel = '✅ Selesai & Tandai Selesai';
                        } elseif ($nextStatus) {
                            $btnLabel = '✅ Selesai & Lanjut ke ' . str_replace('_', ' ', $nextStatus);
                        } else {
                            $btnLabel = '✅ Selesai';
                        }
                    @endphp
                    {{ $btnLabel }}
                </span>
                <span wire:loading wire:target="selesaiKerjakan">⏳ Memproses...</span>
            </button>
            @endif
        </div>
        @endif
    @else
    <div class="action-banner banner-gray">
        @if($myTask && $myTask->status === 'done')
            <span>✅ Tugas Anda pada tahap {{ str_replace('_', ' ', $myTask->stage_name) }} telah selesai dikerjakan!</span>
        @else
            <span>⏳ Menunggu giliran tugas Anda atau Anda tidak bertugas di tahap ini.</span>
        @endif
    </div>
    @endif

    @elseif($wo->status === 'CREATED')
    <div class="action-banner banner-gray">
        <span>⏳ Menunggu persetujuan QC Persiapan...</span>
    </div>
    @endif



    {{-- ── QC REVIEW SECTION (hanya untuk QC worker di halaman detail) ── --}}
    @if($wo->status === \App\Models\WorkOrder::STATUS_QC_REVIEW && $worker && $wo->qc_worker_id === $worker->id)
    @php
        $qcGroupItemIds = $orderItem ? $orderItem->getItemsInGroup()->pluck('id') : collect([$wo->order_item_id]);
        $qcReviewTasks = \App\Models\ProductionTask::withoutGlobalScopes()
            ->whereIn('order_item_id', $qcGroupItemIds)
            ->where('stage_name', $wo->current_review_stage)
            ->with('assignedTo')
            ->get();
    @endphp

    <div class="action-card" style="margin-top: 16px; margin-bottom: 24px; border: 1.5px solid #FDBA74; box-shadow: 0 4px 15px rgba(249, 115, 22, 0.08);">
        <div style="font-size:14px;font-weight:800;color:#EA580C;text-transform:uppercase;
                    letter-spacing:0.5px;margin-bottom:14px;display:flex;align-items:center;gap:6px;">
            <span>🔍</span> QC Review: {{ str_replace('_', ' ', $wo->current_review_stage ?? '') }}
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;">
            @foreach($qcReviewTasks as $qcTask)
            @php $isApproved = $qcTask->qc_approved ?? false; @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;
                        background:{{ $isApproved ? '#f0fdf4' : '#fefce8' }};
                        border:1px solid {{ $isApproved ? '#86efac' : '#fde68a' }};
                        border-radius:12px;padding:12px 14px;gap:8px;">
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:14px;color:#1e293b;">
                        {{ $qcTask->assignedTo?->name ?? 'Tanpa Pekerja' }}
                    </div>
                    <div style="font-size:12px;color:#64748b;margin-top:2px;">
                        {{ $qcTask->quantity }} pcs
                        @if($qcTask->is_revision)
                        <span style="margin-left:4px;font-size:11px;font-weight:600;color:#dc2626;
                                     background:#fee2e2;border-radius:10px;padding:2px 6px;">Revisi</span>
                        @endif
                    </div>
                </div>
                @php
                    $isRevising = $qcTask->is_revision && in_array($qcTask->status, ['pending', 'in_progress']);
                @endphp

                @if($isApproved)
                <span style="font-size:12px;font-weight:600;color:#16a34a;
                             background:#dcfce7;border-radius:20px;padding:5px 12px;
                             white-space:nowrap;flex-shrink:0;">✅ Disetujui</span>
                @elseif($isRevising)
                <span style="font-size:12px;font-weight:600;color:#dc2626;
                             background:#fee2e2;border-radius:20px;padding:5px 12px;
                             white-space:nowrap;flex-shrink:0;">⏳ Sedang Direvisi</span>
                @else
                <div style="display:flex;gap:6px;flex-shrink:0;">
                    <button wire:click="approveTask({{ $qcTask->id }})"
                            wire:loading.attr="disabled"
                            style="font-size:12px;font-weight:600;color:#fff;background:#22c55e;
                                   border:none;border-radius:8px;padding:7px 14px;cursor:pointer;white-space:nowrap;">
                        <span wire:loading.remove wire:target="approveTask({{ $qcTask->id }})">✅ Setujui</span>
                        <span wire:loading wire:target="approveTask({{ $qcTask->id }})">⏳</span>
                    </button>
                    <button wire:click="openTaskReject({{ $qcTask->id }})"
                            style="font-size:12px;font-weight:600;color:#fff;background:#ef4444;
                                   border:none;border-radius:8px;padding:7px 14px;cursor:pointer;white-space:nowrap;">
                        🔧 Revisi
                    </button>
                </div>
                @endif
            </div>
            @endforeach
        </div>
    </div>

    {{-- Modal Revisi Task dari halaman detail --}}
    @if($qcRejectTaskId)
    @php
        $rejectingTask = \App\Models\ProductionTask::withoutGlobalScopes()
            ->with(['assignedTo', 'orderItem.order.customer'])
            ->find($qcRejectTaskId);
    @endphp
    @if($rejectingTask)
    <div class="modal-overlay" wire:click.self="closeTaskReject">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Revisi Pekerjaan</h3>
                <button wire:click="closeTaskReject" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="review-info">
                    <div class="review-stage">{{ $rejectingTask->assignedTo?->name ?? 'Tanpa Pekerja' }}</div>
                    <div class="review-product">{{ $rejectingTask->orderItem?->product_name ?? '-' }} ({{ $rejectingTask->quantity }} pcs)</div>
                    <div class="review-customer">{{ $rejectingTask->orderItem?->order?->customer?->name ?? '-' }}</div>
                </div>
                <div class="reject-section" style="margin-top:16px;">
                    <label class="reject-label">Alasan Revisi (wajib):</label>
                    <textarea wire:model="qcRejectReason"
                              class="reject-textarea"
                              placeholder="Tuliskan alasan revisi untuk {{ $rejectingTask->assignedTo?->name ?? 'pekerja' }}..."
                              rows="3"></textarea>
                    @error('qcRejectReason')
                    <span class="field-error">{{ $message }}</span>
                    @enderror
                    <button wire:click="rejectTask({{ $qcRejectTaskId }})"
                            wire:loading.attr="disabled"
                            class="btn btn-danger btn-full"
                            style="margin-top:12px;">
                        <span wire:loading.remove wire:target="rejectTask({{ $qcRejectTaskId }})">🔧 Kirim Revisi</span>
                        <span wire:loading wire:target="rejectTask({{ $qcRejectTaskId }})">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endif
    @endif

    {{-- ── QC AKHIR SECTION ── --}}
    @if($wo->status === \App\Models\WorkOrder::STATUS_QC_AKHIR && $worker && $wo->qc_worker_id === $worker->id)
    @php
        $qaGroupItemIds = $orderItem ? $orderItem->getItemsInGroup()->pluck('id') : collect([$wo->order_item_id]);
        $qaAllTasks = \App\Models\ProductionTask::withoutGlobalScopes()
            ->whereIn('order_item_id', $qaGroupItemIds)
            ->where('stage_name', '!=', 'QC_PERSIAPAN')
            ->with('assignedTo')
            ->get()
            ->groupBy('stage_name');
        $qaHasPendingRevision = $qaAllTasks->flatten()->contains(fn($t) => $t->status !== 'done');
    @endphp

    <div class="action-card" style="margin-top:16px;margin-bottom:24px;border:1.5px solid #C084FC;box-shadow:0 4px 15px rgba(128,0,255,0.08);">
        <div style="font-size:14px;font-weight:800;color:#7C3AED;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:14px;display:flex;align-items:center;gap:6px;">
            <span>🔍</span> QC Akhir — Verifikasi Final
        </div>
        <div style="font-size:12px;color:#64748b;margin-bottom:14px;line-height:1.5;">
            Tinjau semua hasil pengerjaan. Tahap yang sudah punya QC Review sendiri tidak bisa direvisi dari sini.
        </div>

        @if($qaHasPendingRevision)
        <div style="background:#FEF3C7;border:1px solid #FCD34D;border-radius:10px;padding:10px 12px;margin-bottom:14px;font-size:12px;color:#92400E;font-weight:600;">
            ⏳ Ada pekerjaan yang masih dalam proses revisi. Tunggu hingga selesai.
        </div>
        @endif

        <div style="display:flex;flex-direction:column;gap:12px;">
            @foreach($qaAllTasks as $stageName => $stageTasks)
            <div>
                <div style="font-size:11px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:0.7px;margin-bottom:6px;">
                    {{ str_replace('_', ' ', $stageName) }}
                </div>
                <div style="display:flex;flex-direction:column;gap:6px;">
                    @foreach($stageTasks as $qaTask)
                    @php
                        $woStages     = $wo->stage_sequence ?? [];
                        $isLastStage  = end($woStages) === $qaTask->stage_name;
                        $hasQcAkhir   = $wo->has_qc_selesai ?? true;
                        
                        $qaApproved   = $qaTask->qc_approved ?? false;
                        $qaHasOwnQc   = ($qaTask->wajib_qc ?? false) && !($isLastStage && $hasQcAkhir); // Bypass "Sudah QC" jika tahap terakhir
                        $qaDone       = $qaTask->status === 'done';
                        $qaRevision   = $qaTask->is_revision ?? false;
                    @endphp
                    <div style="display:flex;align-items:center;justify-content:space-between;
                                background:{{ $qaApproved ? '#f0fdf4' : ($qaHasOwnQc ? '#f8fafc' : '#fefce8') }};
                                border:1px solid {{ $qaApproved ? '#86efac' : ($qaHasOwnQc ? '#e2e8f0' : '#fde68a') }};
                                border-radius:12px;padding:12px 14px;gap:8px;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:700;font-size:13px;color:#1e293b;">
                                {{ $qaTask->assignedTo?->name ?? 'Tanpa Pekerja' }}
                            </div>
                            <div style="font-size:12px;color:#64748b;margin-top:2px;">
                                {{ $qaTask->quantity }} pcs
                                @if($qaRevision)
                                <span style="margin-left:4px;font-size:11px;font-weight:600;color:#dc2626;background:#fee2e2;border-radius:10px;padding:2px 6px;">Revisi</span>
                                @endif
                                @if($qaHasOwnQc)
                                <span style="margin-left:4px;font-size:11px;font-weight:600;color:#2563eb;background:#dbeafe;border-radius:10px;padding:2px 6px;">Sudah QC</span>
                                @endif
                                @if(!$qaDone && !$qaRevision)
                                <span style="margin-left:4px;font-size:11px;font-weight:600;color:#d97706;background:#fef3c7;border-radius:10px;padding:2px 6px;">Dalam Revisi</span>
                                @endif
                            </div>
                        </div>
                        @if($qaApproved || $qaHasOwnQc)
                            <span style="font-size:12px;font-weight:600;color:{{ $qaHasOwnQc ? '#2563eb' : '#16a34a' }};
                                         background:{{ $qaHasOwnQc ? '#dbeafe' : '#dcfce7' }};
                                         border-radius:20px;padding:5px 12px;white-space:nowrap;flex-shrink:0;">
                                {{ $qaHasOwnQc ? '✅ Sudah QC' : '✅ Disetujui' }}
                            </span>
                        @elseif(!$qaDone)
                            <span style="font-size:12px;font-weight:600;color:#dc2626;background:#fee2e2;border-radius:20px;padding:5px 12px;white-space:nowrap;flex-shrink:0;">
                                🔧 Dalam Revisi
                            </span>
                        @else
                            <div style="display:flex;gap:6px;flex-shrink:0;">
                                <button wire:click="approveTaskQcAkhir({{ $qaTask->id }})" wire:loading.attr="disabled"
                                        style="font-size:12px;font-weight:600;color:#fff;background:#22c55e;border:none;border-radius:8px;padding:7px 14px;cursor:pointer;white-space:nowrap;">
                                    <span wire:loading.remove wire:target="approveTaskQcAkhir({{ $qaTask->id }})">✅ Setujui</span>
                                    <span wire:loading wire:target="approveTaskQcAkhir({{ $qaTask->id }})">⏳</span>
                                </button>
                                <button wire:click="openQcAkhirReject({{ $qaTask->id }})"
                                        style="font-size:12px;font-weight:600;color:#fff;background:#ef4444;border:none;border-radius:8px;padding:7px 14px;cursor:pointer;white-space:nowrap;">
                                    🔧 Revisi
                                </button>
                            </div>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        {{-- Tombol Serahkan ke Admin (semua approved & tidak ada revisi pending) --}}
        @if(!$qaHasPendingRevision)
        @php
            $qaAllApproved = $qaAllTasks->flatten()->every(function($t) use ($wo) {
                $woStages = $wo->stage_sequence ?? [];
                $isLastStage = end($woStages) === $t->stage_name;
                $hasQcAkhir = $wo->has_qc_selesai ?? true;
                $hasOwnQc = ($t->wajib_qc ?? false) && !($isLastStage && $hasQcAkhir);
                return ($t->qc_approved ?? false) || $hasOwnQc;
            });
        @endphp
        @if($qaAllApproved)
        <div style="margin-top:16px;">
            <button wire:click="approveQcAkhir" wire:loading.attr="disabled"
                    class="btn btn-success btn-full btn-lg">
                <span wire:loading.remove wire:target="approveQcAkhir">✅ Serahkan ke Admin — Selesai</span>
                <span wire:loading wire:target="approveQcAkhir">⏳ Memproses...</span>
            </button>
        </div>
        @endif
        @endif
    </div>

    {{-- Modal Revisi QC Akhir --}}
    @if($qcAkhirRejectTaskId)
    @php
        $qaRejectingTask = \App\Models\ProductionTask::withoutGlobalScopes()
            ->with(['assignedTo', 'orderItem.order.customer'])
            ->find($qcAkhirRejectTaskId);
    @endphp
    @if($qaRejectingTask)
    <div class="modal-overlay" wire:click.self="closeQcAkhirReject">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🔧 Revisi QC Akhir</h3>
                <button wire:click="closeQcAkhirReject" class="modal-close">&times;</button>
            </div>
            <div class="modal-body">
                <div class="review-info">
                    <div class="review-stage">{{ $qaRejectingTask->assignedTo?->name ?? 'Tanpa Pekerja' }}</div>
                    <div class="review-product">
                        {{ $qaRejectingTask->orderItem?->product_name ?? '-' }} ({{ $qaRejectingTask->quantity }} pcs)
                        — Tahap: {{ str_replace('_', ' ', $qaRejectingTask->stage_name) }}
                    </div>
                    <div class="review-customer">{{ $qaRejectingTask->orderItem?->order?->customer?->name ?? '-' }}</div>
                </div>
                <div class="reject-section" style="margin-top:16px;">
                    <label class="reject-label">Alasan Revisi (wajib):</label>
                    <textarea wire:model="qcAkhirRejectReason"
                              class="reject-textarea"
                              placeholder="Tuliskan alasan revisi untuk {{ $qaRejectingTask->assignedTo?->name ?? 'pekerja' }}..."
                              rows="3"></textarea>
                    @error('qcAkhirRejectReason')
                    <span class="field-error">{{ $message }}</span>
                    @enderror
                    <button wire:click="rejectTaskQcAkhir({{ $qcAkhirRejectTaskId }})"
                            wire:loading.attr="disabled"
                            class="btn btn-danger btn-full"
                            style="margin-top:12px;">
                        <span wire:loading.remove wire:target="rejectTaskQcAkhir({{ $qcAkhirRejectTaskId }})">🔧 Kirim Revisi ke Pekerja</span>
                        <span wire:loading wire:target="rejectTaskQcAkhir({{ $qcAkhirRejectTaskId }})">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif
    @endif
    @endif

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
.header-left { display: flex; align-items: center; }
.back-btn {
    display: flex; align-items: center; gap: 6px;
    color: #6600CC; font-size: 13px; font-weight: 700;
    text-decoration: none;
    padding: 6px 10px 6px 2px;
    border-radius: 8px;
    transition: color 0.15s;
}
.back-btn:hover { color: #4D0099; }
.download-spk-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: #F3E8FF;
    color: #8000FF;
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid #E9D5FF;
    transition: all 0.15s ease;
}
.download-spk-link:hover {
    background: #E9D5FF;
    color: #6B21A8;
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
.size-grid.mt-0 { margin-top: 0; }
.size-chip {
    display: flex; flex-direction: column; align-items: center;
    padding: 8px 14px;
    background: #F9FAFB;
    border: 1px solid #E5E7EB;
    border-radius: 10px;
    min-width: 56px;
}
.size-chip-sm {
    padding: 4px 8px;
    min-width: 40px;
}
.size-chip-sm .size-qty {
    font-size: 15px;
}
.size-label { font-size: 12px; font-weight: 700; color: #374151; }
.size-qty { font-size: 20px; font-weight: 800; color: #8000FF; line-height: 1.2; }

/* ── Section Title Bar ── */
.section-title-bar {
    font-size: 11px;
    font-weight: 700;
    color: #4B5563;
    margin: 20px 18px 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ── Card Task Highlight ── */
.card-task-highlight {
    border-left: 4px solid #8000FF;
    background: #FAF8FF;
}
.task-detail-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 8px 0;
    border-bottom: 1px dashed #E5E7EB;
}
.task-detail-row:last-child {
    border-bottom: none;
}
.task-detail-label {
    font-size: 12px;
    color: #6B7280;
    font-weight: 600;
}
.task-detail-value {
    font-size: 13px;
    font-weight: 700;
    color: #111827;
    text-align: right;
}
.task-detail-value.text-note {
    font-weight: 500;
    color: #4B5563;
    font-style: normal;
}
.revision-notice {
    margin-top: 8px;
    padding: 8px 12px;
    background: #FEF2F2;
    border: 1px solid #FEE2E2;
    color: #DC2626;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
}

/* ── Bahan & Deadline ── */
.bahan-name {
    font-size: 15px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 8px;
}
.info-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}
.catatan-box {
    margin-top: 10px;
    padding: 10px;
    background: #FFFBEB;
    border: 1px solid #FEF3C7;
    border-radius: 8px;
}
.catatan-label {
    font-size: 11px;
    font-weight: 700;
    color: #D97706;
}
.catatan-text {
    font-size: 12px;
    color: #78350F;
    margin-top: 4px;
    line-height: 1.4;
}

/* ── Spec Group Card ── */
.spec-group-card {
    border-top: 3px solid #8000FF;
}
.spec-group-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}
.spec-gender {
    font-size: 14px;
    font-weight: 800;
    color: #111827;
}
.spec-qty-badge {
    background: #F3E8FF;
    color: #8000FF;
    font-size: 12px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 6px;
}
.spec-model-row {
    font-size: 11px;
    color: #4B5563;
    background: #F9FAFB;
    padding: 6px 10px;
    border-radius: 6px;
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
    align-items: center;
    margin-bottom: 8px;
}
.spec-tag-item {
    font-weight: 500;
}
.spec-sep {
    color: #D1D5DB;
}
.request-row {
    font-size: 12px;
    font-weight: 600;
    color: #6B7280;
    background: #EFF6FF;
    padding: 6px 10px;
    border-radius: 6px;
    margin-bottom: 10px;
}
.sablon-section {
    margin-top: 8px;
    margin-bottom: 12px;
    padding: 10px;
    background: #F5F3FF;
    border: 1px solid #EDE9FE;
    border-radius: 8px;
}
.sablon-title {
    font-size: 10px;
    font-weight: 700;
    color: #6D28D9;
    margin-bottom: 4px;
}
.sablon-item {
    font-size: 12px;
    color: #4C1D95;
    font-weight: 600;
}
.recipients-section {
    margin-top: 12px;
    border-top: 1px solid #E5E7EB;
    padding-top: 10px;
}
.recipients-title {
    font-size: 11px;
    font-weight: 700;
    color: #6B7280;
    margin-bottom: 6px;
}
.recipient-row {
    display: flex;
    font-size: 12px;
    margin-bottom: 4px;
}
.recipient-size {
    font-weight: 700;
    color: #8000FF;
    background: #F3E8FF;
    padding: 0 4px;
    border-radius: 4px;
    margin-right: 8px;
    min-width: 24px;
    text-align: center;
}
.recipient-names {
    color: #374151;
}
.recipient-custom-row {
    font-size: 12px;
    padding: 4px 0;
    border-bottom: 1px dashed #F3F4F6;
}
.recipient-custom-row:last-child {
    border-bottom: none;
}
.recipient-nama {
    font-weight: 700;
    color: #374151;
}
.recipient-ukuran {
    font-size: 11px;
    color: #6B7280;
    display: block;
    font-family: monospace;
}

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

/* ── Modal ── */
.modal-overlay {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.6);
    display: flex; align-items: center; justify-content: center;
    z-index: 10000;
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
.review-stage { font-weight: 700; color: #EA580C; font-size: 14px; }
.review-product { font-weight: 600; color: #111827; font-size: 15px; margin-top: 4px; }
.review-customer { font-size: 12px; color: #6B7280; margin-top: 2px; }
.reject-section { margin-top: 8px; }
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


/* ── Instruction Typography & Lists ── */
.instruction-list {
    margin: 0;
    padding-left: 0;
    list-style-type: none;
    display: flex;
    flex-direction: column;
    gap: 8px;
    width: 100%;
}
.instruction-item {
    position: relative;
    padding-left: 20px;
    color: #374151;
    font-size: 14px;
    line-height: 1.5;
}
.instruction-pin {
    position: absolute;
    left: 0;
    color: #8000FF;
    font-size: 13px;
}
.instruction-plain-text {
    color: #374151;
    padding-left: 4px;
    white-space: pre-line;
    font-size: 14px;
    line-height: 1.5;
}

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
.btn-warning  { background: #EA580C; color: white; }
.btn-warning:not(:disabled):hover { background: #C2410C; }
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
