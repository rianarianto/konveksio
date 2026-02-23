@php
    // $getRecord() is available in ViewColumn to get the Order model
    $order = $getRecord();
    // We already eager-loaded orderItems with design_status = 'approved' in the Resource query
    $items = $order->orderItems;
@endphp
<div class="bg-white dark:bg-gray-800 rounded-xl shadow border border-gray-200 dark:border-gray-700 p-5 mb-2 transition hover:shadow-md">
  <div class="flex justify-between items-center mb-4 pb-3 border-b border-gray-100 dark:border-gray-700">
    <div>
        <h3 class="font-bold text-lg text-gray-900 dark:text-gray-100">
            <span class="text-primary-600 dark:text-primary-400">#{{ $order->order_number }}</span>
            <span class="text-sm font-normal text-gray-500 ml-2">
                <x-heroicon-o-user class="w-4 h-4 inline-block mr-1 text-gray-400"/>
                {{ $order->customer->name ?? 'Tanpa Nama' }}
            </span>
        </h3>
    </div>
    <div class="text-right">
        <span class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wider font-semibold">Deadline</span>
        <div class="text-sm font-medium {{ $order->deadline < now() ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }} flex items-center justify-end gap-1 mt-0.5">
            <x-heroicon-o-calendar class="w-4 h-4"/>
            {{ $order->deadline ? \Carbon\Carbon::parse($order->deadline)->format('d M Y') : '-' }}
        </div>
    </div>
  </div>

  <div class="space-y-3">
      @forelse($items as $item)
      <div class="flex flex-col sm:flex-row sm:items-center justify-between p-3.5 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-100 dark:border-gray-800 gap-4">
          <div class="flex-1">
             <div class="flex items-center gap-2.5 mb-2">
                 <span class="bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300 px-2.5 py-1 rounded-md text-xs font-bold tracking-wide">
                     {{ ucfirst(str_replace('_', ' ', $item->production_category ?? 'produksi')) }}
                 </span>
                 <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $item->quantity }}x {{ $item->product_name }}</span>
             </div>
             
             <!-- Status Tracker Badges -->
             @php
                $tasks = $item->productionTasks;
                $category = $item->production_category ?? 'produksi';
                
                // Fetch stages (Ideally these could be cached or pre-loaded, but doing it here for simplicity of the prototype)
                $query = \App\Models\ProductionStage::query()->orderBy('order_sequence');
                if ($category === 'produksi' || $category === 'custom') {
                    $query->where('for_produksi_custom', true);
                } elseif ($category === 'non_produksi') {
                    $query->where('for_non_produksi', true);
                } elseif ($category === 'jasa') {
                    $query->where('for_jasa', true);
                }
                $stages = $query->get();
             @endphp
             <div class="flex items-center gap-1.5 pl-1">
                 @foreach($stages as $stage)
                     @php
                        $stageTasks = $tasks->where('stage_name', $stage->name);
                        $doneQty = $stageTasks->where('status', 'done')->sum('quantity');
                        $totalQtyAssigned = $stageTasks->sum('quantity');
                        
                        $cssClasses = 'bg-gray-200 text-gray-400 dark:bg-gray-700 dark:text-gray-500';
                        if ($totalQtyAssigned > 0) {
                            if ($doneQty >= $item->quantity) {
                                $cssClasses = 'bg-success-500 text-white shadow-sm ring-1 ring-success-600';
                            } elseif ($doneQty > 0 || $stageTasks->where('status', 'in_progress')->count() > 0) {
                                $cssClasses = 'bg-warning-500 text-white shadow-sm ring-1 ring-warning-600';
                            } else {
                                $cssClasses = 'bg-primary-500 text-white shadow-sm ring-1 ring-primary-600';
                            }
                        }
                        
                        $initial = strtoupper(substr($stage->name, 0, 1));
                        $tooltip = $stage->name . ' (Assigned: ' . $totalQtyAssigned . ' | Done: ' . $doneQty . '/' . $item->quantity . ')';
                     @endphp
                     <span title="{{ $tooltip }}" class="inline-flex items-center justify-center w-6 h-6 rounded text-[10px] font-bold cursor-help transition hover:opacity-80 {{ $cssClasses }}">
                         {{ $initial }}
                     </span>
                 @endforeach
                 
                 <span class="text-xs font-medium ml-3 {{ $tasks->count() > 0 ? 'text-primary-600 dark:text-primary-400' : 'text-gray-400 dark:text-gray-500' }}">
                     @if($tasks->count() == 0) 
                        Belum Diproses 
                     @else 
                        Sedang Berjalan 
                     @endif
                 </span>
             </div>
          </div>
          
          <div class="flex-shrink-0">
              <!-- Action Button triggering the Page Action -->
              <button type="button" 
                      wire:click="mountAction('atur_tugas', { item_id: {{ $item->id }} })"
                      class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-colors shadow-sm w-full sm:w-auto">
                  <x-heroicon-m-clipboard-document-check class="w-4 h-4 mr-1.5"/>
                  Atur Tugas
              </button>
          </div>
      </div>
      @empty
      <div class="text-sm text-gray-500 italic py-2">Tidak ada item yang telah disetujui desainnya.</div>
      @endforelse
  </div>
</div>
