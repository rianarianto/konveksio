<?php

namespace App\Filament\Resources\Keuangans\Widgets;

use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\HtmlString;
use Filament\Facades\Filament;

class KasMasukTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Riwayat Kas Masuk (Pembayaran)';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $tenantId = Filament::getTenant()?->id;

        return $table
            ->query(
                Payment::query()
                    ->with(['order.customer', 'recorder'])
                    ->where(function ($q) use ($tenantId) {
                        $q->where('shop_id', $tenantId)
                          ->orWhereHas('order', function ($oq) use ($tenantId) {
                              $oq->withoutGlobalScopes()->where('shop_id', $tenantId);
                          });
                    })
                    ->latest('payment_date')
            )
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('order.order_number')
                    ->label('Pesanan / Keterangan')
                    ->formatStateUsing(function ($record) {
                        if ($record->type === 'modal_awal' || !$record->order_id) {
                            $html = '<div class="flex flex-col gap-1">';
                            $html .= '<div class="inline-flex items-center gap-1.5">';
                            $html .= '<span style="display:inline-block; padding:2px 8px; border-radius:6px; background:#ecfdf5; color:#047857; font-size:11px; font-weight:800; border:1px solid #a7f3d0;">💰 MODAL KAS KECIL</span>';
                            $html .= '</div>';
                            $html .= '<div style="font-weight:600; font-size:13px; color:#374151;">' . htmlspecialchars($record->note ?: 'Modal Harian / Kas Masuk') . '</div>';
                            $html .= '</div>';
                            return new HtmlString($html);
                        }

                        $order = $record->order;
                        if (!$order) return '-';

                        $html = '<div class="flex flex-col">';
                        $html .= '<div style="font-weight:bold; font-size:14px;">' . $order->order_number . '</div>';
                        $html .= '<div style="color:gray; font-size:12px;">' . ($order->customer->name ?? '-') . '</div>';

                        if ($record->note) {
                            $html .= '<div style="color:#6b7280; font-size:11px; font-style:italic;">Ket: ' . htmlspecialchars($record->note) . '</div>';
                        }

                        // Tampilkan item produk dengan gaya yang dicari
                        if ($order->orderItems && $order->orderItems->count() > 0) {
                            $html .= '<div class="flex flex-wrap gap-1 mt-1">';
                            $groupedItems = $order->orderItems->groupBy(fn($item) => ($item->product_name ?: 'Item'));
                            
                            foreach ($groupedItems as $name => $items) {
                                $qty = $items->sum('quantity');
                                $html .= '<div style="padding:2px 8px; border-radius:8px; background:#f3f4f6; color:#6b7280; font-size:10px; font-weight:700; border:1px solid #e5e7eb;">'
                                    . $qty . 'x ' . $name 
                                    . '</div>';
                            }
                            $html .= '</div>';
                        }

                        $html .= '</div>';
                        return new HtmlString($html);
                    })
                    ->searchable()
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->extraAttributes([
                        'class' => 'text-purple-600',
                    ])
                    ->weight('bold')
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn($record) => $record->methodLabel())
                    ->color(fn($record) => match ($record->payment_method) {
                        'transfer' => 'primary',
                        'qris' => 'info',
                        default => 'gray',
                    })
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                TextColumn::make('recorder.name')
                    ->label('Penerima / Dicatat Oleh')
                    ->default('-')
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                ImageColumn::make('proof_image')
                    ->label('Bukti')
                    ->state(fn($record) => $record->proof_image ? asset('storage/' . $record->proof_image) : null)
                    ->disk(null)
                    ->width(48)
                    ->defaultImageUrl(null)
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),
            ])
            ->headerActions([
                \Filament\Actions\CreateAction::make('tambah_modal')
                    ->label('Tambah Modal Kas Kecil')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->modalHeading('Tambah Modal Kas Kecil / Kas Masuk')
                    ->form([
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Nominal Modal (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->placeholder('1000000'),

                        \Filament\Forms\Components\DatePicker::make('payment_date')
                            ->label('Tanggal Masuk')
                            ->required()
                            ->native(false)
                            ->default(now()),

                        \Filament\Forms\Components\Select::make('payment_method')
                            ->label('Metode Penerimaan')
                            ->options([
                                'cash' => 'Cash (Tunai Fisik)',
                                'transfer' => 'Transfer Bank',
                            ])
                            ->default('cash')
                            ->required(),

                        \Filament\Forms\Components\TextInput::make('note')
                            ->label('Keterangan / Catatan')
                            ->placeholder('Contoh: Modal harian dari Owner')
                            ->default('Modal harian dari Owner')
                            ->required()
                            ->maxLength(255),

                        \Filament\Forms\Components\FileUpload::make('proof_image')
                            ->label('Foto Bukti / Struk (Opsional)')
                            ->image()
                            ->disk('public')
                            ->directory('payment-proofs')
                            ->imagePreviewHeight('120'),
                    ])
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['shop_id'] = Filament::getTenant()?->id;
                        $data['order_id'] = null;
                        $data['type'] = 'modal_awal';
                        $data['recorded_by'] = auth()->id();
                        return $data;
                    })
                    ->after(function () {
                        $this->dispatch('refreshStats');
                    }),
            ])
            ->actions([
                \Filament\Actions\EditAction::make()
                    ->visible(fn($record) => $record->type === 'modal_awal' || !$record->order_id)
                    ->form([
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Nominal Modal (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),

                        \Filament\Forms\Components\DatePicker::make('payment_date')
                            ->label('Tanggal Masuk')
                            ->required()
                            ->native(false),

                        \Filament\Forms\Components\Select::make('payment_method')
                            ->label('Metode Penerimaan')
                            ->options([
                                'cash' => 'Cash (Tunai Fisik)',
                                'transfer' => 'Transfer Bank',
                            ])
                            ->required(),

                        \Filament\Forms\Components\TextInput::make('note')
                            ->label('Keterangan / Catatan')
                            ->required()
                            ->maxLength(255),

                        \Filament\Forms\Components\FileUpload::make('proof_image')
                            ->label('Foto Bukti (Opsional)')
                            ->image()
                            ->disk('public')
                            ->directory('payment-proofs'),
                    ])
                    ->after(function () {
                        $this->dispatch('refreshStats');
                    }),
                \Filament\Actions\DeleteAction::make()
                    ->visible(fn($record) => ($record->type === 'modal_awal' || !$record->order_id) && auth()->user()->role === 'owner')
                    ->after(function () {
                        $this->dispatch('refreshStats');
                    }),
            ])
            ->emptyStateHeading('Belum Ada Kas Masuk')
            ->emptyStateDescription('Catat modal harian atau pembayaran pesanan melalui detail pesanan / tab Piutang.')
            ->filters([
                Tables\Filters\Filter::make('payment_date')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (\Illuminate\Database\Eloquent\Builder $query, array $data): \Illuminate\Database\Eloquent\Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn(\Illuminate\Database\Eloquent\Builder $query, $date): \Illuminate\Database\Eloquent\Builder => $query->whereDate('payment_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'Dari ' . \Illuminate\Support\Carbon::parse($data['from'])->format('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Sampai ' . \Illuminate\Support\Carbon::parse($data['until'])->format('d M Y');
                        }
                        return $indicators;
                    }),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options([
                        'cash' => 'Cash',
                        'transfer' => 'Transfer Bank',
                        'qris' => 'QRIS',
                    ]),
            ]);
    }
}
