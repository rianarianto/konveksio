<?php

namespace App\Filament\Resources\DesignTasks;

use App\Filament\Resources\DesignTasks\Pages\ManageDesignTasks;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Tables\Grouping\Group as TableGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Database\Eloquent\Model;

class DesignTaskResource extends Resource
{
    protected static ?string $model = OrderItem::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';
    protected static ?string $navigationLabel = 'Meja Desain';
    protected static ?string $modelLabel = 'Tugas Desain';
    protected static ?string $slug = 'design-tasks';

    protected static string|\UnitEnum|null $navigationGroup = 'PRODUKSI';

    protected static ?int $navigationSort = 1;

    protected static bool $isScopedToTenant = true;
    protected static ?string $tenantOwnershipRelationshipName = 'orderShop';

    public static function canAccess(): bool
    {
        // Akses untuk Designer dan Owner
        return in_array(auth()->user()->role, ['designer', 'owner']);
    }

    public static function scopeEloquentQueryToTenant(Builder $query, ?Model $tenant): Builder
    {
        return $query->whereHas('order', function (Builder $q) use ($tenant) {
            $q->where('shop_id', $tenant?->getKey());
        });
    }

    public static function observeTenancyModelCreation(\Filament\Panel $panel): void
    {
        // Override and do nothing.
        // This prevents Filament from attempting to auto-save the `orderShop`
        // HasOneThrough relation whenever an OrderItem is created globally,
        // which would cause a "Call to undefined method HasOneThrough::save()" error.
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['order.customer'])
            ->whereIn('design_status', ['pending', 'uploaded'])
            ->whereIn('id', function (\Illuminate\Database\Query\Builder $query) {
                $query->selectRaw('MIN(id)')
                    ->from('order_items')
                    ->whereIn('design_status', ['pending', 'uploaded'])
                    ->groupBy('order_id', 'product_name');
            });
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    Section::make('Rincian Teknis Produk')
                        ->schema([
                            Placeholder::make('technical_specs')
                                ->label(false)
                                ->content(function ($record): HtmlString {
                                    if (!$record)
                                        return new HtmlString('');

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

                                    // Bahan & Sablon Summary
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

                                    // Fetch all items for this product group
                                    $allItems = OrderItem::where('order_id', $record->order_id)
                                        ->where('product_name', $record->product_name)
                                        ->get();

                                    $genders = []; // e.g. ['P' => ['qty' => 20, 'variations' => [...]], 'L' => [...]]
                                    foreach ($allItems as $item) {
                                        $itemDetails = $item->size_and_request_details ?? [];
                                        $g = $itemDetails['gender'] ?? 'L';

                                        if (!isset($genders[$g])) {
                                            $genders[$g] = ['qty' => 0, 'variations' => []];
                                        }

                                        $genders[$g]['qty'] += $item->quantity;

                                        // Build variation key
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

                                        // Track sizes
                                        $sz = $item->size ?? 'Tanpa Ukuran';
                                        if (!isset($genders[$g]['variations'][$varKey]['sizes'][$sz])) {
                                            $genders[$g]['variations'][$varKey]['sizes'][$sz] = 0;
                                        }
                                        $genders[$g]['variations'][$varKey]['sizes'][$sz] += $item->quantity;

                                        // Track requests
                                        $req = trim($itemDetails['request_tambahan'] ?? '');
                                        if (!empty($req)) {
                                            $genders[$g]['variations'][$varKey]['requests'][] = $req;
                                        }
                                    }

                                    // Render Accordions
                                    if (in_array($cat, ['Konveksi'])) {
                                        $html .= '<div class="space-y-2">';
                                        foreach ($genders as $gCode => $gData) {
                                            $gName = $gCode === 'P' ? 'Perempuan' : 'Laki-laki';
                                            $gIcon = $gCode === 'P' ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-pink-500"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21h-7.5M12 12.75a5.25 5.25 0 110-10.5 5.25 5.25 0 010 10.5z" /></svg>' : '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-blue-500"><path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z" /></svg>';

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

                                                // Sizes string
                                                $sizeStrs = [];
                                                foreach ($vData['sizes'] as $sz => $sqty) {
                                                    $sizeStrs[] = htmlspecialchars($sz) . ' (' . $sqty . ')';
                                                }
                                                $html .= '<div class="text-[12px] text-gray-600 dark:text-gray-400"><strong class="text-gray-700 dark:text-gray-300">Size:</strong> ' . implode(', ', $sizeStrs) . '</div>';

                                                // Requests
                                                if (!empty($vData['requests'])) {
                                                    $html .= '<div class="text-[12px] text-orange-700 dark:text-orange-300 bg-orange-50 dark:bg-orange-900/20 px-2 py-1.5 rounded border border-orange-100 dark:border-orange-800/50 mt-1">';
                                                    $html .= '<strong class="block mb-0.5">Catatan/Request Tambahan:</strong>';
                                                    $html .= '<ul class="list-disc pl-4 space-y-0.5">';
                                                    foreach ($vData['requests'] as $req) {
                                                        $html .= '<li>' . htmlspecialchars($req) . '</li>';
                                                    }
                                                    $html .= '</ul></div>';
                                                }
                                                $html .= '</div>';
                                                $html .= '</div>';
                                            }

                                            $html .= '</div>'; // End expanded content
                                            $html .= '</div>'; // End accordion
                                        }
                                        $html .= '</div>';
                                    }

                                    // Button Lihat Detail
                                    try {
                                        $url = route('filament.admin.resources.orders.edit', ['tenant' => filament()->getTenant()->id, 'record' => $record->order_id]);
                                    } catch (\Exception $e) {
                                        $url = '#'; // Fallback
                                    }
                                    $html .= '<div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">';
                                    $html .= '<a href="' . $url . '" target="_blank" class="inline-flex items-center justify-center gap-2 px-4 py-2 text-[13px] font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:ring-4 focus:ring-gray-200 dark:focus:ring-gray-800 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:text-white dark:hover:bg-gray-700 transition-colors w-full shadow-sm">';
                                    $html .= '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4 text-gray-500"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>';
                                    $html .= 'Lihat Detail Pesanan Asli';
                                    $html .= '</a>';
                                    $html .= '</div>';

                                    $html .= '</div>';
                                    return new HtmlString($html);
                                })
                        ])
                ])->columnSpan(1),

                Group::make([
                    Section::make('Upload File Final Desain')
                        ->description('Silakan unggah file referensi atau panduan desain akhir untuk produk ini saja.')
                        ->schema([
                            FileUpload::make('design_image')
                                ->label('Artwork / Desain Final')
                                ->image()
                                ->imageEditor()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                ->disk('public')
                                ->directory('designs')
                                ->downloadable()
                                ->openable()
                                ->required()
                                ->helperText('Setelah disimpan, status desain otomatis berubah menjadi Approved dan masuk antrian konveksi.'),
                        ])
                ])->columnSpan(1),
            ])
            ->columns(2);
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
                    ->color(fn(string $state) => [
                        50 => '#F2E6FF',
                        500 => '#8000FF',
                        600 => '#8000FF',
                    ])
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
                    ->color(fn(string $state): string => match ($state) {
                        'pending' => 'warning',
                        'uploaded' => 'info',
                        'approved' => 'success',
                        default => 'gray',
                    }),

                \Filament\Tables\Columns\ImageColumn::make('design_image')
                    ->label('Desain')
                    ->disk('public')
                    ->visibility('public')
                    ->square()
                    ->size(40)
                    ->toggleable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make()
                    ->label('Rincian')
                    ->icon('heroicon-o-eye')
                    ->using(function (OrderItem $record, array $data): OrderItem {
                        // Perbarui seluruh item dengan nama produk dan pesanan yang sama
                        $data['design_status'] = 'approved';

                        OrderItem::where('order_id', $record->order_id)
                            ->where('product_name', $record->product_name)
                            ->update([
                                'design_status' => 'approved',
                                'design_image' => $data['design_image'] ?? null,
                            ]);

                        $record->refresh();
                        return $record;
                    }),
            ])
            ->defaultGroup(
                TableGroup::make('order.order_number')
                    ->label('Pesanan')
                    ->getTitleFromRecordUsing(fn(Model $record): string => $record->order->order_number . ' - ' . ($record->order->customer->name ?? 'Tanpa Nama'))
                    ->collapsible()
            )
            ->heading('Antrian Tugas Desain');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDesignTasks::route('/'),
        ];
    }

    // Blokir fungsi tambah & hapus karena Desainer hanya mengunggah file
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }
}

