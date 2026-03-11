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

    protected static string|\UnitEnum|null $navigationGroup = 'PRODUKSI';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'designer', 'owner']);
    }

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
                    ->description(fn(OrderItem $record): string => match ($record->production_category) {
                        'custom' => '🧵 Custom (Ukur Badan)',
                        'non_produksi' => '📦 Non-Produksi',
                        'jasa' => '🔧 Jasa',
                        default => '🏭 Produksi',
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
                        'Antrian' => 'warning',
                        'Diproses' => 'info',
                        'Selesai' => 'success',
                        default => 'gray',
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
                    ->getTitleFromRecordUsing(function (Model $record): HtmlString {
                        $order = $record->order;
                        $prefix = $order->is_express
                            ? '<span style="background:#dc2626;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;margin-right:6px;">⚡ EXPRESS</span>'
                            : '';
                        return new HtmlString($prefix . $order->order_number . ' — ' . ($order->customer->name ?? 'Tanpa Nama'));
                    })
                    ->collapsible()
            )
            ->modifyQueryUsing(
                fn($query) => $query
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
                    ->hidden(fn(OrderItem $record) => $record->productionTasks()->where('status', '!=', 'done')->count() === 0)
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
                        $sortedTasks = $tasks->sortBy(function ($task) use ($stageOrder) {
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

                            $statusBadge = match ($task->status) {
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
                    ->hidden(fn(OrderItem $record) => $record->productionTasks()->exists() && $record->productionTasks()->where('status', '!=', 'done')->count() === 0)
                    ->label(fn(OrderItem $record) => $record->productionTasks()->exists() ? 'Edit Tugas' : 'Atur Tugas')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->slideOver()
                    ->modalWidth('xl')
                    ->modalHeading(fn(OrderItem $record) => $record->productionTasks()->exists() ? 'Edit Tugas Produksi' : 'Atur Tugas Produksi')
                    ->fillForm(function (OrderItem $record) {
                        $item = $record->load('productionTasks');
                        $tasksForRepeater = [];
                        foreach ($item->productionTasks as $task) {
                            $wagePerPcs = $task->quantity > 0 ? (int) ($task->wage_amount / $task->quantity) : 0;
                            $taskRow = [
                                'id' => $task->id,
                                'stage_name' => $task->stage_name,
                                'assigned_to' => $task->assigned_to,
                                'quantity' => $task->quantity,
                                'wage_per_pcs' => $wagePerPcs,
                                'description' => $task->description,
                            ];

                            // Unpack size_quantities into the flat row state
                            if (is_array($task->size_quantities)) {
                                $details = $item->size_and_request_details ?? [];
                                foreach ($task->size_quantities as $sz => $qty) {
                                    $szUpper = strtoupper($sz);
                                    $taskRow[$szUpper] = $qty;

                                    // Mapping for custom items (backward compatibility)
                                    if ($item->production_category === 'custom' && !empty($details['detail_custom'])) {
                                        foreach ($details['detail_custom'] as $index => $u) {
                                            $pName = trim($u['nama'] ?? 'Person ' . ($index + 1));
                                            $safeKey = strtoupper(preg_replace('/[^a-zA-Z0-9_]/', '_', $pName)) . '_' . $index;
                                            if ($szUpper === strtoupper($pName) || $szUpper === $safeKey) {
                                                $taskRow[$safeKey] = $qty;
                                            }
                                        }
                                    }
                                }
                            }
                            $tasksForRepeater[(string) \Illuminate\Support\Str::uuid()] = $taskRow;
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

                                    $details = $item->size_and_request_details ?? [];
                                    $html = '<div class="space-y-4 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-xl border border-gray-100 dark:border-gray-800">';

                                    // --- Row 1: Header (Product Name & Badges) ---
                                    $html .= '<div class="flex flex-wrap items-center gap-3">';
                                    $html .= '<h3 class="text-lg font-bold text-gray-800 dark:text-gray-100 leading-none">' . htmlspecialchars($item->product_name ?? 'Produk Tak Bernama') . '</h3>';

                                    $cat = $item->production_category ?? 'produksi';
                                    $catLabel = mb_convert_case(str_replace('_', ' ', $cat), MB_CASE_TITLE, "UTF-8");
                                    $html .= '<span class="px-2 py-1 rounded-md text-[10px] font-bold uppercase tracking-wider bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 border border-primary-100 dark:border-primary-800">' . $catLabel . '</span>';

                                    $bahanArr = [];
                                    $resolveName = function ($id, $type = 'material') {
                                        if (!is_numeric($id))
                                            return $id;
                                        if ($type === 'material') {
                                            return \App\Models\Material::find($id)?->name ?? $id;
                                        }
                                        return \App\Models\Product::find($id)?->name ?? $id;
                                    };

                                    if ($cat === 'non_produksi') {
                                        if (!empty($details['supplier_product']))
                                            $bahanArr[] = htmlspecialchars($resolveName($details['supplier_product'], 'product'));
                                    } elseif ($cat !== 'jasa') {
                                        if (!empty($details['brand_bahan']))
                                            $bahanArr[] = htmlspecialchars($details['brand_bahan']);
                                        if (!empty($details['bahan']))
                                            $bahanArr[] = htmlspecialchars($resolveName($details['bahan'], 'material'));
                                        if (!empty($details['warna_bahan']))
                                            $bahanArr[] = htmlspecialchars($details['warna_bahan']);
                                    }

                                    if (!empty($bahanArr)) {
                                        $html .= '<span class="px-3 py-1.5 rounded-lg text-[11px] font-bold tracking-wide bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700 shadow-sm">' . implode(' &nbsp;•&nbsp; ', $bahanArr) . '</span>';
                                    }
                                    $html .= '</div>';

                                    // --- Row 2: Specs (Sablon/Bordir & Additional Request) ---
                                    $specs = [];
                                    if (!empty($details['sablon_jenis']))
                                        $specs[] = '🎨 ' . htmlspecialchars($details['sablon_jenis']) . ' (' . htmlspecialchars($details['sablon_lokasi'] ?? 'Lokasi tidak diset') . ')';

                                    $requests = $details['request_tambahan'] ?? [];
                                    if (!empty($requests)) {
                                        $reqStr = [];
                                        foreach ($requests as $r) {
                                            if (isset($r['jenis'])) {
                                                $uk = isset($r['ukuran']) ? ($r['ukuran'] === '__semua__' ? 'Semua Ukuran' : $r['ukuran']) : '';
                                                $reqStr[] = htmlspecialchars($r['jenis']) . ($uk ? " ($uk)" : "");
                                            }
                                        }
                                        if (!empty($reqStr))
                                            $specs[] = '🛠️ ' . implode(', ', $reqStr);
                                    }

                                    if (!empty($specs)) {
                                        $html .= '<div class="flex flex-wrap gap-x-6 gap-y-2 text-xs font-medium text-gray-600 dark:text-gray-400">';
                                        foreach ($specs as $s) {
                                            $html .= '<span>' . $s . '</span>';
                                        }
                                        $html .= '</div>';
                                    }

                                    // --- Row 3: Measurement Details ---
                                    $html .= '<div class="pt-3 border-t border-dashed border-gray-200 dark:border-gray-700">';

                                    if ($cat === 'custom' && !empty($details['detail_custom'])) {
                                        $html .= '<p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2">Detail Ukuran Custom</p>';
                                        $customItems = [];
                                        foreach ($details['detail_custom'] as $u) {
                                            $person = htmlspecialchars($u['nama'] ?? 'TN');
                                            $mParts = [];
                                            if (!empty($u['LD']))
                                                $mParts[] = "LD:$u[LD]";
                                            if (!empty($u['PL']))
                                                $mParts[] = "PL:$u[PL]";
                                            if (!empty($u['LP']))
                                                $mParts[] = "LP:$u[LP]";
                                            if (!empty($u['LB']))
                                                $mParts[] = "LB:$u[LB]";
                                            if (!empty($u['LPi']))
                                                $mParts[] = "LPi:$u[LPi]";
                                            if (!empty($u['PB']))
                                                $mParts[] = "PB:$u[PB]";

                                            $mStr = !empty($mParts) ? '<span class="text-gray-500 font-normal"> (' . implode(' ', $mParts) . ')</span>' : '';
                                            $customItems[] = "<div class='text-xs py-1 px-3 bg-white dark:bg-gray-800 rounded-md border border-gray-100 dark:border-gray-800 shadow-sm'><span class='font-bold text-gray-800 dark:text-gray-200'>$person</span>$mStr</div>";
                                        }
                                        $html .= '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">' . implode('', $customItems) . '</div>';
                                    } else {
                                        $varian = $details['varian_ukuran'] ?? [];
                                        if (!empty($varian)) {
                                            $html .= '<p class="text-[11px] font-bold text-gray-400 uppercase tracking-widest mb-2">Daftar Ukuran</p>';
                                            $vItems = [];
                                            foreach ($varian as $v) {
                                                $size = htmlspecialchars($v['ukuran'] ?? '-');
                                                $qty = htmlspecialchars($v['qty'] ?? 0);
                                                $vItems[] = "<span class='px-2 py-1 bg-white dark:bg-gray-800 rounded border border-gray-100 dark:border-gray-800 text-xs font-bold text-gray-700 dark:text-gray-300'>Size $size: <span class='text-primary-600'>$qty pcs</span></span>";
                                            }
                                            $html .= '<div class="flex flex-wrap gap-2">' . implode('', $vItems) . '</div>';
                                        }
                                    }
                                    $html .= '</div>';

                                    $html .= '</div>';
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

                                    \Filament\Schemas\Components\Fieldset::make(null)
                                        ->label('Detail Qty per Ukuran')
                                        ->schema(function (\App\Models\OrderItem $record) use ($item) {
                                            $fields = [];
                                            $cat = $record->production_category ?? 'produksi';
                                            $details = $record->size_and_request_details ?? [];

                                            // Recalculate Total Qty from all size inputs
                                            $recalcQty = function (Get $get, Set $set) {
                                                $excludeKeys = ['id', 'stage_name', 'assigned_to', 'wage_per_pcs', 'quantity', 'description', '_fill_all', 'qty'];
                                                $total = 0;
                                                // Because Fieldset flattens the schema, $get('') from a sub-field or using a key gets the flattened array of the Repeater row
                                                // So we must manually check all keys in the current repeater item's state
                                                // Filament's $get('../') from within the fieldset usually just gives the repeater row if we use specific keys
                                                // However, since we define fields dynamically, they are at the ROOT of the repeater item.
                                                // We can grab the whole item state by getting relative path ''
                                                $state = $get('');
                                                if (is_array($state)) {
                                                    foreach ($state as $k => $v) {
                                                        if (!in_array($k, $excludeKeys) && is_numeric($v)) {
                                                            $total += (int) $v;
                                                        }
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
                                                        $person = trim($u['nama'] ?? 'Person ' . ($index + 1));
                                                        $sz = strtoupper($u['ukuran'] ?? 'CUSTOM');
                                                        $safeKey = strtoupper(preg_replace('/[^a-zA-Z0-9_]/', '_', $person) . '_' . $index);
                                                        $people[] = [
                                                            'key' => $safeKey,
                                                            'label' => $person,
                                                            'sz' => $sz
                                                        ];
                                                    }
                                                }

                                                // Toggle Kerjakan Semua (custom)
                                                $fields[] = \Filament\Forms\Components\Toggle::make('_fill_all')
                                                    ->label('✓ Kerjakan semua (' . count($people) . ' orang)')
                                                    ->dehydrated(false)
                                                    ->live()
                                                    ->afterStateUpdated(function (bool $state, Set $set, Get $get) use ($people, $recalcQty) {
                                                    foreach ($people as $p) {
                                                        $set($p['key'], $state ? 1 : 0);
                                                    }
                                                    $recalcQty($get, $set);
                                                })
                                                    ->columnSpanFull();

                                                foreach ($people as $p) {
                                                    $fields[] = \Filament\Forms\Components\TextInput::make($p['key'])
                                                        ->label($p['label'])
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
                                                        ->afterStateUpdated(function (bool $state, Set $set, Get $get) use ($sizes, $recalcQty) {
                                                        foreach ($sizes as $sizeName => $maxQty) {
                                                            $set($sizeName, $state ? $maxQty : 0);
                                                        }
                                                        $recalcQty($get, $set);
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
                                                        ->afterStateUpdated(function ($state, Set $set, Get $get) use ($recalcQty) {
                                                        $recalcQty($get, $set);
                                                    });
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
                        $category = $item->production_category ?? 'produksi';
                        $details = $item->size_and_request_details ?? [];

                        $tasksData = $data['productionTasks'] ?? [];

                        // ══════════════════════════════════════════════════════════════
                        // PERSIAPAN DATA KAPASITAS ASLI
                        // ══════════════════════════════════════════════════════════════
                        $originalSizes = []; // ['S' => 20, 'M' => 30, ...]
                        if (isset($details['sizes']) && is_array($details['sizes'])) {
                            foreach ($details['sizes'] as $sz => $qty) {
                                if ((int) $qty > 0)
                                    $originalSizes[strtoupper($sz)] = (int) $qty;
                            }
                        } elseif (isset($details['varian_ukuran']) && is_array($details['varian_ukuran'])) {
                            foreach ($details['varian_ukuran'] as $v) {
                                $sz = strtoupper($v['ukuran'] ?? '');
                                if ($sz && (int) ($v['qty'] ?? 0) > 0)
                                    $originalSizes[$sz] = (int) $v['qty'];
                            }
                        } elseif ($category === 'custom' && !empty($details['detail_custom'])) {
                            // Kategori custom: qty = jumlah orang (nama)
                            foreach ($details['detail_custom'] as $index => $u) {
                                $person = strtoupper($u['nama'] ?? 'Person ' . ($index + 1));
                                $originalSizes[$person] = 1;
                            }
                        }
                        $totalOrderQty = $item->quantity ?? 0;

                        // ══════════════════════════════════════════════════════════════
                        // AGREGASI: Qty & stage dari semua baris tugas
                        // ══════════════════════════════════════════════════════════════
                        $usedQtyPerStageSize = []; // ['Jahit']['M'] = 50
                        $usedQtyPerStage = []; // ['Jahit'] = 100
                        $assignedStages = []; // ['Jahit', 'Potong']
            
                        foreach ($tasksData as $idx => $taskItem) {
                            $stage = $taskItem['stage_name'] ?? null;

                            $calculatedQty = 0;
                            $sqs = [];
                            $excludeKeys = ['id', 'stage_name', 'assigned_to', 'wage_per_pcs', 'quantity', 'description', '_fill_all', 'qty'];
                            foreach ($taskItem as $k => $v) {
                                if (!in_array($k, $excludeKeys) && is_numeric($v) && (int) $v > 0) {
                                    $calculatedQty += (int) $v;
                                    $sqs[$k] = (int) $v;
                                }
                            }
                            if ($calculatedQty === 0 && isset($taskItem['qty']) && (int) $taskItem['qty'] > 0) {
                                $calculatedQty = (int) $taskItem['qty'];
                            }
                            if ($calculatedQty === 0 && isset($taskItem['quantity']) && (int) $taskItem['quantity'] > 0) {
                                $calculatedQty = (int) $taskItem['quantity'];
                            }

                            $qty = $calculatedQty;

                            // ─── Cek #1: Setiap baris wajib punya qty > 0 ─────────────
                            if ($qty === 0) {
                                \Filament\Notifications\Notification::make()
                                    ->title('Ada Tugas Tanpa Qty!')
                                    ->body("Baris tugas <strong>{$stage}</strong> tidak memiliki qty yang diisi. Isi qty atau hapus baris tersebut.")
                                    ->danger()->send();
                                $action->halt();
                                return;
                            }

                            if ($stage) {
                                $assignedStages[] = $stage;
                                $usedQtyPerStage[$stage] = ($usedQtyPerStage[$stage] ?? 0) + $qty;

                                foreach ($sqs as $key => $val) {
                                    $upperKey = strtoupper($key);
                                    $usedQtyPerStageSize[$stage][$upperKey] = ($usedQtyPerStageSize[$stage][$upperKey] ?? 0) + (int) $val;
                                }
                            }
                        }

                        // ─── Cek #2: Qty per ukuran per tahap tidak boleh melebihi kapasitas asli ──
                        if (!empty($originalSizes)) {
                            $errors = [];
                            foreach ($usedQtyPerStageSize as $stageName => $sizeUsage) {
                                foreach ($sizeUsage as $sz => $usedQty) {
                                    $maxQty = $originalSizes[$sz] ?? null;
                                    if ($maxQty !== null && $usedQty > $maxQty) {
                                        $errors[] = "Tahap <strong>{$stageName}</strong> — Ukuran <strong>{$sz}</strong>: ditugaskan {$usedQty} pcs, maks {$maxQty} pcs.";
                                    }
                                }
                            }
                            if (!empty($errors)) {
                                \Filament\Notifications\Notification::make()
                                    ->title('❌ Qty Per Ukuran Melebihi Kapasitas!')
                                    ->body(new \Illuminate\Support\HtmlString(implode('<br>', $errors)))
                                    ->danger()->send();
                                $action->halt();
                                return;
                            }
                        }

                        // ─── Cek #3: Total qty per tahap tidak boleh melebihi total order ────────
                        $stageOverErrors = [];
                        foreach ($usedQtyPerStage as $stageName => $totalAssigned) {
                            if ($totalAssigned > $totalOrderQty) {
                                $stageOverErrors[] = "Tahap <strong>{$stageName}</strong>: total ditugaskan {$totalAssigned} pcs, maks {$totalOrderQty} pcs.";
                            }
                        }
                        if (!empty($stageOverErrors)) {
                            \Filament\Notifications\Notification::make()
                                ->title('❌ Total Qty Melebihi Total Order!')
                                ->body(new \Illuminate\Support\HtmlString(implode('<br>', $stageOverErrors)))
                                ->danger()->send();
                            $action->halt();
                            return;
                        }

                        // ─── Cek #4 (Warning): Total qty per tahap kurang dari total order ────────
                        $underAssignedWarnings = [];
                        foreach ($usedQtyPerStage as $stageName => $totalAssigned) {
                            if ($totalAssigned < $totalOrderQty) {
                                $kekurangan = $totalOrderQty - $totalAssigned;
                                $underAssignedWarnings[] = "Tahap <strong>{$stageName}</strong>: baru {$totalAssigned} dari {$totalOrderQty} pcs (kurang {$kekurangan} pcs).";
                            }
                        }
                        if (!empty($underAssignedWarnings)) {
                            \Filament\Notifications\Notification::make()
                                ->title('❌ Qty Belum Lengkap')
                                ->body(new \Illuminate\Support\HtmlString(
                                    'Total penugasan belum mencapai total order:<br>' . implode('<br>', $underAssignedWarnings)
                                ))
                                ->danger()->persistent()->send();
                            $action->halt();
                            return;
                        }

                        // ─── Cek #5 (Warning): Pastikan tahapan yang diperlukan sudah ada ─────────
                        $requiredStages = ProductionStage::query()->orderBy('order_sequence');
                        if ($category === 'produksi' || $category === 'custom') {
                            $requiredStages->where('for_produksi_custom', true);
                        } elseif ($category === 'non_produksi') {
                            $requiredStages->where('for_non_produksi', true);
                        } elseif ($category === 'jasa') {
                            $requiredStages->where('for_jasa', true);
                        }
                        $requiredStageNames = $requiredStages->pluck('name')->toArray();
                        $missingStages = array_diff($requiredStageNames, array_unique($assignedStages));
                        if (!empty($missingStages)) {
                            \Filament\Notifications\Notification::make()
                                ->title('❌ Tahapan Belum Lengkap')
                                ->body(new \Illuminate\Support\HtmlString(
                                    'Penyimpanan dibatalkan, tahapan berikut wajib di-assign:<br><strong>' . implode(', ', $missingStages) . '</strong>'
                                ))
                                ->danger()->persistent()->send();
                            $action->halt();
                            return;
                        }
                        // ══════════════════════════════════════════════════════════════
            
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
                                        $person = trim($u['nama'] ?? 'Person ' . ($index + 1));
                                        $safeKey = strtoupper(preg_replace('/[^a-zA-Z0-9_]/', '_', $person) . '_' . $index);
                                        $sizesToLookFor[] = $safeKey;
                                    }
                                }
                            } else {
                                $details = $item->size_and_request_details ?? [];
                                if (isset($details['sizes']) && is_array($details['sizes'])) {
                                    foreach ($details['sizes'] as $sz => $qty) {
                                        if ((int) $qty > 0)
                                            $sizesToLookFor[] = strtoupper($sz);
                                    }
                                } elseif (isset($details['varian_ukuran']) && is_array($details['varian_ukuran'])) {
                                    foreach ($details['varian_ukuran'] as $v) {
                                        $sz = strtoupper($v['ukuran'] ?? '');
                                        if ($sz && (int) ($v['qty'] ?? 0) > 0)
                                            $sizesToLookFor[] = $sz;
                                    }
                                }
                            }

                            // Extract these specific keys from $taskItem if they exist and are greater than 0
                            foreach ($sizesToLookFor as $key) {
                                if (isset($taskItem[$key]) && (int) $taskItem[$key] > 0) {
                                    $sizeQuantities[$key] = (int) $taskItem[$key];
                                }
                            }

                            // Hitung quantity dari sizeQuantities yang sudah diekstrak (quantity field readOnly jadi tidak reliable)
                            $quantity = array_sum($sizeQuantities);
                            // Fallback ke field qty biasa (untuk item tanpa ukuran)
                            if ($quantity === 0 && isset($taskItem['qty']) && (int) $taskItem['qty'] > 0) {
                                $quantity = (int) $taskItem['qty'];
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

                        // Emit success notification!
                        \Filament\Notifications\Notification::make()
                            ->title('Berhasil Diatur')
                            ->body('Tugas produksi berhasil diteapkan dan disimpan.')
                            ->success()
                            ->send();
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
