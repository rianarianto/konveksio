<div class="space-y-4">
    <div class="flex items-center justify-between pl-1 mb-2">
        <h3 class="font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-widest text-xs flex items-center">
            <x-heroicon-o-users class="w-4 h-4 mr-2 text-primary-600 dark:text-primary-400" />
            Monitoring Karyawan
        </h3>
    </div>

    @forelse($employees as $employee)
    <div class="bg-white dark:bg-gray-800 rounded-lg p-3.5 border border-gray-200 dark:border-gray-700 shadow-sm flex items-center justify-between hover:shadow transition-shadow">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-full bg-primary-100 dark:bg-primary-900/40 flex items-center justify-center text-primary-700 dark:text-primary-300 font-bold text-sm ring-1 ring-primary-200 dark:ring-primary-800">
                {{ strtoupper(substr($employee->name, 0, 1)) }}
            </div>
            <div>
                <p class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ $employee->name }}</p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">Sedang aktif mengerjakan</p>
            </div>
        </div>
        <div class="text-right">
            <div class="text-xl font-black text-gray-900 dark:text-white leading-none">{{ $employee->active_workload ?? 0 }}</div>
            <div class="text-[10px] uppercase font-semibold text-gray-500 tracking-wider mt-1">Pcs</div>
        </div>
    </div>
    @empty
    <div class="text-sm text-gray-500 dark:text-gray-400 italic bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg border border-dashed border-gray-200 dark:border-gray-700 text-center">
        Belum ada penugasan aktif.
    </div>
    @endforelse
</div>
