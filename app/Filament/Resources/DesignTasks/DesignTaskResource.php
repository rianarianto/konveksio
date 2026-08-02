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

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()
            ->where('design_status', 'pending')
            ->count();
            
        return $count > 0 ? (string)$count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
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
                                    $isKonveksi = true;
                                    if (!$record)
                                        return new HtmlString('');

                                    $name = htmlspecialchars($record->product_name ?? 'Produk');
                                    $cat = $record->production_category ?? 'produksi';
                                    $details = $record->size_and_request_details ?? [];
                                    $bahan = $record->bahan;
                                    $allOrderItems = OrderItem::where('order_id', $record->order_id)->where('product_name', $record->product_name)->get();

                                    $catLabel = match ($cat) {
                                        'non_produksi' => 'NON-PRODUKSI',
                                        'jasa' => 'JASA',
                                        default => 'PRODUKSI',
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
                                    if ($cat === 'non_produksi') {
                                        $vId = $details['product_variant_id'] ?? null;
                                        $v = $vId ? \App\Models\ProductVariant::with('product')->find($vId) : null;
                                        $hex = $v?->color_code ?: '#e5e7eb';
                                        $bahanLabel = $v ? (($v->product?->name ?? $record->product_name) . ' - ' . ($v->color_name ?? 'Tanpa Warna')) : ($record->product_name ?? '-');
                                    } else {
                                        $hex = $bahan?->color_code ?: '#e5e7eb';
                                        $bahanLabel = $bahan ? (($bahan->material->name ?? 'Bahan') . ' - ' . ($bahan->color_name ?? 'Tanpa Warna')) : ($details['bahan'] ?? '-');
                                    }

                                    $html .= '<div style="margin-bottom:24px;">';
                                    $html .= '<div style="font-size:11px; font-weight:800; color:#6b7280; letter-spacing:0.05em; margin-bottom:8px;">' . ($cat === 'non_produksi' ? 'INFORMASI NON-PRODUKSI' : 'INFORMASI BAHAN') . '</div>';
                                    $html .= '<div style="padding:12px 16px; background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px;">';
                                    $html .= '<div style="font-size:13px; font-weight:700; color:#111827;">' . htmlspecialchars($bahanLabel) . '</div>';

                                     // Sablon Info inside Material Info
                                     $sablonJenis = $details['sablon_jenis'] ?? null;
                                     $sablonLokasi = $details['sablon_lokasi'] ?? null;
                                     $sablonKet = $details['sablon_keterangan'] ?? null;

                                     if ($sablonJenis && $sablonJenis !== 'Tanpa Sablon/Bordir') {
                                         $html .= '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #e5e7eb;">';
                                         $html .= '<div style="font-size:11px; font-weight:800; color:' . $primaryColor . '; letter-spacing:0.02em; margin-bottom:4px;">🎨 DETAIL APLIKASI (SABLON / BORDIR)</div>';
                                         $html .= '<div style="font-size:12px; font-weight:700; color:#111827;">Teknik: <span style="font-weight:600;">' . htmlspecialchars($sablonJenis) . '</span></div>';
                                         if ($sablonLokasi) {
                                             $html .= '<div style="font-size:12px; font-weight:700; color:#111827; margin-top:2px;">Titik Lokasi: <span style="font-weight:600;">' . htmlspecialchars($sablonLokasi) . '</span></div>';
                                         }
                                         if ($sablonKet) {
                                             $html .= '<div style="font-size:11px; font-weight:600; color:#b45309; background:#fffbeb; border:1px solid #fef3c7; padding:6px 10px; border-radius:4px; margin-top:6px; font-style:italic;">Keterangan Desain: "' . htmlspecialchars($sablonKet) . '"</div>';
                                         }
                                         $html .= '</div>';
                                     }
                                     $html .= '</div>';
                                     $html .= '</div>';

                                     // GENERAL ORDER NOTES SECTION
                                     $orderNotes = $record->order->notes ?? null;
                                     if (!empty($orderNotes)) {
                                         $html .= '<div style="margin-bottom:24px; padding:12px 16px; background:#fffbeb; border:1px solid #fef3c7; border-radius:8px;">';
                                         $html .= '<div style="font-size:11px; font-weight:800; color:#d97706; letter-spacing:0.05em; margin-bottom:6px; display:flex; align-items:center; gap:4px;">';
                                         $html .= '⚠️ CATATAN UMUM PESANAN';
                                         $html .= '</div>';
                                         $html .= '<div style="font-size:12px; font-weight:700; color:#92400e; white-space:pre-wrap; line-height:1.5;">' . htmlspecialchars($orderNotes) . '</div>';
                                         $html .= '</div>';
                                     }

                                    // VARIATION SECTION
                                    $genders = [];
                                    foreach ($allOrderItems as $item) {
                                        $idtl = $item->size_and_request_details ?? [];
                                        $isProduksi = in_array($cat, ['produksi', 'custom']);
                                        $g = $isProduksi ? ($idtl['gender'] ?? 'L') : 'UMUM';
                                        if (!isset($genders[$g]))
                                            $genders[$g] = ['qty' => 0, 'models' => []];
                                        $genders[$g]['qty'] += $item->quantity;

                                        $mLabel = !empty($idtl['group_label']) ? $idtl['group_label'] : null;
                                        if (!$mLabel) {
                                            $mParts = [];
                                            if ($isProduksi) {
                                                if (isset($idtl['sleeve_model']))
                                                    $mParts[] = 'Lengan ' . $idtl['sleeve_model'];
                                                if (isset($idtl['pocket_model']) && $idtl['pocket_model'] !== 'tanpa_saku')
                                                    $mParts[] = 'Saku ' . str_replace('_', ' ', $idtl['pocket_model']);
                                                if (!empty($idtl['is_tunic']))
                                                    $mParts[] = 'Tunik';
                                            }
                                            $mLabel = empty($mParts) ? ($isKonveksi ? 'Model Standar' : 'Rincian Pesanan') : implode(', ', $mParts);
                                        }
                                        $mKey = $mLabel;

                                        $attrs = $isKonveksi ? [
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
                                                'qty' => 0,
                                                'sizes' => [],
                                                'notes' => [],
                                                'custom' => [],
                                                'attrs' => $attrs
                                            ];
                                        }
                                        $genders[$g]['models'][$mKey]['qty'] += $item->quantity;
                                        $sz = $item->size ?? '-';
                                        $genders[$g]['models'][$mKey]['sizes'][$sz] = ($genders[$g]['models'][$mKey]['sizes'][$sz] ?? 0) + $item->quantity;
                                        
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

                                        if ($item->size === 'Custom') {
                                            $m = [];
                                            foreach (['LD', 'PB', 'PL', 'LB', 'LP', 'LPh'] as $mk) {
                                                if (!empty($idtl[$mk])) $m[] = $mk . ':' . $idtl[$mk];
                                            }
                                            if (!empty($m) || $item->recipient_name) {
                                                $genders[$g]['models'][$mKey]['custom'][] = ($item->recipient_name ?: '-') . (!empty($m) ? ' (' . implode(' ', $m) . ')' : '');
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

                                            // Header Model/Varian
                                            $html .= '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">';
                                            $html .= '<span style="font-size:11px; font-weight:800; color:#4338ca; background:#e0e7ff; padding:2px 8px; border-radius:4px; border:1px solid #c7d2fe;">VARIAN: ' . htmlspecialchars(strtoupper($mData['label'])) . '</span>';
                                            $html .= '<span style="font-size:11px; font-weight:800; color:' . $primaryColor . '; background:#ede9fe; padding:1px 6px; border-radius:4px;">' . $mData['qty'] . ' pcs</span>';
                                            $html .= '</div>';

                                            // Attributes (Compact style)
                                            $atxt = [];
                                            foreach ($mData['attrs'] as $ak => $av) {
                                                $atxt[] = '<span style="color:#64748b; font-size:10px;">' . $ak . ':</span> <strong style="color:#334155; font-size:10px;">' . $av . '</strong>';
                                            }
                                            if (!empty($atxt)) {
                                                $html .= '<div style="display:flex; flex-wrap:wrap; gap:8px; font-size:10px; margin-bottom:8px; background:#ffffff; padding:4px 8px; border-radius:4px; border:1px solid #e2e8f0;">' . implode('<span style="color:#cbd5e1;">|</span>', $atxt) . '</div>';
                                            }

                                            // Sizes (Pill style)
                                            $stxt = [];
                                            foreach ($mData['sizes'] as $sz => $sqty) {
                                                $stxt[] = '<div style="padding:3px 8px; background:#ffffff; border:1px solid #e2e8f0; border-radius:4px; font-size:11px; font-weight:800; color:#1e293b;">' . $sz . ': <span style="color:' . $primaryColor . ';">' . $sqty . '</span></div>';
                                            }
                                            $html .= '<div style="display:flex; flex-wrap:wrap; gap:6px;">' . implode('', $stxt) . '</div>';

                                            // Custom Measurements / Recipients
                                            if (!empty($mData['custom'])) {
                                                $html .= '<div style="margin-top:8px; padding:6px 10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px;">';
                                                $html .= '<div style="font-size:10px; font-weight:800; color:#15803d; text-transform:uppercase; margin-bottom:2px;">📐 Rincian Ukuran Custom / Penerima:</div>';
                                                foreach ($mData['custom'] as $cItem) {
                                                    $html .= '<div style="font-size:11px; font-weight:700; color:#166534;">- ' . htmlspecialchars($cItem) . '</div>';
                                                }
                                                $html .= '</div>';
                                            }

                                            // Notes
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
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf'])
                                ->maxSize(5120) // 5MB Limit
                                ->disk('public')
                                ->directory('designs')
                                ->downloadable()
                                ->openable()
                                ->required()
                                ->saveUploadedFileUsing(function (\Livewire\Features\SupportFileUploads\TemporaryUploadedFile $file): string {
                                    $mimeType = $file->getMimeType();
                                    
                                    // Jika PDF, simpan apa adanya
                                    if ($mimeType === 'application/pdf') {
                                        $filename = \Illuminate\Support\Str::uuid() . '.pdf';
                                        $path = 'designs/' . $filename;
                                        \Illuminate\Support\Facades\Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));
                                        return $path;
                                    }

                                    // Jika Gambar, Resize & Kompres
                                    $img = \Intervention\Image\Laravel\Facades\Image::read($file->getRealPath());
                                    
                                    // Resize ke lebar max 1600px jika lebih besar
                                    if ($img->width() > 1600) {
                                        $img->scaleDown(width: 1600);
                                    }

                                    // Simpan sebagai JPG dengan kualitas 75% agar ringan
                                    $encoded = $img->toJpeg(quality: 75);
                                    $filename = \Illuminate\Support\Str::uuid() . '.jpg';
                                    $path = 'designs/' . $filename;
                                    
                                    \Illuminate\Support\Facades\Storage::disk('public')->put($path, (string) $encoded);
                                    
                                    return $path;
                                }),
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
                    ->formatStateUsing(fn($state) => match ($state) { 'non_produksi' => 'Non-Produksi', 'jasa' => 'Jasa', default => 'Produksi', }),
                TextColumn::make('order.deadline')->label('Deadline')->date('d M Y')->sortable()->color('danger')->weight('bold'),
                TextColumn::make('design_status')->label('Status Desain')->badge()
                    ->color(fn($state) => match ($state) { 'pending' => 'warning', 'uploaded' => 'info', 'approved' => 'success', default => 'gray', }),
                \Filament\Tables\Columns\ImageColumn::make('design_image')->label('Desain')->disk('public')->square()->size(40)->toggleable(),
            ])
            ->filters([])
            ->actions([
                EditAction::make()->label('Upload / Edit Desain')->icon('heroicon-m-paint-brush')->modalWidth('6xl')
                    ->using(function (OrderItem $record, array $data): OrderItem {
                        OrderItem::where('order_id', $record->order_id)->where('product_name', $record->product_name)->update([
                            'design_status' => 'approved',
                            'design_image' => $data['design_image'] ?? null,
                        ]);
                        $record->refresh();
                        return $record;
                    })
                    ->after(fn ($livewire) => $livewire->dispatch('designTaskCompleted')),
            ])
            ->defaultGroup(
                TableGroup::make('order_id')
                    ->label('Pesanan')
                    ->getTitleFromRecordUsing(fn(Model $record): string => $record->order->order_number . ' - ' . ($record->order->customer->name ?? 'Tanpa Nama'))
                    ->collapsible()
                    ->orderQueryUsing(fn ($query) => $query->orderBy('order_id', 'desc'))
            )
            ->defaultSort('order_id', 'desc')
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
