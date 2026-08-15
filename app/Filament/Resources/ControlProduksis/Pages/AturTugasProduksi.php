<?php

namespace App\Filament\Resources\ControlProduksis\Pages;

use App\Filament\Resources\ControlProduksis\ControlProduksiResource;
use App\Models\OrderItem;
use App\Models\ProductionStage;
use App\Models\ProductionTask;
use App\Models\User;
use App\Models\Worker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\Toggle;

class AturTugasProduksi extends Page
{
    protected static string $resource = ControlProduksiResource::class;

    protected string $view = 'filament.resources.control-produksi.pages.atur-tugas-produksi';

    public ?OrderItem $record = null;

    public ?array $data = [];

    public function mount(OrderItem $record): void
    {
        $this->record = $record;
        
        $item = $this->record->load('productionTasks');
        
        // Aggregate max quantities for the whole group to determine _fill_all state
        $allGroupItems = $item->getItemsInGroup();
            
        $maxSizes = [];
        foreach ($allGroupItems as $gi) {
            $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
            $maxSizes[$sz] = ($maxSizes[$sz] ?? 0) + $gi->quantity;
        }

        if ($item->production_category === 'custom') {
            $details = $item->size_and_request_details ?? [];
            $count = count($details['detail_custom'] ?? []);
            if ($count > 0) {
                $maxSizes['CUSTOM'] = ($maxSizes['CUSTOM'] ?? 0) + $count;
            }
        }

        // Move 'CUSTOM' to the end of the array if it exists
        if (array_key_exists('CUSTOM', $maxSizes)) {
            $customVal = $maxSizes['CUSTOM'];
            unset($maxSizes['CUSTOM']);
            $maxSizes['CUSTOM'] = $customVal;
        }

        $tasksForRepeater = [];
        $groupedTasks = $item->productionTasks->groupBy('stage_name');

        // If this item has active retur revision tasks & active OrderReturn, show active retur target stages!
        $hasActiveReturn = \App\Models\OrderReturn::where('order_item_id', $item->id)
            ->whereIn('status', ['pending', 'diproses'])
            ->exists();

        $activeReturStages = [];
        if ($hasActiveReturn) {
            $activeReturStages = $item->productionTasks()
                ->where('is_revision', true)
                ->where('status', '!=', 'done')
                ->where('stage_name', 'not like', 'QC_%')
                ->pluck('stage_name')
                ->unique()
                ->toArray();
        } else {
            // If return was deleted or no active return, clean up lingering is_revision flags
            $item->productionTasks()
                ->where('is_revision', true)
                ->update(['is_revision' => false]);
        }



        foreach ($groupedTasks as $stageName => $tasks) {
            // Skip QC stages - dikontrol oleh toggle di atas, bukan row repeater
            if (str_starts_with($stageName, 'QC_')) {
                continue;
            }
            
            $firstTask = $tasks->first();
            
            // Clean description at stage level
            $rawDesc = $firstTask->description ?? '';
            $cleanDescLines = array_filter(
                explode("\n", $rawDesc),
                fn($line) => !str_starts_with(trim($line), 'Pembagian Ukuran:')
                          && !str_starts_with(trim($line), 'Baju Custom:')
            );
            $cleanDesc = trim(implode("\n", $cleanDescLines));

            $workerRows = [];
            foreach ($tasks as $task) {
                $wagePerPcs = $task->quantity > 0 ? (int) ($task->wage_amount / $task->quantity) : 0;

                $workerRow = [
                    'id' => $task->id,
                    'worker_id' => $task->assigned_to,
                    'quantity' => $task->quantity,
                    'wage_per_pcs' => $wagePerPcs,
                    'wage_custom_per_pcs' => $task->size_quantities['_wage_custom'] ?? $wagePerPcs,
                ];

                if (is_array($task->size_quantities)) {
                    foreach ($task->size_quantities as $sz => $qty) {
                        $upperSz = trim(str_replace(['SIZE ', 'SIZE'], '', strtoupper($sz)));
                        if (str_starts_with($sz, '_')) {
                            if ($sz === '_custom_recipients') {
                                $workerRow['_custom_recipients'] = $qty;
                            }
                            continue;
                        }
                        if ((str_starts_with($upperSz, 'PERSON_') || !isset($maxSizes[$upperSz])) && isset($maxSizes['CUSTOM'])) {
                            $workerRow['CUSTOM'] = ($workerRow['CUSTOM'] ?? 0) + $qty;
                        } else {
                            $workerRow[$upperSz] = $qty ?: null;
                        }
                    }
                }

                if (isset($workerRow['CUSTOM']) && $workerRow['CUSTOM'] === 0) {
                    $workerRow['CUSTOM'] = null;
                }

                $isAllFilled = !empty($maxSizes);
                foreach ($maxSizes as $key => $max) {
                    if (($workerRow[$key] ?? 0) < $max) {
                        $isAllFilled = false;
                        break;
                    }
                }
                $workerRow['_fill_all'] = $isAllFilled;

                $workerRows[(string) Str::uuid()] = $workerRow;
            }

            $tasksForRepeater[(string) Str::uuid()] = [
                'stage_name' => $stageName,
                'wajib_qc' => $firstTask->wajib_qc ?? true,
                'description' => $cleanDesc,
                'workers' => $workerRows,
            ];
        }

        // Load has_qc_prep, qc_worker_id dari WorkOrder yang sudah ada (jika ada)
        $groupItemIds = $item->getItemsInGroup()->pluck('id');
        $existingWo = \App\Models\WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->first();
        $hasQcPrep = $existingWo?->has_qc_prep ?? true;
        $qcWorkerId = $existingWo?->qc_worker_id ?? null;

        $this->form->fill([
            'productionTasks' => $tasksForRepeater,
            'has_qc_prep' => $hasQcPrep,
            'qc_worker_id' => $qcWorkerId,
        ]);
    }

    public function getTitle(): string
    {
        return "Atur Tugas: " . ($this->record->product_name ?? 'Produk');
    }

