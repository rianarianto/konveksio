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
        return $table
            ->query(
                Payment::query()
                    ->whereHas('order', function ($q) {
                        $q->withoutGlobalScopes(); // In case ShopScope is not yet applied correctly? No, it's better to use tenant.
                        $q->where('shop_id', Filament::getTenant()?->id);
                    })
                    ->latest('payment_date')
            )
            ->columns([
                TextColumn::make('payment_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('order.order_number')
                    ->label('Pesanan & Pelanggan')
                    ->formatStateUsing(fn($record) => new HtmlString(
                        '<div style="font-weight:bold;">' . ($record->order->order_number ?? '-') . '</div>' .
                        '<div style="color:gray; font-size:0.875rem;">' . ($record->order->customer->name ?? '-') . '</div>'
                    ))
                    ->searchable()
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                TextColumn::make('amount')
                    ->label('Nominal')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->extraAttributes([
                        'class' => 'text-purple-600', // Pakai standar Tailwind
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
                    ->label('Penerima')
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
            ->emptyStateHeading('Belum Ada Kas Masuk')
            ->emptyStateDescription('Catat pembayaran melalui detail pesanan atau tab Piutang.');
    }
}
