<?php

namespace App\Filament\Resources\DesignTasks\Widgets;

use App\Models\OrderItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Grouping\Group as TableGroup;
use Illuminate\Database\Eloquent\Model;

class DesignHistoryTableWidget extends BaseWidget
{
    protected $listeners = ['designTaskCompleted' => '$refresh'];
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                OrderItem::query()
                    ->with(['order.customer'])
                    ->where('design_status', 'approved')
                    ->whereIn('id', function (\Illuminate\Database\Query\Builder $query) {
                        $query->selectRaw('MIN(id)')
                            ->from('order_items')
                            ->where('design_status', 'approved')
                            ->groupBy('order_id', 'product_name');
                    })
            )
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

                TextColumn::make('total_quantity')
                    ->label('Total Qty')
                    ->getStateUsing(
                        fn(OrderItem $record): int => OrderItem::where('order_id', $record->order_id)
                            ->where('product_name', $record->product_name)
                            ->sum('quantity')
                    )
                    ->sortable(['quantity']),

                TextColumn::make('production_category')
                    ->label('Kategori')
                    ->badge()
                    ->color(fn(string $state) => match ($state) {
                        'non_produksi' => 'info',
                        'jasa' => 'warning',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'non_produksi' => 'Baju Jadi',
                        'jasa' => 'Jasa',
                        default => 'Konveksi',
                    }),

                TextColumn::make('order.deadline')
                    ->label('Deadline')
                    ->date('d M Y')
                    ->sortable()
                    ->color('danger'),

                TextColumn::make('design_status')
                    ->label('Status Desain')
                    ->badge()
                    ->color('success')
                    ->formatStateUsing(fn(string $state): string => 'Selesai'),

                TextColumn::make('design_image')
                    ->label('Desain')
                    ->formatStateUsing(function ($state) {
                        if (!$state)
                            return '-';

                        $url = asset('storage/' . $state);

                        if (str_ends_with(strtolower($state), '.pdf')) {
                            return '<div class="flex items-center gap-2 text-red-600 font-bold bg-red-50 px-2 py-1 rounded w-fit border border-red-100">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-5 h-5">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                </svg>
                                PDF
                            </div>';
                        }

                        return '<img src="' . $url . '" class="w-10 h-10 object-cover rounded-md shadow-sm border border-gray-200" alt="Desain" />';
                    })
                    ->html()
                    ->url(fn(OrderItem $record): ?string => $record->design_image ? asset('storage/' . $record->design_image) : null)
                    ->openUrlInNewTab()
                    ->toggleable(),
            ])
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->label('Rincian')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Rincian Teknis Produk')
                    ->modalWidth('2xl')
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->form([
                        \Filament\Forms\Components\Placeholder::make('technical_specs')
                            ->hiddenLabel()
                            ->content(function (OrderItem $record): \Illuminate\Support\HtmlString {
                                if (!$record)
                                    return new \Illuminate\Support\HtmlString('');

                                $name = htmlspecialchars($record->product_name ?? 'Produk');
                                $cat = $record->production_category ?? 'produksi';
                                $details = $record->size_and_request_details ?? [];
                                $bahan = $record->bahan;
                                $allOrderItems = OrderItem::where('order_id', $record->order_id)->where('product_name', $record->product_name)->get();

                                $catLabel = match ($cat) {
                                    'non_produksi' => 'BAJU JADI',
                                    'jasa' => 'JASA',
                                    default => 'KONVEKSI',
                                };

                                // STYLE CONSTANTS
                                $primaryColor = '#7c3aed';
                                $html = '<div style="font-family:inherit; color:#1f2937;">';

                                // HEADER (Standard Title Style)
                                $html .= '<div style="margin-bottom:20px; border-bottom:1px solid #e5e7eb; padding-bottom:12px;">';
                                $html .= '<div style="display:flex; align-items:center; gap:8px;">';
                                $html .= '<span style="font-size:20px; font-weight:800; letter-spacing:-0.01em;">' . strtoupper($name) . '</span>';
                                $html .= '<small style="background:#f3e8ff; color:' . $primaryColor . '; font-weight:800; padding:2px 8px; border-radius:4px; font-size:10px; border:1px solid #ddd6fe;">' . $catLabel . '</small>';
                                $html .= '</div>';
                                $html .= '</div>';

                                // MATERIAL SECTION (Standard Color Swatch Style)
                                $hex = $bahan?->color_code ?: '#e5e7eb';
                                $bahanLabel = $bahan ? (($bahan->material->name ?? 'Bahan') . ' - ' . ($bahan->color_name ?? 'Tanpa Warna')) : ($details['bahan'] ?? '-');

                                $html .= '<div style="margin-bottom:24px;">';
                                $html .= '<div style="font-size:11px; font-weight:800; color:#6b7280; letter-spacing:0.05em; margin-bottom:8px;">INFORMASI BAHAN</div>';
                                $html .= '<div style="display:flex; align-items:center; gap:12px; padding:12px 16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">';
                                $html .= '<span style="width:16px; height:16px; border-radius:50%; background:' . $hex . '; border:1px solid rgba(0,0,0,0.15); flex-shrink:0;"></span>';
                                $html .= '<div style="flex:1;">';
                                $html .= '<div style="font-size:13px; font-weight:700; color:#111827;">' . htmlspecialchars($bahanLabel) . '</div>';

                                // Sablon Info inside Material Info
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

                                // VARIATION SECTION
                                $genders = [];
                                foreach ($allOrderItems as $item) {
                                    $idtl = $item->size_and_request_details ?? [];
                                    $g = $idtl['gender'] ?? 'L';
                                    if (!isset($genders[$g]))
                                        $genders[$g] = ['qty' => 0, 'models' => []];
                                    $genders[$g]['qty'] += $item->quantity;

                                    $mParts = [];
                                    if (isset($idtl['sleeve_model']))
                                        $mParts[] = 'Lengan ' . $idtl['sleeve_model'];
                                    if (isset($idtl['pocket_model']) && $idtl['pocket_model'] !== 'tanpa_saku')
                                        $mParts[] = 'Saku ' . str_replace('_', ' ', $idtl['pocket_model']);
                                    if (!empty($idtl['is_tunic']))
                                        $mParts[] = 'Tunik';
                                    $mKey = empty($mParts) ? 'Model Standar' : implode(', ', $mParts);

                                    if (!isset($genders[$g]['models'][$mKey])) {
                                        $genders[$g]['models'][$mKey] = [
                                            'qty' => 0,
                                            'sizes' => [],
                                            'notes' => [],
                                            'attrs' => [
                                                'LENGAN' => isset($idtl['sleeve_model']) ? strtoupper($idtl['sleeve_model']) : 'PENDEK',
                                                'SAKU' => isset($idtl['pocket_model']) ? strtoupper(str_replace('_', ' ', $idtl['pocket_model'])) : 'TANPA SAKU',
                                                'KANCING' => isset($idtl['button_model']) ? strtoupper($idtl['button_model']) : 'BIASA',
                                            ]
                                        ];
                                    }
                                    $genders[$g]['models'][$mKey]['qty'] += $item->quantity;
                                    $sz = $item->size ?? '-';
                                    $genders[$g]['models'][$mKey]['sizes'][$sz] = ($genders[$g]['models'][$mKey]['sizes'][$sz] ?? 0) + $item->quantity;
                                    if (!empty($idtl['request_tambahan']))
                                        $genders[$g]['models'][$mKey]['notes'][] = $idtl['request_tambahan'];
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

                                            // Hanya tampilkan sub-header model jika ada lebih dari 1 varian
                                            if ($hasMultipleModels) {
                                                $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; padding:6px 10px; background:#f3e8ff; border-radius:6px;">';
                                                $html .= '<span style="font-size:11px; font-weight:800; color:' . $primaryColor . '; text-transform:uppercase;">' . $mKey . '</span>';
                                                $html .= '<span style="font-size:11px; font-weight:800; color:' . $primaryColor . ';">' . $mData['qty'] . ' pcs</span>';
                                                $html .= '</div>';
                                            }

                                            // Attributes (Compact style)
                                            $atxt = [];
                                            foreach ($mData['attrs'] as $ak => $av) {
                                                $atxt[] = '<span style="color:#9ca3af; font-size:10px;">' . $ak . ':</span><span style="color:#4b5563; margin-left:2px;">' . $av . '</span>';
                                            }
                                            $html .= '<div style="display:flex; gap:12px; font-size:11px; font-weight:700; margin-bottom:8px;">' . implode('<span style="color:#e5e7eb;">|</span>', $atxt) . '</div>';

                                            // Sizes (Pill style)
                                            $stxt = [];
                                            foreach ($mData['sizes'] as $sz => $sqty) {
                                                $stxt[] = '<div style="padding:4px 8px; background:#f8fafc; border:1px solid #f1f5f9; border-radius:4px; font-size:12px; font-weight:800; color:#1e293b;">' . $sz . ': <span style="color:' . $primaryColor . ';">' . $sqty . '</span></div>';
                                            }
                                            $html .= '<div style="display:flex; flex-wrap:wrap; gap:6px;">' . implode('', $stxt) . '</div>';

                                            // Notes
                                            if (!empty($mData['notes'])) {
                                                $html .= '<div style="margin-top:8px; padding:8px 12px; background:#fffcf0; border:1px solid #fef3c7; border-radius:6px;">';
                                                $html .= '<div style="font-size:10px; font-weight:800; color:#b45309; text-transform:uppercase; margin-bottom:4px;">CATATAN:</div>';
                                                foreach ($mData['notes'] as $n) {
                                                    $html .= '<div style="font-size:12px; font-weight:700; color:#92400e;">- ' . htmlspecialchars($n) . '</div>';
                                                }
                                                $html .= '</div>';
                                            }
                                            $html .= '</div>';
                                            if ($hasMultipleModels && next($gData['models']))
                                                $html .= '<hr style="border:none; border-top:1px dashed #e2e8f0; margin:4px 0;">';
                                        }
                                        $html .= '</div></div>';
                                    }
                                } else {
                                     $html .= '<div style="padding:16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; text-align:center; font-size:12px; color:#6b7280; font-weight:600;">Tidak ada rincian produksi untuk varian ini.</div>';
                                }
                                $html .= '</div>';
                                $html .= '</div>';

                                // Action Link
                                try {
                                    $url = route('filament.admin.resources.orders.edit', ['tenant' => filament()->getTenant()->id, 'record' => $record->order_id]);
                                } catch (\Exception $e) {
                                    $url = '#';
                                }
                                $html .= '<div style="margin-top:24px;">';
                                $html .= '<a href="' . $url . '" target="_blank" style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:10px; background:#ffffff; color:#374151; border:1px solid #d1d5db; border-radius:6px; text-decoration:none; font-size:11px; font-weight:800; transition:0.1s;">';
                                $html .= '<svg style="width:14px; height:14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>';
                                $html .= 'LIHAT DETAIL PESANAN';
                                $html .= '</a>';
                                $html .= '</div>';

                                $html .= '</div>';
                                return new \Illuminate\Support\HtmlString($html);
                            })
                    ])
            ])
            ->recordAction('view')
            ->recordUrl(null)
            ->defaultGroup(
                TableGroup::make('order.order_number')
                    ->label('Pesanan')
                    ->getTitleFromRecordUsing(fn(Model $record): string => $record->order->order_number . ' - ' . ($record->order->customer->name ?? 'Tanpa Nama'))
                    ->collapsible()
            )
            ->heading('Riwayat Desain (Selesai)');
    }
}