    public function form(Schema $schema): Schema
    {
        $item = $this->record;
        
        return $schema
            ->schema([
                \Filament\Forms\Components\Placeholder::make('_global_styles')
                    ->hiddenLabel()
                    ->content(new \Illuminate\Support\HtmlString('
                        <style>
                            .fi-fo-repeater-item-header {
                                position: sticky !important;
                                top: 0 !important;
                                z-index: 10 !important;
                                background-color: white !important;
                                box-shadow: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px -1px rgba(0,0,0,0.1) !important;
                            }
                            .dark .fi-fo-repeater-item-header {
                                background-color: #1f2937 !important;
                            }
                        </style>
                    ')),

                Grid::make(12)
                    ->schema([
                        // LEFT COLUMN: TASK ASSIGNMENT
                        Group::make([
                            Section::make('Daftar Tugas Produksi')
                                ->description('Tentukan tahapan dan karyawan yang bertugas')
                                ->icon('heroicon-m-clipboard-document-list')
                                ->schema([
                                    // Baris 1: Template Alur + Petugas QC + QC Persiapan
                                    Grid::make(3)
                                        ->extraAttributes(['style' => 'align-items: flex-end;'])
                                        ->schema([
                                            Select::make('_preset_template')
                                                ->label('Template Alur Pekerjaan')
                                                ->placeholder('Pilih Preset')
                                                ->options([
                                                    'full' => 'Produksi Penuh',
                                                    'simple' => 'Jasa / Non-Produksi',
                                                ])
                                                ->searchable()
                                                ->live()
                                                ->afterStateUpdated(function ($state, Set $set) {
                                                    if (!$state) return;
                                                    
                                                    $presets = [
                                                        'full' => [
                                                            ['stage_name' => 'Potong', 'wajib_qc' => true],
                                                            ['stage_name' => 'Jahit', 'wajib_qc' => true],
                                                            ['stage_name' => 'Kancing', 'wajib_qc' => false],
                                                            ['stage_name' => 'Bordir/Sablon', 'wajib_qc' => false],
                                                            ['stage_name' => 'Finishing', 'wajib_qc' => true],
                                                        ],
                                                        'simple' => [
                                                            ['stage_name' => 'Bordir/Sablon', 'wajib_qc' => false],
                                                            ['stage_name' => 'Finishing', 'wajib_qc' => true],
                                                        ]
                                                    ];
                                                    
                                                    $selectedPreset = $presets[$state] ?? [];
                                                    
                                                    $newTasks = [];
                                                    foreach ($selectedPreset as $p) {
                                                        $stageModel = \App\Models\ProductionStage::where('name', 'like', '%' . $p['stage_name'] . '%')->first();
                                                        $baseWage = $stageModel?->base_wage ?? 0;
                                                        
                                                        $newTasks[] = [
                                                            'stage_name' => $stageModel?->name ?? $p['stage_name'],
                                                            'wajib_qc' => $p['wajib_qc'],
                                                            'workers' => [],
                                                        ];
                                                    }
                                                    
                                                    $set('productionTasks', $newTasks);
                                                    $set('_preset_template', null);
                                                }),
                                            Select::make('qc_worker_id')
                                                ->label('Petugas QC')
                                                ->placeholder('Pilih Petugas QC')
                                                ->options(\App\Models\Worker::pluck('name', 'id'))
                                                ->searchable()
                                                ->required(),
                                            Toggle::make('has_qc_prep')
                                                ->label('QC Persiapan & QC Akhir')
                                                ->default(true)
                                                ->disabled()
                                                ->dehydrated() // Agar value true tetap dikirim ke database meski disabled di UI
                                                ->inline(false),
                                        ]),

                                    Repeater::make('productionTasks')
                                        ->label(false)
                                        ->collapsible()
                                        ->collapsed()
                                        ->itemLabel(function (array $state) {
                                            $stage = $state['stage_name'] ?? null;
                                            if (!$stage) return null;
                                            $colors = \App\Models\ProductionStage::getThemeColor($stage);
                                            $stageId = str($stage)->slug();
                                            
                                            // Dynamic summary details
                                            $workersData = $state['workers'] ?? [];
                                            $totalQty = 0;
                                            $workerNames = [];
                                            $totalWage = 0;

                                            foreach ($workersData as $wData) {
                                                $qty = $wData['quantity'] ?? 0;
                                                $totalQty += $qty;

                                                $workerId = $wData['worker_id'] ?? null;
                                                if ($workerId) {
                                                    $name = \App\Models\Worker::find($workerId)?->name;
                                                    if ($name) $workerNames[] = $name;
                                                }

                                                $wagePerPcs = (int) ($wData['wage_per_pcs'] ?? 0);
                                                $wageCustomPerPcs = (int) ($wData['wage_custom_per_pcs'] ?? $wagePerPcs);

                                                $customQty = 0;
                                                if (isset($wData['CUSTOM'])) {
                                                    $customQty = (int) ($wData['CUSTOM'] ?? 0);
                                                }
                                                
                                                $standardQty = $qty - $customQty;
                                                $totalWage += ($standardQty * $wagePerPcs) + ($customQty * $wageCustomPerPcs);
                                            }

                                            $summaryParts = [];
                                            if ($totalQty > 0) {
                                                $summaryParts[] = $totalQty . ' Pcs';
                                            }
                                            if (!empty($workerNames)) {
                                                $summaryParts[] = '👤 ' . implode(' & ', array_unique($workerNames));
                                            }
                                            if ($totalWage > 0) {
                                                $summaryParts[] = '💰 Rp ' . number_format($totalWage, 0, ',', '.');
                                            }
                                            
                                            $qcLabel = '';
                                            if (!($state['wajib_qc'] ?? true)) {
                                                $qcLabel = '<span style="font-size:9px; padding:2px 8px; background:#fee2e2; color:#ef4444; border-radius:4px; font-weight:800; text-transform:uppercase; margin-left:12px; flex-shrink:0;">QC SKIP</span>';
                                            }

                                            $summaryText = implode(' &nbsp;|&nbsp; ', $summaryParts);

                                            return new \Illuminate\Support\HtmlString('
                                                <style>
                                                    .fi-fo-repeater-item-header:has(.stage-label-'.$stageId.') {
                                                        background-color: '.$colors['bg'].' !important;
                                                        border-bottom: 2px solid '.$colors['border'].' !important;
                                                        border-top-left-radius: 12px !important;
                                                        border-top-right-radius: 12px !important;
                                                    }
                                                </style>
                                                <div style="display:flex; justify-content:space-between; width:100%; align-items:center; gap:12px;">
                                                     <span class="stage-label-'.$stageId.'"
                                                           style="font-weight:900; color:'.$colors['text'].'; text-transform:uppercase; letter-spacing:0.07em; white-space:nowrap; min-width:80px;">
                                                         ' . $stage . '
                                                     </span>
                                                     <span style="display:flex; align-items:center; gap:4px; font-size:11px; font-weight:700; color:#4b5563; margin-right:24px; flex-wrap:nowrap;">
                                                         ' . $summaryText . $qcLabel . '
                                                     </span>
                                                </div>
                                            ');
                                        })
                                        ->schema([
                                            Grid::make(2)
                                                ->schema([
                                                    Select::make('stage_name')
                                                        ->label('Tahap Pekerjaan')
                                                        ->placeholder('-- Pilih Tahap --')
                                                        ->options(function () use ($item) {
                                                            $category = $item->production_category ?? 'produksi';
                                                            $query = ProductionStage::query()->orderBy('order_sequence');
                                                            if ($category === 'produksi' || $category === 'custom') {
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
                                                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                                            if ($state) {
                                                                $stage = ProductionStage::where('name', $state)->first();
                                                                if ($stage) {
                                                                    $workers = $get('workers') ?? [];
                                                                    foreach (array_keys($workers) as $key) {
                                                                        $set("workers.{$key}.wage_per_pcs", $stage->base_wage);
                                                                        if (str_contains(strtolower($state), 'potong') || str_contains(strtolower($state), 'jahit')) {
                                                                            $set("workers.{$key}.wage_custom_per_pcs", $stage->base_wage);
                                                                        }
                                                                    }
                                                                }
                                                            }
                                                        }),
                                                    Toggle::make('wajib_qc')
                                                        ->label('Wajib di QC')
                                                        ->inline(false)
                                                        ->live()
                                                        ->default(true),
                                                ]),

                                            Textarea::make('description')
                                                ->label('Catatan Instruksi')
                                                ->rows(2)
                                                ->columnSpanFull()
                                                ->placeholder('Catatan instruksi ini berlaku untuk seluruh pekerja di tahap ini...'),

                                            Repeater::make('workers')              ->label('Daftar Pekerja & Kuantitas')
                                                ->collapsible()
                                                ->extraAttributes([
                                                    'data-auto-collapse-new' => true,
                                                ])
                                                ->itemLabel(function (array $state) {
                                                    $workerId = $state['worker_id'] ?? null;
                                                    $qty = $state['quantity'] ?? 0;
                                                    $name = $workerId ? \App\Models\Worker::find($workerId)?->name : null;
                                                    return ($name ?? 'Pilih Karyawan') . ' (' . $qty . ' Pcs)';
                                                })
                                                ->schema([
                                                    Hidden::make('id'),
                                                    Select::make('worker_id')
                                                        ->label('Karyawan')
                                                        ->options(function() {
                                                            return Worker::where('is_active', true)
                                                                ->get()
                                                                ->mapWithKeys(function ($worker) {
                                                                    $queue = $worker->active_queue_count;
                                                                    $label = $worker->name . ($queue > 0 ? " (Antrian: {$queue} pcs)" : " (Kosong)");
                                                                    return [$worker->id => $label];
                                                                });
                                                        })
                                                        ->live()
                                                        ->searchable()
                                                        ->required(),

                                                    Fieldset::make('Distribusi Qty Per Ukuran')
                                                        ->schema(function (Get $get, Set $set) use ($item) {
                                                            $allGroupItems = $item->getItemsInGroup();

                                                            $standardSizes = [];
                                                            $requestPerSize = [];
                                                            foreach ($allGroupItems as $gi) {
                                                                $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                                                                $standardSizes[$sz] = ($standardSizes[$sz] ?? 0) + $gi->quantity;
                                                                $dtl = $gi->size_and_request_details ?? [];
                                                                if (!empty($dtl['request_tambahan'])) {
                                                                    foreach ($dtl['request_tambahan'] as $rt) {
                                                                        $reqText = ($rt['jenis'] ?? '') . ': ' . ($rt['keterangan'] ?? '');
                                                                        $requestPerSize[$sz][] = $reqText;
                                                                    }
                                                                }
                                                            }

                                                            if ($item->production_category === 'custom') {
                                                                $details = $item->size_and_request_details ?? [];
                                                                $count = count($details['detail_custom'] ?? []);
                                                                if ($count > 0) $standardSizes['CUSTOM'] = ($standardSizes['CUSTOM'] ?? 0) + $count;
                                                            }

                                                            // Move 'CUSTOM' to the end of the array if it exists
                                                            if (array_key_exists('CUSTOM', $standardSizes)) {
                                                                $customVal = $standardSizes['CUSTOM'];
                                                                unset($standardSizes['CUSTOM']);
                                                                $standardSizes['CUSTOM'] = $customVal;
                                                            }

                                                            $recalcQty = function (Get $get, Set $set) use ($standardSizes) {
                                                                $total = 0;
                                                                foreach (array_keys($standardSizes) as $sz) {
                                                                    $val = (int) ($get($sz) ?? 0);
                                                                    if ($val > 0) {
                                                                        $total += $val;
                                                                    }
                                                                }
                                                                if ($total === 0) {
                                                                    $qtyVal = (int) ($get('qty') ?? 0);
                                                                    $total = $qtyVal > 0 ? $qtyVal : 0;
                                                                }
                                                                $set('quantity', $total);
                                                            };

                                                            $fields = [];

                                                            if ($item->production_category === 'custom') {
                                                                $fields[] = Select::make('_custom_recipients')
                                                                    ->label('Pilih Orang (Custom)')
                                                                    ->multiple()
                                                                    ->options(function () use ($item) {
                                                                        $details = $item->size_and_request_details ?? [];
                                                                        $options = [];
                                                                        foreach ($details['detail_custom'] ?? [] as $u) {
                                                                            $name = $u['nama'] ?? 'Tanpa Nama';
                                                                            $specs = [];
                                                                            foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                                                                                if (!empty($u[$mk])) {
                                                                                    $specs[] = "$mk:{$u[$mk]}";
                                                                                }
                                                                            }
                                                                            $label = $name . (!empty($specs) ? ' (' . implode(' ', $specs) . ')' : '');
                                                                            $options[$name] = $label;
                                                                        }
                                                                        return $options;
                                                                    })
                                                                    ->live()
                                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) use ($recalcQty) {
                                                                        $set('CUSTOM', count($state ?? []));
                                                                        $recalcQty($get, $set);
                                                                    })
                                                                    ->columnSpanFull();
                                                            }

                                                            $sizeColumns = [];
                                                            foreach ($standardSizes as $sz => $max) {
                                                                $sizeColumns[] = TextInput::make($sz)
                                                                    ->label($sz)
                                                                    ->helperText('Maks ' . $max)
                                                                    ->numeric()
                                                                    ->minValue(0)
                                                                    ->maxValue($max)
                                                                    ->placeholder('0')
                                                                    ->default(null)
                                                                    ->readOnly(fn() => $sz === 'CUSTOM' && $item->production_category === 'custom')
                                                                    ->live(debounce: 300)
                                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) use ($sz, $max, $recalcQty) {
                                                                        $val = (int) $state;
                                                                        if ($val < 0) {
                                                                            $set($sz, 0);
                                                                        } elseif ($val > $max) {
                                                                            $set($sz, $max);
                                                                        }
                                                                        $recalcQty($get, $set);
                                                                    })
                                                                    ->extraInputAttributes(['style' => 'text-align: center;']);
                                                            }

                                                            foreach ($sizeColumns as $col) {
                                                                $fields[] = $col;
                                                            }

                                                            if (!empty($requestPerSize)) {
                                                                $summaryParts = [];
                                                                foreach ($requestPerSize as $sz => $reqs) $summaryParts[] = strtoupper($sz) . ' -> ' . implode(', ', $reqs);
                                                                $summaryText = '* Request Tambahan:  ' . implode('   |   ', $summaryParts);
                                                                $fields[] = Placeholder::make('req_info')->hiddenLabel()->content(new HtmlString('<div style="font-size:11px;color:#b45309;font-weight:600;padding:4px 8px;background:#fffbeb;border-radius:6px;border:1px solid #fef3c7;">' . $summaryText . '</div>'))->columnSpanFull();
                                                            }

                                                            if (empty($fields) || (count($fields) === 1 && $fields[0] instanceof Toggle)) {
                                                                $fields[] = TextInput::make('qty')
                                                                    ->label('Target Qty')
                                                                    ->numeric()
                                                                    ->minValue(0)
                                                                    ->placeholder('0')
                                                                    ->live(debounce: 300)
                                                                    ->afterStateUpdated(function ($state, Set $set, Get $get) use ($recalcQty) {
                                                                        if ($state !== null && $state !== '' && (int) $state < 0) {
                                                                            $set('qty', 0);
                                                                        }
                                                                        $recalcQty($get, $set);
                                                                    })
                                                                    ->extraInputAttributes(['style' => 'text-align: center;'])
                                                                    ->extraAttributes(['style' => 'width: 150px; max-width: 150px;']);
                                                            }

                                                            return $fields;
                                                        })
                                                        ->columns(6)
                                                        ->columnSpanFull(),

                                                    Grid::make(2)
                                                        ->schema([
                                                            TextInput::make('quantity')
                                                                ->label('Total Qty')
                                                                ->numeric()
                                                                ->disabled()
                                                                ->dehydrated()
                                                                ->prefix('Σ'),
                                                            Hidden::make('quantity_hidden'),  // backup
                                                            TextInput::make('wage_per_pcs')->live(debounce: 500)
                                                                ->label('Upah Standar per Pcs')
                                                                ->numeric()
                                                                ->prefix('Rp')
                                                                ->default(function (Get $get) {
                                                                    $stageName = $get('../../stage_name');
                                                                    if ($stageName) {
                                                                        $stage = ProductionStage::where('name', $stageName)->first();
                                                                        return $stage?->base_wage ?? 0;
                                                                    }
                                                                    return 0;
                                                                }),
                                                            TextInput::make('wage_custom_per_pcs')->live(debounce: 500)
                                                                ->label('Upah Custom per Pcs')
                                                                ->numeric()
                                                                ->prefix('Rp')
                                                                ->default(function (Get $get) {
                                                                    $stageName = $get('../../stage_name');
                                                                    if ($stageName) {
                                                                        $stage = ProductionStage::where('name', $stageName)->first();
                                                                        return $stage?->base_wage ?? 0;
                                                                    }
                                                                    return 0;
                                                                })
                                                                ->visible(fn (Get $get) => (str_contains(strtolower($get('../../stage_name')), 'potong') || str_contains(strtolower($get('../../stage_name')), 'jahit')) && (int) ($get('CUSTOM') ?? 0) > 0),
                                                        ]),
                                                ])
                                                ->columnSpanFull()
                                                ->addActionLabel('+ Tambah Pekerja')
                                                ->addActionAlignment(\Filament\Support\Enums\Alignment::Start)
                                                ->addAction(fn($action) => $action->color('primary')),

                                            \Filament\Schemas\Components\Actions::make([
                                                \Filament\Actions\Action::make('autoDistribute')
                                                    ->label('Bagi Tugas Otomatis')
                                                    ->icon('heroicon-m-sparkles')
                                                    ->color('primary')
                                                    ->action(function (Set $set, Get $get) use ($item) {
                                                        $workers = $get('workers') ?? [];
                                                        if (empty($workers)) {
                                                            \Filament\Notifications\Notification::make()
                                                                ->title('Pekerja Kosong')
                                                                ->body('Silakan tambahkan pekerja terlebih dahulu sebelum melakukan pembagian otomatis.')
                                                                ->warning()
                                                                ->send();
                                                            return;
                                                        }

                                                        // 1. Kumpulkan ukuran dari semua item di grup
                                                        $allGroupItems = $item->getItemsInGroup();

                                                        $standardSizes = [];
                                                        foreach ($allGroupItems as $gi) {
                                                            $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                                                            $standardSizes[$sz] = ($standardSizes[$sz] ?? 0) + $gi->quantity;
                                                        }

                                                        // Hitung CUSTOM (ukuran perorangan)
                                                        $customQtyTotal = 0;
                                                        $customNames = [];
                                                        if ($item->production_category === 'custom') {
                                                            $details = $item->size_and_request_details ?? [];
                                                            $customNames = array_values(array_filter(
                                                                array_map(fn($u) => $u['nama'] ?? null, $details['detail_custom'] ?? [])
                                                            ));
                                                            $customQtyTotal = count($customNames);
                                                        }

                                                        $totalQty = array_sum($standardSizes) + $customQtyTotal;
                                                        if ($totalQty <= 0) return;

                                                        $allSizeKeys   = array_merge(array_keys($standardSizes), $customQtyTotal > 0 ? ['CUSTOM'] : []);
                                                        $workerKeys    = array_keys($workers);
                                                        $numWorkers    = count($workerKeys);

                                                        // 2. Reset semua alokasi
                                                        foreach ($workerKeys as $wKey) {
                                                            foreach ($allSizeKeys as $sz) {
                                                                $workers[$wKey][$sz] = null;
                                                            }
                                                            $workers[$wKey]['qty']               = null;
                                                            $workers[$wKey]['quantity']          = 0;
                                                            $workers[$wKey]['_custom_recipients'] = [];
                                                        }

                                                        // 3. Hitung target kuantitas per worker (seimbang & adil)
                                                        $baseShare = intdiv($totalQty, $numWorkers);
                                                        $remainder = $totalQty % $numWorkers;
                                                        $workerTargets = [];
                                                        foreach ($workerKeys as $idx => $wKey) {
                                                            $workerTargets[$wKey] = $baseShare + ($idx < $remainder ? 1 : 0);
                                                        }

                                                        // 4. Kumpulkan semua blok ukuran dalam bentuk array datar agar bisa dialokasikan secara seri
                                                        $flatSizeQueue = [];
                                                        foreach ($standardSizes as $szName => $szQty) {
                                                            if ($szQty > 0) {
                                                                $flatSizeQueue[] = [
                                                                    'key'   => $szName,
                                                                    'qty'   => $szQty,
                                                                    'type'  => 'standard',
                                                                    'names' => []
                                                                ];
                                                            }
                                                        }
                                                        if ($customQtyTotal > 0) {
                                                            $flatSizeQueue[] = [
                                                                'key'   => 'CUSTOM',
                                                                'qty'   => $customQtyTotal,
                                                                'type'  => 'custom',
                                                                'names' => $customNames
                                                            ];
                                                        }

                                                        // 5. Alokasikan ukuran secara SERI (Batching per ukuran utuh semaksimal mungkin)
                                                        $workerIdx = 0;
                                                        $currentWorkerKey = $workerKeys[$workerIdx];
                                                        $currentWorkerQuotaRemaining = $workerTargets[$currentWorkerKey];

                                                        $customOffset = 0;
                                                        foreach ($flatSizeQueue as $sizeItem) {
                                                            $sizeKey = $sizeItem['key'];
                                                            $sizeQtyRemaining = $sizeItem['qty'];

                                                            while ($sizeQtyRemaining > 0 && $workerIdx < $numWorkers) {
                                                                $allocation = min($sizeQtyRemaining, $currentWorkerQuotaRemaining);

                                                                if ($allocation <= 0) {
                                                                    if ($workerIdx < ($numWorkers - 1)) {
                                                                        $workerIdx++;
                                                                        $currentWorkerKey = $workerKeys[$workerIdx];
                                                                        $currentWorkerQuotaRemaining = $workerTargets[$currentWorkerKey];
                                                                        $allocation = min($sizeQtyRemaining, $currentWorkerQuotaRemaining);
                                                                    } else {
                                                                        break;
                                                                    }
                                                                }

                                                                $workers[$currentWorkerKey][$sizeKey] = ($workers[$currentWorkerKey][$sizeKey] ?? 0) + $allocation;
                                                                $workers[$currentWorkerKey]['quantity'] = ($workers[$currentWorkerKey]['quantity'] ?? 0) + $allocation;

                                                                $currentWorkerQuotaRemaining -= $allocation;
                                                                $sizeQtyRemaining -= $allocation;

                                                                if ($sizeItem['type'] === 'custom' && !empty($sizeItem['names'])) {
                                                                    $assignedNames = array_slice($sizeItem['names'], $customOffset, $allocation);
                                                                    $workers[$currentWorkerKey]['_custom_recipients'] = array_merge(
                                                                        $workers[$currentWorkerKey]['_custom_recipients'] ?? [],
                                                                        $assignedNames
                                                                    );
                                                                    $customOffset += $allocation;
                                                                }

                                                                if ($currentWorkerQuotaRemaining <= 0 && $workerIdx < ($numWorkers - 1)) {
                                                                    $workerIdx++;
                                                                    $currentWorkerKey = $workerKeys[$workerIdx];
                                                                    $currentWorkerQuotaRemaining = $workerTargets[$currentWorkerKey];
                                                                }
                                                            }
                                                        }

                                                        // 6. Bersihkan nilai 0 → null agar UI tetap rapi
                                                        foreach ($workerKeys as $wKey) {
                                                            foreach ($allSizeKeys as $sz) {
                                                                if (isset($workers[$wKey][$sz]) && $workers[$wKey][$sz] === 0) {
                                                                    $workers[$wKey][$sz] = null;
                                                                }
                                                            }
                                                        }

                                                        $set('workers', $workers);
                                                        \Filament\Notifications\Notification::make()
                                                            ->title('Tugas Terbagi (Metode Seri/Batching)')
                                                            ->body('Ukuran dialokasikan secara utuh per blok untuk memaksimalkan efisiensi tukang.')
                                                            ->success()
                                                            ->send();
                                                    })
                                            ])->alignLeft(),
                                        ])
                                        ->columnSpanFull()
                                        ->addActionLabel('+ Tambah Tugas Baru')
                                        ->addAction(fn($action) => $action->color('primary')),
                                     ]),
                                     \Filament\Forms\Components\ViewField::make('submit_buttons')
                                         ->hiddenLabel()
                                         ->view('filament.resources.control-produksi.components.submit-buttons'),
                                ])->columnSpan(8),

                        // RIGHT COLUMN: SPECIFICATIONS (Sticky)
                        Group::make([
                            Section::make('Rincian Spesifikasi')
                                ->description('Detail model, ukuran, dan bahan')
                                ->icon('heroicon-m-information-circle')
                                ->compact()
                                ->schema([
                                    Placeholder::make('technical_specs')
                                        ->hiddenLabel()
                                        ->content(function () use ($item): HtmlString {
                                            if (!$item) return new HtmlString('');

                                            $name = htmlspecialchars($item->product_name ?? 'Produk');
                                            $cat = $item->production_category ?? 'produksi';
                                            $details = $item->size_and_request_details ?? [];
                                            $bahan = $item->bahan;
                                            $allOrderItems = $item->getItemsInGroup();

                                            $catLabel = match ($cat) {
                                                'non_produksi' => 'NON-PRODUKSI',
                                                'jasa' => 'JASA',
                                                default => 'PRODUKSI',
                                            };

                                            $primaryColor = '#7c3aed';
                                            $html = '<div style="font-family:inherit; color:#1f2937;">';

                                            $html .= '<div style="margin-bottom:20px; border-bottom:1px solid #e5e7eb; padding-bottom:12px;">';
                                            $html .= '<div style="display:flex; align-items:center; gap:8px;">';
                                            $html .= '<span style="font-size:20px; font-weight:800; letter-spacing:-0.01em;">' . strtoupper($name) . '</span>';
                                            $html .= '<small style="background:#f3e8ff; color:' . $primaryColor . '; font-weight:800; padding:2px 8px; border-radius:4px; font-size:10px; border:1px solid #ddd6fe;">' . $catLabel . '</small>';
                                            $html .= '</div>';
                                            $html .= '</div>';

                                            if ($cat === 'non_produksi') {
                                                $vId = $details['product_variant_id'] ?? null;
                                                $v = $vId ? \App\Models\ProductVariant::with('product')->find($vId) : null;
                                                $hex = $v?->color_code ?: '#e5e7eb';
                                                $bahanLabel = $v ? (($v->product?->name ?? $item->product_name) . ' - ' . ($v->color_name ?? 'Tanpa Warna')) : ($item->product_name ?? '-');
                                            } else {
                                                $hex = $bahan?->color_code ?: '#e5e7eb';
                                                $bahanLabel = $bahan ? (($bahan->material->name ?? 'Bahan') . ' - ' . ($bahan->color_name ?? 'Tanpa Warna')) : ($details['bahan'] ?? '-');
                                            }

                                            $activeReturn = \App\Models\OrderReturn::where('order_item_id', $item->id)
                                                ->whereIn('status', ['pending', 'diproses'])
                                                ->latest('id')
                                                ->first();

                                            if ($activeReturn) {
                                                $returWo = \App\Models\WorkOrder::withoutGlobalScopes()
                                                    ->where('order_item_id', $item->id)
                                                    ->where('wo_number', 'like', '%-R%')
                                                    ->latest('id')
                                                    ->first();
                                                $woNumText = $returWo ? " ({$returWo->wo_number})" : '';
                                                $garansiText = $activeReturn->responsibility_type === 'customer_paid' ? '💳 Retur Berbayar' : '🛡️ Retur Garansi Toko';
                                                $defectNote = htmlspecialchars($activeReturn->items_description ?: '-');

                                                $breakdownHtml = '';
                                                if (!empty($activeReturn->size_breakdown) && is_array($activeReturn->size_breakdown)) {
                                                    $lines = [];
                                                    foreach ($activeReturn->size_breakdown as $label => $qty) {
                                                        if ($label === '_raw' || is_array($qty)) continue;
                                                        $lines[] = '• ' . htmlspecialchars($label) . ' ➔ <strong>' . ((int)$qty) . ' pcs</strong>';
                                                    }
                                                    if (!empty($lines)) {
                                                        $breakdownHtml = '<div style="margin-top:10px; padding:10px; background:#fff; border-radius:6px; border:1px solid #fca5a5; font-size:12px; color:#991b1b; line-height:1.6;">'
                                                            . '<div style="font-weight:800; margin-bottom:4px;">🎯 RINCIAN UKURAN / VARIAN CACAT DIREVISI:</div>'
                                                            . implode('<br>', $lines)
                                                            . '</div>';
                                                    }
                                                }

                                                $html .= '<div style="margin-bottom:16px; padding:14px; background:#fef2f2; border:1.5px solid #fca5a5; border-radius:10px;">'
                                                    . '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">'
                                                    . '<span style="font-size:13px; font-weight:800; color:#991b1b;">🔄 REVISI RETUR REPAIR' . $woNumText . '</span>'
                                                    . '<span style="font-size:11px; font-weight:800; color:#b91c1c; background:#fee2e2; padding:2px 8px; border-radius:12px; border:1px solid #fca5a5;">Total Retur: ' . $activeReturn->quantity . ' pcs</span>'
                                                    . '</div>'
                                                    . '<div style="font-size:12px; font-weight:700; color:#7f1d1d; margin-bottom:6px;">' . $garansiText . '</div>'
                                                    . '<div style="font-size:12px; background:#fff; padding:8px 10px; border-radius:6px; border:1px solid #fecaca; color:#991b1b; font-weight:600; line-height:1.4;">'
                                                    . '📝 <strong>Catatan Perbaikan / Defect:</strong><br>' . $defectNote
                                                    . '</div>'
                                                    . $breakdownHtml
                                                    . '</div>';
                                            }

                                            $html .= '<div style="margin-bottom:20px;">';
                                            $html .= '<div style="font-size:11px; font-weight:800; color:#6b7280; letter-spacing:0.05em; margin-bottom:8px;">' . ($cat === 'non_produksi' ? 'INFORMASI NON-PRODUKSI' : 'INFORMASI BAHAN') . '</div>';
                                            
                                            // Material Info Box
                                            $html .= '<div style="padding:16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:12px;">';
                                            
                                            // Title
                                            $html .= '<div style="font-size:13px; font-weight:700; color:#111827; margin-bottom:4px;">' . htmlspecialchars($bahanLabel) . '</div>';

                                            $sablonJenis = $details['sablon_jenis'] ?? null;
                                            $sablonLokasi = $details['sablon_lokasi'] ?? null;
                                            $sablonKet = $details['sablon_keterangan'] ?? null;
                                            $sablon = [];
                                            if ($sablonJenis && $sablonJenis !== 'Tanpa Sablon/Bordir') {
                                                $sablon[] = [
                                                    'jenis' => $sablonJenis,
                                                    'lokasi' => ($sablonLokasi ?: '-') . ($sablonKet ? ' - Keterangan: "' . $sablonKet . '"' : '')
                                                ];
                                            }
                                            if (empty($sablon) && !empty($details['sablon_jenis'])) {
                                                $sablon = [['jenis' => $details['sablon_jenis'], 'lokasi' => $details['sablon_lokasi'] ?? '-']];
                                            }

                                            if (!empty($sablon)) {
                                                $sblnTexts = [];
                                                foreach ($sablon as $s) {
                                                    $sblnTexts[] = ($s['jenis'] ?? '') . ' (' . ($s['lokasi'] ?? '') . ')';
                                                }
                                                $html .= '<div style="font-size:11px; font-weight:600; color:' . $primaryColor . '; margin-top:4px;">SABLON/BORDIR: ' . htmlspecialchars(implode(', ', $sblnTexts)) . '</div>';
                                            }
                                            $html .= '</div>'; // Closes Material Info Box

                                            // GENERAL ORDER NOTES SECTION
                                            $orderNotes = $item->order->notes ?? null;
                                            if (!empty($orderNotes)) {
                                                $html .= '<div style="margin-bottom:12px; padding:12px 16px; background:#fffbeb; border:1px solid #fef3c7; border-radius:8px;">';
                                                $html .= '<div style="font-size:11px; font-weight:800; color:#d97706; letter-spacing:0.05em; margin-bottom:6px; display:flex; align-items:center; gap:4px;">';
                                                $html .= '⚠️ CATATAN UMUM PESANAN';
                                                $html .= '</div>';
                                                $html .= '<div style="font-size:12px; font-weight:700; color:#92400e; white-space:pre-wrap; line-height:1.5;">' . htmlspecialchars($orderNotes) . '</div>';
                                                $html .= '</div>';
                                            }
                                            $genders = [];
                                            foreach ($allOrderItems as $ai) {
                                                $idtl = $ai->size_and_request_details ?? [];
                                                $isProduksi = in_array($cat, ['produksi', 'custom']);
                                                $g = $isProduksi ? ($idtl['gender'] ?? 'L') : 'UMUM';
                                                
                                                if (!isset($genders[$g])) $genders[$g] = ['qty' => 0, 'models' => []];
                                                $genders[$g]['qty'] += $ai->quantity;

                                                $mLabel = !empty($idtl['group_label']) ? $idtl['group_label'] : null;
                                                if (!$mLabel) {
                                                    $mParts = [];
                                                    if ($isProduksi) {
                                                        if (isset($idtl['sleeve_model'])) $mParts[] = 'Lengan ' . $idtl['sleeve_model'];
                                                        if (isset($idtl['pocket_model']) && $idtl['pocket_model'] !== 'tanpa_saku') $mParts[] = 'Saku ' . str_replace('_', ' ', $idtl['pocket_model']);
                                                        if (!empty($idtl['is_tunic'])) $mParts[] = 'Tunik';
                                                    }
                                                    $mLabel = empty($mParts) ? ($isProduksi ? 'Model Standar' : 'Rincian Pesanan') : implode(', ', $mParts);
                                                }
                                                $mKey = $mLabel;

                                                $attrs = $isProduksi ? [
                                                     'JENIS KELAMIN' => $g === 'P' ? 'PEREMPUAN' : ($g === 'L' ? 'LAKI-LAKI' : 'UMUM'),
                                                     'LENGAN' => isset($idtl['sleeve_model']) ? strtoupper($idtl['sleeve_model']) : 'PENDEK',
                                                     'SAKU' => isset($idtl['pocket_model']) ? strtoupper(str_replace('_', ' ', $idtl['pocket_model'])) : 'TANPA SAKU',
                                                     'KANCING' => isset($idtl['button_model']) ? strtoupper($idtl['button_model']) : 'BIASA',
                                                     'KERAH' => isset($idtl['collar_model']) ? strtoupper($idtl['collar_model']) : 'BIASA',
                                                 ] : [];
                                                 if (!empty($idtl['is_tunic'])) {
                                                     $attrs['MODEL'] = 'TUNIK';
                                                 }

                                                 if (!isset($genders[$g]['models'][$mKey])) {
                                                     $genders[$g]['models'][$mKey] = [
                                                         'label' => $mLabel,
                                                         'qty' => 0, 'sizes' => [], 'notes' => [], 'custom' => [],
                                                         'attrs' => $attrs
                                                     ];
                                                 }
                                                 $genders[$g]['models'][$mKey]['qty'] += $ai->quantity;
                                                 $sz = $ai->size ?? '-';
                                                 $genders[$g]['models'][$mKey]['sizes'][$sz] = ($genders[$g]['models'][$mKey]['sizes'][$sz] ?? 0) + $ai->quantity;
                                                 
                                                 if (!empty($idtl['spec_notes'])) {
                                                     $genders[$g]['models'][$mKey]['notes'][] = $idtl['spec_notes'];
                                                 }
                                                 if (!empty($idtl['request_tambahan'])) {
                                                     if (is_array($idtl['request_tambahan'])) {
                                                         foreach ($idtl['request_tambahan'] as $rt) {
                                                             $genders[$g]['models'][$mKey]['notes'][] = is_array($rt) ? (($rt['jenis'] ?? '') . ': ' . ($rt['keterangan'] ?? '')) : $rt;
                                                         }
                                                     } else {
                                                         $genders[$g]['models'][$mKey]['notes'][] = $idtl['request_tambahan'];
                                                     }
                                                 }

                                                 if ($ai->size === 'Custom') {
                                                     $m = [];
                                                     foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                                                         if (!empty($idtl[$mk])) $m[] = $mk . ':' . $idtl[$mk];
                                                     }
                                                     if (!empty($m) || $ai->recipient_name) {
                                                         $genders[$g]['models'][$mKey]['custom'][] = ($ai->recipient_name ?: '-') . (!empty($m) ? ' (' . implode(' ', $m) . ')' : '');
                                                     }
                                                 }

                                                 if (!empty($idtl['detail_custom'])) {
                                                     foreach ($idtl['detail_custom'] as $u) {
                                                         $m = [];
                                                         foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                                                             if (!empty($u[$mk])) $m[] = $mk . ':' . $u[$mk];
                                                         }
                                                         $genders[$g]['models'][$mKey]['custom'][] = ($u['nama'] ?? '-') . (!empty($m) ? ' (' . implode(' ', $m) . ')' : '');
                                                     }
                                                 }
                                            }

                                            $html .= '<div>';
                                            $html .= '<div style="font-size:11px; font-weight:800; color:#6b7280; letter-spacing:0.05em; margin-bottom:8px;">RINCIAN PRODUKSI</div>';
                                            $html .= '<div style="display:flex; flex-direction:column; gap:10px;">';

                                            $hasItemsToShow = count($genders) > 0;

                                            if ($hasItemsToShow) {
                                                foreach ($genders as $gCode => $gData) {
                                                    $gName = $gCode === 'P' ? 'PEREMPUAN' : ($gCode === 'L' ? 'LAKI-LAKI' : 'RINCIAN UKURAN PESANAN');
                                                    $html .= '<div x-data="{ open: true }" style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; background:white;">';
                                                    $html .= '<div @click="open = !open" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; padding:10px 16px; background:#f9fafb;">';
                                                    $html .= '<div style="display:flex; align-items:center; gap:8px;">';
                                                    $html .= '<span style="font-size:13px; font-weight:800; color:#1e293b;">' . $gName . '</span>';
                                                    $html .= '<span style="font-size:12px; font-weight:900; color:' . $primaryColor . '; background:#f3e8ff; padding:2px 8px; border-radius:12px;">' . $gData['qty'] . ' pcs</span>';
                                                    $html .= '</div>';
                                                    $html .= '<svg :class="open ? \'rotate-180\' : \'\'" style="width:14px; height:14px; transition:0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>';
                                                    $html .= '</div>';

                                                    $html .= '<div x-show="open" style="padding:14px; border-top:1px solid #f1f5f9; display:flex; flex-direction:column; gap:12px;">';
                                                    foreach ($gData['models'] as $mKey => $mData) {
                                                        $html .= '<div style="background:#fafafa; border:1px solid #f1f5f9; border-radius:6px; padding:10px 12px;">';
                                                        $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">';
                                                        $html .= '<span style="font-size:11px; font-weight:800; color:#4338ca; background:#e0e7ff; padding:2px 8px; border-radius:4px; border:1px solid #c7d2fe;">VARIAN: ' . htmlspecialchars(strtoupper($mData['label'])) . '</span>';
                                                        $html .= '<span style="font-size:11px; font-weight:800; color:' . $primaryColor . '; background:#ede9fe; padding:1px 6px; border-radius:4px;">' . $mData['qty'] . ' pcs</span>';
                                                        $html .= '</div>';
                                                        
                                                        if (!empty($mData['attrs'])) {
                                                            $atxt = [];
                                                            foreach ($mData['attrs'] as $ak => $av) {
                                                                $atxt[] = '<span style="display:inline-block; white-space:nowrap; background:#f8fafc; border:1px solid #e2e8f0; padding:2px 8px; border-radius:4px; font-size:11px;"><span style="color:#64748b; font-weight:600;">' . htmlspecialchars($ak) . ':</span> <strong style="color:#1e293b; font-weight:800;">' . htmlspecialchars($av) . '</strong></span>';
                                                            }
                                                            $html .= '<div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:8px;">' . implode('', $atxt) . '</div>';
                                                        }
                                                        $stxt = [];
                                                        foreach ($mData['sizes'] as $szKey => $sqty) {
                                                            $stxt[] = '<div style="padding:3px 8px; background:#ffffff; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; font-weight:800; color:#1e293b;">' . $szKey . ': <span style="color:' . $primaryColor . ';">' . $sqty . '</span></div>';
                                                        }
                                                        $html .= '<div style="display:flex; flex-wrap:wrap; gap:6px;">' . implode('', $stxt) . '</div>';

                                                        if (!empty($mData['custom'])) {
                                                             $html .= '<div style="margin-top:8px; padding:6px 10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px;">';
                                                             $html .= '<div style="font-size:10px; font-weight:800; color:#15803d; text-transform:uppercase; margin-bottom:2px;">📐 Rincian Ukuran Custom / Penerima:</div>';
                                                             foreach (array_unique($mData['custom']) as $cItem) {
                                                                 $html .= '<div style="font-size:11px; font-weight:700; color:#166534;">- ' . htmlspecialchars($cItem) . '</div>';
                                                             }
                                                             $html .= '</div>';
                                                         }

                                                         if (!empty($mData['notes'])) {
                                                             $html .= '<div style="margin-top:8px; padding:6px 10px; background:#fffcf0; border:1px solid #fef3c7; border-radius:4px;">';
                                                             $html .= '<div style="font-size:10px; font-weight:800; color:#b45309; text-transform:uppercase; margin-bottom:2px;">CATATAN KHUSUS / REQUEST:</div>';
                                                             foreach (array_unique($mData['notes']) as $n) {
                                                                 $html .= '<div style="font-size:11px; font-weight:700; color:#92400e;">- ' . htmlspecialchars(is_array($n) ? json_encode($n) : $n) . '</div>';
                                                             }
                                                             $html .= '</div>';
                                                         }
                                                        $html .= '</div>';
                                                    }
                                                    $html .= '</div></div>';
                                                }
                                            }
                                            $html .= '</div></div>';
                                            
                                            $url = route('filament.admin.resources.orders.edit', ['tenant' => \Filament\Facades\Filament::getTenant()->id, 'record' => $item->order_id]);
                                            $html .= '<div style="margin-top:24px;">';
                                            $html .= '<a href="' . $url . '" target="_blank" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:10px; background:#ffffff; color:#374151; border:1px solid #d1d5db; border-radius:6px; text-decoration:none; font-size:11px; font-weight:800; transition:0.1s;">';
                                            $html .= '<svg style="width:14px; height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>';
                                            $html .= 'LIHAT DETAIL PESANAN';
                                            $html .= '</a>';
                                            $html .= '</div>';

                                            $html .= '</div>';
                                            return new HtmlString($html);
                                        })
                                        ->columnSpanFull(),
                                ])
                        ])
                        ->columnSpan(4)
                        ->extraAttributes(['class' => 'sticky top-20']),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('← Kembali ke Daftar')
                ->url(ControlProduksiResource::getUrl('index'))
                ->color('gray')
                ->outlined(),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $item = $this->record;
        
        $category = $item->production_category ?? 'produksi';
        $tasksData = $data['productionTasks'] ?? [];

        // ══════════════════════════════════════════════════════════════
        // PERSIAPAN DATA KAPASITAS ASLI
        // ══════════════════════════════════════════════════════════════
        $activeReturTask = $item->productionTasks()->where('is_revision', true)->where('status', '!=', 'done')->latest('id')->first();
        
        if ($activeReturTask) {
            $originalSizes = [];
            if (is_array($activeReturTask->size_quantities)) {
                foreach ($activeReturTask->size_quantities as $rSz => $rQty) {
                    if (str_starts_with($rSz, '_')) continue;
                    $cleanSz = trim(str_replace(['SIZE ', 'SIZE'], '', strtoupper($rSz)));
                    $originalSizes[$cleanSz] = (int) $rQty;
                }
            }
            $totalOrderQty = (int) $activeReturTask->quantity;
        } else {
            $allGroupItems = $item->getItemsInGroup();

            $originalSizes = [];
            foreach ($allGroupItems as $gi) {
                $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                $originalSizes[$sz] = ($originalSizes[$sz] ?? 0) + (int) $gi->quantity;
            }

            if ($item->production_category === 'custom') {
                $details = $item->size_and_request_details ?? [];
                $count = count($details['detail_custom'] ?? []);
                if ($count > 0) {
                    $originalSizes['CUSTOM'] = ($originalSizes['CUSTOM'] ?? 0) + $count;
                }
            }
            $totalOrderQty = array_sum($originalSizes);
        }

        // ══════════════════════════════════════════════════════════════
        // VALIDASI
        // ══════════════════════════════════════════════════════════════
        $allocatedPerStageSize = [];
        $allocatedPerStageTotal = [];
        $excludeKeys = ['id', 'worker_id', 'wage_per_pcs', 'wage_custom_per_pcs', 'quantity', 'description', '_fill_all', 'qty', '_custom_recipients'];
        
        foreach ($tasksData as $taskItem) {
            $stage = $taskItem['stage_name'] ?? null;
            if (!$stage) continue;

            $workers = $taskItem['workers'] ?? [];
            if (empty($workers)) {
                Notification::make()->title('Pekerja Belum Dipilih!')->body("Tahap <strong>{$stage}</strong> belum ditentukan pekerja/karyawannya.")->danger()->send();
                return;
            }

            // Check duplicate workers in same stage
            $seenWorkers = [];
            foreach ($workers as $wItem) {
                $wid = $wItem['worker_id'] ?? null;
                if ($wid) {
                    if (isset($seenWorkers[$wid])) {
                        $workerName = \App\Models\Worker::find($wid)?->name ?? "ID {$wid}";
                        Notification::make()->title('Pekerja Duplikat!')->body("<strong>{$workerName}</strong> dipilih lebih dari sekali di tahap <strong>{$stage}</strong>. Gabungkan jadi satu baris.")->danger()->send();
                        return;
                    }
                    $seenWorkers[$wid] = true;
                }
            }

            foreach ($workers as $wItem) {
                $workerId = $wItem['worker_id'] ?? null;
                if (!$workerId) {
                    Notification::make()->title('Pekerja Belum Dipilih!')->body("Ada baris pekerja di tahap <strong>{$stage}</strong> yang belum dipilih.")->danger()->send();
                    return;
                }

                $rowQty = 0;
                $rowSizes = [];
                foreach ($wItem as $k => $v) {
                    if (!in_array($k, $excludeKeys) && is_numeric($v) && (int) $v > 0) {
                        $rowQty += (int) $v;
                        $rowSizes[strtoupper($k)] = (int) $v;
                    }
                }

                if ($rowQty === 0 && isset($wItem['qty']) && (int) $wItem['qty'] > 0) $rowQty = (int) $wItem['qty'];
                if ($rowQty === 0 && isset($wItem['quantity']) && (int) $wItem['quantity'] > 0) $rowQty = (int) $wItem['quantity'];

                if ($rowQty <= 0) {
                    Notification::make()->title('Ada Pekerja Tanpa Qty!')->body("Pekerja di tahap <strong>{$stage}</strong> tidak memiliki qty.")->danger()->send();
                    return;
                }

                $allocatedPerStageTotal[$stage] = ($allocatedPerStageTotal[$stage] ?? 0) + $rowQty;
                foreach ($rowSizes as $sz => $qty) {
                    $allocatedPerStageSize[$stage][$sz] = ($allocatedPerStageSize[$stage][$sz] ?? 0) + $qty;
                }
            }
        }

        if (empty($allocatedPerStageTotal)) {
            Notification::make()->title('❌ Belum Ada Tugas!')->danger()->send();
            return;
        }

        $mismatchErrors = [];
        foreach ($allocatedPerStageTotal as $stageName => $totalUsed) {
            if ($totalUsed !== $totalOrderQty) {
                $diff = $totalOrderQty - $totalUsed;
                $mismatchErrors[] = "Tahap <strong>{$stageName}</strong>: Total target harus {$totalOrderQty} pcs, saat ini teratur {$totalUsed} pcs (" . ($diff > 0 ? "kurang {$diff} pcs" : "kelebihan " . abs($diff) . " pcs") . ").";
            }

            if (!empty($originalSizes)) {
                $stageSizes = $allocatedPerStageSize[$stageName] ?? [];
                foreach ($originalSizes as $sz => $expectedQty) {
                    $actualQty = $stageSizes[$sz] ?? 0;
                    if ($actualQty !== $expectedQty) {
                        $diff = $expectedQty - $actualQty;
                        $mismatchErrors[] = "Tahap <strong>{$stageName}</strong> ukuran <strong>{$sz}</strong>: Harus {$expectedQty} pcs, teratur {$actualQty} pcs (" . ($diff > 0 ? "kurang {$diff} pcs" : "kelebihan " . abs($diff) . " pcs") . ").";
                    }
                }
            }
        }

        if (!empty($mismatchErrors)) {
            Notification::make()->title('❌ Jumlah Tugas Tidak Sesuai!')->body(new HtmlString(implode('<br>', $mismatchErrors)))->danger()->send();
            return;
        }

        // ══════════════════════════════════════════════════════════════
        // SIMPAN
        // ══════════════════════════════════════════════════════════════
        // Get existing tasks to preserve status/payment if they match stage and worker
        $existingTasks = $item->productionTasks()->get()->groupBy(fn($t) => $t->stage_name . '_' . $t->assigned_to);

        // Get all custom names and size genders in order once
        $customNames = [];
        $sizeGenders = [];
        $allItems = OrderItem::where('order_id', $item->order_id)
            ->where('product_name', $item->product_name)
            ->where('bahan_id', $item->bahan_id)
            ->where('production_category', $item->production_category)
            ->where('design_status', 'approved')
            ->get();

        foreach ($allItems as $ai) {
            $idtl = $ai->size_and_request_details ?? [];
            if ($ai->production_category === 'custom') {
                if (!empty($idtl['detail_custom'])) {
                    foreach ($idtl['detail_custom'] as $u) {
                        if (!empty($u['nama'])) {
                            $customNames[] = $u['nama'];
                        }
                    }
                }
            } else {
                $sz = strtoupper($ai->size ?? 'TANPA_UKURAN');
                $g = strtoupper($idtl['gender'] ?? 'L');
                $gLabel = ($g === 'P' ? 'P' : 'L');
                
                $qtyVal = (int) $ai->quantity;
                for ($qIdx = 0; $qIdx < $qtyVal; $qIdx++) {
                    $sizeGenders[$sz][] = $gLabel;
                }
            }
        }

        // Sort sizeGenders by frequency descending so the largest gender group is allocated continuously
        foreach ($sizeGenders as $sz => $gList) {
            $counts = array_count_values($gList);
            arsort($counts);
            $sortedList = [];
            foreach ($counts as $gLabel => $cnt) {
                for ($c = 0; $c < $cnt; $c++) {
                    $sortedList[] = $gLabel;
                }
            }
            $sizeGenders[$sz] = $sortedList;
        }

        // Delete existing non-revision tasks for this item (preserve retur tasks)
        $item->productionTasks()->where('is_revision', false)->delete();

        // Keep local pools of custom names and genders per stage
        $stageCustomPools = [];
        $stageGenderPools = [];

        foreach ($tasksData as $taskItem) {
            $stage = $taskItem['stage_name'];
            $wajibQc = $taskItem['wajib_qc'] ?? true;
            $workers = $taskItem['workers'] ?? [];

            if (!isset($stageCustomPools[$stage])) {
                $stageCustomPools[$stage] = $customNames;
            }
            if (!isset($stageGenderPools[$stage])) {
                $stageGenderPools[$stage] = $sizeGenders;
            }

            foreach ($workers as $wItem) {
                $workerId = $wItem['worker_id'];
                
                $sizeQuantities = [];
                foreach (array_keys($originalSizes) as $key) {
                    if (isset($wItem[$key]) && (int) $wItem[$key] > 0) $sizeQuantities[$key] = (int) $wItem[$key];
                }
                $quantity = array_sum($sizeQuantities);
                if ($quantity === 0 && isset($wItem['qty']) && (int) $wItem['qty'] > 0) $quantity = (int) $wItem['qty'];
                
                $wagePerPcs = (float) ($wItem['wage_per_pcs'] ?? 0);
                $wageCustomPerPcs = (float) ($wItem['wage_custom_per_pcs'] ?? $wagePerPcs);
                
                $customQty = (int) ($sizeQuantities['CUSTOM'] ?? 0);
                $standardQty = $quantity - $customQty;
                
                $worker = \App\Models\Worker::find($workerId);
                if ($worker && $worker->wage_type === 'monthly') {
                    $totalWage = 0;
                } else {
                    $totalWage = ($standardQty * $wagePerPcs) + ($customQty * $wageCustomPerPcs);
                }

                if ($customQty > 0) {
                    $sizeQuantities['_wage_custom'] = $wageCustomPerPcs;
                }

                if (!empty($wItem['_custom_recipients'])) {
                    $sizeQuantities['_custom_recipients'] = $wItem['_custom_recipients'];
                }

                $workerCustomNames = $wItem['_custom_recipients'] ?? [];
                $workerGenders = [];

                foreach ($sizeQuantities as $sz => $qty) {
                    if (str_starts_with($sz, '_')) continue;

                    for ($qIdx = 0; $qIdx < $qty; $qIdx++) {
                        if ($sz === 'CUSTOM') {
                            if (empty($workerCustomNames) && !empty($stageCustomPools[$stage])) {
                                $workerCustomNames[] = array_shift($stageCustomPools[$stage]);
                            }
                        } else {
                            if (!empty($stageGenderPools[$stage][$sz])) {
                                $gLabel = array_shift($stageGenderPools[$stage][$sz]);
                                $workerGenders[$sz][$gLabel] = ($workerGenders[$sz][$gLabel] ?? 0) + 1;
                            }
                        }
                    }
                }

                if (!empty($workerCustomNames)) {
                    $sizeQuantities['_custom_recipients'] = $workerCustomNames;
                }
                if (!empty($workerGenders)) {
                    $sizeQuantities['_genders'] = $workerGenders;
                }

                $autoDesc = [];
                $cleanSizes = array_filter($sizeQuantities, fn($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
                foreach ($cleanSizes as $sz => $sqty) {
                    $szDisplay = $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz;
                    $szGenders = $workerGenders[$sz] ?? [];
                    if (!empty($szGenders)) {
                        $gStrings = [];
                        foreach ($szGenders as $gLabel => $gQty) {
                            $gStrings[] = ($gLabel === 'P' ? 'Perempuan' : 'Laki-laki') . ': ' . $gQty;
                        }
                        $autoDesc[] = $szDisplay . ' (' . implode(', ', $gStrings) . ')';
                    } else {
                        $autoDesc[] = $szDisplay . ': ' . $sqty;
                    }
                }

                $descStrings = [];
                if (!empty($autoDesc)) {
                    $descStrings[] = 'Pembagian Ukuran: ' . implode(' | ', $autoDesc);
                }
                if (!empty($workerCustomNames)) {
                    $descStrings[] = 'Baju Custom: ' . implode(', ', $workerCustomNames);
                }
                
                $baseDesc = $taskItem['description'] ?? '';
                if (!empty($descStrings)) {
                    $baseDesc = trim($baseDesc . "\n" . implode("\n", $descStrings));
                }

                $lookupKey = $stage . '_' . $workerId;
                $oldTask = isset($existingTasks[$lookupKey]) ? $existingTasks[$lookupKey]->first() : null;

                $taskData = [
                    'stage_name' => $stage,
                    'assigned_to' => $workerId,
                    'quantity' => $quantity,
                    'wage_amount' => $totalWage,
                    'size_quantities' => empty($sizeQuantities) ? null : $sizeQuantities,
                    'description' => !empty($baseDesc) ? $baseDesc : null,
                    'shop_id' => \Filament\Facades\Filament::getTenant()->id,
                    'assigned_by' => $oldTask?->assigned_by ?? auth()->id(),
                    'status' => $oldTask?->status ?? 'pending',
                    'is_paid' => $oldTask?->is_paid ?? false,
                    'is_revision' => false,
                    'revision_source' => null,
                    'wajib_qc' => $wajibQc,
                    'worker_payroll_id' => $oldTask?->worker_payroll_id ?? null,
                    'completed_at' => $oldTask?->completed_at ?? null,
                ];
                $item->productionTasks()->create($taskData);
            }
        }

        // Sync to original WorkOrder
        $groupItemIds = $item->getItemsInGroup()->pluck('id');
        $workOrder = \App\Models\WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->where('wo_number', 'not like', '%-R%')
            ->first();

        // Ambil is_express dari order
        $isExpress = (bool) ($item->order?->is_express ?? false);

        // Ambil QC settings dari form
        $hasQcPrep = (bool) ($data['has_qc_prep'] ?? true);
        $qcWorkerId = $data['qc_worker_id'] ?? null;

        // Build stage_sequence: production stages only
        // QC after each stage handled by QC_REVIEW mechanism (not QC_ prefix stages)
        $stageSequence = [];
        
        // Tahap produksi saja (tanpa QC_ prefix)
        foreach ($tasksData as $taskData) {
            $stageName = $taskData['stage_name'] ?? null;
            if ($stageName) {
                $stageSequence[] = $stageName;
            }
        }

        if (!$workOrder && $item->order) {
            $initialStatus = $hasQcPrep ? \App\Models\WorkOrder::STATUS_QC_PREP : ($stageSequence[0] ?? \App\Models\WorkOrder::STATUS_COMPLETED);
            $workOrder = \App\Models\WorkOrder::create([
                'shop_id' => $item->order->shop_id,
                'order_item_id' => $item->id,
                'stage_sequence' => $stageSequence,
                'status' => $initialStatus,
                'is_express' => $isExpress,
                'has_qc_prep' => $hasQcPrep,
                'qc_worker_id' => $qcWorkerId,
                'stage_entered_at' => now(),
                'started_at' => now(),
            ]);
        }

        if ($workOrder) {
            $tasks = \App\Models\ProductionTask::withoutGlobalScopes()
                ->whereIn('order_item_id', $groupItemIds)
                ->get();

            $workerUpdates = [
                'cutting_worker_id' => null,
                'sewing_worker_id' => null,
                'decoration_worker_id' => null,
                'button_worker_id' => null,
                'finishing_worker_id' => null,
            ];

            foreach ($tasks as $task) {
                $col = \App\Models\WorkOrder::resolveWorkerColumnForStage($task->stage_name);
                if ($col && $task->assigned_to) {
                    if ($workerUpdates[$col] === null) {
                        $workerUpdates[$col] = $task->assigned_to;
                    }
                }
            }

            // Update stage_sequence, is_express, QC settings, dan worker assignments
            $workerUpdates['stage_sequence'] = $stageSequence;
            $workerUpdates['is_express'] = $isExpress;
            $workerUpdates['has_qc_prep'] = $hasQcPrep;
            $workerUpdates['qc_worker_id'] = $qcWorkerId;
            $workOrder->update($workerUpdates);

            // Buat ProductionTask HANYA untuk QC_PERSIAPAN (QC worker perlu action)
            // QC setelah stage (QC_POTONG, QC_JAHIT, dll) = WO stage aja, bukan task
            if ($qcWorkerId && $hasQcPrep) {
                $groupItems = \App\Models\OrderItem::whereIn('id', $groupItemIds)->get();
                $totalGroupQty = $groupItems->sum('quantity');

                $groupSizeQuantities = [];
                $customNames = [];
                foreach ($groupItems as $gi) {
                    $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
                    $groupSizeQuantities[$sz] = ($groupSizeQuantities[$sz] ?? 0) + $gi->quantity;
                    if ($sz === 'CUSTOM' && !empty($gi->recipient_name)) {
                        $customNames[] = $gi->recipient_name;
                    }
                }

                $sizeBreakdown = [];
                foreach ($groupSizeQuantities as $sz => $q) {
                    $displaySz = $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz;
                    $sizeBreakdown[] = "{$displaySz} ({$q})";
                }
                $qcDesc = 'Pembagian Ukuran: ' . implode(' | ', $sizeBreakdown);
                if (!empty($customNames)) {
                    $qcDesc .= "\nBaju Custom: " . implode(', ', $customNames);
                }

                $existingQcTask = \App\Models\ProductionTask::withoutGlobalScopes()
                    ->where('order_item_id', $item->id)
                    ->where('stage_name', 'QC_PERSIAPAN')
                    ->first();
                
                $isWoPastQcPrep = $workOrder && !in_array($workOrder->status, [\App\Models\WorkOrder::STATUS_CREATED, \App\Models\WorkOrder::STATUS_QC_PREP, 'QC_PERSIAPAN']);
                $qcTaskStatus = $isWoPastQcPrep ? 'done' : 'pending';

                if (!$existingQcTask) {
                    \App\Models\ProductionTask::create([
                        'order_item_id' => $item->id,
                        'stage_name' => 'QC_PERSIAPAN',
                        'quantity' => $totalGroupQty,
                        'size_quantities' => $groupSizeQuantities,
                        'description' => $qcDesc,
                        'assigned_to' => $qcWorkerId,
                        'assigned_by' => auth()->id(),
                        'status' => $qcTaskStatus,
                        'qc_approved' => $isWoPastQcPrep,
                        'completed_at' => $isWoPastQcPrep ? now() : null,
                        'is_paid' => false,
                        'wajib_qc' => false,
                        'shop_id' => \Filament\Facades\Filament::getTenant()->id,
                    ]);
                } else {
                    $updateQcData = [
                        'quantity' => $totalGroupQty,
                        'size_quantities' => $groupSizeQuantities,
                        'description' => $qcDesc,
                        'assigned_to' => $qcWorkerId,
                    ];
                    if ($isWoPastQcPrep && $existingQcTask->status !== 'done') {
                        $updateQcData['status'] = 'done';
                        $updateQcData['qc_approved'] = true;
                        $updateQcData['completed_at'] = now();
                    }
                    $existingQcTask->update($updateQcData);
                }
            }

            // Auto-advance dari CREATED ke status berikutnya (QC_PREP atau tahap pertama)
            if ($workOrder->status === \App\Models\WorkOrder::STATUS_CREATED) {
                $nextStatus = $workOrder->getNextStatus();
                if ($nextStatus) {
                    $workOrder->update([
                        'status' => $nextStatus,
                        'stage_entered_at' => now(),
                        'started_at' => now(),
                    ]);
                }
            } elseif (empty($workOrder->status)) {
                // Jika status kosong atau null karena instansiasi baru, fallback ke default dari getNextStatus()
                $nextStatus = $workOrder->getNextStatus();
                if ($nextStatus) {
                    $workOrder->update([
                        'status' => $nextStatus,
                        'stage_entered_at' => now(),
                        'started_at' => now(),
                    ]);
                }
            }

            // Tambahan Koreksi: Jika baru saja ditambahkan QC Persiapan (has_qc_prep & qcWorkerId)
            // sedangkan status WorkOrder saat ini bukan QC_PREP dan task QC_PERSIAPAN masih pending,
            // kembalikan status WorkOrder ke QC_PREP agar kunci tugas terbuka untuk QC worker.
            if ($hasQcPrep && $qcWorkerId) {
                $qcTask = \App\Models\ProductionTask::withoutGlobalScopes()
                    ->where('order_item_id', $item->id)
                    ->where('stage_name', 'QC_PERSIAPAN')
                    ->first();
                if ($qcTask && in_array($qcTask->status, ['pending', 'in_progress']) && $workOrder->status !== \App\Models\WorkOrder::STATUS_QC_PREP) {
                    $workOrder->update([
                        'status' => \App\Models\WorkOrder::STATUS_QC_PREP,
                        'stage_entered_at' => now(),
                    ]);
                }
            }
        }

        // Sync status order
        $item->refresh();
        if ($order = $item->order) {
            $totalTasks = $order->orderItems()->withCount('productionTasks')->get()->sum('production_tasks_count');
            if ($totalTasks > 0 && $order->status === 'diterima') $order->update(['status' => 'antrian']);
        }

        Notification::make()->title('Berhasil Diatur')->success()->send();

        $this->redirect(ControlProduksiResource::getUrl('index'));
    }
}
