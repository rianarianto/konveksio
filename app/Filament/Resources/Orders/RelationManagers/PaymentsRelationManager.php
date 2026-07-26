<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Riwayat Pembayaran';

    protected static ?string $modelLabel = 'Pembayaran';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Forms\Components\Placeholder::make('payment_summary')
                ->label(false)
                ->content(function() {
                    $order = $this->getOwnerRecord();
                    if (!$order) return '';
                    
                    $subtotal = (int) ($order->subtotal ?? 0);
                    $tax = (int) ($order->tax ?? 0);
                    $shipping = (int) ($order->shipping_cost ?? 0);
                    $discount = (int) ($order->discount ?? 0);
                    $expressFee = $order->is_express ? (int) ($order->express_fee ?? 0) : 0;
                    $total = $order->total_price;
                    
                    $paid = (int) $order->payments()->sum('amount');
                    $remaining = max(0, $total - $paid);
                    
                    return new \Illuminate\Support\HtmlString("
                        <div style='background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; font-size: 13px; color: #374151;'>
                            <div style='display: flex; justify-content: space-between; margin-bottom: 4px;'>
                                <span>Subtotal Biaya:</span>
                                <span style='font-weight: 600;'>Rp " . number_format($subtotal, 0, ',', '.') . "</span>
                            </div>
                            " . ($tax > 0 ? "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 4px;'>
                                <span>PPN 11%:</span>
                                <span style='font-weight: 600; color: #ef4444;'>Rp " . number_format($tax, 0, ',', '.') . "</span>
                            </div>" : "") . "
                            " . ($shipping > 0 ? "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 4px;'>
                                <span>Ongkos Kirim:</span>
                                <span style='font-weight: 600;'>Rp " . number_format($shipping, 0, ',', '.') . "</span>
                            </div>" : "") . "
                            " . ($expressFee > 0 ? "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 4px;'>
                                <span>Biaya Express:</span>
                                <span style='font-weight: 600; color: #e11d48;'>Rp " . number_format($expressFee, 0, ',', '.') . "</span>
                            </div>" : "") . "
                            " . ($discount > 0 ? "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 4px;'>
                                <span>Diskon:</span>
                                <span style='font-weight: 600; color: #22c55e;'>-Rp " . number_format($discount, 0, ',', '.') . "</span>
                            </div>" : "") . "
                            <div style='border-top: 1px dashed #d1d5db; margin: 8px 0; padding-top: 8px; display: flex; justify-content: space-between;'>
                                <span style='font-weight: 700;'>Total Tagihan:</span>
                                <span style='font-weight: 700; color: #7e22ce;'>Rp " . number_format($total, 0, ',', '.') . "</span>
                            </div>
                            <div style='display: flex; justify-content: space-between; margin-bottom: 4px;'>
                                <span>Sudah Dibayar:</span>
                                <span style='font-weight: 600; color: #22c55e;'>Rp " . number_format($paid, 0, ',', '.') . "</span>
                            </div>
                            <div style='display: flex; justify-content: space-between; font-size: 14px; font-weight: 800; border-top: 1px solid #e5e7eb; padding-top: 6px; margin-top: 6px;'>
                                <span>Sisa Pembayaran:</span>
                                <span style='color: " . ($remaining > 0 ? '#ef4444' : '#22c55e') . ";'>Rp " . number_format($remaining, 0, ',', '.') . "</span>
                            </div>
                        </div>
                    ");
                })
                ->columnSpanFull(),
            TextInput::make('amount')
                ->label('Jumlah Dibayar (Rp)')
                ->numeric()
                ->prefix('Rp')
                ->required()
                ->minValue(1),

            DatePicker::make('payment_date')
                ->label('Tanggal Pembayaran')
                ->required()
                ->default(now()),

            Select::make('payment_method')
                ->label('Metode Pembayaran')
                ->options([
                    'cash' => '💵 Cash',
                    'transfer' => '🏦 Transfer Bank',
                    'qris' => '📱 QRIS',
                ])
                ->required()
                ->selectablePlaceholder(false)
                ->default('cash'),

            TextInput::make('note')
                ->label('Catatan')
                ->placeholder('Contoh: DP 1, Cicilan 2, Pelunasan...')
                ->maxLength(255),

            FileUpload::make('proof_image')
                ->label('Bukti Transfer / Pembayaran')
                ->image()
                ->imagePreviewHeight('150')
                ->disk('public')
                ->directory('payments/proofs')
                ->maxSize(5120)
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->downloadable()
                ->openable()
                ->previewable()
                ->helperText('Upload foto bukti transfer atau struk pembayaran (maks 5MB)')
                ->getUploadedFileUsing(function (string $file): ?array {
                    $disk = Storage::disk('public');
                    if (!$disk->exists($file)) {
                        return null;
                    }
                    return [
                        'name' => basename($file),
                        'size' => $disk->size($file),
                        'type' => mime_content_type($disk->path($file)) ?: 'image/jpeg',
                        'url' => asset('storage/' . $file),
                    ];
                })
                ->saveUploadedFileUsing(function (TemporaryUploadedFile $file): string {
                    $mimeType = $file->getMimeType();

                    // Gambar — kompres dengan Intervention Image
                    $img = Image::read($file->getRealPath());

                    // Resize jika lebar > 1920px (pertahankan aspek rasio)
                    if ($img->width() > 1920) {
                        $img->scaleDown(width: 1920);
                    }

                    // Encode ke JPEG quality 75
                    $encoded = $img->toJpeg(quality: 75);

                    $filename = Str::uuid() . '.jpg';
                    $path = 'payments/proofs/' . $filename;
                    Storage::disk('public')->put($path, (string) $encoded);

                    return $path;
                }),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('note')
            ->columns([

                TextColumn::make('payment_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable()
                    ->verticallyAlignCenter()
                    ->weight('semibold'),

                TextColumn::make('amount')
                    ->label('Jumlah')
                    ->formatStateUsing(fn($state) => 'Rp ' . number_format($state, 0, ',', '.'))
                    ->color('success')
                    ->verticallyAlignCenter()
                    ->weight('bold'),

                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn($state) => match ($state) {
                        'transfer' => '🏦 Transfer Bank',
                        'qris' => '📱 QRIS',
                        default => '💵 Cash',
                    })
                    ->color(fn($state) => match ($state) {
                        'transfer' => 'info',
                        'qris' => 'warning',
                        default => 'success',
                    })
                    ->verticallyAlignCenter(),

                TextColumn::make('note')
                    ->label('Catatan')
                    ->verticallyAlignCenter()
                    ->placeholder('—'),

                ImageColumn::make('proof_image')
                    ->label('Bukti')
                    ->state(fn($record) => $record->proof_image ? asset('storage/' . $record->proof_image) : null)
                    ->disk(null)
                    ->square()
                    ->size(48)
                    ->verticallyAlignCenter()
                    ->defaultImageUrl(null),

                TextColumn::make('recorder.name')
                    ->label('Dicatat Oleh')
                    ->placeholder('—')
                    ->verticallyAlignCenter()
                    ->color('gray'),

            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Pembayaran')
                    ->icon('heroicon-o-plus')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['recorded_by'] = auth()->id();
                        return $data;
                    })
                    ->after(function () {
                        $this->dispatch('refreshOrderSummary');
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->visible(fn() => auth()->user()->role === 'owner')
                    ->after(function () {
                        $this->dispatch('refreshOrderSummary');
                    }),
                DeleteAction::make()
                    ->visible(fn() => auth()->user()->role === 'owner')
                    ->after(function () {
                        $this->dispatch('refreshOrderSummary');
                    }),
            ])
            ->defaultSort('payment_date', 'asc');
    }
}
