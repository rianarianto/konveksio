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
                ->dehydrateStateUsing(fn($state) => (int) preg_replace('/[^\d]/', '', (string) ($state ?? 0)))
                ->minValue(1)
                ->maxValue(function () {
                    $order = $this->getOwnerRecord();
                    if (!$order) return null;
                    return max(0, (int) $order->total_price - (int) $order->payments()->sum('amount'));
                })
                ->validationMessages([
                    'max' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan.',
                ]),

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
            ->modifyQueryUsing(fn ($query) => $query->with('recorder'))
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

                TextColumn::make('recorder_display_name')
                    ->label('Dicatat Oleh')
                    ->state(function ($record) {
                        return $record->recorder?->name 
                            ?? ($record->recorded_by ? \App\Models\User::find($record->recorded_by)?->name : null)
                            ?? '—';
                    })
                    ->verticallyAlignCenter()
                    ->color('gray'),

                TextColumn::make('status_approval')
                    ->label('Status')
                    ->badge()
                    ->state(function ($record) {
                        $pending = $record->pendingCorrectionRequest();
                        if ($pending) {
                            return '⏳ Menunggu Approval Owner (' . ($pending->request_type === 'delete' ? 'Hapus' : 'Ubah') . ')';
                        }
                        return 'Tercatat';
                    })
                    ->color(function ($record) {
                        return $record->pendingCorrectionRequest() ? 'warning' : 'success';
                    })
                    ->verticallyAlignCenter(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Tambah Pembayaran')
                    ->icon('heroicon-o-plus')
                    ->using(function (array $data, RelationManager $livewire): \App\Models\Payment {
                        $data['recorded_by'] = \Filament\Facades\Filament::auth()->id() ?? auth()->id();
                        $data['shop_id'] = \Filament\Facades\Filament::getTenant()?->id;
                        return $livewire->getOwnerRecord()->payments()->create($data);
                    })
                    ->after(function () {
                        $this->dispatch('refreshOrderSummary');
                    }),
            ])
            ->actions([
                \Filament\Actions\ActionGroup::make([
                    // ── Aksi 1: Owner Langsung Edit ──
                    EditAction::make()
                        ->visible(fn() => auth()->user()->role === 'owner')
                        ->after(function () {
                            $this->dispatch('refreshOrderSummary');
                        }),

                // ── Aksi 2: Owner Review Pengajuan Koreksi / Hapus dari Admin ──
                \Filament\Actions\Action::make('review_pengajuan')
                    ->label('Review Pengajuan')
                    ->icon('heroicon-o-check-badge')
                    ->color('warning')
                    ->visible(fn($record) => auth()->user()->role === 'owner' && $record->pendingCorrectionRequest() !== null)
                    ->modalHeading('Review Pengajuan Koreksi Pembayaran')
                    ->modalDescription(function ($record) {
                        $req = $record->pendingCorrectionRequest();
                        return "Diajukan oleh: " . ($req->requester->name ?? 'Admin') . " | Alasan: " . $req->reason;
                    })
                    ->form(function ($record) {
                        $req = $record->pendingCorrectionRequest();
                        if (!$req) return [];

                        $isDelete = $req->request_type === 'delete';

                        return [
                            \Filament\Forms\Components\Placeholder::make('info_perbandingan')
                                ->label(false)
                                ->content(function() use ($record, $req, $isDelete) {
                                    $html = "<div style='background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:12px; font-size:13px;'>";
                                    $html .= "<div style='font-weight:700; color:#111827; margin-bottom:8px;'>Jenis Pengajuan: " . ($isDelete ? "<span style='color:#ef4444;'>HAPUS PEMBAYARAN</span>" : "<span style='color:#8000FF;'>UBAH / KOREKSI DATA</span>") . "</div>";
                                    $html .= "<table style='width:100%; border-collapse:collapse;'>";
                                    $html .= "<tr style='border-bottom:1px solid #e5e7eb;'><td style='padding:4px 0; color:#6b7280;'>Data Saat Ini:</td><td style='padding:4px 0; font-weight:600;'>Rp " . number_format($req->old_amount, 0, ',', '.') . " (" . ucfirst($req->old_payment_method) . ") - Tgl: " . \Carbon\Carbon::parse($req->old_payment_date)->format('d/m/Y') . "</td></tr>";
                                    if (!$isDelete) {
                                        $html .= "<tr style='border-bottom:1px solid #e5e7eb;'><td style='padding:4px 0; color:#047857;'>Data Yang Diajukan:</td><td style='padding:4px 0; font-weight:700; color:#047857;'>Rp " . number_format($req->new_amount, 0, ',', '.') . " (" . ucfirst($req->new_payment_method) . ") - Tgl: " . \Carbon\Carbon::parse($req->new_payment_date)->format('d/m/Y') . "</td></tr>";
                                    }
                                    $html .= "<tr><td style='padding:4px 0; color:#b45309;'>Alasan Admin:</td><td style='padding:4px 0; font-style:italic; color:#b45309;'>" . htmlspecialchars($req->reason) . "</td></tr>";
                                    $html .= "</table>";
                                    $html .= "</div>";
                                    return new \Illuminate\Support\HtmlString($html);
                                })
                                ->columnSpanFull(),
                            
                            \Filament\Forms\Components\Textarea::make('catatan_penolakan')
                                ->label('Catatan (Jika Ingin Menolak)')
                                ->placeholder('Tulis alasan jika menolak pengajuan ini...'),
                        ];
                    })
                    ->modalSubmitActionLabel('Setujui Perubahan (Approve)')
                    ->action(function ($record, array $data) {
                        $req = $record->pendingCorrectionRequest();
                        if (!$req) return;

                        if ($req->request_type === 'delete') {
                            $req->update([
                                'status' => 'approved',
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                            $record->delete();
                        } else {
                            $record->update([
                                'amount' => $req->new_amount,
                                'payment_method' => $req->new_payment_method,
                                'payment_date' => $req->new_payment_date,
                                'note' => $req->new_note,
                                'proof_image' => $req->new_proof_image ?: $record->proof_image,
                            ]);
                            $req->update([
                                'status' => 'approved',
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);
                        }

                        // Kirim notifikasi lonceng ke Admin pemohon
                        if ($req->requester) {
                            \Filament\Notifications\Notification::make()
                                ->title('Pengajuan Koreksi Pembayaran Disetujui')
                                ->body("Pengajuan koreksi untuk pembayaran {$record->order?->order_number} telah disetujui Owner.")
                                ->success()
                                ->sendToDatabase($req->requester);
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Perubahan Berhasil Disetujui')
                            ->success()
                            ->send();

                        $this->dispatch('refreshOrderSummary');
                    })
                    ->extraModalFooterActions([
                        \Filament\Actions\Action::make('tolak_pengajuan')
                            ->label('Tolak Pengajuan')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->action(function ($record, array $data) {
                                $req = $record->pendingCorrectionRequest();
                                if (!$req) return;

                                $req->update([
                                    'status' => 'rejected',
                                    'reviewed_by' => auth()->id(),
                                    'reviewed_at' => now(),
                                    'rejection_reason' => $data['catatan_penolakan'] ?? 'Ditolak oleh Owner',
                                ]);

                                // Kirim notifikasi lonceng ke Admin pemohon
                                if ($req->requester) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Pengajuan Koreksi Pembayaran Ditolak')
                                        ->body("Pengajuan koreksi untuk pembayaran {$record->order?->order_number} ditolak Owner. Alasan: " . ($data['catatan_penolakan'] ?? '-'))
                                        ->danger()
                                        ->sendToDatabase($req->requester);
                                }

                                \Filament\Notifications\Notification::make()
                                    ->title('Pengajuan Berhasil Ditolak')
                                    ->warning()
                                    ->send();

                                $this->dispatch('refreshOrderSummary');
                            }),
                    ]),

                // ── Aksi 3: Admin Ajukan Koreksi ──
                \Filament\Actions\Action::make('ajukan_koreksi')
                    ->label('Ajukan Koreksi')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn($record) => auth()->user()->role !== 'owner' && $record->pendingCorrectionRequest() === null)
                    ->fillForm(fn($record): array => [
                        'new_amount' => $record->amount,
                        'new_payment_date' => $record->payment_date,
                        'new_payment_method' => $record->payment_method,
                        'new_note' => $record->note,
                    ])
                    ->form([
                        \Filament\Forms\Components\TextInput::make('new_amount')
                            ->label('Nominal Baru yang Benar (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),

                        \Filament\Forms\Components\DatePicker::make('new_payment_date')
                            ->label('Tanggal Pembayaran')
                            ->required()
                            ->native(false),

                        \Filament\Forms\Components\Select::make('new_payment_method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'cash' => '💵 Cash',
                                'transfer' => '🏦 Transfer Bank',
                                'qris' => '📱 QRIS',
                            ])
                            ->required(),

                        \Filament\Forms\Components\TextInput::make('new_note')
                            ->label('Catatan Pembayaran'),

                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Alasan Pengajuan Koreksi (Wajib)')
                            ->placeholder('Contoh: Salah ketik kelebihan nol, pelanggan sebenarnya bayar via transfer...')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->action(function ($record, array $data) {
                        $shopId = $record->shop_id ?? \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;

                        $req = \App\Models\PaymentCorrectionRequest::create([
                            'shop_id' => $shopId,
                            'payment_id' => $record->id,
                            'request_type' => 'edit',
                            'old_amount' => $record->amount,
                            'old_payment_method' => $record->payment_method,
                            'old_payment_date' => $record->payment_date,
                            'old_note' => $record->note,
                            'old_proof_image' => $record->proof_image,
                            'new_amount' => $data['new_amount'],
                            'new_payment_method' => $data['new_payment_method'],
                            'new_payment_date' => $data['new_payment_date'],
                            'new_note' => $data['new_note'] ?? null,
                            'reason' => $data['reason'],
                            'requested_by' => auth()->id(),
                            'status' => 'pending',
                        ]);

                        // Kirim Notifikasi ke semua Owner di toko ini
                        try {
                            $owners = \App\Models\User::withoutGlobalScopes()
                                ->where(function ($q) use ($shopId) {
                                    $q->where('shop_id', $shopId)->orWhereNull('shop_id');
                                })
                                ->where('role', 'owner')
                                ->get();

                            $orderUrl = null;
                            try {
                                $orderUrl = \App\Filament\Resources\Orders\OrderResource::getUrl('view', [
                                    'record' => $record->order_id,
                                    'tenant' => $shopId,
                                ]);
                            } catch (\Throwable $e) {
                                // Fallback jika format routing beda
                            }

                            foreach ($owners as $owner) {
                                $notif = \Filament\Notifications\Notification::make()
                                    ->title('⚠️ Permintaan Koreksi Pembayaran')
                                    ->body(auth()->user()->name . " mengajukan koreksi pembayaran " . ($record->order?->order_number ?? '') . " dari Rp " . number_format($record->amount, 0, ',', '.') . " -> Rp " . number_format($data['new_amount'], 0, ',', '.') . ". Alasan: " . $data['reason'])
                                    ->warning();

                                if ($orderUrl) {
                                    $notif->actions([
                                        \Filament\Notifications\Actions\Action::make('view')
                                            ->label('Lihat Pesanan')
                                            ->url($orderUrl),
                                    ]);
                                }

                                $notif->sendToDatabase($owner);
                            }
                        } catch (\Throwable $e) {
                            // Abaikan error notifikasi jika gagal
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Pengajuan Koreksi Terkirim')
                            ->body('Pengajuan telah dikirim ke Owner untuk ditinjau.')
                            ->success()
                            ->send();

                        $this->dispatch('refreshOrderSummary');
                    }),

                // ── Aksi 4: Admin Ajukan Hapus ──
                \Filament\Actions\Action::make('ajukan_hapus')
                    ->label('Ajukan Hapus')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn($record) => auth()->user()->role !== 'owner' && $record->pendingCorrectionRequest() === null)
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Alasan Pengajuan Hapus Pembayaran (Wajib)')
                            ->placeholder('Contoh: Transaksi pembayaran duplikat / salah input ke pesanan ini...')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->action(function ($record, array $data) {
                        $shopId = $record->shop_id ?? \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;

                        \App\Models\PaymentCorrectionRequest::create([
                            'shop_id' => $shopId,
                            'payment_id' => $record->id,
                            'request_type' => 'delete',
                            'old_amount' => $record->amount,
                            'old_payment_method' => $record->payment_method,
                            'old_payment_date' => $record->payment_date,
                            'old_note' => $record->note,
                            'old_proof_image' => $record->proof_image,
                            'reason' => $data['reason'],
                            'requested_by' => auth()->id(),
                            'status' => 'pending',
                        ]);

                        // Kirim Notifikasi ke semua Owner di toko ini
                        try {
                            $owners = \App\Models\User::withoutGlobalScopes()
                                ->where(function ($q) use ($shopId) {
                                    $q->where('shop_id', $shopId)->orWhereNull('shop_id');
                                })
                                ->where('role', 'owner')
                                ->get();

                            $orderUrl = null;
                            try {
                                $orderUrl = \App\Filament\Resources\Orders\OrderResource::getUrl('view', [
                                    'record' => $record->order_id,
                                    'tenant' => $shopId,
                                ]);
                            } catch (\Throwable $e) {
                                // Fallback jika format routing beda
                            }

                            foreach ($owners as $owner) {
                                $notif = \Filament\Notifications\Notification::make()
                                    ->title('⚠️ Permintaan Hapus Pembayaran')
                                    ->body(auth()->user()->name . " mengajukan penghapusan pembayaran " . ($record->order?->order_number ?? '') . " (Rp " . number_format($record->amount, 0, ',', '.') . "). Alasan: " . $data['reason'])
                                    ->danger();

                                if ($orderUrl) {
                                    $notif->actions([
                                        \Filament\Notifications\Actions\Action::make('view')
                                            ->label('Lihat Pesanan')
                                            ->url($orderUrl),
                                    ]);
                                }

                                $notif->sendToDatabase($owner);
                            }
                        } catch (\Throwable $e) {
                            // Abaikan error notifikasi jika gagal
                        }

                        \Filament\Notifications\Notification::make()
                            ->title('Pengajuan Hapus Terkirim')
                            ->body('Pengajuan telah dikirim ke Owner untuk ditinjau.')
                            ->success()
                            ->send();

                        $this->dispatch('refreshOrderSummary');
                    }),

                // ── Aksi 5: Owner Langsung Hapus ──
                DeleteAction::make()
                    ->visible(fn() => auth()->user()->role === 'owner')
                    ->after(function () {
                        $this->dispatch('refreshOrderSummary');
                    }),
                ])
                ->label('Opsi')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->size('sm')
                ->tooltip('Menu Aksi'),
            ])
            ->defaultSort('payment_date', 'asc');
    }
}
