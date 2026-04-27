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
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['order.customer', 'bahan.material'])
            ->whereHas('order', fn($q) => $q->where('status', '!=', 'draft'))
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
                    Section::make('SPESIFIKASI TEKNIS')
                        ->schema([
                            Placeholder::make('technical_specs')
                                ->label(false)
                                ->content(function ($record): HtmlString {
                                    if (!$record)
                                        return new HtmlString('');

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

                                    // STYLE CONSTANTS FROM ORDERRESOURCE
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
                                    return new HtmlString($html);
                                })
                        ])
                ])->columnSpan(1),

                Group::make([
                    Section::make('UPLOAD DESAIN FINAL')
                        ->description('Pastikan artwork sudah sesuai dengan rincian teknis di samping.')
                        ->schema([
                            FileUpload::make('design_image')
                                ->label('Pilih File Artwork')
                                ->image()
                                ->imageEditor()
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                ->disk('public')
                                ->directory('designs')
                                ->downloadable()
                                ->openable()
                                ->required(),
                        ])
                ])->columnSpan(1),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order.order_number')->label('No. Pesanan')->searchable()->sortable()->weight('bold'),
                TextColumn::make('product_name')->label('Nama Produk')->searchable()->sortable(),
                TextColumn::make('total_quantity')->label('Total Qty')
                    ->getStateUsing(fn(OrderItem $record): int => OrderItem::where('order_id', $record->order_id)->where('product_name', $record->product_name)->sum('quantity'))
                    ->sortable(['quantity']),
                TextColumn::make('production_category')->label('Kategori')->badge()
                    ->color(fn($state) => match ($state) { 'non_produksi' => 'info', 'jasa' => 'success', default => 'primary', })
                    ->formatStateUsing(fn($state) => match ($state) { 'non_produksi' => 'Baju Jadi', 'jasa' => 'Jasa', default => 'Konveksi', }),
                TextColumn::make('order.deadline')->label('Deadline')->date('d M Y')->sortable()->color('danger')->weight('bold'),
                TextColumn::make('design_status')->label('Status Desain')->badge()
                    ->color(fn($state) => match ($state) { 'pending' => 'warning', 'uploaded' => 'info', 'approved' => 'success', default => 'gray', }),
                \Filament\Tables\Columns\ImageColumn::make('design_image')->label('Desain')->disk('public')->square()->size(40)->toggleable(),
            ])
            ->filters([])
            ->actions([
                EditAction::make()->label('Edit Desain')->icon('heroicon-m-paint-brush')->modalWidth('6xl')
                    ->using(function (OrderItem $record, array $data): OrderItem {
                        OrderItem::where('order_id', $record->order_id)->where('product_name', $record->product_name)->update([
                            'design_status' => 'approved',
                            'design_image' => $data['design_image'] ?? null,
                        ]);
                        $record->refresh();
                        return $record;
                    })
                    ->after(function (\Livewire\Component $livewire) {
                        $livewire->dispatch('designTaskCompleted');
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
        return ['index' => ManageDesignTasks::route('/'),];
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
