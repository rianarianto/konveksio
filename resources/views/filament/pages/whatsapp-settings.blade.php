<x-filament-panels::page>
    <style>
        [x-cloak] { display: none !important; }
        
        .wa-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1.5px solid #f1f5f9;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.04), 0 2px 4px -1px rgba(0, 0, 0, 0.02);
            padding: 24px;
        }

        .wa-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid #f3f4f6;
        }

        .wa-title {
            font-size: 15px;
            font-weight: 600;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .wa-stat-card {
            background: #ffffff;
            border-radius: 16px;
            border: 1.5px solid #f1f5f9;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }

        .wa-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .wa-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 13px;
            font-weight: 600;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            border: none;
            line-height: 1.25;
        }
        
        .wa-btn:active {
            transform: scale(0.98);
        }

        .wa-btn-primary {
            background: #8000FF;
            color: #ffffff;
        }
        .wa-btn-primary:hover {
            background: #6b00d6;
        }

        .wa-btn-warning {
            background: #f59e0b;
            color: #ffffff;
        }
        .wa-btn-warning:hover {
            background: #d97706;
        }

        .wa-btn-danger {
            background: #ef4444;
            color: #ffffff;
        }
        .wa-btn-danger:hover {
            background: #dc2626;
        }

        .wa-btn-secondary {
            background: #f3f4f6;
            color: #4b5563;
            border: 1px solid #e5e7eb;
        }
        .wa-btn-secondary:hover {
            background: #e5e7eb;
            color: #1f2937;
        }

        .wa-input, .wa-textarea {
            width: 100%;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 9px 12px;
            font-size: 13px;
            color: #374151;
            outline: none;
            transition: all 0.15s ease;
        }
        .wa-input:focus, .wa-textarea:focus {
            background: #ffffff;
            border-color: #8000FF;
            box-shadow: 0 0 0 3px rgba(128, 0, 255, 0.1);
        }

        .wa-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 9999px;
            line-height: 1;
        }
    </style>

    <div
        x-data="whatsappSettings()"
        x-init="startPolling()"
        class="space-y-6"
    >
        {{-- 1. TOP HEADER STATUS BANNER --}}
        <div class="wa-card">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                
                {{-- Left: Status & Info --}}
                <div class="flex items-center gap-4">
                    {{-- WhatsApp Official Icon + Status Overlay --}}
                    <div class="flex-shrink-0" style="width: 52px; height: 52px;">
                        <div style="width: 52px; height: 52px; border-radius: 14px; background: #f0fdf4; border: 1.5px solid #bbf7d0; display: flex; align-items: center; justify-content: center;">
                            <svg style="width: 30px; height: 30px;" viewBox="0 0 32 32" fill="none">
                                <path d="M16 2C8.268 2 2 8.268 2 16c0 2.766.804 5.344 2.19 7.514L2.054 29.58a.75.75 0 00.93.93l6.233-2.09A13.935 13.935 0 0016 30c7.732 0 14-6.268 14-14S23.732 2 16 2z" fill="#25D366"/>
                                <path d="M22.84 19.348c-.373-.187-2.208-1.09-2.55-1.214-.343-.125-.592-.187-.841.187-.249.373-.965 1.214-1.183 1.463-.218.25-.436.28-.809.094-.374-.187-1.577-.582-3.003-1.854-1.11-.99-1.86-2.213-2.078-2.587-.218-.374-.023-.576.164-.762.168-.168.374-.436.56-.654.187-.218.25-.373.374-.622.124-.25.062-.468-.032-.655-.093-.187-.84-2.024-1.151-2.772-.303-.728-.611-.63-.84-.641-.218-.011-.468-.013-.717-.013-.249 0-.654.093-.996.467-.343.374-1.308 1.277-1.308 3.114 0 1.838 1.339 3.613 1.526 3.862.187.25 2.635 4.024 6.384 5.644.892.386 1.589.616 2.132.788.896.285 1.712.245 2.356.149.719-.107 2.208-.903 2.519-1.775.311-.872.311-1.619.218-1.775-.093-.156-.343-.25-.716-.436z" fill="#ffffff"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Text Info --}}
                    <div>
                        <div class="flex items-center gap-2.5">
                            <h2 class="text-lg font-semibold text-[#1f2937] tracking-tight">WhatsApp Gateway</h2>
                            <template x-if="status === 'ready'">
                                <span class="wa-badge" style="background-color: #dcfce7; color: #16a34a; border: 1px solid #bbf7d0;">
                                    <span style="font-size: 8px;">●</span> ONLINE & SIAP
                                </span>
                            </template>
                            <template x-if="status === 'qr'">
                                <span class="wa-badge" style="background-color: #fef9c3; color: #ca8a04; border: 1px solid #fef08a;">
                                    <span style="font-size: 8px;">●</span> BUTUH SCAN QR
                                </span>
                            </template>
                            <template x-if="status !== 'ready' && status !== 'qr'">
                                <span class="wa-badge" style="background-color: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">
                                    <span style="font-size: 8px;">●</span> TERPUTUS
                                </span>
                            </template>
                        </div>

                        <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mt-1.5 text-xs text-[#6b7280]">
                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-[#9ca3af]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                </svg>
                                <span>Nomor: </span>
                                <span class="font-semibold text-[#374151]" x-text="connectedPhone ? ('+' + connectedPhone) : '-'"></span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-[#9ca3af]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Uptime: </span>
                                <span class="font-semibold text-[#374151]" x-text="uptimeHuman || '-'"></span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-[#9ca3af]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                <span>Engine: </span>
                                <span class="font-semibold text-[#374151]">Baileys Socket (v6.7)</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Right: Actions --}}
                <div class="flex items-center gap-2.5 flex-wrap">
                    <button
                        x-on:click="refreshStatus(true)"
                        class="wa-btn wa-btn-secondary"
                        title="Perbarui data status"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh
                    </button>

                    <button
                        x-on:click="reconnect()"
                        :disabled="isReconnecting"
                        class="wa-btn wa-btn-warning"
                    >
                        <svg class="w-4 h-4" :class="{ 'animate-spin': isReconnecting }" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.75a.75.75 0 00-.75.75v4.482a.75.75 0 001.5 0v-2.186l.488.488a7 7 0 0011.824-3.189.75.75 0 00-1.5-.01zM4.688 8.576a5.5 5.5 0 019.201-2.466l.312.311H11.77a.75.75 0 000 1.5h4.482a.75.75 0 00.75-.75V2.689a.75.75 0 00-1.5 0v2.186l-.488-.488a7 7 0 00-11.824 3.189.75.75 0 001.5.01z" clip-rule="evenodd" />
                        </svg>
                        <span x-text="isReconnecting ? 'Menyambung...' : 'Reconnect'"></span>
                    </button>

                    @if($isOwner)
                    <button
                        x-on:click="confirmLogout()"
                        :disabled="isLoggingOut"
                        class="wa-btn wa-btn-danger"
                    >
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span x-text="isLoggingOut ? 'Logging out...' : 'Ganti Nomor'"></span>
                    </button>
                    @endif
                </div>

            </div>
        </div>

        {{-- 2. QR CODE SCANNER MODAL / INLINE (Hanya Tampil Jika Perlu Scan QR) --}}
        <div x-show="status === 'qr' && qrUrl" x-transition class="wa-card bg-amber-50/50 border-amber-200">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="space-y-2">
                    <h3 class="text-lg font-bold text-amber-900 flex items-center gap-2">
                        📷 Scan QR Code untuk Menghubungkan WhatsApp
                    </h3>
                    <ol class="text-xs text-amber-800 list-decimal list-inside space-y-1 font-medium">
                        <li>Buka aplikasi WhatsApp di HP Anda</li>
                        <li>Tekan menu titik tiga (Android) atau Pengaturan (iOS) lalu pilih <strong>Perangkat Tertaut</strong></li>
                        <li>Tekan tombol <strong>Tautkan Perangkat</strong></li>
                        <li>Arahkan kamera HP Anda ke QR code di samping</li>
                    </ol>
                    <p class="text-[11px] text-amber-600 mt-2">QR Code ini otomatis me-refresh diri setiap beberapa detik.</p>
                </div>

                <div class="bg-white p-3 rounded-2xl shadow-sm border border-amber-200 flex-shrink-0">
                    <img :src="qrUrl" alt="QR Code WhatsApp" class="w-48 h-48 rounded-lg" />
                </div>
            </div>
        </div>

        {{-- 3. STATS METRICS (4 COLUMNS EQUAL) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            {{-- Stat 1: Terkirim --}}
            <div class="wa-stat-card">
                <div class="wa-stat-icon bg-blue-50 text-blue-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </div>
                <div>
                    <div class="text-[11px] font-bold tracking-wider uppercase text-slate-400">Total Terkirim</div>
                    <div class="text-2xl font-black text-slate-800 mt-0.5" x-text="stats.sentToday ?? 0"></div>
                    <div class="text-[11px] text-slate-400">Hari ini</div>
                </div>
            </div>

            {{-- Stat 2: Terkonfirmasi --}}
            <div class="wa-stat-card">
                <div class="wa-stat-icon bg-emerald-50 text-emerald-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-[11px] font-bold tracking-wider uppercase text-slate-400">Sampai di HP</div>
                    <div class="text-2xl font-black text-emerald-600 mt-0.5" x-text="stats.deliveredToday ?? 0"></div>
                    <div class="text-[11px] text-slate-400">Ack diterima</div>
                </div>
            </div>

            {{-- Stat 3: Gagal --}}
            <div class="wa-stat-card">
                <div class="wa-stat-icon bg-rose-50 text-rose-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <div class="text-[11px] font-bold tracking-wider uppercase text-slate-400">Gagal Kirim</div>
                    <div class="text-2xl font-black text-rose-600 mt-0.5" x-text="stats.failedToday ?? 0"></div>
                    <div class="text-[11px] text-slate-400">Error response</div>
                </div>
            </div>

            {{-- Stat 4: Ghost Failure Tracker --}}
            <div class="wa-stat-card">
                <div class="wa-stat-icon bg-amber-50 text-amber-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <div>
                    <div class="text-[11px] font-bold tracking-wider uppercase text-slate-400">Ghost Check</div>
                    <div class="text-2xl font-black text-amber-600 mt-0.5">
                        <span x-text="stats.consecutiveFailures ?? 0"></span>
                        <span class="text-xs font-normal text-slate-400">/ <span x-text="stats.ghostThreshold ?? 3"></span></span>
                    </div>
                    <div class="text-[11px] text-slate-400">Auto reconnect jika limit</div>
                </div>
            </div>

        </div>

        {{-- 4. TWO COLUMN: TEST FORM (LEFT) & RECENT LOGS (RIGHT) --}}
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            {{-- LEFT: TEST KIRIM FORM (5 Cols) --}}
            <div class="lg:col-span-5 wa-card">
                <div class="wa-card-header">
                    <div class="wa-title">
                        <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                        </svg>
                        <span>Uji Coba Kirim Pesan</span>
                    </div>
                    <span class="text-[11px] text-slate-400 font-medium">Test Delivery API</span>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Nomor WhatsApp Tujuan</label>
                        <input
                            type="text"
                            x-model="testNumber"
                            placeholder="Contoh: 08123456789 atau 628123456789"
                            class="wa-input"
                        />
                        <p class="text-[11px] text-slate-400 mt-1">Bisa format 08xx atau 628xx</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Isi Pesan Uji Coba</label>
                        <textarea
                            x-model="testMessage"
                            rows="3"
                            placeholder="Tulis pesan test Anda disini..."
                            class="wa-textarea"
                        ></textarea>
                    </div>

                    <button
                        x-on:click="sendTestMessage()"
                        :disabled="isSending || status !== 'ready'"
                        class="w-full wa-btn wa-btn-primary"
                    >
                        <svg class="w-4 h-4" :class="{ 'animate-spin': isSending }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                        <span x-text="isSending ? 'Mengirim pesan...' : 'Kirim Pesan Sekarang'"></span>
                    </button>

                    {{-- Result Feedback --}}
                    <div x-show="sendResult" x-transition class="p-3.5 rounded-xl text-xs font-medium" :class="{
                        'bg-emerald-50 text-emerald-800 border border-emerald-200': sendResult?.success,
                        'bg-rose-50 text-rose-800 border border-rose-200': sendResult && !sendResult.success,
                    }">
                        <div class="flex items-center gap-2 font-bold" x-text="sendResult?.success ? '✅ Pesan Berhasil Dikirim!' : '❌ Gagal Mengirim Pesan'"></div>
                        <div class="mt-1 opacity-80 text-[11px] font-mono" x-text="sendResult?.detail"></div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: ACTIVITY LOGS (7 Cols) --}}
            <div class="lg:col-span-7 wa-card">
                <div class="wa-card-header">
                    <div class="wa-title">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" />
                        </svg>
                        <span>Riwayat Pengiriman Pesan</span>
                    </div>
                    <button x-on:click="refreshLogs()" class="text-xs text-purple-600 hover:text-purple-700 font-bold flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Refresh Log
                    </button>
                </div>

                <div class="divide-y divide-slate-100 max-h-[340px] overflow-y-auto pr-1">
                    <template x-if="!logs || logs.length === 0">
                        <div class="py-12 text-center">
                            <svg class="w-10 h-10 text-slate-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                            </svg>
                            <p class="text-xs text-slate-400 font-medium">Belum ada riwayat pengiriman pesan hari ini.</p>
                        </div>
                    </template>

                    <template x-for="(log, index) in (logs || [])" :key="index">
                        <div class="py-3 flex items-start justify-between gap-4">
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="mt-0.5 flex-shrink-0">
                                    <template x-if="log.deliveryStatus === 'delivered'">
                                        <span class="w-6 h-6 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs font-bold">✓</span>
                                    </template>
                                    <template x-if="log.deliveryStatus === 'sent'">
                                        <span class="w-6 h-6 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center text-xs">↑</span>
                                    </template>
                                    <template x-if="log.deliveryStatus === 'unconfirmed'">
                                        <span class="w-6 h-6 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center text-xs font-bold">!</span>
                                    </template>
                                    <template x-if="log.deliveryStatus === 'error'">
                                        <span class="w-6 h-6 rounded-full bg-rose-100 text-rose-600 flex items-center justify-center text-xs font-bold">✕</span>
                                    </template>
                                </div>

                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-bold text-slate-800 font-mono" x-text="log.to || 'System'"></span>
                                        <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full" :class="{
                                            'bg-emerald-50 text-emerald-700': log.deliveryStatus === 'delivered',
                                            'bg-blue-50 text-blue-700': log.deliveryStatus === 'sent',
                                            'bg-amber-50 text-amber-700': log.deliveryStatus === 'unconfirmed',
                                            'bg-rose-50 text-rose-700': log.deliveryStatus === 'error',
                                            'bg-slate-100 text-slate-600': log.type === 'system'
                                        }" x-text="log.deliveryStatus || log.type"></span>
                                    </div>
                                    <p class="text-xs text-slate-600 truncate mt-0.5" x-text="log.preview || log.message"></p>
                                </div>
                            </div>

                            <span class="text-[10px] font-medium text-slate-400 whitespace-nowrap" x-text="formatTimestamp(log.timestamp)"></span>
                        </div>
                    </template>
                </div>
            </div>

        </div>

        {{-- 5. OWNER ONLY: CONFIG & URL INFO --}}
        @if($isOwner)
        <div class="wa-card">
            <div class="wa-card-header">
                <div class="wa-title">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span>Konfigurasi Endpoint & Keamanan Gateway</span>
                </div>
                <span class="text-[11px] text-slate-400 font-medium">Pengaturan Khusus Owner</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-slate-50 p-4 rounded-xl border border-slate-100">
                    <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Bot Service Endpoint URL</div>
                    <div class="text-xs font-mono font-bold text-slate-800 break-all">{{ $botUrl }}</div>
                    <p class="text-[11px] text-slate-400 mt-1">Dikonfigurasi via <code>WA_BOT_URL</code> di file .env</p>
                </div>

                <div x-data="{ showKey: false }" class="bg-slate-50 p-4 rounded-xl border border-slate-100 flex items-center justify-between">
                    <div>
                        <div class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Secret Key API Authorization</div>
                        <div class="text-xs font-mono font-bold text-slate-800" x-show="showKey">{{ $secretKey }}</div>
                        <div class="text-xs text-slate-400 tracking-widest" x-show="!showKey">••••••••••••••••••••••••</div>
                    </div>
                    <button x-on:click="showKey = !showKey" class="text-xs text-purple-600 font-bold hover:underline" x-text="showKey ? 'Sembunyikan' : 'Lihat Key'"></button>
                </div>
            </div>
        </div>
        @endif

    </div>

    @push('scripts')
    <script>
    function whatsappSettings() {
        return {
            status: {!! json_encode($botStatus['status'] ?? null) !!},
            connectedPhone: {!! json_encode($botStatus['connectedPhone'] ?? null) !!},
            uptimeHuman: {!! json_encode($botStatus['uptime_human'] ?? null) !!},
            qrUrl: {!! json_encode($botStatus['connection']['qr_url'] ?? null) !!},
            stats: {
                sentToday: {!! json_encode($botStatus['stats']['sent_today'] ?? 0) !!},
                deliveredToday: {!! json_encode($botStatus['stats']['delivered_today'] ?? 0) !!},
                failedToday: {!! json_encode($botStatus['stats']['failed_today'] ?? 0) !!},
                consecutiveFailures: {!! json_encode($botStatus['stats']['consecutive_failures'] ?? 0) !!},
                ghostThreshold: {!! json_encode($botStatus['stats']['ghost_threshold'] ?? 3) !!},
            },
            logs: {!! json_encode($logs['logs'] ?? []) !!},

            // Test send
            testNumber: '',
            testMessage: '',
            isSending: false,
            sendResult: null,

            // Actions
            isReconnecting: false,
            isLoggingOut: false,

            pollInterval: null,

            startPolling() {
                this.pollInterval = setInterval(() => this.refreshStatus(), 5000);
            },

            async refreshStatus() {
                try {
                    const botUrl = {!! json_encode($botUrl) !!};
                    const secretKey = {!! json_encode($secretKey) !!};
                    const headers = {};
                    if (secretKey) headers['x-bot-key'] = secretKey;

                    const res = await fetch(botUrl + '/api/status', { headers });
                    if (res.ok) {
                        const data = await res.json();
                        this.status = data.status;
                        this.connectedPhone = data.connectedPhone;
                        this.uptimeHuman = data.uptime_human;
                        this.qrUrl = data.connection?.qr_url || null;
                        this.stats = {
                            sentToday: data.stats?.sent_today ?? 0,
                            deliveredToday: data.stats?.delivered_today ?? 0,
                            failedToday: data.stats?.failed_today ?? 0,
                            consecutiveFailures: data.stats?.consecutive_failures ?? 0,
                            ghostThreshold: data.stats?.ghost_threshold ?? 3,
                        };
                        
                        if (isManual) {
                            new FilamentNotification()
                                .title('Data Diperbarui')
                                .body('Status koneksi & metrik WhatsApp berhasil disinkronkan.')
                                .success()
                                .send();
                        }
                    }
                } catch (e) {
                    console.warn('Status poll error:', e);
                    if (isManual) {
                        new FilamentNotification()
                            .title('Gagal Refresh')
                            .body('Tidak dapat menghubungi server WhatsApp Bot: ' + e.message)
                            .danger()
                            .send();
                    }
                }
            },

            async refreshLogs() {
                try {
                    const botUrl = {!! json_encode($botUrl) !!};
                    const secretKey = {!! json_encode($secretKey) !!};
                    const headers = {};
                    if (secretKey) headers['x-bot-key'] = secretKey;

                    const res = await fetch(botUrl + '/api/logs?limit=20', { headers });
                    if (res.ok) {
                        const data = await res.json();
                        this.logs = data.logs || [];
                        new FilamentNotification()
                            .title('Log Diperbarui')
                            .body('Riwayat pesan WhatsApp berhasil disinkronkan.')
                            .success()
                            .send();
                    }
                } catch (e) {
                    console.warn('Logs fetch error:', e);
                }
            },

            async sendTestMessage() {
                if (!this.testNumber || !this.testMessage) {
                    this.sendResult = { success: false, detail: 'Nomor WhatsApp dan isi pesan wajib diisi!' };
                    new FilamentNotification()
                        .title('Form Belum Lengkap')
                        .body('Silakan masukkan nomor tujuan dan isi pesan uji coba.')
                        .warning()
                        .send();
                    return;
                }

                this.isSending = true;
                this.sendResult = null;

                try {
                    const botUrl = {!! json_encode($botUrl) !!};
                    const secretKey = {!! json_encode($secretKey) !!};
                    const headers = { 'Content-Type': 'application/json' };
                    if (secretKey) headers['x-bot-key'] = secretKey;

                    const res = await fetch(botUrl + '/api', {
                        method: 'POST',
                        headers,
                        body: JSON.stringify({
                            number: this.testNumber,
                            message: this.testMessage,
                        }),
                    });

                    const data = await res.json();
                    const isSuccess = res.ok && data.status === 'berhasil terkirim';
                    this.sendResult = {
                        success: isSuccess,
                        detail: res.ok
                            ? `Pesan dikirim ke ID: ${data.id || '-'} (${data.delivery || 'terkirim'})`
                            : (data.pesan || 'Gagal mengirim pesan'),
                    };

                    if (isSuccess) {
                        new FilamentNotification()
                            .title('Pesan Terkirim!')
                            .body('Pesan uji coba berhasil diserahkan ke server WhatsApp.')
                            .success()
                            .send();
                    } else {
                        new FilamentNotification()
                            .title('Gagal Kirim Pesan')
                            .body(data.pesan || 'Terjadi kesalahan saat pengiriman pesan.')
                            .danger()
                            .send();
                    }

                    // Refresh logs after send
                    setTimeout(() => {
                        this.refreshLogs();
                        this.refreshStatus();
                    }, 1000);
                } catch (e) {
                    this.sendResult = { success: false, detail: 'Network error: ' + e.message };
                    new FilamentNotification()
                        .title('Koneksi Error')
                        .body('Gagal menghubungi bot WhatsApp: ' + e.message)
                        .danger()
                        .send();
                } finally {
                    this.isSending = false;
                }
            },

            async reconnect() {
                if (this.isReconnecting) return;
                this.isReconnecting = true;

                new FilamentNotification()
                    .title('Memulai Reconnect')
                    .body('Memutuskan socket lama dan menyambung ulang session WhatsApp...')
                    .info()
                    .send();

                try {
                    const botUrl = {!! json_encode($botUrl) !!};
                    const secretKey = {!! json_encode($secretKey) !!};
                    const headers = { 'Content-Type': 'application/json' };
                    if (secretKey) headers['x-bot-key'] = secretKey;

                    await fetch(botUrl + '/api/reconnect', { method: 'POST', headers });

                    // Wait and refresh
                    setTimeout(async () => {
                        await this.refreshStatus();
                        this.isReconnecting = false;

                        new FilamentNotification()
                            .title('Reconnect Berhasil')
                            .body('Koneksi WhatsApp telah berhasil disambungkan ulang.')
                            .success()
                            .send();
                    }, 4000);
                } catch (e) {
                    console.error('Reconnect error:', e);
                    this.isReconnecting = false;
                    new FilamentNotification()
                        .title('Gagal Reconnect')
                        .body('Terjadi kesalahan saat reconnect: ' + e.message)
                        .danger()
                        .send();
                }
            },

            async confirmLogout() {
                if (!confirm('⚠️ PERINGATAN:\n\nApakah Anda yakin ingin logout nomor WhatsApp saat ini?\nNomor akan diputuskan dan sistem butuh scan QR ulang dengan nomor baru.')) {
                    return;
                }

                this.isLoggingOut = true;
                try {
                    const botUrl = {!! json_encode($botUrl) !!};
                    const secretKey = {!! json_encode($secretKey) !!};
                    const headers = { 'Content-Type': 'application/json' };
                    if (secretKey) headers['x-bot-key'] = secretKey;

                    await fetch(botUrl + '/api/logout', { method: 'POST', headers });

                    this.connectedPhone = null;
                    this.status = 'not ready';

                    // Wait for new QR
                    setTimeout(() => {
                        this.refreshStatus();
                        this.isLoggingOut = false;
                    }, 4000);
                } catch (e) {
                    console.error('Logout error:', e);
                    this.isLoggingOut = false;
                }
            },

            formatTimestamp(ts) {
                if (!ts) return '';
                try {
                    const d = new Date(ts);
                    const pad = n => String(n).padStart(2, '0');
                    return `${pad(d.getDate())}/${pad(d.getMonth()+1)} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
                } catch {
                    return ts;
                }
            },

            destroy() {
                if (this.pollInterval) clearInterval(this.pollInterval);
            }
        };
    }
    </script>
    @endpush
</x-filament-panels::page>
