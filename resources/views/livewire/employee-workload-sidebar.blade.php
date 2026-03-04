<div class="space-y-4">
    <div class="flex items-center justify-between pl-1 mb-2">
        <h3 class="font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-widest text-xs flex items-center">
            <x-heroicon-o-users class="w-4 h-4 mr-2 text-primary-600 dark:text-primary-400" />
            Monitoring Karyawan
        </h3>
    </div>

    {{-- Search Input --}}
    <div style="position:relative; margin-bottom:12px;">
        <div
            style="position:absolute; top:0; bottom:0; left:0; padding-left:12px; display:flex; align-items:center; pointer-events:none; color:#9ca3af;">
            <svg style="width:16px; height:16px;" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                    d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
            </svg>
        </div>
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Cari nama karyawan..."
            style="width:100%; padding:8px 12px 8px 36px; border:0.5px solid #444444ff; border-radius:12px; font-size:14px; outline:none; transition: border-color 0.2s;"
            onfocus="this.style.borderColor='#7c3aed'" onblur="this.style.borderColor='#e5e7eb'"
            class="dark:text-gray-800">
    </div>

    @forelse($employees as $employee)
        <div
            class="bg-white dark:bg-gray-800/60 rounded-lg p-3.5 border border-gray-200 dark:border-white/5 shadow-sm flex items-center justify-between hover:shadow transition-shadow">
            <div class="flex items-center gap-3">
                <div
                    class="w-9 h-9 rounded-full border border-gray-400 bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center text-primary-700 dark:text-white-200/80 font-bold text-sm dark:ring-primary-800">
                    {{ strtoupper(substr($employee->name, 0, 1)) }}
                </div>
                <div>
                    <p class="text-sm font-bold text-gray-700 dark:text-gray-100">{{ $employee->name }}</p>
                    <div style="display:flex; flex-wrap:wrap; gap:6px; margin-top:6px;">
                        @if($employee->in_progress_qty > 0)
                            <span
                                style="font-size:10px; font-weight:700; background:#dbeafe; color:#1e40af; padding:2px 8px; border-radius:999px; display:inline-flex; align-items:center; gap:4px;">
                                <span
                                    style="width:5px; height:5px; border-radius:50%; background:#1e40af; display:inline-block; animation:pulse 2s infinite;"></span>
                                {{ $employee->in_progress_qty }} Aktif
                            </span>
                        @endif
                        @if($employee->pending_qty > 0)
                            <span
                                style="font-size:10px; font-weight:700; background:#fef3c7; color:#92400e; padding:2px 8px; border-radius:999px;">
                                {{ $employee->pending_qty }} Antrian
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div style="text-align:right;">
                <div class="text-gray-700 dark:text-gray-100" style="text-size:20px; font-weight:900; line-height:1;">
                    {{ $employee->active_workload }}
                </div>
                <div style="font-size:10px; font-weight:600; color:#6b7280; text-transform:uppercase; margin-top:4px;">Total
                </div>
            </div>
        </div>
    @empty
        <div
            class="text-sm text-gray-500 dark:text-gray-400 italic bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg border border-dashed border-gray-200 dark:border-gray-700 text-center">
            Belum ada penugasan aktif.
        </div>
    @endforelse
</div>