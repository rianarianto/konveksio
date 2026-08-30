<x-filament-panels::page>
    <div
        x-data="whatsappSettings()"
        x-init="startPolling()"
        class="space-y-6"
    >
        {{-- CONNECTION STATUS CARD --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="fi-section-content p-6">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div class="flex items-center gap-4">
                        {{-- Status Indicator --}}
                        <div class="relative">
                            <div
                                x-show="status === 'ready'"
                                class="w-16 h-16 rounded-2xl bg-emerald-500/10 flex items-center justify-center"
                            >
                                <div class="w-5 h-5 rounded-full bg-emerald-500 animate-pulse"></div>
                            </div>
                            <div
                                x-show="status === 'qr'"
                                class="w-16 h-16 rounded-2xl bg-amber-500/10 flex items-center justify-center"
                            >
                                <div class="w-5 h-5 rounded-full bg-amber-500 animate-pulse"></div>
                            </div>
                            <div
                                x-show="status !== 'ready' && status !== 'qr'"
                                class="w-16 h-16 rounded-2xl bg-red-500/10 flex items-center justify-center"
                            >
                                <div class="w-5 h-5 rounded-full bg-red-500"></div>
                            </div>
                        </div>

                        <div>
                            <h3 class="text-lg font-bold text-gray-950 dark:text-white">
                                Status WhatsApp Bot
                            </h3>
                            <p class="text-sm mt-0.5" :class="{
                                'text-emerald-600 dark:text-emerald-400': status === 'ready',
                                'text-amber-600 dark:text-amber-400': status === 'qr',
                                'text-red-600 dark:text-red-400': status !== 'ready' && status !== 'qr',
                            }">
                                <span x-show="status === 'ready'">✅ Terhubung & Siap Kirim Pesan</span>
                                <span x-show="status === 'qr'">📱 Perlu Scan QR Code</span>
                                <span x-show="status === 'loading'">⏳ Menyiapkan koneksi...</span>
                                <span x-show="status === 'not ready' || status === 'error'">❌ Tidak Terhubung</span>
                                <span x-show="!status">⏳ Memuat status...</span>
                            </p>
                            <p x-show="connectedPhone" class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                📱 Nomor: <span class="font-mono font-semibold" x-text="'+' + connectedPhone"></span>
                            </p>
                            <p x-show="uptimeHuman" class="text-xs text-gray-500 dark:text-gray-400">
                                ⏱️ Uptime: <span x-text="uptimeHuman"></span>
                            </p>
                        </div>
                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex flex-wrap gap-2">
                        <button
                            x-show="status !== 'ready'"
                            x-on:click="refreshStatus()"
                            class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm bg-primary-600 text-white hover:bg-primary-500 transition"
                        >
                            <x-heroicon-s-arrow-path class="w-4 h-4" />
                            Refresh
                        </button>

                        <button
                            x-on:click="reconnect()"
                            :disabled="isReconnecting"
                            class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm bg-amber-600 text-white hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >
                            <svg class="w-4 h-4" :class="{ 'animate-spin': isReconnecting }" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M15.312 11.424a5.5 5.5 0 01-9.201 2.466l-.312-.311h2.433a.75.75 0 000-1.5H3.75a.75.75 0 00-.75.75v4.482a.75.75 0 001.5 0v-2.186l.488.488a7 7 0 0011.824-3.189.75.75 0 00-1.5-.01zM4.688 8.576a5.5 5.5 0 019.201-2.466l.312.311H11.77a.75.75 0 000 1.5h4.482a.75.75 0 00.75-.75V2.689a.75.75 0 00-1.5 0v2.186l-.488-.488a7 7 0 00-11.824 3.189.75.75 0 001.5.01z" clip-rule="evenodd" />
                            </svg>
                            <span x-text="isReconnecting ? 'Reconnecting...' : 'Reconnect'"></span>
                        </button>

                        @if($isOwner)
                        <button
                            x-on:click="confirmLogout()"
                            :disabled="isLoggingOut"
                            class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm bg-red-600 text-white hover:bg-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                        >
                            <x-heroicon-s-arrow-right-on-rectangle class="w-4 h-4" />
                            <span x-text="isLoggingOut ? 'Logging out...' : 'Logout & Ganti Nomor'"></span>
                        </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- QR CODE CARD (shown only when QR needed) --}}
        <div x-show="status === 'qr' && qrUrl" x-transition class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="fi-section-content p-6 text-center">
                <h3 class="text-lg font-bold text-gray-950 dark:text-white mb-2">📷 Scan QR Code</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                    Buka WhatsApp di HP → Menu → Perangkat Tertaut → Tautkan Perangkat → Arahkan kamera ke QR
                </p>
                <div class="inline-block bg-white p-3 rounded-xl shadow-lg">
                    <img :src="qrUrl" alt="QR Code WhatsApp" class="w-64 h-64 rounded-lg" />
                </div>
                <p class="text-xs text-gray-400 mt-3">QR code akan refresh otomatis setiap 5 detik</p>
            </div>
        </div>

        {{-- STATS CARDS --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {{-- Sent Today --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-500/10 flex items-center justify-center">
                        <x-heroicon-o-paper-airplane class="w-5 h-5 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Terkirim Hari Ini</p>
                        <p class="text-xl font-bold text-gray-950 dark:text-white" x-text="stats.sentToday ?? '-'"></p>
                    </div>
                </div>
            </div>
            {{-- Delivered --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                        <x-heroicon-o-check-badge class="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Terkonfirmasi</p>
                        <p class="text-xl font-bold text-gray-950 dark:text-white" x-text="stats.deliveredToday ?? '-'"></p>
                    </div>
                </div>
            </div>
            {{-- Failed --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-red-500/10 flex items-center justify-center">
                        <x-heroicon-o-x-circle class="w-5 h-5 text-red-600 dark:text-red-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Gagal</p>
                        <p class="text-xl font-bold text-gray-950 dark:text-white" x-text="stats.failedToday ?? '-'"></p>
                    </div>
                </div>
            </div>
            {{-- Ghost Failures --}}
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-amber-500/10 flex items-center justify-center">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ghost Failures</p>
                        <p class="text-xl font-bold text-gray-950 dark:text-white">
                            <span x-text="stats.consecutiveFailures ?? '0'"></span>
                            <span class="text-xs font-normal text-gray-400">/ <span x-text="stats.ghostThreshold ?? '3'"></span></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- TEST SEND MESSAGE & LOG --}}
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            {{-- Test Send --}}
            <div class="lg:col-span-2 fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                    <x-heroicon-o-chat-bubble-bottom-center-text class="w-5 h-5 text-gray-400" />
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Test Kirim Pesan</h3>
                </div>
                <div class="fi-section-content p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nomor WhatsApp</label>
                        <input
                            type="text"
                            x-model="testNumber"
                            placeholder="628xxxxxxxxxx"
                            class="fi-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm py-2 px-3"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Pesan</label>
                        <textarea
                            x-model="testMessage"
                            rows="3"
                            placeholder="Tulis pesan test..."
                            class="fi-input block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:bg-gray-800 dark:border-gray-700 dark:text-white text-sm py-2 px-3"
                        ></textarea>
                    </div>
                    <button
                        x-on:click="sendTestMessage()"
                        :disabled="isSending || status !== 'ready'"
                        class="w-full fi-btn inline-flex items-center justify-center gap-2 rounded-lg px-4 py-2.5 text-sm font-semibold shadow-sm bg-emerald-600 text-white hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed transition"
                    >
                        <x-heroicon-s-paper-airplane class="w-4 h-4" />
                        <span x-text="isSending ? 'Mengirim...' : 'Kirim Pesan Test'"></span>
                    </button>

                    {{-- Send Result --}}
                    <div x-show="sendResult" x-transition class="rounded-lg p-3 text-sm" :class="{
                        'bg-emerald-50 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300': sendResult?.success,
                        'bg-red-50 text-red-800 dark:bg-red-900/30 dark:text-red-300': sendResult && !sendResult.success,
                    }">
                        <p class="font-semibold" x-text="sendResult?.success ? '✅ Pesan terkirim!' : '❌ Gagal kirim'"></p>
                        <p class="text-xs mt-1 font-mono opacity-75" x-text="sendResult?.detail"></p>
                    </div>
                </div>
            </div>

            {{-- Message Log --}}
            <div class="lg:col-span-3 fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
                <div class="fi-section-header flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-white/10">
                    <div class="flex items-center gap-3">
                        <x-heroicon-o-document-text class="w-5 h-5 text-gray-400" />
                        <h3 class="text-base font-semibold text-gray-950 dark:text-white">Log Aktivitas</h3>
                    </div>
                    <button x-on:click="refreshLogs()" class="text-xs text-primary-600 hover:text-primary-500 font-semibold">
                        Refresh
                    </button>
                </div>
                <div class="fi-section-content divide-y divide-gray-100 dark:divide-white/5 max-h-96 overflow-y-auto">
                    <template x-if="!logs || logs.length === 0">
                        <div class="p-6 text-center text-sm text-gray-400">
                            Belum ada log aktivitas
                        </div>
                    </template>
                    <template x-for="(log, index) in (logs || [])" :key="index">
                        <div class="px-6 py-3 flex items-start gap-3">
                            {{-- Status icon --}}
                            <div class="mt-0.5">
                                <span x-show="log.deliveryStatus === 'delivered'" class="text-emerald-500">✅</span>
                                <span x-show="log.deliveryStatus === 'sent'" class="text-blue-500">📤</span>
                                <span x-show="log.deliveryStatus === 'unconfirmed'" class="text-amber-500">⚠️</span>
                                <span x-show="log.deliveryStatus === 'error'" class="text-red-500">❌</span>
                                <span x-show="log.type === 'system'" class="text-gray-400">⚙️</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span x-show="log.to" class="text-xs font-mono text-gray-600 dark:text-gray-400" x-text="log.to"></span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium" :class="{
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400': log.deliveryStatus === 'delivered',
                                        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': log.deliveryStatus === 'sent',
                                        'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400': log.deliveryStatus === 'unconfirmed',
                                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': log.deliveryStatus === 'error',
                                        'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400': log.type === 'system',
                                    }" x-text="log.deliveryStatus || log.type"></span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate" x-text="log.preview || log.message"></p>
                                <p class="text-[10px] text-gray-400 dark:text-gray-500 mt-0.5" x-text="formatTimestamp(log.timestamp)"></p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- BOT CONFIGURATION (Owner only) --}}
        @if($isOwner)
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 overflow-hidden">
            <div class="fi-section-header flex items-center gap-3 px-6 py-4 border-b border-gray-200 dark:border-white/10">
                <x-heroicon-o-cog-6-tooth class="w-5 h-5 text-gray-400" />
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Konfigurasi Bot</h3>
            </div>
            <div class="fi-section-content p-6 space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Bot URL</label>
                        <div class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2 ring-1 ring-gray-200 dark:ring-gray-700">
                            <x-heroicon-o-link class="w-4 h-4 text-gray-400 flex-shrink-0" />
                            <code class="text-xs text-gray-700 dark:text-gray-300 break-all">{{ $botUrl }}</code>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Secret Key</label>
                        <div x-data="{ showKey: false }" class="flex items-center gap-2 bg-gray-50 dark:bg-gray-800 rounded-lg px-3 py-2 ring-1 ring-gray-200 dark:ring-gray-700">
                            <x-heroicon-o-key class="w-4 h-4 text-gray-400 flex-shrink-0" />
                            <code class="text-xs text-gray-700 dark:text-gray-300 flex-1 break-all" x-show="showKey">{{ $secretKey }}</code>
                            <code class="text-xs text-gray-400 flex-1" x-show="!showKey">••••••••••••••••</code>
                            <button x-on:click="showKey = !showKey" class="text-xs text-primary-600 hover:text-primary-500 font-semibold flex-shrink-0" x-text="showKey ? 'Sembunyikan' : 'Tampilkan'"></button>
                        </div>
                    </div>
                </div>
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Konfigurasi ini diatur melalui file <code class="bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">.env</code> di server.
                    Ubah <code class="bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">WA_BOT_URL</code> dan <code class="bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">BOT_SECRET_KEY</code> di file tersebut.
                </p>
            </div>
        </div>
        @endif

        {{-- TIPS / INFO --}}
        <div class="fi-section rounded-xl bg-blue-50/50 dark:bg-blue-900/10 ring-1 ring-blue-200/50 dark:ring-blue-500/20 overflow-hidden">
            <div class="p-6">
                <h4 class="text-sm font-semibold text-blue-800 dark:text-blue-300 mb-2">💡 Tips</h4>
                <ul class="text-xs text-blue-700 dark:text-blue-400 space-y-1.5">
                    <li>• Jika status <strong>Tidak Terhubung</strong>, coba tekan <strong>Reconnect</strong> terlebih dahulu.</li>
                    <li>• Jika pesan sering gagal terkirim (Ghost Failures > 0), bot akan otomatis reconnect.</li>
                    <li>• Gunakan <strong>Test Kirim Pesan</strong> untuk memastikan bot benar-benar bisa mengirim pesan.</li>
                    <li>• <strong>Ghost Failures</strong> menunjukkan jumlah pesan berturut-turut yang dikirim tapi tidak terkonfirmasi sampai. Jika mencapai batas (default: 3), bot otomatis reconnect.</li>
                    @if($isOwner)
                    <li>• Untuk <strong>ganti nomor WhatsApp</strong>, tekan "Logout & Ganti Nomor" lalu scan QR dengan nomor baru.</li>
                    @endif
                </ul>
            </div>
        </div>
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
                    }
                } catch (e) {
                    console.warn('Status poll error:', e);
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
                    }
                } catch (e) {
                    console.warn('Logs fetch error:', e);
                }
            },

            async sendTestMessage() {
                if (!this.testNumber || !this.testMessage) {
                    this.sendResult = { success: false, detail: 'Nomor dan pesan wajib diisi' };
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
                    this.sendResult = {
                        success: res.ok && data.status === 'berhasil terkirim',
                        detail: res.ok
                            ? `ID: ${data.id || '-'} | Delivery: ${data.delivery || '-'}`
                            : (data.pesan || 'Unknown error'),
                    };

                    // Refresh logs after send
                    setTimeout(() => this.refreshLogs(), 1000);
                } catch (e) {
                    this.sendResult = { success: false, detail: 'Network error: ' + e.message };
                } finally {
                    this.isSending = false;
                }
            },

            async reconnect() {
                if (this.isReconnecting) return;
                this.isReconnecting = true;

                try {
                    const botUrl = {!! json_encode($botUrl) !!};
                    const secretKey = {!! json_encode($secretKey) !!};
                    const headers = { 'Content-Type': 'application/json' };
                    if (secretKey) headers['x-bot-key'] = secretKey;

                    await fetch(botUrl + '/api/reconnect', { method: 'POST', headers });

                    // Wait and refresh
                    setTimeout(() => {
                        this.refreshStatus();
                        this.isReconnecting = false;
                    }, 5000);
                } catch (e) {
                    console.error('Reconnect error:', e);
                    this.isReconnecting = false;
                }
            },

            async confirmLogout() {
                if (!confirm('⚠️ Yakin ingin logout WhatsApp?\n\nNomor yang terhubung akan di-putuskan dan Anda perlu scan QR lagi dengan nomor baru.')) {
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
                    }, 5000);
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
