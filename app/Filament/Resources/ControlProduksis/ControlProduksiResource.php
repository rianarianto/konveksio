<?php

namespace App\Filament\Resources\ControlProduksis;

use App\Filament\Resources\ControlProduksis\Pages\ManageControlProduksis;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Tables\Grouping\Group as TableGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductionStage;
use App\Models\User;
use Filament\Forms\Get;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;

class ControlProduksiResource extends Resource
{
    protected static ?string $model = \App\Models\OrderItem::class;

    protected static ?string $slug = 'control-produksi';

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-squares-2x2';
    }

    public static function getModelLabel(): string
    {
        return 'Tugas Produksi';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Control Produksi';
    }

    protected static bool $isScopedToTenant = true;

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->whereHas('order', function (Builder $q) use ($tenant) {
            $q->where('shop_id', $tenant?->getKey());
        });
    }

    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
        // Override and do nothing.
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('design_status', 'approved')
            ->with(['order.customer', 'productionTasks'])
            ->latest('id');
    }


    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The form schema is now handled via Page Actions in ManageControlProduksis
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('product_name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('production_category')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn(string $state) => [
                        50 => '#F2E6FF',
                        500 => '#8000FF',
                        600 => '#8000FF',
                    ])
                    ->formatStateUsing(fn(string $state): string => ucfirst(str_replace('_', ' ', $state))),

                TextColumn::make('order.deadline')
                    ->label('Deadline')
                    ->date('d M Y')
                    ->sortable()
                    ->color('danger'),

                TextColumn::make('progress_status')
                    ->label('Status Produksi')
                    ->badge()
                    ->state(function (OrderItem $record) {
                        $tasks = $record->productionTasks;
                        if ($tasks->count() === 0)
                            return 'Belum Diproses';
                        if ($tasks->where('status', '!=', 'done')->count() === 0)
                            return 'Selesai';
                        return 'Sedang Berjalan';
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'Belum Diproses' => 'gray',
                        'Sedang Berjalan' => 'primary',
                        'Selesai' => 'success',
                        default => 'gray',
                    })
                    ->description(function (OrderItem $record) {
                        $tasks = $record->productionTasks;
                        if ($tasks->isEmpty())
                            return null;

                        // Cari yang 'in_progress' dulu
                        $activeTask = $tasks->firstWhere('status', 'in_progress');
                        if ($activeTask) {
                            return 'Sedang: ' . $activeTask->stage_name;
                        }

                        // Kalau ga ada yang in_progress, cari yang masih 'pending'
                        $pendingTask = $tasks->firstWhere('status', 'pending');
                        if ($pendingTask) {
                            return 'Tahap: ' . $pendingTask->stage_name;
                        }

                        return null; // Kalau semuanya 'done'
                    }),

            ])
            ->defaultGroup(
                TableGroup::make('order.order_number')
                    ->label('Pesanan')
                    ->getTitleFromRecordUsing(fn(Model $record): string => $record->order->order_number . ' - ' . ($record->order->customer->name ?? 'Tanpa Nama'))
                    ->collapsible()
            )
            ->filters([
                // Filters handled by Tabs in Manage page
            ])
            ->actions([
                Action::make('update_progress')
                    ->hidden(fn(OrderItem $record) => $record->productionTasks()->count() === 0)
                    ->label('Update Progress')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->modalWidth('md')
                    ->fillForm(function (OrderItem $record) {
                        return [
                            'tasks' => $record->productionTasks->map(fn($task) => [
                                'id' => $task->id,
                                'stage_name' => $task->stage_name,
                                'status' => $task->status,
                            ])->toArray(),
                        ];
                    })
                    ->form([
                        Repeater::make('tasks')
                            ->label('Tahapan Produksi')
                            ->schema([
                                Hidden::make('id'),
                                TextInput::make('stage_name')
                                    ->label('Tahap')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'pending' => 'Antrian (Belum Mulai)',
                                        'in_progress' => 'Sedang Dikerjakan',
                                        'done' => 'Selesai',
                                    ])
                                    ->required()
                                    ->columnSpan(2),
                            ])
                            ->columns(4)
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                    ])
                    ->action(function (array $data, OrderItem $record) {
                        // 1. Update status masing-masing task
                        foreach ($data['tasks'] ?? [] as $taskData) {
                            if (!empty($taskData['id'])) {
                                \App\Models\ProductionTask::where('id', $taskData['id'])
                                    ->update(['status' => $taskData['status']]);
                            }
                        }

                        // 2. Evaluasi status Order global
                        $order = $record->order;
                        if ($order) {
                            $allTasks = $order->orderItems()->with('productionTasks')->get()
                                ->flatMap(fn($item) => $item->productionTasks);

                            if ($allTasks->isEmpty()) {
                                // Jika tidak ada tugas, order bisa jadi masih di Meja Desain (diterima)
                                // atau baru keluar dari Meja Desain dan menunggu di-assign tugas (antrian).
                                // Jangan turunkan status yang sudah antrian/diproses/selesai kembali ke diterima
                                $newStatus = in_array($order->status, ['diterima', 'antrian']) ? $order->status : 'antrian';
                            } else {
                                $completedCount = $allTasks->where('status', 'done')->count();
                                $inProgressCount = $allTasks->where('status', 'in_progress')->count();
                                $totalTasks = $allTasks->count();

                                if ($completedCount === $totalTasks) {
                                    $newStatus = 'selesai';
                                } elseif ($inProgressCount > 0) {
                                    $newStatus = 'diproses';
                                } else {
                                    // Semua task pending OR ada selesai tapi ada pending (tapi ga ada yg in_progress)
                                    // Berarti lagi nunggu / antri di sela-sela tahapan
                                    $newStatus = 'diproses'; // Kalau sudah pernah mulai (ada yg done), tetap diproses.
            
                                    // Kalau bener-bener belum pernah ada yang selesai dan ngga ada progress = antrian murni
                                    if ($completedCount === 0) {
                                        $newStatus = 'antrian';
                                    }
                                }
                            }

                            // Update order kalau berubah aja (hindari query tak perlu)
                            if ($order->status !== $newStatus) {
                                $order->update(['status' => $newStatus]);
                            }
                        }
                    }),

                Action::make('atur_tugas')
                    ->label('Atur Tugas')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->hidden(fn(OrderItem $record): bool => $record->productionTasks()->count() > 0)
                    ->slideOver()
                    ->modalWidth('xl')
                    ->modalHeading('Atur Tugas Produksi')
                    ->fillForm(function (OrderItem $record) {
                        $item = $record->load('productionTasks');
                        $tasksForRepeater = [];
                        foreach ($item->productionTasks as $task) {
                            $tasksForRepeater[(string) \Illuminate\Support\Str::uuid()] = [
                                'id' => $task->id,
                                'stage_name' => $task->stage_name,
                                'assigned_to' => $task->assigned_to,
                                'quantity' => $task->quantity,
                                'size_quantities' => $task->size_quantities,
                                'description' => $task->description,
                            ];
                        }
                        return [
                            'productionTasks' => $tasksForRepeater,
                        ];
                    })
                    ->form(function (OrderItem $record) {
                        $item = $record;

                        return [
                            Placeholder::make('technical_specs')
                                ->hiddenLabel()
                                ->content(function () use ($item): HtmlString {
                                    if (!$item)
                                        return new HtmlString('');

                                    $name = htmlspecialchars($item->product_name ?? 'Produk Tak Bernama');
                                    $qty = $item->quantity ?? 0;
                                    $cat = $item->production_category ?? 'produksi';
                                    $details = $item->size_and_request_details ?? [];

                                    // Lacak Ukuran S:4, M:10 dari berbagai skema array (termasuk varian_ukuran untuk Produksi Standard)
                                    $sizes = [];
                                    if (isset($details['sizes']) && is_array($details['sizes'])) {
                                        foreach ($details['sizes'] as $s => $q) {
                                            if ($q > 0)
                                                $sizes[] = strtoupper($s) . ':' . $q;
                                        }
                                    } elseif (isset($details['varian_ukuran']) && is_array($details['varian_ukuran'])) {
                                        foreach ($details['varian_ukuran'] as $v) {
                                            $sz = strtoupper($v['ukuran'] ?? '');
                                            $qtySz = (int) ($v['qty'] ?? 0);
                                            if ($sz && $qtySz > 0) {
                                                $sizes[] = $sz . ':' . $qtySz;
                                            }
                                        }
                                    } elseif (isset($details['detail_custom']) && is_array($details['detail_custom'])) {
                                        $sizeCounts = [];
                                        foreach ($details['detail_custom'] as $u) {
                                            $sz = strtoupper($u['ukuran'] ?? 'CUSTOM');
                                            $sizeCounts[$sz] = ($sizeCounts[$sz] ?? 0) + 1;
                                        }
                                        foreach ($sizeCounts as $s => $q) {
                                            $sizes[] = $s . ':' . $q;
                                        }
                                    }
                                    $sizeString = empty($sizes) ? '' : htmlspecialchars(implode(', ', $sizes));

                                    // Badge Kategori Produk
                                    $catLabel = mb_convert_case(str_replace('_', ' ', $cat), MB_CASE_TITLE, "UTF-8");
                                    $baseStyle = 'display: inline-flex; align-items: center; justify-content: center; min-height: 1.25rem; padding: 0.125rem 0.5rem; font-size: 0.75rem; font-weight: 500; border-radius: 0.375rem; white-space: nowrap;';
                                    $catBadge = '<span style="' . $baseStyle . ' background-color: #F2E6FF; color: #8000FF; box-shadow: inset 0 0 0 1px rgba(128, 0, 255, 0.2);">' . $catLabel . '</span>';

                                    // Status Progress Bar Layout (Top Right)
                                    $tasks = $item->productionTasks;
                                    $statusText = 'Belum Diproses';
                                    $statusColor = '#8000FF';
                                    $progressPerc = 0;
                                    if ($tasks->count() > 0) {
                                        $totalTasks = $tasks->count();
                                        $doneTasks = $tasks->where('status', 'done')->count();
                                        if ($doneTasks === $totalTasks) {
                                            $statusText = 'Selesai';
                                            $statusColor = '#10B981'; // Green Emrald
                                            $progressPerc = 100;
                                        } else {
                                            $statusText = 'Sedang Diproses';
                                            $statusColor = '#8000FF';
                                            $progressPerc = max(10, round(($doneTasks / $totalTasks) * 100));
                                        }
                                    }
                                    $bgTrack = $statusText === 'Selesai' ? 'rgba(16,185,129,0.2)' : 'rgba(128,0,255,0.2)';
                                    $barHtml = '<div style="width: 48px; height: 8px; border-radius: 9999px; background-color: ' . $bgTrack . '; overflow: hidden; display: flex;"><div style="width: ' . $progressPerc . '%; height: 100%; background-color: ' . $statusColor . '; border-radius: 9999px;"></div></div>';

                                    // Badge Detail Material (Brand Bahan, Warna, dll)
                                    $badges = [];
                                    $bahanArr = [];
                                    if ($cat === 'non_produksi') {
                                        if (!empty($details['supplier_product']))
                                            $bahanArr[] = htmlspecialchars($details['supplier_product']);
                                    } elseif ($cat !== 'jasa') {
                                        if (!empty($details['brand_bahan']))
                                            $bahanArr[] = htmlspecialchars($details['brand_bahan']);
                                        if (!empty($details['bahan']))
                                            $bahanArr[] = htmlspecialchars($details['bahan']);
                                        if (!empty($details['warna_bahan']))
                                            $bahanArr[] = htmlspecialchars($details['warna_bahan']);
                                    }

                                    if (!empty($bahanArr)) {
                                        $badges[] = '<span class="px-2.5 py-2 rounded-md text-[11px] font-bold tracking-wide" style="background-color: #00bcd4; color: white;">' . implode(' &nbsp;&nbsp;|&nbsp;&nbsp; ', $bahanArr) . '</span>';
                                    } else {
                                        $badges[] = '<span class="px-2.5 py-2 rounded-md text-[11px] font-medium bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400 border border-gray-200 dark:border-gray-700"> Tidak ada detail bahan </span>';
                                    }

                                    // Sablon / Bordir text (Center Right)
                                    $sablonBordirText = '';
                                    if (isset($details['sablon_bordir']) && is_array($details['sablon_bordir'])) {
                                        $sbItems = [];
                                        foreach ($details['sablon_bordir'] as $s) {
                                            $j = htmlspecialchars($s['jenis'] ?? '');
                                            $l = htmlspecialchars($s['lokasi'] ?? '');
                                            if ($j || $l)
                                                $sbItems[] = trim("$j $l");
                                        }
                                        $sablonBordirText = implode(', ', $sbItems);
                                    } elseif (!empty($details['sablon_jenis']) || !empty($details['sablon_lokasi'])) {
                                        $j = htmlspecialchars($details['sablon_jenis'] ?? '');
                                        $l = htmlspecialchars($details['sablon_lokasi'] ?? '');
                                        $sablonBordirText = trim("$j $l");
                                    }
                                    if (empty($sablonBordirText)) {
                                        $sablonBordirText = '<span class="text-gray-400 italic font-normal">- Tidak ada detail sablon/bordir -</span>';
                                    }

                                    // Tambahan Request Box (Bottom Outline Box)
                                    $requestBox = '';
                                    $reqItems = [];
                                    if (!empty($details['request_tambahan']) && is_array($details['request_tambahan'])) {
                                        foreach ($details['request_tambahan'] as $req) {
                                            if (is_array($req)) {
                                                $jenis = htmlspecialchars($req['jenis'] ?? '');
                                                $ukuran = htmlspecialchars($req['ukuran'] ?? '');
                                                if ($ukuran === '__semua__') {
                                                    $ukuran = 'Semua Ukuran';
                                                }
                                                $qty = htmlspecialchars($req['qty_tambahan'] ?? '');
                                                $qtyText = $qty ? "({$qty} pcs)" : '';

                                                if (empty($jenis) || empty($ukuran)) {
                                                    $reqItems[] = htmlspecialchars(implode(' ', array_values(array_filter($req))));
                                                } else {
                                                    $itemText = implode(' ', array_filter([$jenis, $ukuran, $qtyText]));
                                                    $reqItems[] = trim($itemText);
                                                }
                                            } else {
                                                $reqItems[] = htmlspecialchars((string) $req);
                                            }
                                        }
                                    }

                                    if (!empty($details['catatan_tambahan'])) {
                                        $reqItems[] = htmlspecialchars($details['catatan_tambahan']);
                                    }

                                    $requestContentHtml = '';
                                    if (!empty($reqItems)) {
                                        $requestContentHtml .= '<div class="mb-2">' . implode('<br>', $reqItems) . '</div>';
                                    }

                                    // Custom Size Details text inside Tambahan Request 
                                    if ($cat === 'custom' && !empty($details['detail_custom'])) {
                                        $customItems = [];
                                        foreach ($details['detail_custom'] as $u) {
                                            $person = htmlspecialchars($u['nama'] ?? 'TN');
                                            $ld = htmlspecialchars($u['LD'] ?? '-');
                                            $lp = htmlspecialchars($u['LP'] ?? '-');
                                            $p = htmlspecialchars($u['P'] ?? '-');
                                            $customItems[] = "<span class='font-bold text-gray-800 dark:text-gray-200'>$person</span> <span class='text-gray-500'>(LD:$ld LP:$lp P:$p)</span>";
                                        }

                                        if (count($customItems) > 0) {
                                            if (count($customItems) > 2) {
                                                $requestContentHtml .= '
                                                <details class="group mt-2 mb-1">
                                                    <summary class="cursor-pointer text-primary-600 font-bold hover:underline mb-2 select-none">Tampilkan ' . count($customItems) . ' Detail Ukuran Custom</summary>
                                                    <div class="pl-3 border-l-2 border-primary-200 dark:border-primary-800 space-y-1 mt-2 text-xs">
                                                        ' . implode('<br>', $customItems) . '
                                                    </div>
                                                </details>';
                                            } else {
                                                $requestContentHtml .= '
                                                <div class="pl-3 border-l-2 border-gray-200 dark:border-gray-700 space-y-1 mt-2 text-xs">
                                                    ' . implode('<br>', $customItems) . '
                                                </div>';
                                            }
                                        }
                                    }

                                    if (!empty($requestContentHtml)) {
                                        $requestBox = '
                                        <fieldset class="mt-5 border border-gray-200 dark:border-gray-700 rounded-lg px-4 pb-3 pt-2">
                                            <legend class="px-2 ml-1 text-[10px] uppercase tracking-widest text-gray-500 font-bold bg-white dark:bg-gray-900">Tambahan Request</legend>
                                            <div class="text-[13px] text-gray-700 dark:text-gray-300 leading-relaxed font-medium">
                                                ' . $requestContentHtml . '
                                            </div>
                                        </fieldset>';
                                    }

                                    // Design Links
                                    $designLinksHtml = '';
                                    if (!empty($item->design_links) && is_array($item->design_links)) {
                                        $links = [];
                                        foreach ($item->design_links as $link) {
                                            $url = htmlspecialchars($link['link'] ?? '#');
                                            $lbl = htmlspecialchars($link['title'] ?? 'Desain');
                                            $links[] = '<a href="' . $url . '" target="_blank" class="inline-flex items-center px-2 py-1 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded text-xs font-medium text-primary-600 dark:text-primary-400 hover:bg-gray-100 transition"><svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>' . $lbl . '</a>';
                                        }
                                        if (count($links) > 0) {
                                            $designLinksHtml = '<div class="mt-4 flex flex-wrap gap-2 items-center"><span class="text-[11px] text-gray-500 font-bold uppercase tracking-wider mr-1">File Desain:</span>' . implode('', $links) . '</div>';
                                        }
                                    }

                                    $html = '
                                    <div class="p-5 border border-gray-200 dark:border-gray-700 rounded-xl bg-white dark:bg-gray-900 shadow-sm mb-4">
                                        
                                        <!-- Row 1: Kategori & Status -->
                                        <div class="flex items-center justify-between mb-4">
                                            <div>
                                                ' . $catBadge . '
                                            </div>
                                            <div class="flex items-center gap-2">
                                                ' . $barHtml . '
                                                <span class="text-xs font-bold" style="color: ' . $statusColor . ';">' . $statusText . '</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Row 2: Qty, Name & Sizes -->
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                                            <h3 class="text-base font-bold text-gray-800 dark:text-gray-100">
                                                <span class="text-gray-500 font-semibold">' . $qty . 'x</span> ' . $name . '
                                            </h3>
                                            ' . ($sizeString ? '<div class="text-[13px] font-medium text-gray-600 dark:text-gray-400">' . $sizeString . '</div>' : '') . '
                                        </div>
                                        
                                        <!-- Row 3: Material Badges & Sablon/Bordir Description -->
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-2">
                                            <div class="flex flex-wrap gap-1.5 items-center">
                                                ' . implode('', $badges) . '
                                            </div>
                                            <div class="text-[13px] font-medium text-gray-500 dark:text-gray-400 text-left sm:text-right max-w-md leading-tight">
                                                ' . $sablonBordirText . '
                                            </div>
                                        </div>
                                        
                                        <!-- Bottom Section: Tambahan & Files -->
                                        ' . $requestBox . '
                                        ' . $designLinksHtml . '
                                    </div>
                                    ';

                                    return new HtmlString($html);
                                })
                                ->columnSpanFull(),

                            \Filament\Forms\Components\Repeater::make('productionTasks')
                                ->label('Daftar Tugas Produksi')
                                ->schema([
                                    Hidden::make('id'),
                                    \Filament\Forms\Components\Select::make('stage_name')
                                        ->label('Tahap Pekerjaan')
                                        ->options(function () use ($item) {
                                            $category = $item->production_category ?? 'produksi';

                                            $query = ProductionStage::query()->orderBy('order_sequence');
                                            if ($category === 'produksi') {
                                                $query->where('for_produksi_custom', true);
                                            } elseif ($category === 'custom') {
                                                $query->where('for_produksi_custom', true);
                                            } elseif ($category === 'non_produksi') {
                                                $query->where('for_non_produksi', true);
                                            } elseif ($category === 'jasa') {
                                                $query->where('for_jasa', true);
                                            }

                                            return $query->pluck('name', 'name');
                                        })
                                        ->required()
                                        ->disableOptionsWhenSelectedInSiblingRepeaterItems(),

                                    \Filament\Forms\Components\Select::make('assigned_to')
                                        ->label('Tugaskan Ke (Pegawai)')
                                        ->options(User::where('shop_id', \Filament\Facades\Filament::getTenant()->id)->pluck('name', 'id'))
                                        ->searchable()
                                        ->preload()
                                        ->required(),

                                    TextInput::make('quantity')
                                        ->label('Total Qty')
                                        ->numeric()
                                        ->required()
                                        ->default(function () use ($item) {
                                            return $item ? $item->quantity : 0;
                                        })
                                        ->rules([
                                            fn(Get $get) => function (string $attribute, $value, \Closure $fail) use ($get, $item) {
                                                if (!$item)
                                                    return;

                                                $stageName = $get('stage_name');
                                                if (!$stageName)
                                                    return;

                                                $stage = ProductionStage::where('name', $stageName)->first();
                                                if (!$stage)
                                                    return;

                                                $sequence = $stage->order_sequence;
                                                if ($sequence <= 1) {
                                                    $maxQty = $item->quantity;
                                                    if ($value > $maxQty) {
                                                        $fail("Kuantitas untuk tahapan awal tidak boleh melebihi total pesanan ({$maxQty}).");
                                                    }
                                                    return;
                                                }

                                                $previousStage = ProductionStage::where('order_sequence', '<', $sequence)
                                                    ->orderByDesc('order_sequence')
                                                    ->first();

                                                if (!$previousStage)
                                                    return;

                                                $completedQty = $item->productionTasks()
                                                    ->where('stage_name', $previousStage->name)
                                                    ->where('status', 'done')
                                                    ->sum('quantity');

                                                if ($value > $completedQty) {
                                                    $fail("Kuantitas {$stageName} tidak boleh melebihi {$completedQty} (jumlah selesai dari {$previousStage->name}).");
                                                }
                                            }
                                        ]),

                                    \Filament\Forms\Components\KeyValue::make('size_quantities')
                                        ->label('Detail Qty per Ukuran (Opsional)')
                                        ->keyLabel('Ukuran (S, M, dll)')
                                        ->valueLabel('Jumlah')
                                        ->columnSpanFull(),

                                    \Filament\Forms\Components\Textarea::make('description')
                                        ->label('Catatan Instruksi')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                                ->columns(3)
                                ->columnSpanFull()
                                ->itemLabel(fn(array $state): ?string => $state['stage_name'] ?? null)
                                ->addActionLabel('Tambah Tugas Baru'),
                        ];
                    })
                    ->action(function (array $data, OrderItem $record) {
                        $item = $record;

                        $existingTaskIds = [];
                        $tasksData = $data['productionTasks'] ?? [];

                        foreach ($tasksData as $taskItem) {
                            $taskData = [
                                'stage_name' => $taskItem['stage_name'],
                                'assigned_to' => $taskItem['assigned_to'],
                                'quantity' => $taskItem['quantity'],
                                'size_quantities' => $taskItem['size_quantities'] ?? null,
                                'description' => $taskItem['description'] ?? null,
                                'shop_id' => \Filament\Facades\Filament::getTenant()->id,
                            ];

                            if (!empty($taskItem['id'])) {
                                $task = \App\Models\ProductionTask::find($taskItem['id']);
                                if ($task) {
                                    $task->update($taskData);
                                    $existingTaskIds[] = $task->id;
                                }
                            } else {
                                $taskData['assigned_by'] = auth()->id();
                                $taskData['status'] = 'pending';
                                $newTask = $item->productionTasks()->create($taskData);
                                $existingTaskIds[] = $newTask->id;
                            }
                        }

                        $item->productionTasks()->whereNotIn('id', $existingTaskIds)->delete();

                        // ── Sinkronisasi status Order ──────────────────────────────
                        $item->refresh();
                        $order = $item->order;

                        if ($order) {
                            $totalTasks = $order->orderItems()
                                ->withCount('productionTasks')
                                ->get()
                                ->sum('production_tasks_count');

                            if ($totalTasks > 0 && in_array($order->status, ['diterima'])) {
                                $order->update(['status' => 'antrian']);
                            }
                        }
                    })
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageControlProduksis::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
