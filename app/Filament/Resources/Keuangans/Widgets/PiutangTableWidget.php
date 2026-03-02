<?php

namespace App\Filament\Resources\Keuangans\Widgets;

use App\Models\Order;
use App\Models\Payment;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\HtmlString;
use Filament\Facades\Filament;

class PiutangTableWidget extends BaseWidget
{
    protected static ?string $heading = 'Daftar Piutang & Penagihan';
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->where('shop_id', Filament::getTenant()?->id)
                    ->whereRaw('total_price > (SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payments.order_id = orders.id)')
                    ->latest('created_at')
            )
            ->columns([
                TextColumn::make('index')
                    ->label('#')
                    ->rowIndex()
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                TextColumn::make('order_number')
                    ->label('Pesanan & Pelanggan')
                    ->formatStateUsing(function ($record) {
                        $customerName = $record->customer->name ?? '-';
                        $customerPhone = $record->customer->phone ?? '-';

                        $userIcon = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" /></svg>';
                        $phoneIcon = '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-2.896-1.596-5.54-4.24-7.136-7.136l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" /></svg>';

                        $html = '<div class="flex flex-col gap-1.5">';
                        $html .= '<div class="font-bold text-gray-800 text-base">' . $record->order_number . '</div>';

                        $pillClass = 'inline-flex items-center px-2.5 py-0.5 bg-purple-50 text-purple-600 border border-purple-100 rounded-full text-xs font-bold w-max';
                        $html .= '<div class="' . $pillClass . '">' . $customerName . '</div>';
                        $html .= '<div class="' . $pillClass . '">' . $customerPhone . '</div>';

                        $html .= '</div>';
                        return new HtmlString($html);
                    })
                    ->searchable(['order_number'])
                    ->wrap()
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                TextColumn::make('produk_status')
                    ->label('Tipe Produk & Status Pesanan')
                    ->state(function ($record) {
                        $statusText = ucwords(str_replace('_', ' ', $record->status));

                        $bgClass = 'bg-gray-100';
                        $textClass = 'text-gray-700';
                        $borderClass = 'border-gray-200';
                        switch (strtolower($record->status)) {
                            case 'pending':
                                $bgClass = 'bg-gray-100';
                                $textClass = 'text-gray-700';
                                $borderClass = 'border-gray-200';
                                break;
                            case 'diterima':
                                $bgClass = 'bg-purple-100';
                                $textClass = 'text-purple-700';
                                $borderClass = 'border-purple-200';
                                break;
                            case 'dikerjakan':
                                $bgClass = 'bg-blue-100';
                                $textClass = 'text-blue-700';
                                $borderClass = 'border-blue-200';
                                break;
                            case 'selesai':
                            case 'diambil':
                                $bgClass = 'bg-green-100';
                                $textClass = 'text-green-700';
                                $borderClass = 'border-green-200';
                                break;
                            case 'batal':
                                $bgClass = 'bg-red-100';
                                $textClass = 'text-red-700';
                                $borderClass = 'border-red-200';
                                break;
                        }

                        $html = '<div class="flex flex-col gap-2">';

                        $html .= '<div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold w-max border ' . $bgClass . ' ' . $textClass . ' ' . $borderClass . '">';
                        $html .= '<div class="w-1.5 h-4 bg-purple-500 rounded-sm"></div>';
                        $html .= '<span>' . $statusText . '</span>';
                        $html .= '</div>';

                        if ($record->orderItems && $record->orderItems->count() > 0) {
                            $html .= '<div class="flex flex-wrap gap-2 mt-1">';
                            foreach ($record->orderItems as $item) {
                                $itemStatus = 'Antrian';
                                if (in_array($record->status, ['dikerjakan', 'selesai', 'diambil'])) {
                                    $itemStatus = ($record->status == 'dikerjakan') ? 'Proses' : 'Siap Diambil';
                                }
                                $itemName = $item->custom_name ?: $item->product?->name ?: 'Item';
                                $qty = $item->quantity . 'x';

                                $html .= '<div class="inline-flex items-stretch bg-white border border-gray-200 rounded-md overflow-hidden text-xs h-8">';
                                $html .= '<div class="px-2.5 text-gray-500 border-r border-gray-100 flex items-center">' . $qty . ' ' . $itemName . '</div>';
                                $html .= '<div class="px-2.5 font-bold text-gray-800 flex items-center justify-center bg-gray-50">' . $itemStatus . '</div>';
                                $html .= '</div>';
                            }
                            $html .= '</div>';
                        }

                        $html .= '</div>';
                        return new HtmlString($html);
                    })
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                TextColumn::make('finance')
                    ->label('Rincian Biaya')
                    ->state(function ($record) {
                        $html = '<div class="flex flex-col gap-1.5 text-sm text-gray-500 min-w-[220px] max-w-[400px]">';

                        $html .= '<div class="flex gap-2 items-center flex-wrap">';
                        $html .= '<span class="whitespace-nowrap">Total Tagihan</span>';
                        $html .= '<span class="font-bold text-gray-700 bg-gray-100 px-3 py-1 rounded-full text-sm">Rp ' . number_format($record->total_price, 0, ',', '.') . '</span>';
                        $html .= '</div>';

                        $html .= '<div class="mt-2">';
                        $html .= '<div class="text-gray-400 mb-1.5 text-xs font-semibold uppercase tracking-wider">Riwayat Pembayaran</div>';

                        $payments = $record->payments()->orderBy('created_at')->get();
                        if ($payments->count() > 0) {
                            $html .= '<div class="flex flex-wrap gap-1.5 justify-start">';
                            foreach ($payments as $payment) {
                                $html .= '<div class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-800 shadow-sm">';
                                $html .= '<span>Rp ' . number_format($payment->amount, 0, ',', '.') . '</span>';
                                $html .= '<div class="w-4 h-4 bg-purple-500 text-white rounded-full flex items-center justify-center shadow-sm shrink-0">';
                                $html .= '<svg class="w-2.5 h-2.5" fill="none" viewBox="0 0 24 24" stroke-width="4" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>';
                                $html .= '</div>';
                                $html .= '</div>';
                            }
                            $html .= '</div>';
                        } else {
                            $html .= '<div class="text-gray-300 italic text-xs">Belum ada pembayaran</div>';
                        }
                        $html .= '</div>';

                        $html .= '</div>';
                        return new HtmlString($html);
                    }),
            ])
            ->filters([
                \Filament\Tables\Filters\Filter::make('deadline_date')
                    ->form([
                        DatePicker::make('deadline_from')
                            ->label('Deadline Dari')
                            ->native(false),
                        DatePicker::make('deadline_until')
                            ->label('Deadline Sampai')
                            ->native(false),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['deadline_from'],
                                fn($query, $date) => $query->whereDate('deadline_date', '>=', $date),
                            )
                            ->when(
                                $data['deadline_until'],
                                fn($query, $date) => $query->whereDate('deadline_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['deadline_from'] ?? null) {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('Dari ' . \Carbon\Carbon::parse($data['deadline_from'])->toFormattedDateString())
                                ->removeField('deadline_from');
                        }
                        if ($data['deadline_until'] ?? null) {
                            $indicators[] = \Filament\Tables\Filters\Indicator::make('Sampai ' . \Carbon\Carbon::parse($data['deadline_until'])->toFormattedDateString())
                                ->removeField('deadline_until');
                        }
                        return $indicators;
                    }),

                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label('Status Pesanan')
                    ->options([
                        'pending' => 'Pending',
                        'diterima' => 'Diterima',
                        'dikerjakan' => 'Dikerjakan',
                        'selesai' => 'Selesai',
                        'diambil' => 'Diambil',
                        'batal' => 'Batal',
                    ])
                    ->multiple(),
            ])
            ->actions([
                ActionGroup::make([
                    // ACTION: BAYAR
                    Action::make('bayar')
                        ->label(fn($record) => in_array($record->status, ['dikerjakan', 'selesai', 'diambil']) ? 'Pelunasan' : 'Bayar')
                        ->link()
                        ->color('info')
                        ->extraAttributes(function ($record) {
                            return [
                                'class' => '!text-purple-700 font-semibold',
                                'style' => 'width: fit-content;',
                            ];
                        })
                        ->icon('heroicon-o-banknotes')
                        ->modalHeading(fn($record) => 'Pelunasan / Cicilan - ' . $record->order_number)
                        ->modalDescription(fn($record) => in_array($record->status, ['dikerjakan', 'selesai', 'diambil']) ? 'Pelunasan kalau status pesanan siap diambil.' : 'Catat pembayaran cicilan baru untuk pesanan ini.')
                        ->tooltip(fn($record) => in_array($record->status, ['dikerjakan', 'selesai', 'diambil']) ? 'Pelunasan kalau status pesanan siap diambil' : null)
                        ->form(fn($record) => [
                            TextInput::make('amount')
                                ->label('Nominal Pembayaran')
                                ->numeric()
                                ->prefix('Rp')
                                ->default($record->remaining_balance)
                                ->required(),
                            DatePicker::make('payment_date')
                                ->label('Tanggal')
                                ->default(now())
                                ->required()
                                ->native(false),
                            Select::make('payment_method')
                                ->label('Metode Pembayaran')
                                ->options([
                                    'cash' => 'Cash',
                                    'transfer' => 'Transfer Bank',
                                    'qris' => 'QRIS',
                                ])
                                ->required(),
                            Textarea::make('note')->label('Catatan (Opsional)'),
                            FileUpload::make('proof_image')
                                ->label('Foto Bukti (Opsional)')
                                ->image()
                                ->disk('public')
                                ->directory('payment-proofs'),
                        ])
                        ->action(function (array $data, $record, $livewire) {
                            Payment::create([
                                'order_id' => $record->id,
                                'amount' => $data['amount'],
                                'payment_date' => $data['payment_date'],
                                'payment_method' => $data['payment_method'],
                                'note' => $data['note'] ?? null,
                                'proof_image' => $data['proof_image'] ?? null,
                                'recorded_by' => auth()->id(),
                            ]);
                            Notification::make()
                                ->title('Pembayaran Berhasil Dicatat')
                                ->success()
                                ->send();
                            $livewire->dispatch('refreshStats');
                        }),

                    // ACTION: WHATSAPP
                    Action::make('whatsapp')
                        ->label('Whatsapp')
                        ->link()
                        ->color('success')
                        ->extraAttributes([
                            'class' => 'font-semibold',
                            'style' => 'width: fit-content;',
                        ])
                        ->icon('heroicon-o-chat-bubble-oval-left-ellipsis')
                        ->url(function ($record) {
                            $phone = $record->customer->phone ?? '';
                            $phone = preg_replace('/^0/', '62', $phone);
                            $phone = preg_replace('/[^\d]/', '', $phone);
                            $sisa = number_format($record->remaining_balance, 0, ',', '.');
                            $msg = "Halo Kak " . ($record->customer->name ?? '') . ",\n\n"
                                . "Ini konfirmasi dari Konveksio untuk pesanan *" . $record->order_number . "*.\n"
                                . "Saat ini masih ada sisa pembayaran sebesar *Rp " . $sisa . "*.\n"
                                . "Mohon konfirmasinya ya Kak jika sudah bisa dilunasi. Terima kasih banyak! \n"
                                . "https://konveksio.id"; // Atau dummy url
                            return "https://wa.me/" . $phone . "?text=" . urlencode($msg);
                        })
                        ->openUrlInNewTab(),
                ])
                    ->dropdown(false)
                    ->extraAttributes([
                        'class' => 'flex flex-col gap-1 items-start justify-start',
                    ])
            ])
            ->actionsColumnLabel('Aksi (Action)')
            ->emptyStateHeading('Tidak Ada Piutang')
            ->emptyStateDescription('Semua pesanan sudah lunas.');
    }
}
