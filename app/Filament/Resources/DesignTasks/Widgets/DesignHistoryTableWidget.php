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

                                $html = '<div class="text-sm pb-2">';

                                $name = htmlspecialchars($record->product_name ?? 'Produk Tak Bernama');
                                $cat = match ($record->production_category ?? 'produksi') {
                                    'non_produksi' => 'Baju Jadi',
                                    'jasa' => 'Jasa Murni',
                                    default => 'Konveksi'
                                };
                                $details = $record->size_and_request_details ?? [];

                                $html .= '<h4 class="mb-4 font-bold text-lg flex flex-col gap-0.5">';
                                $html .= '<span class="text-gray-900 dark:text-gray-100">' . $name . '</span>';
                                $html .= '<span class="text-[11px] font-bold text-primary-600 dark:text-primary-400 uppercase tracking-widest">[' . strtoupper($cat) . ']</span>';
                                $html .= '</h4>';

                                $bahanName = htmlspecialchars($record->bahan->name ?? $details['bahan'] ?? '-');
                                $html .= '<div class="mb-2 text-[13px] space-y-1.5 text-gray-600 dark:text-gray-400 bg-gray-50 dark:bg-gray-800/50 p-3 rounded-lg border border-gray-100 dark:border-gray-800">';
                                $html .= '<p><strong class="text-gray-800 dark:text-gray-200">Bahan Utama :</strong> ' . $bahanName . '</p>';

                                $sablon = $details['sablon_bordir'] ?? [];
                                if (!empty($sablon)) {
                                    $sbln = [];
                                    foreach ($sablon as $s) {
                                        $sbln[] = ($s['jenis'] ?? '') . ' (' . ($s['lokasi'] ?? '') . ')';
                                    }
                                    $html .= '<p><strong class="text-gray-800 dark:text-gray-200">Sablon / Bordir:</strong> ' . htmlspecialchars(implode(' | ', $sbln)) . '</p>';
                                } elseif (!empty($details['sablon_jenis']) || !empty($details['sablon_lokasi'])) {
                                    $html .= '<p><strong class="text-gray-800 dark:text-gray-200">Sablon / Bordir:</strong> ' . htmlspecialchars(($details['sablon_jenis'] ?? '') . ' (' . ($details['sablon_lokasi'] ?? '') . ')') . '</p>';
                                }
                                $html .= '</div>';

                                $allItems = OrderItem::where('order_id', $record->order_id)
                                    ->where('product_name', $record->product_name)
                                    ->get();

                                $genders = [];
                                foreach ($allItems as $item) {
                                    $itemDetails = $item->size_and_request_details ?? [];
                                    $g = $itemDetails['gender'] ?? 'L';

                                    if (!isset($genders[$g])) {
                                        $genders[$g] = ['qty' => 0, 'variations' => []];
                                    }

                                    $genders[$g]['qty'] += $item->quantity;

                                    $parts = [];
                                    if (isset($itemDetails['sleeve_model']))
                                        $parts[] = 'Lengan ' . ucfirst($itemDetails['sleeve_model']);
                                    if (isset($itemDetails['pocket_model']) && $itemDetails['pocket_model'] !== 'tanpa_saku')
                                        $parts[] = 'Saku ' . ucfirst(str_replace('_', ' ', $itemDetails['pocket_model']));
                                    if (isset($itemDetails['button_model']) && $itemDetails['button_model'] !== 'biasa')
                                        $parts[] = 'Kancing ' . ucfirst(str_replace('_', ' ', $itemDetails['button_model']));
                                    if (!empty($itemDetails['is_tunic']))
                                        $parts[] = 'Tunik/Gamis';

                                    $varKey = empty($parts) ? 'Model Standar' : implode(', ', $parts);

                                    if (!isset($genders[$g]['variations'][$varKey])) {
                                        $genders[$g]['variations'][$varKey] = [
                                            'qty' => 0,
                                            'sizes' => [],
                                            'requests' => [],
                                            'attributes' => [
                                                'Lengan' => isset($itemDetails['sleeve_model']) ? ucfirst($itemDetails['sleeve_model']) : 'Pendek',
                                                'Saku' => isset($itemDetails['pocket_model']) && $itemDetails['pocket_model'] !== 'tanpa_saku' ? ucfirst(str_replace('_', ' ', $itemDetails['pocket_model'])) : 'Tanpa Saku',
                                                'Kancing' => isset($itemDetails['button_model']) && $itemDetails['button_model'] !== 'biasa' ? ucfirst(str_replace('_', ' ', $itemDetails['button_model'])) : 'Biasa',
                                                'Tunik' => !empty($itemDetails['is_tunic']) ? 'Ya' : 'Tidak',
                                            ]
                                        ];
                                    }

                                    $genders[$g]['variations'][$varKey]['qty'] += $item->quantity;

                                    $sz = $item->size ?? 'Tanpa Ukuran';
                                    if (!isset($genders[$g]['variations'][$varKey]['sizes'][$sz])) {
                                        $genders[$g]['variations'][$varKey]['sizes'][$sz] = 0;
                                    }
                                    $genders[$g]['variations'][$varKey]['sizes'][$sz] += $item->quantity;

                                    $req = trim($itemDetails['request_tambahan'] ?? '');
                                    if (!empty($req)) {
                                        $genders[$g]['variations'][$varKey]['requests'][] = $req;
                                    }
                                }

                                if (in_array($cat, ['Konveksi'])) {
                                    $html .= '<div class="space-y-2 mt-4">';
                                    foreach ($genders as $gCode => $gData) {
                                        $gName = $gCode === 'P' ? 'Perempuan' : 'Laki-laki';

                                        $html .= '<div x-data="{ expanded: false }" class="border border-gray-200 dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 overflow-hidden shadow-sm">';
                                        $html .= '<button @click="expanded = !expanded" type="button" class="w-full flex justify-between items-center px-4 py-3 bg-gray-50 dark:bg-gray-800/50 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">';
                                        $html .= '<div class="flex items-center gap-2"><span class="font-bold text-gray-800 dark:text-gray-200">' . $gName . ' :</span><span class="text-xs font-bold bg-gray-800 dark:bg-gray-600 py-0.5 rounded-full">' . $gData['qty'] . ' pcs</span></div>';
                                        $html .= '<svg :class="expanded ? \'rotate-180\' : \'\'" class="w-4 h-4 text-gray-500 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>';
                                        $html .= '</button>';

                                        $html .= '<div x-show="expanded" x-collapse class="px-4 py-3 border-t border-gray-200 dark:border-gray-700 space-y-4">';

                                        foreach ($gData['variations'] as $vKey => $vData) {
                                            $html .= '<div>';
                                            $html .= '<p class="font-semibold text-[13px] text-gray-800 dark:text-gray-200 leading-snug flex items-start gap-1.5"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5 text-primary-500 mt-0.5 shrink-0"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg> <span>' . htmlspecialchars($vKey) . ' <span class="text-xs font-normal text-primary-700 dark:text-primary-300 bg-primary-50 dark:bg-primary-900/30 px-1.5 py-0.5 rounded ml-1">' . $vData['qty'] . ' pcs</span></span></p>';

                                            $html .= '<div class="ml-5 mt-2 space-y-1.5">';

                                            // Attributes
                                            $attrStrs = [];
                                            foreach ($vData['attributes'] as $attrKey => $attrVal) {
                                                $attrStrs[] = '<span class="inline-flex items-center gap-1 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded text-[11px] text-gray-600 dark:text-gray-400 border border-gray-200 dark:border-gray-700"><strong class="text-gray-700 dark:text-gray-300">' . $attrKey . ':</strong> ' . $attrVal . '</span>';
                                            }
                                            $html .= '<div class="flex flex-wrap gap-1.5 mb-2">' . implode('', $attrStrs) . '</div>';

                                            $sizeStrs = [];
                                            foreach ($vData['sizes'] as $sz => $sqty) {
                                                $sizeStrs[] = htmlspecialchars($sz) . ' (' . $sqty . ')';
                                            }
                                            $html .= '<div class="text-[12px] text-gray-600 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-300">Size:</strong> ' . implode(', ', $sizeStrs) . '</div>';

                                            if (!empty($vData['requests'])) {
                                                $html .= '<div class="text-[12px] text-orange-700 dark:text-orange-300 bg-orange-50 dark:bg-orange-900/20 px-2 py-1.5 rounded border border-orange-100 dark:border-orange-800/50 mt-1">';
                                                $html .= '<strong class="block mb-0.5">Catatan/Request Tambahan:</strong>';
                                                $html .= '<ul class="list-disc pl-4 space-y-0.5">';
                                                foreach ($vData['requests'] as $req) {
                                                    $html .= '<li>' . htmlspecialchars($req) . '</li>';
                                                }
                                                $html .= '</ul></div>';
                                            }
                                            $html .= '</div></div>';
                                        }
                                        $html .= '</div></div>';
                                    }
                                    $html .= '</div>';
                                }

                                try {
                                    $url = route('filament.admin.resources.orders.edit', ['tenant' => filament()->getTenant()->id, 'record' => $record->order_id]);
                                    $html .= '<div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">';
                                    $html .= '<a href="' . $url . '" target="_blank" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-[13px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors w-full shadow-sm">';
                                    $html .= '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>';
                                    $html .= 'Lihat Detail Pesanan Asli';
                                    $html .= '</a></div>';
                                } catch (\Exception $e) {
                                }

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
