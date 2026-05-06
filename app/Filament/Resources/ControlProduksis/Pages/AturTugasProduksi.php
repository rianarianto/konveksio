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
        $allGroupItems = OrderItem::where('order_id', $item->order_id)
            ->where('product_name', $item->product_name)
            ->where('bahan_id', $item->bahan_id)
            ->where('design_status', 'approved')
            ->get();
            
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

        $tasksForRepeater = [];
        foreach ($item->productionTasks as $task) {
            $wagePerPcs = $task->quantity > 0 ? (int) ($task->wage_amount / $task->quantity) : 0;
            $taskRow = [
                'id' => $task->id,
                'stage_name' => $task->stage_name,
                'assigned_to' => $task->assigned_to,
                'quantity' => $task->quantity,
                'wage_per_pcs' => $wagePerPcs,
                'wage_custom_per_pcs' => $task->size_quantities['_wage_custom'] ?? $wagePerPcs,
                'description' => $task->description,
            ];

            if (is_array($task->size_quantities)) {
                foreach ($task->size_quantities as $sz => $qty) {
                    $upperSz = strtoupper($sz);
                    if ((str_starts_with($upperSz, 'PERSON_') || !isset($maxSizes[$upperSz])) && isset($maxSizes['CUSTOM'])) {
                        $taskRow['CUSTOM'] = ($taskRow['CUSTOM'] ?? 0) + $qty;
                    } else {
                        $taskRow[$upperSz] = $qty;
                    }
                }
            }

            $isAllFilled = !empty($maxSizes);
            foreach ($maxSizes as $key => $max) {
                if (($taskRow[$key] ?? 0) < $max) {
                    $isAllFilled = false;
                    break;
                }
            }
            $taskRow['_fill_all'] = $isAllFilled;

            $tasksForRepeater[(string) Str::uuid()] = $taskRow;
        }

        $this->form->fill([
            'productionTasks' => $tasksForRepeater,
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
                Grid::make(12)
                    ->schema([
                        // LEFT COLUMN: SPECIFICATIONS
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
                                            $allOrderItems = OrderItem::where('order_id', $item->order_id)->where('product_name', $item->product_name)->get();

                                            $catLabel = match ($cat) {
                                                'non_produksi' => 'BAJU JADI',
                                                'jasa' => 'JASA',
                                                default => 'KONVEKSI',
                                            };

                                            $primaryColor = '#7c3aed';
                                            $html = '<div style="font-family:inherit; color:#1f2937;">';

                                            $html .= '<div style="margin-bottom:20px; border-bottom:1px solid #e5e7eb; padding-bottom:12px;">';
                                            $html .= '<div style="display:flex; align-items:center; gap:8px;">';
                                            $html .= '<span style="font-size:20px; font-weight:800; letter-spacing:-0.01em;">' . strtoupper($name) . '</span>';
                                            $html .= '<small style="background:#f3e8ff; color:' . $primaryColor . '; font-weight:800; padding:2px 8px; border-radius:4px; font-size:10px; border:1px solid #ddd6fe;">' . $catLabel . '</small>';
                                            $html .= '</div>';
                                            $html .= '</div>';

                                            $hex = $bahan?->color_code ?: '#e5e7eb';
                                            $bahanLabel = $bahan ? (($bahan->material->name ?? 'Bahan') . ' - ' . ($bahan->color_name ?? 'Tanpa Warna')) : ($details['bahan'] ?? '-');

                                            $html .= '<div style="margin-bottom:24px;">';
                                            $html .= '<div style="font-size:11px; font-weight:800; color:#6b7280; letter-spacing:0.05em; margin-bottom:8px;">INFORMASI BAHAN</div>';
                                            $html .= '<div style="display:flex; align-items:center; gap:12px; padding:12px 16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">';
                                            $html .= '<span style="width:16px; height:16px; border-radius:50%; background:' . $hex . '; border:1px solid rgba(0,0,0,0.15); flex-shrink:0;"></span>';
                                            $html .= '<div style="flex:1;">';
                                            $html .= '<div style="font-size:13px; font-weight:700; color:#111827;">' . htmlspecialchars($bahanLabel) . '</div>';

                                            $sablon = $details['sablon_bordir'] ?? [];
                                            if (!empty($sablon)) {
                                                $sblnTexts = [];
                                                foreach ($sablon as $s) {
                                                    $sblnTexts[] = ($s['jenis'] ?? '') . ' (' . ($s['lokasi'] ?? '') . ')';
                                                }
                                                $html .= '<div style="font-size:11px; font-weight:600; color:' . $primaryColor . '; margin-top:2px;">SABLON/BORDIR: ' . htmlspecialchars(implode(', ', $sblnTexts)) . '</div>';
                                            }
                                            $html .= '</div>';
                                            $html .= '</div>';
                                            $html .= '</div>';

                                            $genders = [];
                                            foreach ($allOrderItems as $ai) {
                                                $idtl = $ai->size_and_request_details ?? [];
                                                $g = $idtl['gender'] ?? 'L';
                                                if (!isset($genders[$g])) $genders[$g] = ['qty' => 0, 'models' => []];
                                                $genders[$g]['qty'] += $ai->quantity;

                                                $mParts = [];
                                                if (isset($idtl['sleeve_model'])) $mParts[] = 'Lengan ' . $idtl['sleeve_model'];
                                                if (isset($idtl['pocket_model']) && $idtl['pocket_model'] !== 'tanpa_saku') $mParts[] = 'Saku ' . str_replace('_', ' ', $idtl['pocket_model']);
                                                if (!empty($idtl['is_tunic'])) $mParts[] = 'Tunik';
                                                $mKey = empty($mParts) ? 'Model Standar' : implode(', ', $mParts);

                                                if (!isset($genders[$g]['models'][$mKey])) {
                                                    $genders[$g]['models'][$mKey] = [
                                                        'qty' => 0, 'sizes' => [], 'notes' => [], 'custom' => [],
                                                        'attrs' => [
                                                            'LENGAN' => isset($idtl['sleeve_model']) ? strtoupper($idtl['sleeve_model']) : 'PENDEK',
                                                            'SAKU' => isset($idtl['pocket_model']) ? strtoupper(str_replace('_', ' ', $idtl['pocket_model'])) : 'TANPA SAKU',
                                                            'KANCING' => isset($idtl['button_model']) ? strtoupper($idtl['button_model']) : 'BIASA',
                                                        ]
                                                    ];
                                                }
                                                $genders[$g]['models'][$mKey]['qty'] += $ai->quantity;
                                                $sz = $ai->size ?? '-';
                                                $genders[$g]['models'][$mKey]['sizes'][$sz] = ($genders[$g]['models'][$mKey]['sizes'][$sz] ?? 0) + $ai->quantity;
                                                if (!empty($idtl['request_tambahan'])) $genders[$g]['models'][$mKey]['notes'][] = $idtl['request_tambahan'];

                                                if ($ai->size === 'Custom' && $ai->recipient_name) {
                                                    $m = [];
                                                    foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                                                        if (!empty($idtl[$mk])) $m[] = $mk . ':' . $idtl[$mk];
                                                    }
                                                    $genders[$g]['models'][$mKey]['custom'][] = $ai->recipient_name . (!empty($m) ? ' (' . implode(' ', $m) . ')' : '');
                                                }
                                            }

                                            $html .= '<div>';
                                            $html .= '<div style="font-size:11px; font-weight:800; color:#6b7280; letter-spacing:0.05em; margin-bottom:8px;">RINCIAN PRODUKSI</div>';
                                            $html .= '<div style="display:flex; flex-direction:column; gap:8px;">';

                                            $hasKonveksiItems = count($genders) > 0 && in_array($cat, ['produksi', 'custom']);

                                            if ($hasKonveksiItems) {
                                                foreach ($genders as $gCode => $gData) {
                                                    $gName = $gCode === 'P' ? 'PEREMPUAN' : 'LAKI-LAKI';
                                                    $html .= '<div x-data="{ open: true }" style="border:1px solid #e5e7eb; border-radius:8px; overflow:hidden; background:white;">';
                                                    $html .= '<div @click="open = !open" style="cursor:pointer; display:flex; justify-content:space-between; align-items:center; padding:10px 16px; background:#f9fafb;">';
                                                    $html .= '<div style="display:flex; align-items:center; gap:8px;">';
                                                    $html .= '<span style="font-size:12px; font-weight:800; color:#374151;">' . $gName . '</span>';
                                                    $html .= '<span style="font-size:12px; font-weight:900; color:' . $primaryColor . ';">' . $gData['qty'] . ' pcs</span>';
                                                    $html .= '</div>';
                                                    $html .= '<svg :class="open ? \'rotate-180\' : \'\'" style="width:14px; height:14px; transition:0.2s;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>';
                                                    $html .= '</div>';

                                                    $html .= '<div x-show="open" style="padding:16px; border-top:1px solid #f1f5f9; display:flex; flex-direction:column; gap:16px;">';
                                                    $hasMultipleModels = count($gData['models']) > 1;
                                                    foreach ($gData['models'] as $mKey => $mData) {
                                                        $html .= '<div>';
                                                        if ($hasMultipleModels) {
                                                            $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding:6px 10px; background:#f3e8ff; border-radius:6px;">';
                                                            $html .= '<span style="font-size:11px; font-weight:800; color:' . $primaryColor . '; text-transform:uppercase;">' . $mKey . '</span>';
                                                            $html .= '<span style="font-size:11px; font-weight:800; color:' . $primaryColor . ';">' . $mData['qty'] . ' pcs</span>';
                                                            $html .= '</div>';
                                                        }
                                                        $atxt = [];
                                                        foreach ($mData['attrs'] as $ak => $av) {
                                                            $atxt[] = '<span style="color:#9ca3af; font-size:10px;">' . $ak . ':</span><span style="color:#4b5563; margin-left:2px;">' . $av . '</span>';
                                                        }
                                                        $html .= '<div style="display:flex; gap:12px; font-size:11px; font-weight:700; margin-bottom:8px;">' . implode('<span style="color:#e5e7eb;">|</span>', $atxt) . '</div>';
                                                        $stxt = [];
                                                        foreach ($mData['sizes'] as $sz => $sqty) {
                                                            $stxt[] = '<div style="padding:4px 8px; background:#f8fafc; border:1px solid #f1f5f9; border-radius:4px; font-size:12px; font-weight:800; color:#1e293b;">' . $sz . ': <span style="color:' . $primaryColor . ';">' . $sqty . '</span></div>';
                                                        }
                                                        $html .= '<div style="display:flex; flex-wrap:wrap; gap:6px;">' . implode('', $stxt) . '</div>';
                                                        if (!empty($mData['custom'])) {
                                                            $html .= '<div style="margin-top:8px; padding:8px 12px; background:#faf5ff; border:1px solid #e9d5ff; border-radius:6px;">';
                                                            $html .= '<div style="font-size:10px; font-weight:800; color:#6b21a8; text-transform:uppercase; margin-bottom:4px;">UKURAN CUSTOM:</div>';
                                                            foreach ($mData['custom'] as $c) $html .= '<div style="font-size:12px; font-weight:700; color:#581c87;">• ' . htmlspecialchars($c) . '</div>';
                                                            $html .= '</div>';
                                                        }
                                                        if (!empty($mData['notes'])) {
                                                            $html .= '<div style="margin-top:8px; padding:8px 12px; background:#fffcf0; border:1px solid #fef3c7; border-radius:6px;">';
                                                            $html .= '<div style="font-size:10px; font-weight:800; color:#b45309; text-transform:uppercase; margin-bottom:4px;">CATATAN:</div>';
                                                            foreach ($mData['notes'] as $n) $html .= '<div style="font-size:12px; font-weight:700; color:#92400e;">- ' . htmlspecialchars($n) . '</div>';
                                                            $html .= '</div>';
                                                        }
                                                        $html .= '</div>';
                                                        if ($hasMultipleModels && next($gData['models'])) $html .= '<hr style="border:none; border-top:1px dashed #e2e8f0; margin:4px 0;">';
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
                        ])->columnSpan(4),

                        // RIGHT COLUMN: TASK ASSIGNMENT
                        Group::make([
                            Section::make('Daftar Tugas Produksi')
                                ->description('Tentukan tahapan dan karyawan yang bertugas')
                                ->icon('heroicon-m-clipboard-document-list')
                                ->schema([
                                    Repeater::make('productionTasks')
                                        ->label(false)
                                        ->collapsible()
                                        ->schema([
                                            Hidden::make('id'),
                                            Grid::make(2)
                                                ->schema([
                                                    Select::make('stage_name')
                                                        ->label('Tahap Pekerjaan')
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
                                                        ->afterStateUpdated(function ($state, Set $set) {
                                                            if ($state) {
                                                                $stage = ProductionStage::where('name', $state)->first();
                                                                if ($stage) $set('wage_per_pcs', $stage->default_wage);
                                                            }
                                                        }),
                                                    Select::make('assigned_to')
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
                                                        ->searchable()
                                                        ->required(),
                                                ]),

                                            Fieldset::make('Distribusi Qty Per Ukuran')
                                                ->schema(function (Get $get, Set $set) use ($item) {
                                                    $allGroupItems = OrderItem::where('order_id', $item->order_id)
                                                        ->where('product_name', $item->product_name)
                                                        ->where('bahan_id', $item->bahan_id)
                                                        ->where('design_status', 'approved')
                                                        ->get()
                                                        ->filter(function($gi) use ($item) {
                                                            $d1 = $item->size_and_request_details ?? [];
                                                            $d2 = $gi->size_and_request_details ?? [];
                                                            $keys = ['gender', 'sleeve_model', 'pocket_model', 'button_model', 'is_tunic', 'sablon_jenis', 'sablon_lokasi'];
                                                            foreach ($keys as $k) { if (($d1[$k] ?? null) !== ($d2[$k] ?? null)) return false; }
                                                            return true;
                                                        });

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

                                                    $recalcQty = function (Get $get, Set $set) use ($standardSizes) {
                                                        $total = 0;
                                                        foreach (array_keys($standardSizes) as $sz) $total += (int) ($get($sz) ?? 0);
                                                        if ($total === 0) $total = (int) ($get('qty') ?? 0);
                                                        $set('quantity', $total);
                                                    };

                                                    $fields = [
                                                        Toggle::make('_fill_all')
                                                            ->label('Kerjakan Semua')
                                                            ->live()
                                                            ->afterStateUpdated(function ($state, Set $set) use ($standardSizes) {
                                                                foreach ($standardSizes as $sz => $max) $set($sz, $state ? $max : 0);
                                                                $total = $state ? array_sum($standardSizes) : 0;
                                                                $set('quantity', $total);
                                                            })
                                                            ->columnSpanFull(),
                                                    ];

                                                    foreach ($standardSizes as $sz => $max) {
                                                        $fields[] = TextInput::make($sz)
                                                            ->label("Size {$sz} (Maks: {$max})")
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->maxValue($max)
                                                            ->placeholder('0')
                                                            ->live(debounce: 300)
                                                            ->afterStateUpdated(function ($state, Set $set, Get $get) use ($sz, $max, $recalcQty) {
                                                                if ((int) $state > $max) {
                                                                    $set($sz, $max);
                                                                }
                                                                $recalcQty($get, $set);
                                                            })
                                                            ->extraAttributes(['style' => 'align-self: end;']);
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
                                                            ->afterStateUpdated(fn($state, Set $set, Get $get) => $recalcQty($get, $set))
                                                            ->extraAttributes(['style' => 'align-self: end;']);
                                                    }

                                                    return $fields;
                                                })
                                                ->columns(6)
                                                ->extraAttributes(['class' => 'distribusi-qty-grid'])
                                                ->columnSpanFull(),

                                            Grid::make(2)
                                                ->schema([
                                                    TextInput::make('quantity')
                                                        ->label('Total Qty')
                                                        ->numeric()
                                                        ->readOnly()
                                                        ->prefix('Σ'),
                                                    TextInput::make('wage_per_pcs')
                                                        ->label('Upah Standar per Pcs')
                                                        ->numeric()
                                                        ->prefix('Rp'),
                                                    TextInput::make('wage_custom_per_pcs')
                                                        ->label('Upah Custom per Pcs')
                                                        ->numeric()
                                                        ->prefix('Rp')
                                                        ->visible(fn (Get $get) => $get('stage_name') === 'Potong' && (int) ($get('CUSTOM') ?? 0) > 0),
                                                ]),

                                            Textarea::make('description')
                                                ->label('Catatan Instruksi')
                                                ->rows(2)
                                                ->columnSpanFull(),
                                        ])
                                        ->columnSpanFull()
                                        ->itemLabel(fn(array $state): ?string => $state['stage_name'] ?? null)
                                        ->addActionLabel('+ Tambah Tugas Baru')
                                        ->addAction(fn($action) => $action->color('primary')),
                                ])
                        ])->columnSpan(8),
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
        $allGroupItems = OrderItem::where('order_id', $item->order_id)
            ->where('product_name', $item->product_name)
            ->where('bahan_id', $item->bahan_id)
            ->where('design_status', 'approved')
            ->get()
            ->filter(function($gi) use ($item) {
                $d1 = $item->size_and_request_details ?? [];
                $d2 = $gi->size_and_request_details ?? [];
                $keys = ['gender', 'sleeve_model', 'pocket_model', 'button_model', 'is_tunic', 'sablon_jenis', 'sablon_lokasi'];
                foreach ($keys as $k) { if (($d1[$k] ?? null) !== ($d2[$k] ?? null)) return false; }
                return true;
            });

        $originalSizes = [];
        foreach ($allGroupItems as $gi) {
            $sz = strtoupper($gi->size ?? 'TANPA_UKURAN');
            $originalSizes[$sz] = ($originalSizes[$sz] ?? 0) + (int) $gi->quantity;
        }
        $totalOrderQty = array_sum($originalSizes);

        // ══════════════════════════════════════════════════════════════
        // VALIDASI
        // ══════════════════════════════════════════════════════════════
        $usedQtyPerStageSize = [];
        $usedQtyPerStage = [];
        $assignedStages = [];
        
        foreach ($tasksData as $taskItem) {
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
            if ($calculatedQty === 0 && isset($taskItem['qty']) && (int) $taskItem['qty'] > 0) $calculatedQty = (int) $taskItem['qty'];
            if ($calculatedQty === 0 && isset($taskItem['quantity']) && (int) $taskItem['quantity'] > 0) $calculatedQty = (int) $taskItem['quantity'];

            if ($calculatedQty === 0) {
                Notification::make()->title('Ada Tugas Tanpa Qty!')->body("Baris tugas <strong>{$stage}</strong> tidak memiliki qty.")->danger()->send();
                return;
            }

            if ($stage) {
                $assignedStages[] = $stage;
                $usedQtyPerStage[$stage] = ($usedQtyPerStage[$stage] ?? 0) + $calculatedQty;
                foreach ($sqs as $key => $val) {
                    $upperKey = strtoupper($key);
                    $usedQtyPerStageSize[$stage][$upperKey] = ($usedQtyPerStageSize[$stage][$upperKey] ?? 0) + (int) $val;
                }
            }
        }

        if (empty($usedQtyPerStage)) {
            Notification::make()->title('❌ Belum Ada Tugas!')->danger()->send();
            return;
        }

        $mismatchErrors = [];
        foreach ($usedQtyPerStage as $stageName => $totalUsed) {
            if ($totalUsed !== $totalOrderQty) {
                $diff = $totalOrderQty - $totalUsed;
                $mismatchErrors[] = "Tahap <strong>{$stageName}</strong>: " . ($diff > 0 ? "kurang {$diff} pcs" : "kelebihan " . abs($diff) . " pcs");
            }
        }
        if (!empty($mismatchErrors)) {
            Notification::make()->title('❌ Jumlah Tugas Tidak Sesuai!')->body(new HtmlString(implode('<br>', $mismatchErrors)))->danger()->send();
            return;
        }

        // ══════════════════════════════════════════════════════════════
        // SIMPAN
        // ══════════════════════════════════════════════════════════════
        $existingTaskIds = [];
        foreach ($tasksData as $taskItem) {
            $sizeQuantities = [];
            foreach (array_keys($originalSizes) as $key) {
                if (isset($taskItem[$key]) && (int) $taskItem[$key] > 0) $sizeQuantities[$key] = (int) $taskItem[$key];
            }
            $quantity = array_sum($sizeQuantities);
            if ($quantity === 0 && isset($taskItem['qty']) && (int) $taskItem['qty'] > 0) $quantity = (int) $taskItem['qty'];
            
            $wagePerPcs = (float) ($taskItem['wage_per_pcs'] ?? 0);
            $wageCustomPerPcs = (float) ($taskItem['wage_custom_per_pcs'] ?? $wagePerPcs);
            
            $customQty = (int) ($sizeQuantities['CUSTOM'] ?? 0);
            $standardQty = $quantity - $customQty;
            $totalWage = ($standardQty * $wagePerPcs) + ($customQty * $wageCustomPerPcs);

            if ($customQty > 0) {
                $sizeQuantities['_wage_custom'] = $wageCustomPerPcs;
            }

            $taskData = [
                'stage_name' => $taskItem['stage_name'],
                'assigned_to' => $taskItem['assigned_to'],
                'quantity' => $quantity,
                'wage_amount' => $totalWage,
                'size_quantities' => empty($sizeQuantities) ? null : $sizeQuantities,
                'description' => $taskItem['description'] ?? null,
                'shop_id' => \Filament\Facades\Filament::getTenant()->id,
            ];

            if (!empty($taskItem['id'])) {
                $task = ProductionTask::find($taskItem['id']);
                if ($task) { $task->update($taskData); $existingTaskIds[] = $task->id; }
            } else {
                $taskData['assigned_by'] = auth()->id();
                $taskData['status'] = 'pending';
                $newTask = $item->productionTasks()->create($taskData);
                $existingTaskIds[] = $newTask->id;
            }
        }

        $item->productionTasks()->whereNotIn('id', $existingTaskIds)->delete();

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
