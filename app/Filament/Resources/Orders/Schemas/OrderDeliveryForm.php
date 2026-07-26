<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Order;
use App\Models\Payment;
use App\Models\WorkOrder;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\HtmlString;

class OrderDeliveryForm
{
    public static function getComponents(): array
    {
        return [
            Placeholder::make('financial_summary')
                ->label('Ringkasan Pembayaran Pesanan')
                ->content(function ($record) {
                    if (!$record) return '-';
                    $order = $record instanceof Order ? $record : $record->order ?? null;
                    if (!$order) return '-';

                    $totalPrice = (int) $order->total_price;
                    $paidAmount = (int) $order->payments()->sum('amount');
                    $remaining = max(0, $totalPrice - $paidAmount);

                    $fmtTotal = "Rp " . number_format($totalPrice, 0, ',', '.');
                    $fmtPaid = "Rp " . number_format($paidAmount, 0, ',', '.');
                    $fmtRemaining = "Rp " . number_format($remaining, 0, ',', '.');

                    if ($remaining === 0) {
                        return new HtmlString("
                            <div style='padding:12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;font-size:13px;color:#166534;'>
                                <strong>✅ Lunas (100%)</strong> — Total: {$fmtTotal} | Sudah Dibayar: {$fmtPaid}
                            </div>
                        ");
                    }

                    return new HtmlString("
                        <div style='padding:12px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;font-size:13px;color:#9a3412;'>
                            <div style='display:flex;justify-content:space-between;margin-bottom:4px;'><span>Total Pesanan:</span> <strong>{$fmtTotal}</strong></div>
                            <div style='display:flex;justify-content:space-between;margin-bottom:4px;'><span>Sudah Dibayar:</span> <strong style='color:#059669;'>{$fmtPaid}</strong></div>
                            <div style='display:flex;justify-content:space-between;border-top:1px solid #fdba74;padding-top:4px;margin-top:4px;font-size:14px;'><span>Sisa Tagihan:</span> <strong style='color:#dc2626;'>{$fmtRemaining}</strong></div>
                        </div>
                    ");
                }),

            Section::make('Pelunasan Sisa Tagihan')
                ->description('Input pelunasan sisa pembayaran saat penyerahan barang')
                ->schema([
                    TextInput::make('settlement_amount')
                        ->label('Nominal Pelunasan (Rp)')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(function ($record) {
                            if (!$record) return 0;
                            $order = $record instanceof Order ? $record : $record->order ?? null;
                            if (!$order) return 0;
                            return max(0, (int) $order->total_price - (int) $order->payments()->sum('amount'));
                        })
                        ->required()
                        ->minValue(1)
                        ->maxValue(function ($record) {
                            if (!$record) return null;
                            $order = $record instanceof Order ? $record : $record->order ?? null;
                            if (!$order) return null;
                            return max(0, (int) $order->total_price - (int) $order->payments()->sum('amount'));
                        })
                        ->validationMessages([
                            'max' => 'Nominal pelunasan tidak boleh melebihi sisa tagihan.',
                        ]),

                    Select::make('payment_method')
                        ->label('Metode Pembayaran')
                        ->options([
                            'cash'     => 'Cash / Tunai',
                            'transfer' => 'Transfer Bank',
                            'qris'     => 'QRIS',
                        ])
                        ->default('cash')
                        ->required(),

                    FileUpload::make('payment_proof')
                        ->label('Foto Bukti Pembayaran / Pelunasan')
                        ->image()
                        ->disk('public')
                        ->directory('payments/proofs'),

                    Textarea::make('payment_note')
                        ->label('Catatan Pembayaran')
                        ->rows(2)
                        ->placeholder('Catatan tambahan pelunasan (opsional)...'),
                ])
                ->visible(function ($record) {
                    if (!$record) return false;
                    $order = $record instanceof Order ? $record : $record->order ?? null;
                    if (!$order) return false;
                    $remaining = (int) $order->total_price - (int) $order->payments()->sum('amount');
                    return $remaining > 0;
                }),

            Section::make('Bukti Penyerahan Barang')
                ->schema([
                    FileUpload::make('pickup_proof')
                        ->label('Foto Bukti Pengambilan / Penyerahan Barang')
                        ->image()
                        ->extraInputAttributes(['capture' => 'camera'])
                        ->disk('public')
                        ->directory('pickup-proofs')
                        ->required(),

                    Textarea::make('pickup_note')
                        ->label('Catatan Penyerahan Barang')
                        ->rows(2)
                        ->placeholder('Masukkan nama pengambil, kurir, atau info penyerahan...'),
                ]),
        ];
    }

    public static function processDelivery(Order $record, array $data): void
    {
        // 1. Process payment settlement if there is remaining balance & settlement_amount > 0
        $settlementAmount = (int) ($data['settlement_amount'] ?? 0);
        if ($settlementAmount > 0) {
            Payment::create([
                'order_id'       => $record->id,
                'amount'         => $settlementAmount,
                'payment_date'   => now(),
                'payment_method' => $data['payment_method'] ?? 'cash',
                'note'           => $data['payment_note'] ?? 'Pelunasan saat penyerahan pesanan',
                'proof_image'    => $data['payment_proof'] ?? null,
                'recorded_by'    => auth()->id(),
            ]);
        }

        // 2. Mark all WorkOrders in this order as delivered
        $itemIds = $record->orderItems()->pluck('id');
        WorkOrder::withoutGlobalScopes()
            ->whereIn('order_item_id', $itemIds)
            ->update(['delivered_at' => now()]);

        // 3. Update Order status to 'selesai'
        $record->update([
            'status'       => 'selesai',
            'pickup_proof' => $data['pickup_proof'],
            'pickup_note'  => $data['pickup_note'] ?? null,
            'pickup_at'    => now(),
        ]);

        $bodyText = $settlementAmount > 0
            ? "Pesanan #{$record->order_number} telah lunas (Rp " . number_format($settlementAmount, 0, ',', '.') . ") dan diserahkan ke customer."
            : "Pesanan #{$record->order_number} telah selesai dan seluruh barang diserahkan ke customer.";

        Notification::make()
            ->title('Pesanan Telah Diserahkan!')
            ->body($bodyText)
            ->success()
            ->send();
    }

    public static function isVisible(Order $record): bool
    {
        if (!in_array(auth()->user()->role, ['owner', 'admin'])) {
            return false;
        }

        // Serahkan Pesanan only appears when the entire order is ready for pickup ('siap_diambil')
        return $record->status === 'siap_diambil';
    }
}
