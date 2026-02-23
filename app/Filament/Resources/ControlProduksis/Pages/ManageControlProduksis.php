<?php

namespace App\Filament\Resources\ControlProduksis\Pages;

use App\Filament\Resources\ControlProduksis\ControlProduksiResource;
use App\Models\OrderItem;
use App\Models\ProductionStage;
use App\Models\ProductionTask;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ManageControlProduksis extends ManageRecords
{
    protected static string $resource = ControlProduksiResource::class;
    
    protected string $view = 'filament.resources.control-produksi.pages.manage-control-produksis';

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Resources\ControlProduksis\Widgets\ProduksiStats::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('Semua')
                ->badge(fn() => ControlProduksiResource::getEloquentQuery()->count()),
            'siap_potong' => Tab::make('Siap Potong')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orderItems.productionTasks', function ($q) {
                    $q->where('stage_name', 'Potong')->where('status', 'pending');
                })),
            'sedang_jahit' => Tab::make('Sedang Jahit')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orderItems.productionTasks', function ($q) {
                    $q->where('stage_name', 'Jahit')->whereIn('status', ['pending', 'in_progress']);
                })),
            'siap_qc' => Tab::make('Siap QC')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas('orderItems.productionTasks', function ($q) {
                    $q->where('stage_name', 'QC')->where('status', 'pending');
                })),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('atur_tugas')
                ->hiddenLabel()
                ->extraAttributes(['style' => 'display: none;'])
                ->slideOver()
                ->modalWidth('xl')
                ->modalHeading('Atur Tugas Produksi')
                ->fillForm(function (array $arguments) {
                    $itemId = $arguments['item_id'] ?? null;
                    if (!$itemId) return [];
                    
                    $item = OrderItem::with('productionTasks')->find($itemId);
                    if (!$item) return [];

                    $tasksForRepeater = [];
                    foreach ($item->productionTasks as $task) {
                        $tasksForRepeater[(string) Str::uuid()] = [
                            'id' => $task->id,
                            'stage_name' => $task->stage_name,
                            'assigned_to' => $task->assigned_to,
                            'quantity' => $task->quantity,
                            'size_quantities' => $task->size_quantities,
                            'description' => $task->description,
                        ];
                    }

                    return [
                        'item_id' => $itemId,
                        'productionTasks' => $tasksForRepeater,
                    ];
                })
                ->form(function (array $arguments) {
                    $itemId = $arguments['item_id'] ?? null;
                    $item = $itemId ? OrderItem::find($itemId) : null;
                    
                    return [
                        Hidden::make('item_id'),
                        
                        Placeholder::make('technical_specs')
                            ->label('Spesifikasi Produksi')
                            ->content(function () use ($item): HtmlString {
                                if (!$item) return new HtmlString('');
                                
                                $html = '<div class="text-sm p-4 border border-gray-200 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-gray-100">';
                                
                                $name = htmlspecialchars($item->product_name ?? 'Produk Tak Bernama');
                                $cat = $item->production_category ?? 'produksi';
                                $details = $item->size_and_request_details ?? [];
                                
                                $html .= '<h4 class="mb-3 font-semibold text-lg text-primary-600 dark:text-primary-400">' . $name . '</h4>';

                                if ($cat === 'custom') {
                                    $bahan = htmlspecialchars($details['bahan'] ?? '-');
                                    $html .= '<p class="mb-1"><strong>Bahan:</strong> ' . $bahan . '</p>';
                                    
                                    $sablon = $details['sablon_bordir'] ?? [];
                                    if (count($sablon) > 0) {
                                        $html .= '<p class="mt-3 mb-1 font-semibold text-gray-700 dark:text-gray-300">Sablon / Bordir:</p>';
                                        $html .= '<ul class="mb-2 pl-5 list-disc space-y-1">';
                                        foreach ($sablon as $s) {
                                            $j = htmlspecialchars($s['jenis'] ?? '');
                                            $l = htmlspecialchars($s['lokasi'] ?? '');
                                            $u = htmlspecialchars($s['ukuran_cmxcm'] ?? '');
                                            $html .= '<li>' . $j . ' di ' . $l . ($u ? ' (' . $u . ')' : '') . '</li>';
                                        }
                                        $html .= '</ul>';
                                    }

                                    $ukurans = $details['detail_custom'] ?? [];
                                    if (count($ukurans) > 0) {
                                        $html .= '<p class="mt-3 mb-1 font-semibold text-gray-700 dark:text-gray-300">Ukuran Detail per Orang (Referensi):</p>';
                                        $html .= '<div class="max-h-[200px] overflow-y-auto pr-2 space-y-2 mt-2">';
                                        foreach ($ukurans as $u) {
                                            $person = htmlspecialchars($u['nama'] ?? 'Tanpa Nama');
                                            $ld = htmlspecialchars($u['LD'] ?? '-');
                                            $lp = htmlspecialchars($u['LP'] ?? '-');
                                            $p = htmlspecialchars($u['P'] ?? '-');
                                            $html .= '<div class="text-sm p-2 bg-white dark:bg-gray-900 rounded border border-gray-100 dark:border-gray-700">';
                                            $html .= '<span class="font-semibold">' . $person . '</span> <span class="text-gray-500 mx-1">|</span> LD: ' . $ld . ' • LP: ' . $lp . ' • P: ' . $p;
                                            $html .= '</div>';
                                        }
                                        $html .= '</div>';
                                    }
                                    
                                } elseif ($cat === 'non_produksi') {
                                    $j = htmlspecialchars($details['sablon_jenis'] ?? '-');
                                    $l = htmlspecialchars($details['sablon_lokasi'] ?? '-');
                                    $html .= '<p class="mb-1"><strong>Teknik Sablon/Bordir:</strong> ' . $j . '</p>';
                                    $html .= '<p class="mb-1"><strong>Lokasi:</strong> ' . $l . '</p>';
                                } elseif ($cat === 'jasa') {
                                    $html .= '<p class="mb-1 text-info-600 dark:text-info-400 font-medium">Ini adalah pengerjaan item jasa murni.</p>';
                                } else {
                                    $bahan = htmlspecialchars($details['bahan'] ?? '-');
                                    $j = htmlspecialchars($details['sablon_jenis'] ?? '-');
                                    $l = htmlspecialchars($details['sablon_lokasi'] ?? '-');
                                    
                                    $html .= '<p class="mb-1"><strong>Bahan:</strong> ' . $bahan . '</p>';
                                    $html .= '<p class="mb-1"><strong>Teknik Sablon/Bordir:</strong> ' . $j . '</p>';
                                    $html .= '<p class="mb-1"><strong>Lokasi:</strong> ' . $l . '</p>';
                                }

                                $dLinks = $item->design_links ?? [];
                                if (is_array($dLinks) && count($dLinks) > 0) {
                                    $html .= '<div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">';
                                    $html .= '<p class="mb-2 font-semibold text-gray-700 dark:text-gray-300">File Desain & Mockup (Approved):</p>';
                                    $html .= '<div class="flex flex-wrap gap-2">';
                                    foreach ($dLinks as $link) {
                                        $url = htmlspecialchars($link['link'] ?? '#');
                                        $lbl = htmlspecialchars($link['title'] ?? 'Tautan');
                                        $html .= '<a href="'.$url.'" target="_blank" class="inline-flex items-center justify-center px-3 py-1.5 text-xs font-medium bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400 rounded-full hover:bg-primary-100 dark:hover:bg-primary-900/50 transition-colors">';
                                        $html .= '<svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>';
                                        $html .= $lbl . '</a>';
                                    }
                                    $html .= '</div></div>';
                                }
                                
                                $html .= '</div>';
                                return new HtmlString($html);
                            })
                            ->columnSpanFull(),

                        Repeater::make('productionTasks')
                            ->label('Daftar Tugas Produksi')
                            ->schema([
                                Hidden::make('id'),
                                Select::make('stage_name')
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
                                    
                                Select::make('assigned_to')
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
                                        fn (Get $get) => function (string $attribute, $value, \Closure $fail) use ($get, $item) {
                                            if (!$item) return;

                                            $stageName = $get('stage_name');
                                            if (!$stageName) return;

                                            $stage = ProductionStage::where('name', $stageName)->first();
                                            if (!$stage) return;

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
                                                
                                            if (!$previousStage) return;

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

                                Textarea::make('description')
                                    ->label('Catatan Instruksi')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ])
                            ->columns(3)
                            ->columnSpanFull()
                            ->itemLabel(fn (array $state): ?string => $state['stage_name'] ?? null)
                            ->addActionLabel('Tambah Tugas Baru'),
                    ];
                })
                ->action(function (array $data, array $arguments) {
                    $itemId = $data['item_id'] ?? null;
                    if (!$itemId) return;
                    
                    $item = OrderItem::find($itemId);
                    if (!$item) return;

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
                            $task = ProductionTask::find($taskItem['id']);
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
                })
        ];
    }
}
