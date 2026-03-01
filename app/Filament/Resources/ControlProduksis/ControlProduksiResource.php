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
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\HtmlString;

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
                TextColumn::make('product_name')
                    ->label('Produk')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(OrderItem $record): string => match($record->production_category) {
                        'custom'       => '🧵 Custom (Ukur Badan)',
                        'non_produksi' => '📦 Non-Produksi',
                        'jasa'         => '🔧 Jasa',
                        default        => '🏭 Produksi',
                    }),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable(),

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
                            return 'Belum Diatur';
                        if ($tasks->where('status', '!=', 'done')->count() === 0)
                            return 'Selesai';
                        if ($tasks->where('status', 'in_progress')->count() > 0)
                            return 'Diproses';
                        return 'Antrian'; // Semua masih pending, belum ada yang start
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'Belum Diatur' => 'gray',
                        'Antrian'      => 'warning',
                        'Diproses'     => 'info',
                        'Selesai'      => 'success',
                        default        => 'gray',
                    })
                    ->description(function (OrderItem $record) {
                        $tasks = $record->productionTasks;
                        if ($tasks->isEmpty())
                            return null;

                        // Tampilkan tahap yang sedang berjalan
                        $activeTask = $tasks->firstWhere('status', 'in_progress');
                        if ($activeTask) {
                            return '🔨 ' . $activeTask->stage_name . ' — ' . ($activeTask->assignedTo?->name ?? '-');
                        }

                        // Atau tahap pending pertama (antrian)
                        $pendingTask = $tasks->where('status', 'pending')->sortBy('id')->first();
                        if ($pendingTask) {
                            return '⏳ Menunggu: ' . $pendingTask->stage_name;
                        }

                        return null;
                    }),

            ])

            ->defaultGroup(
                TableGroup::make('order.order_number')
                    ->label('Pesanan')
                    ->getTitleFromRecordUsing(function (Model $record): string {
                        $order = $record->order;
                        $prefix = $order->is_express
                            ? '<span style="background:#dc2626;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;margin-right:6px;">⚡ EXPRESS</span>'
                            : '';
                        return $prefix . $order->order_number . ' — ' . ($order->customer->name ?? 'Tanpa Nama');
                    })
                    ->collapsible()
            )
            ->modifyQueryUsing(fn($query) => $query
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->orderByRaw('orders.is_express DESC')
                ->orderBy('orders.deadline', 'asc')
                ->select('order_items.*')
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
                    ->modalWidth('2xl')
                    ->modalHeading(fn(OrderItem $record) => 'Kelola Tugas: ' . ($record->product_name ?? 'Item'))
                    ->modalDescription('Klik tombol pada setiap baris untuk memperbarui status tugas masing-masing karyawan.')
                    ->fillForm(function (OrderItem $record) {
                        return ['item_id' => $record->id];
                    })
                    ->form(function (OrderItem $record) {
                        // Load tugas dan group berdasarkan urutan stage
                        $tasks = $record->productionTasks()
                            ->with(['assignedTo'])
                            ->get();

                        // Ambil semua stage yang ada beserta order_sequence-nya
                        $stageOrder = \App\Models\ProductionStage::pluck('order_sequence', 'name');

                        // Sort tugas berdasarkan order_sequence tahap, kemudian by id
                        $sortedTasks = $tasks->sortBy(function($task) use ($stageOrder) {
                            return [$stageOrder[$task->stage_name] ?? 999, $task->id];
                        });

                        // Tentukan stage yang sedang "aktif" (bisa dimulai):
                        // Tahap pertama selalu bisa, tahap berikutnya hanya jika SEMUA tasks di tahap sebelumnya = done
                        $groupedByStage = $sortedTasks->groupBy('stage_name');
                        $stagesInOrder = $groupedByStage->keys()->sortBy(fn($s) => $stageOrder[$s] ?? 999);

                        $unlockedStages = [];
                        foreach ($stagesInOrder as $i => $stageName) {
                            if ($i === 0) {
                                $unlockedStages[] = $stageName;
                                continue;
                            }
                            // Stage ini bisa dibuka jika semua task di stage sebelumnya = done
                            $prevStage = $stagesInOrder[$i - 1];
                            $prevDone = $groupedByStage[$prevStage]->every(fn($t) => $t->status === 'done');
                            if ($prevDone) {
                                $unlockedStages[] = $stageName;
                            } else {
                                break; // Tahap berikutnya tetap terkunci
                            }
                        }

                        // Render HTML tabel tugas
                        $rows = '';
                        $prevStageName = null;
                        foreach ($sortedTasks as $task) {
                            $isUnlocked = in_array($task->stage_name, $unlockedStages);
                            $workerName = $task->assignedTo?->name ?? 'Tidak Diketahui';
                            $stageName = htmlspecialchars($task->stage_name);
                            $qty = $task->quantity . ' pcs';
                            $showStageLabel = $task->stage_name !== $prevStageName;
                            $prevStageName = $task->stage_name;

                            $statusBadge = match($task->status) {
                                'pending' => '<span style="background:#fef3c7;color:#92400e;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600">⏳ Antrian</span>',
                                'in_progress' => '<span style="background:#dbeafe;color:#1e40af;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600">🔨 Dikerjakan</span>',
                                'done' => '<span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:600">✅ Selesai</span>',
                                default => '',
                            };

                            if (!$isUnlocked) {
                                $actionBtn = '<span style="color:#9ca3af;font-size:12px">🔒 Menunggu tahap sebelumnya</span>';
                            } elseif ($task->status === 'pending') {
                                $actionBtn = '<a href="' . route('filament.admin.resources.control-produksis.task-action', ['task' => $task->id, 'action' => 'start', 'item' => $record->id]) . '" style="background:#2563eb;color:#fff;padding:4px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">▶ Mulai</a>';
                            } elseif ($task->status === 'in_progress') {
                                $actionBtn = '<a href="' . route('filament.admin.resources.control-produksis.task-action', ['task' => $task->id, 'action' => 'done', 'item' => $record->id]) . '" style="background:#059669;color:#fff;padding:4px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">✓ Tandai Selesai</a>';
                            } else {
                                $actionBtn = '<span style="color:#6b7280;font-size:12px">Selesai</span>';
                            }

                            $stageCell = $showStageLabel
                                ? '<td style="padding:10px 14px;font-weight:700;color:#374151;font-size:13px;border-bottom:1px solid #e5e7eb;">' . $stageName . '</td>'
                                : '<td style="padding:10px 14px;color:#9ca3af;font-size:13px;border-bottom:1px solid #e5e7eb;">↳</td>';

                            $rows .= '
                                <tr>
                                    ' . $stageCell . '
                                    <td style="padding:10px 14px;font-size:13px;color:#374151;border-bottom:1px solid #e5e7eb;">' . htmlspecialchars($workerName) . '</td>
                                    <td style="padding:10px 14px;font-size:13px;color:#374151;border-bottom:1px solid #e5e7eb;">' . $qty . '</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;">' . $statusBadge . '</td>
                                    <td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $actionBtn . '</td>
                                </tr>';
                        }

                        // ─── Banner desain ───────────────────────────────────────
                        $designHtml = '';
                        if ($record->design_image) {
                            $designUrl = asset('storage/' . $record->design_image);
                            $designHtml = '
                                <div style="margin-bottom:16px;border:1.5px solid #c4b5fd;border-radius:12px;overflow:hidden;background:#faf5ff;">
                                    <div style="padding:8px 14px;background:#ede9fe;display:flex;align-items:center;gap:8px;">
                                        <span style="font-size:16px;">🎨</span>
                                        <span style="font-size:13px;font-weight:600;color:#5b21b6;">Referensi Desain</span>
                                        <a href="' . $designUrl . '" target="_blank" style="margin-left:auto;font-size:11px;color:#7c3aed;text-decoration:underline;">Buka full ↗</a>
                                    </div>
                                    <div style="padding:12px;text-align:center;">
                                        <a href="' . $designUrl . '" target="_blank">
                                            <img src="' . $designUrl . '" style="max-height:200px;max-width:100%;object-fit:contain;border-radius:8px;cursor:zoom-in;" alt="Desain">
                                        </a>
                                    </div>
                                </div>';
                        } else {
                            $designHtml = '
                                <div style="margin-bottom:16px;border:1.5px dashed #d1d5db;border-radius:12px;padding:16px;text-align:center;background:#f9fafb;">
                                    <span style="font-size:13px;color:#9ca3af;">🖼️ Belum ada file desain yang diupload untuk item ini</span>
                                </div>';
                        }

                        $html = $designHtml . '
                            <div style="border-radius:10px;overflow:hidden;border:1px solid #e5e7eb;">
                                <table style="width:100%;border-collapse:collapse;">
                                    <thead>
                                        <tr style="background:#f9fafb;">
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;">TAHAP</th>
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;">KARYAWAN</th>
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;">QTY</th>
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;">STATUS</th>
                                            <th style="padding:10px 14px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;">AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>' . $rows . '</tbody>
                                </table>
                            </div>';

                        return [
                            Placeholder::make('task_table')
                                ->hiddenLabel()
                                ->content(new \Illuminate\Support\HtmlString($html)),
                        ];
                    })
                    ->action(function (array $data, OrderItem $record) {
                        // Action utama form ini hanya sebagai re-render / reload
                        // Update status dilakukan via dedicated route (link tombol per baris)
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
                            $wagePerPcs = $task->quantity > 0 ? (int) ($task->wage_amount / $task->quantity) : 0;
                            $tasksForRepeater[(string) \Illuminate\Support\Str::uuid()] = [
                                'id' => $task->id,
                                'stage_name' => $task->stage_name,
                                'assigned_to' => $task->assigned_to,
                                'quantity' => $task->quantity,
                                'wage_per_pcs' => $wagePerPcs,
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

                                    // Validasi qty khusus untuk Custom (menghitung jumlah orang)
                                    $displayQty = $qty;
                                    if ($cat === 'custom' && !empty($details['detail_custom'])) {
                                        $displayQty = count($details['detail_custom']);
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
                                                <span class="text-gray-500 font-semibold">' . $displayQty . 'x</span> ' . $name . '
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
                                    \Filament\Schemas\Components\Grid::make(2)
                                        ->schema([
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
                                                ->live()
                                                ->afterStateUpdated(function ($state, Set $set) {
                                                    if ($state) {
                                                        $stage = \App\Models\ProductionStage::where('name', $state)->first();
                                                        if ($stage) {
                                                            $set('wage_per_pcs', $stage->base_wage);
                                                        }
                                                    }
                                                }),

                                            \Filament\Forms\Components\Select::make('assigned_to')
                                                ->label('Tugaskan Ke (Karyawan)')
                                                ->options(function () {
                                                    $workers = \App\Models\Worker::where('shop_id', \Filament\Facades\Filament::getTenant()->id)
                                                        ->where('is_active', true)
                                                        ->get();

                                                    $opts = [];
                                                    foreach ($workers as $w) {
                                                        $q = $w->active_queue_count;
                                                        $opts[$w->id] = "{$w->name}" . ($q > 0 ? " — (Antrian: {$q} pcs)" : "");
                                                    }
                                                    return $opts;
                                                })
                                                ->searchable()
                                                ->preload()
                                                ->required(),

                                            TextInput::make('wage_per_pcs')
                                                ->label('Upah Satuan Dasar (Rp)')
                                                ->numeric()
                                                ->default(0)
                                                ->prefix('Rp')
                                                ->helperText('Otomatis/Bisa diubah'),

                                            TextInput::make('quantity')
                                                ->label('Total Qty (otomatis)')
                                                ->numeric()
                                                ->readOnly()
                                                ->dehydrated()
                                                ->default(0)
                                                ->extraInputAttributes(['style' => 'font-weight:700;color:#7c3aed;cursor:not-allowed;'])
                                                ->helperText('Dihitung otomatis'),
                                        ]),

                                    \Filament\Schemas\Components\Fieldset::make('size_quantities')
                                        ->label('Detail Qty per Ukuran')
                                        ->schema(function (\App\Models\OrderItem $record) use ($item) {
                                            $fields = [];
                                            $cat = $record->production_category ?? 'produksi';
                                            $details = $record->size_and_request_details ?? [];

                                            // Recalculate Total Qty from all size inputs
                                            $recalcQty = function (Get $get, Set $set) {
                                                $sizeQty = $get('size_quantities') ?? [];
                                                $total = 0;
                                                if (is_array($sizeQty)) {
                                                    foreach ($sizeQty as $v) {
                                                        $total += (int) $v;
                                                    }
                                                }
                                                $set('quantity', $total > 0 ? $total : 0);
                                            };

                                            // Ekstrak ukuran + stok dari detail order
                                            $extractSizes = function () use ($details): array {
                                                $sizes = [];
                                                if (isset($details['sizes']) && is_array($details['sizes'])) {
                                                    foreach ($details['sizes'] as $sz => $qty) {
                                                        if ((int) $qty > 0)
                                                            $sizes[strtoupper($sz)] = (int) $qty;
                                                    }
                                                } elseif (isset($details['varian_ukuran']) && is_array($details['varian_ukuran'])) {
                                                    foreach ($details['varian_ukuran'] as $v) {
                                                        $sz = strtoupper($v['ukuran'] ?? '');
                                                        if ($sz && (int) ($v['qty'] ?? 0) > 0) {
                                                            $sizes[$sz] = (int) $v['qty'];
                                                        }
                                                    }
                                                }
                                                return $sizes;
                                            };

                                            if ($cat === 'custom') {
                                                $people = [];
                                                if (!empty($details['detail_custom']) && is_array($details['detail_custom'])) {
                                                    foreach ($details['detail_custom'] as $index => $u) {
                                                        $person = htmlspecialchars($u['nama'] ?? 'Person ' . ($index + 1));
                                                        $sz = strtoupper($u['ukuran'] ?? 'CUSTOM');
                                                        $people[] = ['key' => $person, 'sz' => $sz];
                                                    }
                                                }

                                                // Toggle Kerjakan Semua (custom)
                                                $fields[] = \Filament\Forms\Components\Toggle::make('_fill_all')
                                                    ->label('✓ Kerjakan semua (' . count($people) . ' orang)')
                                                    ->dehydrated(false)
                                                    ->live()
                                                    ->afterStateUpdated(function (bool $state, Set $set) use ($people) {
                                                    foreach ($people as $p) {
                                                        $set($p['key'], $state ? 1 : 0);
                                                    }
                                                    $set('quantity', $state ? count($people) : 0);
                                                })
                                                    ->columnSpanFull();

                                                foreach ($people as $p) {
                                                    $fields[] = \Filament\Forms\Components\TextInput::make($p['key'])
                                                        ->label($p['key'])
                                                        ->placeholder($p['sz'])
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->maxValue(1)
                                                        ->default(0)
                                                        ->readOnly(fn(Get $get) => (bool) $get('_fill_all'))
                                                        ->extraInputAttributes(fn(Get $get) => array_merge(
                                                            [
                                                                'style' => 'text-align:center;padding-left:0.5rem;padding-right:0.5rem;',
                                                                'oninput' => "if(this.value > 1) this.value = 1;"
                                                            ],
                                                            $get('_fill_all') ? ['style' => 'background-color:rgba(175,175,175,0.08);cursor:not-allowed;text-align:center;padding-left:0.5rem;padding-right:0.5rem;'] : []
                                                        ))
                                                        ->live(debounce: 300)
                                                        ->afterStateUpdated(function ($state, Set $set, Get $get) use ($recalcQty, $p) {
                                                            if ((int) $state > 1) {
                                                                $set($p['key'], 1);
                                                            }
                                                            $recalcQty($get, $set);
                                                        });
                                                }
                                            } else {
                                                $sizes = $extractSizes();

                                                // Map request tambahan per ukuran
                                                $requestPerSize = [];
                                                $reqData = $details['request_tambahan'] ?? [];
                                                if (is_array($reqData)) {
                                                    foreach ($reqData as $req) {
                                                        $jenis = $req['jenis'] ?? null;
                                                        $ukuran = strtoupper($req['ukuran'] ?? '');
                                                        $qty = (int) ($req['qty_tambahan'] ?? 0);
                                                        if (!$jenis || $qty <= 0)
                                                            continue;
                                                        if ($ukuran === '__SEMUA__' || $ukuran === '') {
                                                            foreach (array_keys($sizes) as $sz) {
                                                                $requestPerSize[$sz][] = "{$jenis} ({$qty})";
                                                            }
                                                        } else {
                                                            $requestPerSize[$ukuran][] = "{$jenis} ({$qty})";
                                                        }
                                                    }
                                                }

                                                if (!empty($sizes)) {
                                                    $totalStok = array_sum($sizes);

                                                    // Toggle Kerjakan Semua
                                                    $fields[] = \Filament\Forms\Components\Toggle::make('_fill_all')
                                                        ->label('✓ Kerjakan semua ukuran (total: ' . $totalStok . ' pcs)')
                                                        ->dehydrated(false)
                                                        ->live()
                                                        ->afterStateUpdated(function (bool $state, Set $set) use ($sizes) {
                                                        foreach ($sizes as $sizeName => $maxQty) {
                                                            $set($sizeName, $state ? $maxQty : 0);
                                                        }
                                                        $set('quantity', $state ? array_sum($sizes) : 0);
                                                    })
                                                        ->columnSpanFull();

                                                    foreach ($sizes as $sizeName => $maxQty) {
                                                        $fields[] = \Filament\Forms\Components\TextInput::make($sizeName)
                                                            ->label($sizeName)
                                                            ->placeholder("0 - {$maxQty}")
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->maxValue($maxQty)
                                                            ->default(0)
                                                            ->readOnly(fn(Get $get) => (bool) $get('_fill_all'))
                                                            ->extraInputAttributes(fn(Get $get) => array_merge(
                                                                [
                                                                    'style' => 'text-align:center;padding-left:0.5rem;padding-right:0.5rem;',
                                                                    'oninput' => "if(this.value > {$maxQty}) this.value = {$maxQty};"
                                                                ],
                                                                $get('_fill_all') ? ['style' => 'background-color:rgba(175,175,175,0.08);cursor:not-allowed;text-align:center;padding-left:0.5rem;padding-right:0.5rem;'] : []
                                                            ))
                                                            ->live(debounce: 300)
                                                            ->afterStateUpdated(function ($state, Set $set, Get $get) use ($recalcQty, $maxQty, $sizeName) {
                                                                if ((int) $state > $maxQty) {
                                                                    $set($sizeName, $maxQty);
                                                                }
                                                                $recalcQty($get, $set);
                                                            });
                                                    }

                                                    // Info request tambahan — hanya muncul jika ada & stage_name mengandung 'jahit'
                                                    if (!empty($requestPerSize)) {
                                                        $summaryParts = [];
                                                        foreach ($requestPerSize as $sz => $reqs) {
                                                            $summaryParts[] = strtoupper($sz) . ' -> ' . implode(', ', $reqs);
                                                        }
                                                        $summaryText = '* Request Tambahan:  ' . implode('   |   ', $summaryParts);
                                                    }
                                                } else {
                                                    // Fallback: tidak ada data ukuran
                                                    $fields[] = \Filament\Forms\Components\TextInput::make('qty')
                                                        ->label('Target Qty')
                                                        ->numeric()
                                                        ->minValue(0)
                                                        ->default(0)
                                                        ->live(debounce: 300)
                                                        ->afterStateUpdated($recalcQty);
                                                }
                                            }

                                            return $fields;
                                        })
                                        ->columns(6)
                                        ->columnSpanFull(),

                                    \Filament\Forms\Components\Textarea::make('description')
                                        ->label('Catatan Instruksi')
                                        ->rows(2)
                                        ->columnSpanFull(),
                                ])
                                ->columnSpanFull()
                                ->itemLabel(fn(array $state): ?string => $state['stage_name'] ?? null)
                                ->addActionLabel('Tambah Tugas Baru'),
                        ];
                    })
                    ->action(function (array $data, OrderItem $record, \Filament\Actions\Action $action) {
                        $item = $record;
                        $tasksData = $data['productionTasks'] ?? [];
                        
                        // 1. Validasi Total Qty Per Tahapan & Kelengkapan Tahapan
                        $errors = [];
                        
                        // A. Cek Kelengkapan Tahapan Wajib
                        $category = $item->production_category ?? 'produksi';
                        $stageQuery = \App\Models\ProductionStage::query()->orderBy('order_sequence');
                        if ($category === 'produksi' || $category === 'custom') {
                            $stageQuery->where('for_produksi_custom', true);
                        } elseif ($category === 'non_produksi') {
                            $stageQuery->where('for_non_produksi', true);
                        } elseif ($category === 'jasa') {
                            $stageQuery->where('for_jasa', true);
                        }
                        
                        $requiredStages = $stageQuery->pluck('name')->toArray();
                        $stagedTasks = collect($tasksData)->groupBy('stage_name');
                        
                        $missingStages = array_diff($requiredStages, $stagedTasks->keys()->toArray());
                        if (!empty($missingStages)) {
                            $errors[] = "Tahapan berikut wajib dikerjakan dan belum ditugaskan: <b>" . implode(', ', $missingStages) . "</b>";
                        }
                        
                        // Menyiapkan daftar kunci (key) ukuran/person apa saja yang perlu diekstrak dari data repeater (karena Fieldset mem-flatten inputannya)
                        $sizesToLookFor = [];
                        if ($item->production_category === 'custom') {
                            $details = $item->size_and_request_details ?? [];
                            if (!empty($details['detail_custom']) && is_array($details['detail_custom'])) {
                                foreach ($details['detail_custom'] as $index => $u) {
                                    $person = $u['nama'] ?? 'Person ' . ($index + 1);
                                    $sizesToLookFor[] = $person;
                                }
                            }
                        } else {
                            $details = $item->size_and_request_details ?? [];
                            if (isset($details['sizes']) && is_array($details['sizes'])) {
                                foreach ($details['sizes'] as $sz => $qty) {
                                    if ((int)$qty > 0) $sizesToLookFor[] = strtoupper($sz);
                                }
                            } elseif (isset($details['varian_ukuran']) && is_array($details['varian_ukuran'])) {
                                foreach ($details['varian_ukuran'] as $v) {
                                    $sz = strtoupper($v['ukuran'] ?? '');
                                    if ($sz && (int)($v['qty'] ?? 0) > 0) $sizesToLookFor[] = $sz;
                                }
                            }
                        }
                        $stagedTasks = collect($tasksData)->groupBy('stage_name');
                        
                        // Siapkan stok per ukuran dari item (max yang tersedia untuk setiap ukuran)
                        $stockPerSize = [];
                        if ($item->production_category === 'custom') {
                            $details = $item->size_and_request_details ?? [];
                            if (!empty($details['detail_custom'])) {
                                foreach ($details['detail_custom'] as $u) {
                                    $sz = $u['nama'] ?? null;
                                    if ($sz) $stockPerSize[$sz] = ($stockPerSize[$sz] ?? 0) + 1;
                                }
                            }
                        } else {
                            $details = $item->size_and_request_details ?? [];
                            if (isset($details['sizes']) && is_array($details['sizes'])) {
                                foreach ($details['sizes'] as $sz => $qty) {
                                    if ((int)$qty > 0) $stockPerSize[strtoupper($sz)] = (int)$qty;
                                }
                            } elseif (isset($details['varian_ukuran']) && is_array($details['varian_ukuran'])) {
                                foreach ($details['varian_ukuran'] as $v) {
                                    $sz = strtoupper($v['ukuran'] ?? '');
                                    if ($sz && (int)($v['qty'] ?? 0) > 0) {
                                        $stockPerSize[$sz] = (int)$v['qty'];
                                    }
                                }
                            }
                        }
                        
                        foreach ($stagedTasks as $stageName => $tasksGroup) {
                            $totalAssignedQty = $tasksGroup->sum(function($t) use ($sizesToLookFor) {
                                // Default quantity field is disabled/readOnly so it might be missing or 0
                                // Calculate from size_quantities fields instead (which are flattened to root $t)
                                $sizeQty = 0;
                                foreach ($sizesToLookFor as $key) {
                                    // if a specific size is assigned to this part of the stage
                                    if (isset($t[$key]) && (int)$t[$key] > 0) {
                                        $sizeQty += (int)$t[$key];
                                    }
                                }
                                
                                // Fallback for simple qty item if it doesn't have sizes
                                if ($sizeQty === 0 && isset($t['qty']) && (int)$t['qty'] > 0) {
                                    $sizeQty += (int)$t['qty'];
                                }
                                
                                return $sizeQty;
                            });
                            
                            // Validasi per ukuran: pastikan tidak ada ukuran yang over-quota di dalam 1 tahapan
                            if (!empty($stockPerSize)) {
                                foreach ($stockPerSize as $sizeKey => $maxQty) {
                                    $allocatedForSize = $tasksGroup->sum(fn($t) => (int)($t[$sizeKey] ?? 0));
                                    if ($allocatedForSize > $maxQty) {
                                        $errors[] = "Tahap '<b>{$stageName}</b>': ukuran <b>{$sizeKey}</b> dialokasikan {$allocatedForSize} pcs melebihi stok ({$maxQty} pcs).";
                                    }
                                }
                            }
                            
                            // Bandingkan dengan qty yang seharusnya (kalau custom hitung orang)
                            $expectedQty = (int)$item->quantity;
                            if ($item->production_category === 'custom') {
                                $details = $item->size_and_request_details ?? [];
                                if (!empty($details['detail_custom'])) {
                                    $expectedQty = count($details['detail_custom']);
                                }
                            }
                            
                            if ($totalAssignedQty !== $expectedQty) {
                                $errors[] = "Total kuantitas untuk tahap '<b>{$stageName}</b>' ({$totalAssignedQty} pcs) tidak sesuai dengan total produk ({$expectedQty} pcs).";
                            }
                        }

                        
                        if (count($errors) > 0) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Gagal Menyimpan Tugas')
                                ->body(implode('<br>', $errors))
                                ->send();
                            
                            $action->halt();
                        }

                        $existingTaskIds = [];

                        foreach ($tasksData as $taskItem) {
                            // Extract size_quantities which are dynamically generated inputs flattened by Fieldset
                            $sizeQuantities = [];
                            
                            // The keys we want to grab depend on the item category
                            $sizesToLookFor = [];
                            if ($item->production_category === 'custom') {
                                $details = $item->size_and_request_details ?? [];
                                if (!empty($details['detail_custom']) && is_array($details['detail_custom'])) {
                                    foreach ($details['detail_custom'] as $index => $u) {
                                        $person = $u['nama'] ?? 'Person ' . ($index + 1);
                                        $sizesToLookFor[] = $person;
                                    }
                                }
                            } else {
                                $details = $item->size_and_request_details ?? [];
                                if (isset($details['sizes']) && is_array($details['sizes'])) {
                                    foreach ($details['sizes'] as $sz => $qty) {
                                        if ((int)$qty > 0) $sizesToLookFor[] = strtoupper($sz);
                                    }
                                } elseif (isset($details['varian_ukuran']) && is_array($details['varian_ukuran'])) {
                                    foreach ($details['varian_ukuran'] as $v) {
                                        $sz = strtoupper($v['ukuran'] ?? '');
                                        if ($sz && (int)($v['qty'] ?? 0) > 0) $sizesToLookFor[] = $sz;
                                    }
                                }
                            }

                            // Extract these specific keys from $taskItem if they exist and are greater than 0
                            foreach ($sizesToLookFor as $key) {
                                if (isset($taskItem[$key]) && (int)$taskItem[$key] > 0) {
                                    $sizeQuantities[$key] = (int)$taskItem[$key];
                                }
                            }

                            // Hitung quantity dari sizeQuantities yang sudah diekstrak (quantity field readOnly jadi tidak reliable)
                            $quantity = array_sum($sizeQuantities);
                            // Fallback ke field qty biasa (untuk item tanpa ukuran)
                            if ($quantity === 0 && isset($taskItem['qty']) && (int)$taskItem['qty'] > 0) {
                                $quantity = (int)$taskItem['qty'];
                            }
                            $wagePerPcs = (float) ($taskItem['wage_per_pcs'] ?? 0);

                            $taskData = [
                                'stage_name' => $taskItem['stage_name'],
                                'assigned_to' => $taskItem['assigned_to'],
                                'quantity' => $quantity,
                                'wage_amount' => $quantity * $wagePerPcs,
                                'size_quantities' => empty($sizeQuantities) ? null : $sizeQuantities,
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
