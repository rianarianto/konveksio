<?php

namespace App\Filament\Resources\ControlProduksis;

use App\Filament\Resources\ControlProduksis\Pages\ManageControlProduksis;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Hidden;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group as TableGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\ProductionStage;
use App\Models\User;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Illuminate\Support\HtmlString;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Filament\Resources\ControlProduksis\Pages\AturTugasProduksi;

class ControlProduksiResource extends Resource
{
    protected static ?string $model = \App\Models\OrderItem::class;

    protected static ?string $slug = 'control-produksi';

    protected static string|\UnitEnum|null $navigationGroup = 'PRODUKSI';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return in_array(auth()->user()->role, ['admin', 'designer', 'owner']);
    }

    public static function getNavigationBadge(): ?string
    {
        if (!filament()->getTenant()) {
            return null;
        }

        $count = static::getEloquentQuery()
            ->whereDoesntHave('productionTasks')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
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
            ->where('order_items.design_status', 'approved')
            ->with(['order.customer', 'productionTasks'])
            ->whereIn('order_items.id', function (\Illuminate\Database\Query\Builder $query) {
                $query->selectRaw('MIN(id)')
                    ->from('order_items')
                    ->where('design_status', 'approved')
                    ->groupBy('order_id', 'product_name');
            });
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
                        'custom' => '🧵 Produksi (Ukur Badan)',
                        'non_produksi' => '📦 Non-Produksi',
                        'jasa' => '🔧 Jasa',
                        default => '🏭 Produksi',
                    }),

                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->getStateUsing(
                        fn(OrderItem $record): int => (function() use ($record) {
                            $details = $record->size_and_request_details ?? [];
                            $query = OrderItem::where('order_id', $record->order_id)
                                ->where('product_name', $record->product_name)
                                ->where('bahan_id', $record->bahan_id)
                                ->where('design_status', 'approved');
                            
                            return $query->sum('quantity');
                        })()
                    )
                    ->numeric()
                    ->sortable(['quantity']),

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
                        return 'Antrian';
                    })
                    ->color(fn(string $state): string => match ($state) {
                        'Belum Diatur' => 'gray',
                        'Antrian' => 'warning',
                        'Diproses' => 'info',
                        'Selesai' => 'success',
                        default => 'gray',
                    })
                    ->description(function (OrderItem $record) {
                        // Cek WorkOrder status dulu
                        $groupItemIds = $record->getItemsInGroup()->pluck('id');
                        $wo = \App\Models\WorkOrder::withoutGlobalScopes()
                            ->whereIn('order_item_id', $groupItemIds)
                            ->first();

                        if ($wo) {
                            $woStatus = $wo->status;
                            if ($woStatus === \App\Models\WorkOrder::STATUS_CREATED) {
                                return '📝 Belum dimulai';
                            }
                            if ($woStatus === \App\Models\WorkOrder::STATUS_QC_PREP || $woStatus === 'QC_PERSIAPAN') {
                                return '📋 QC Persiapan - menunggu approve';
                            }
                            if ($woStatus === \App\Models\WorkOrder::STATUS_QC_REVIEW) {
                                return '⚙️ QC ' . ($wo->current_review_stage ?? '') . ' - menunggu verifikasi';
                            }
                            if ($woStatus === \App\Models\WorkOrder::STATUS_COMPLETED) {
                                return '✅ Selesai';
                            }
                        }

                        $tasks = $record->productionTasks;
                        if ($tasks->isEmpty())
                            return null;
                        $activeTask = $tasks->firstWhere('status', 'in_progress');
                        if ($activeTask) {
                            return '🔨 ' . $activeTask->stage_name . ' — ' . ($activeTask->assignedTo?->name ?? '-');
                        }
                        $pendingTask = $tasks->where('status', 'pending')->sortBy('id')->first();
                        if ($pendingTask) {
                            return '⏳ Menunggu: ' . $pendingTask->stage_name;
                        }
                        return null;
                    }),

            ])

            ->groups([
                TableGroup::make('order.order_number')
                    ->label('Pesanan')
                    ->getTitleFromRecordUsing(function (Model $record): HtmlString {
                        $order = $record->order;
                        $prefix = $order->is_express
                            ? '<span style="background:#dc2626;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;margin-right:6px;">⚡ EXPRESS</span>'
                            : '';
                        return new HtmlString($prefix . $order->order_number . ' — ' . ($order->customer->name ?? 'Tanpa Nama'));
                    })
                    ->collapsible(),

                TableGroup::make('production_category')
                    ->label('Kategori Pesanan')
                    ->getTitleFromRecordUsing(fn(OrderItem $record): string => match ($record->production_category) {
                        'custom' => '🧵 Produksi (Ukur Badan)',
                        'non_produksi' => '📦 Non-Produksi',
                        'jasa' => '🔧 Jasa',
                        default => '🏭 Produksi',
                    })
                    ->collapsible(),

            ])
            ->defaultGroup('order.order_number')
            ->modifyQueryUsing(
                fn($query) => $query
                    ->join('orders', 'order_items.order_id', '=', 'orders.id')
                    ->select('order_items.*')
                    ->orderByRaw('orders.is_express DESC')
                    ->orderBy('orders.deadline', 'asc')
            )
            ->filters([
                // Filters handled by Tabs in Manage page
            ])
            ->actions([
                ActionGroup::make([
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
                        // Load WorkOrder untuk item ini
                        $groupItemIds = $record->getItemsInGroup()->pluck('id');
                        $workOrder = \App\Models\WorkOrder::withoutGlobalScopes()
                            ->whereIn('order_item_id', $groupItemIds)
                            ->first();

                        $woStatus = $workOrder?->status ?? 'NO_WO';
                        $woStatusLabel = $workOrder?->status_label ?? 'Belum ada WO';

                        // Load tugas dan group berdasarkan urutan stage
                        $tasks = $record->productionTasks()
                            ->with(['assignedTo'])
                            ->get();

                        // Ambil semua stage yang ada beserta order_sequence-nya
                        $stageOrder = \App\Models\ProductionStage::pluck('order_sequence', 'name');
                        // QC_PERSIAPAN always first (before any production stage)
                        $stageOrder['QC_PERSIAPAN'] = -1;

                        // Sort tugas berdasarkan order_sequence tahap, kemudian by id
                        $sortedTasks = $tasks->sortBy(function ($task) use ($stageOrder) {
                            return [$stageOrder[$task->stage_name] ?? 999, $task->id];
                        });

                        // Tentukan stage yang sedang "aktif" berdasarkan WO status:
                        // - Kalau WO di QC_PREP/QC_REVIEW → semua task terkunci
                        // - Kalau WO di CREATED → semua task terkunci (perlu advance dulu)
                        // - Kalau WO di nama tahap → hanya task di tahap itu yang unlock
                        // - Kalau WO di COMPLETED → semua task done
                        $groupedByStage = $sortedTasks->groupBy('stage_name');
                        $stagesInOrder = $groupedByStage->keys()->sortBy(fn($s) => $stageOrder[$s] ?? 999);

                        // Tentukan stage mana yang "current" berdasarkan WO
                        $currentWoStage = null;
                        // QC_PERSIAPAN is also a QC stage (WO status might be 'QC_PERSIAPAN' from stage_sequence)
                        $isWoInQcStage = in_array($woStatus, [\App\Models\WorkOrder::STATUS_QC_PREP, \App\Models\WorkOrder::STATUS_QC_REVIEW, 'QC_PERSIAPAN']);
                        $isWoCompleted = $woStatus === \App\Models\WorkOrder::STATUS_COMPLETED;
                        $isWoCreated = $woStatus === \App\Models\WorkOrder::STATUS_CREATED;

                        if (!$isWoInQcStage && !$isWoCompleted && !$isWoCreated && $woStatus !== 'NO_WO') {
                            $currentWoStage = $woStatus; // nama tahap (Potong, Jahit, dll)
                        }

                        // Unlock logic: hanya stage yang <= current WO stage yang unlock
                        // Dan task di stage itu bisa dikerjakan kalau task sebelumnya done
                        $unlockedStages = [];
                        $currentStageIdx = $currentWoStage ? array_search($currentWoStage, $stagesInOrder->values()->toArray()) : -1;

                        foreach ($stagesInOrder as $i => $stageName) {
                            // Stage unlock kalau: stage ini <= current WO stage DAN semua task sebelumnya done
                            if ($currentStageIdx >= 0 && $i <= $currentStageIdx) {
                                if ($i === 0) {
                                    $unlockedStages[] = $stageName;
                                } else {
                                    $prevStage = $stagesInOrder->values()[$i - 1];
                                    $prevDone = $groupedByStage[$prevStage]->every(fn($t) => $t->status === 'done');
                                    if ($prevDone) {
                                        $unlockedStages[] = $stageName;
                                    } else {
                                        break;
                                    }
                                }
                            } else {
                                break;
                            }
                        }

                        // Render HTML tabel tugas
                        $rows = '';
                        $prevStageName = null;
                        foreach ($sortedTasks as $task) {
                            $isUnlocked = in_array($task->stage_name, $unlockedStages);
                            $workerName = $task->assignedTo?->name ?? 'Tidak Diketahui';
                            $stageName = htmlspecialchars(str_replace('_', ' ', $task->stage_name));
                            // QC_PERSIAPAN doesn't track quantity - it's just a confirmation task
                            $qty = $task->stage_name === 'QC_PERSIAPAN' ? '-' : ($task->quantity . ' pcs');
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

                        // ─── Banner WorkOrder Status ──────────────────────────────
                        $woBannerHtml = '';
                        $itemId = $record->id;

                        if ($woStatus === 'NO_WO') {
                            $woBannerHtml = '<div style="margin-bottom:16px;padding:12px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;display:flex;align-items:center;gap:10px;">'
                                . '<span style="font-size:18px;">⚠️</span>'
                                . '<span style="font-size:13px;color:#991b1b;font-weight:500;">Work Order belum dibuat</span>'
                                . '</div>';
                        } elseif ($isWoCreated) {
                            $advanceUrl = route('filament.admin.resources.control-produksis.wo-action', ['action' => 'advance', 'item' => $itemId]);
                            $woBannerHtml = '<div style="margin-bottom:16px;padding:12px 16px;background:#f0f9ff;border:1px solid #bae6fd;border-radius:8px;display:flex;align-items:center;gap:10px;">'
                                . '<span style="font-size:18px;">📝</span>'
                                . '<span style="font-size:13px;color:#0c4a6e;font-weight:500;flex:1;">Status: Belum dimulai</span>'
                                . '<a href="' . $advanceUrl . '" style="background:#0284c7;color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">▶ Mulai Produksi</a>'
                                . '</div>';
                        } elseif ($woStatus === \App\Models\WorkOrder::STATUS_QC_PREP || $woStatus === 'QC_PERSIAPAN') {
                            $approveUrl = route('filament.admin.resources.control-produksis.wo-action', ['action' => 'approve_qc', 'item' => $itemId]);
                            $woBannerHtml = '<div style="margin-bottom:16px;padding:12px 16px;background:#faf5ff;border:1px solid #c4b5fd;border-radius:8px;display:flex;align-items:center;gap:10px;">'
                                . '<span style="font-size:18px;">📋</span>'
                                . '<span style="font-size:13px;color:#5b21b6;font-weight:500;flex:1;">QC Persiapan Bahan & Peralatan</span>'
                                . '<a href="' . $approveUrl . '" style="background:#7c3aed;color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">✅ Approve</a>'
                                . '</div>';
                        } elseif ($woStatus === \App\Models\WorkOrder::STATUS_QC_REVIEW) {
                            $reviewStage = $workOrder->current_review_stage ?? '-';
                            $approveUrl = route('filament.admin.resources.control-produksis.wo-action', ['action' => 'approve_qc', 'item' => $itemId]);
                            $woBannerHtml = '<div style="margin-bottom:16px;padding:12px 16px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;display:flex;align-items:center;gap:10px;">'
                                . '<span style="font-size:18px;">⚙️</span>'
                                . '<span style="font-size:13px;color:#9a3412;font-weight:500;flex:1;">QC ' . htmlspecialchars($reviewStage) . ' - menunggu verifikasi</span>'
                                . '<a href="' . $approveUrl . '" style="background:#ea580c;color:#fff;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:600;text-decoration:none;">✅ Approve</a>'
                                . '</div>';
                        } elseif ($isWoCompleted) {
                            $woBannerHtml = '<div style="margin-bottom:16px;padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;display:flex;align-items:center;gap:10px;">'
                                . '<span style="font-size:18px;">✅</span>'
                                . '<span style="font-size:13px;color:#166534;font-weight:500;">Produksi Selesai</span>'
                                . '</div>';
                        } else {
                            // WO di tahap produksi (nama tahap)
                            $woBannerHtml = '<div style="margin-bottom:16px;padding:12px 16px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;display:flex;align-items:center;gap:10px;">'
                                . '<span style="font-size:18px;">🔨</span>'
                                . '<span style="font-size:13px;color:#1e40af;font-weight:500;">Sedang di tahap: <strong>' . htmlspecialchars($woStatus) . '</strong></span>'
                                . '</div>';
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

                        $html = $woBannerHtml . $designHtml . '
                            <div style="border-radius:10px;overflow-x:auto;border:1px solid #e5e7eb;-webkit-overflow-scrolling:touch;">
                                <table style="width:100%;min-width:600px;border-collapse:collapse;">
                                    <thead>
                                        <tr style="background:#f9fafb;">
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;white-space:nowrap;">TAHAP</th>
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;white-space:nowrap;">KARYAWAN</th>
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;white-space:nowrap;">QTY</th>
                                            <th style="padding:10px 14px;text-align:left;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;white-space:nowrap;">STATUS</th>
                                            <th style="padding:10px 14px;text-align:right;font-size:12px;color:#6b7280;font-weight:600;border-bottom:2px solid #e5e7eb;white-space:nowrap;">AKSI</th>
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

                // QC Review Reject Action - only visible when WO is in QC_REVIEW
                Action::make('qc_revisi')
                    ->label('🔧 QC Revisi')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->visible(function (OrderItem $record) {
                        $groupItemIds = $record->getItemsInGroup()->pluck('id');
                        $wo = \App\Models\WorkOrder::withoutGlobalScopes()
                            ->whereIn('order_item_id', $groupItemIds)
                            ->first();
                        return $wo && $wo->status === \App\Models\WorkOrder::STATUS_QC_REVIEW;
                    })
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reject_reason')
                            ->label('Alasan Revisi')
                            ->placeholder('Tuliskan alasan revisi...')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (array $data, OrderItem $record) {
                        $groupItemIds = $record->getItemsInGroup()->pluck('id');
                        $wo = \App\Models\WorkOrder::withoutGlobalScopes()
                            ->whereIn('order_item_id', $groupItemIds)
                            ->first();

                        if (!$wo) {
                            Notification::make()->title('Work Order tidak ditemukan')->danger()->send();
                            return;
                        }

                        app(\App\Services\WorkOrderService::class)->rejectQC($wo->id, $data['reject_reason']);

                        // Mark task back to pending for revision
                        $prevStage = $wo->current_review_stage;
                        if ($prevStage) {
                            $task = \App\Models\ProductionTask::withoutGlobalScopes()
                                ->where('order_item_id', $wo->order_item_id)
                                ->where('stage_name', $prevStage)
                                ->first();
                            if ($task) {
                                $task->update([
                                    'status' => 'pending',
                                    'completed_at' => null,
                                    'is_revision' => true,
                                ]);
                            }
                        }

                        // Sync order status
                        $order = $record->order;
                        if ($order) {
                            $allGroupIds = $order->orderItems()->pluck('id');
                            $wos = \App\Models\WorkOrder::withoutGlobalScopes()
                                ->whereIn('order_item_id', $allGroupIds)
                                ->get();
                            $anyInProgress = $wos->some(fn($w) => !$w->isCompleted() && $w->status !== \App\Models\WorkOrder::STATUS_CREATED);
                            $order->update(['status' => $anyInProgress ? 'diproses' : 'antrian']);
                        }

                        Notification::make()
                            ->title('Revisi Dikirim')
                            ->body('Pekerja akan memperbaiki pekerjaan di tahap ' . str_replace('_', ' ', $prevStage ?? '-'))
                            ->success()
                            ->send();
                    }),

                Action::make('cetak_spk')
                    ->label('Cetak SPK')
                    ->icon('heroicon-o-printer')
                    ->color('success')
                    ->visible(fn (\App\Models\OrderItem $record) => $record->productionTasks()->exists())
                    ->action(function (OrderItem $record) {
                        $item = $record;
                        
                        // Aggregate data for PDF
                        $totalQuantity = OrderItem::where('order_id', $item->order_id)
                            ->where('product_name', $item->product_name)
                            ->where('design_status', 'approved')
                            ->sum('quantity');

                        // Aggregate sizes
                        $allGroupItems = OrderItem::where('order_id', $item->order_id)
                            ->where('product_name', $item->product_name)
                            ->where('design_status', 'approved')
                            ->get();

                        $sizes = [];
                        $specGroups = [];

                        foreach ($allGroupItems as $gi) {
                            $d = $gi->size_and_request_details ?? [];
                            
                            // Essential specs for grouping
                            $isProduksi = in_array($gi->production_category ?? 'produksi', ['produksi', 'custom']);
                            
                            if (!$isProduksi) {
                                $gen = 'UMUM';
                                $slv = '-';
                                $pck = '-';
                                $btn = '-';
                                $tun = '-';
                                $clr = '-';
                            } else {
                                $gen = $d['gender'] ?? 'L';
                                $slv = strtoupper($d['sleeve_model'] ?? 'PENDEK');
                                $pck = strtoupper(str_replace('_', ' ', $d['pocket_model'] ?? 'TANPA SAKU'));
                                $btn = strtoupper($d['button_model'] ?? 'BIASA');
                                $tun = !empty($d['is_tunic']) ? 'TUNIK' : 'STANDAR';
                                $clr = strtoupper($d['collar_model'] ?? 'BIASA');
                            }
                            
                            $stk = !empty($d['use_stock']) ? 'YES' : 'NO';
                            
                            // Detailed Sablon/Bordir info
                            $sbList = [];
                            if (!empty($d['sablon_bordir'])) {
                                foreach ($d['sablon_bordir'] as $sb) {
                                    $ket = !empty($sb['keterangan']) ? " - " . strtoupper($sb['keterangan']) : "";
                                    $sbList[] = strtoupper($sb['jenis'] ?? '') . " (" . strtoupper($sb['lokasi'] ?? '') . $ket . ")";
                                }
                            } elseif (!empty($d['sablon_jenis'])) {
                                $ket = !empty($d['sablon_keterangan']) ? " - " . strtoupper($d['sablon_keterangan']) : "";
                                $sbList[] = strtoupper($d['sablon_jenis']) . " (" . strtoupper($d['sablon_lokasi'] ?? '-') . $ket . ")";
                            }
                            sort($sbList);
                            $sbStr = implode(' | ', $sbList);
                            
                            // Additional requests info
                            $reqList = [];
                            if (!empty($d['request_tambahan']) && is_array($d['request_tambahan'])) {
                                foreach ($d['request_tambahan'] as $rt) {
                                    $reqList[] = strtoupper($rt['jenis'] ?? '') . ": " . ($rt['keterangan'] ?? '');
                                }
                            }
                            sort($reqList);
                            $reqStr = implode(' | ', $reqList);

                            // Trim all components to prevent whitespace issues
                            $gen = trim($gen);
                            $slv = trim($slv);
                            $pck = trim($pck);
                            $btn = trim($btn);
                            $tun = trim($tun);
                            $sbStr = trim($sbStr);
                            $reqStr = trim($reqStr);

                            // Grouping key: Ignore $stk (stock status) so that "Ambil Stok" and "Produksi Baru" 
                            // with same specs are merged in the production table.
                            $groupKey = "{$gen}|{$slv}|{$pck}|{$btn}|{$tun}|{$clr}|{$sbStr}|{$reqStr}";
                            
                            if (!isset($specGroups[$groupKey])) {
                                $specGroups[$groupKey] = [
                                    'gender' => $gen === 'UMUM' ? 'UMUM' : ($gen === 'L' ? 'PRIA' : 'WANITA'),
                                    'is_produksi' => $isProduksi,
                                    'sleeve' => $slv,
                                    'pocket' => $pck,
                                    'button' => $btn,
                                    'tunic' => $tun,
                                    'collar' => $clr,
                                    'use_stock' => ($stk === 'YES'),
                                    'sablon_bordir' => $sbList,
                                    'requests' => $reqList,
                                    'total_qty' => 0,
                                    'sizes' => [],
                                    'recipients' => ['standard' => [], 'custom' => []],
                                ];
                            }
                            
                            $specGroups[$groupKey]['total_qty'] += $gi->quantity;
                            $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                            $specGroups[$groupKey]['sizes'][$sz] = ($specGroups[$groupKey]['sizes'][$sz] ?? 0) + $gi->quantity;

                            // Collect recipient names & custom measurements for Section 3 (Grouped by this spec)
                            if (!empty($d['detail_custom'])) {
                                foreach ($d['detail_custom'] as $u) {
                                    $specGroups[$groupKey]['recipients']['custom'][] = [
                                        'nama' => $u['nama'] ?? '-',
                                        'size' => $u['ukuran'] ?? 'Custom',
                                        'desc' => !empty($u['LD']) ? "LD:{$u['LD']} PB:{$u['PB']} PL:{$u['PL']} LB:{$u['LB']} LP:{$u['LP']} LPh:{$u['LPh']}" : ""
                                    ];
                                }
                            } elseif ($sz === 'CUSTOM') {
                                $m = [];
                                foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                                    if (!empty($d[$mk])) $m[] = "$mk:{$d[$mk]}";
                                }
                                $specGroups[$groupKey]['recipients']['custom'][] = [
                                    'nama' => $gi->recipient_name ?? '-',
                                    'size' => 'Custom',
                                    'desc' => implode(' ', $m)
                                ];
                            } elseif (!empty($gi->recipient_name)) {
                                $specGroups[$groupKey]['recipients']['standard'][$sz][] = $gi->recipient_name;
                            }
                        }

                        $pdf = Pdf::loadView('pdf.spk-produksi', [
                            'record' => $record->load(['order.customer', 'productionTasks.assignedTo', 'bahan.material']),
                            'totalQuantity' => $totalQuantity,
                            'sizes' => $sizes,
                            'specGroups' => $specGroups,
                            'allGroupItems' => $allGroupItems,
                        ]);

                        $filename = 'SPK-' . $record->order->order_number . '-' . \Illuminate\Support\Str::slug($record->product_name) . '.pdf';
                        
                        return response()->streamDownload(function () use ($pdf) {
                            echo $pdf->stream();
                        }, $filename);
                    }),



                Action::make('kirim_tugas')
                    ->label('Kirim Tugas')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn(OrderItem $record) => $record->productionTasks()->where('status', 'pending')->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Kirim Notifikasi ke Semua Pekerja?')
                    ->modalDescription('Sistem akan mengirim pesan WhatsApp ke seluruh pekerja yang ditugaskan pada item ini beserta link portal tugas mereka.')
                    ->modalSubmitActionLabel('Ya, Kirim Sekarang')
                    ->action(function (OrderItem $record) {
                        \App\Helpers\NotificationHelper::notifyAllWorkers($record);

                        \Filament\Notifications\Notification::make()
                            ->title('Notifikasi terkirim!')
                            ->body('Pesan WhatsApp sedang dikirim ke seluruh pekerja.')
                            ->success()
                            ->send();
                    }),

                Action::make('hapus_tugas')
                    ->label('Hapus Semua Tugas')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn(OrderItem $record) => $record->productionTasks()->exists())
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Semua Tugas Produksi?')
                    ->modalDescription('Tindakan ini akan menghapus seluruh penugasan kerja untuk item ini secara permanen. Status pengerjaan item akan kembali ke Belum Diatur.')
                    ->modalSubmitActionLabel('Ya, Hapus')
                    ->action(function (OrderItem $record) {
                        // Hapus task produksi
                        $record->productionTasks()->delete();
                        
                        // Hapus WorkOrder terkait agar statusnya bersih
                        $groupItemIds = $record->getItemsInGroup()->pluck('id');
                        \App\Models\WorkOrder::withoutGlobalScopes()
                            ->whereIn('order_item_id', $groupItemIds)
                            ->delete();
                        
                        $record->refresh();
                        if ($order = $record->order) {
                            $totalTasks = $order->orderItems()->withCount('productionTasks')->get()->sum('production_tasks_count');
                            if ($totalTasks === 0 && $order->status === 'antrian') {
                                $order->update(['status' => 'diterima']);
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Penugasan berhasil dihapus')
                            ->body('Seluruh tugas produksi untuk item ini telah dihapus dan status diatur ulang.')
                            ->success()
                            ->send();
                    }),


                Action::make('atur_tugas')
                    ->hidden(fn(OrderItem $record) => $record->productionTasks()->exists() && $record->productionTasks()->where('status', '!=', 'done')->count() === 0)
                    ->label(fn(OrderItem $record) => $record->productionTasks()->exists() ? 'Edit Tugas' : 'Atur Tugas')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->color('primary')
                    ->url(fn (OrderItem $record): string => AturTugasProduksi::getUrl(['record' => $record])),
                ])
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->tooltip('Aksi')
                    ->color('gray')
                    ->button()
                    ->label('Aksi'),
            ])
            ->actionsPosition(\Filament\Tables\Enums\RecordActionsPosition::AfterCells)
            ->bulkActions([
                //
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageControlProduksis::route('/'),
            'atur-tugas' => AturTugasProduksi::route('/{record}/atur-tugas'),
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
