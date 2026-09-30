<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
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
            Placeholder::make('payment_summary')
                ->label(false)
                ->content(function () {
                    $order = $this->getOwnerRecord();
                    if (!$order) return '';

                    $subtotal = (int) ($order->subtotal ?? 0);
                    $tax = (int) ($order->tax ?? 0);
                    $shipping = (int) ($order->shipping_cost ?? 0);
                    $discount = (int) ($order->discount ?? 0);
                    $expressFee = $order->is_express ? (int) ($order->express_fee ?? 0) : 0;
                    $total = (int) ($order->total_price ?? 0);

                    $paid = (int) ($order->payments()?->sum('amount') ?? 0);
                    $remaining = max(0, $total - $paid);

                    return new HtmlString("
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
                ->dehydrateStateUsing(fn ($state) => (int) preg_replace('/[^\d]/', '', (string) ($state ?? 0)))
                ->minValue(1)
                ->maxValue(function () {
                    $order = $this->getOwnerRecord();
                    if (!$order) return null;
                    return max(0, (int) ($order->total_price ?? 0) - (int) ($order->payments()?->sum('amount') ?? 0));
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
                    $img = Image::read($file->getRealPath());

                    if ($img->width() > 1920) {
                        $img->scaleDown(width: 1920);
                    }

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
            ->modifyQueryUsing(fn ($query) => $query->with(['recorder', 'order']))
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
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format((int) ($state ?? 0), 0, ',', '.'))
                    ->color('success')
                    ->verticallyAlignCenter()
                    ->weight('bold'),

                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'transfer' => '🏦 Transfer Bank',
                        'qris' => '📱 QRIS',
                        default => '💵 Cash',
                    })
                    ->color(fn ($state) => match ($state) {
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
                    ->state(fn ($record) => $record?->proof_image ? asset('storage/' . $record->proof_image) : null)
                    ->disk(null)
                    ->square()
                    ->size(48)
                    ->verticallyAlignCenter()
                    ->defaultImageUrl(null),

                TextColumn::make('recorder_display_name')
                    ->label('Dicatat Oleh')
                    ->state(function ($record) {
                        if (!$record) return '—';
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
                        if (!$record) return 'Tercatat';
                        $pending = $record->pendingCorrectionRequest();
                        if ($pending) {
                            return '⏳ Menunggu Approval Owner (' . ($pending->request_type === 'delete' ? 'Hapus' : 'Ubah') . ')';
                        }
                        return 'Tercatat';
                    })
                    ->color(function ($record) {
                        if (!$record) return 'success';
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
                ActionGroup::make([
                    // ── Aksi 1: Owner Review & Setujui Pengajuan ──
                    Action::make('review_pengajuan')
                        ->label('Review & Setujui Pengajuan')
                        ->icon('heroicon-o-check-badge')
                        ->color('warning')
                        ->visible(fn ($record) => auth()->user()->role === 'owner' && $record?->pendingCorrectionRequest() !== null)
                        ->modalHeading('Review Pengajuan Koreksi Pembayaran')
                        ->modalDescription(function ($record) {
                            $req = $record?->pendingCorrectionRequest();
                            return "Diajukan oleh: " . ($req?->requester?->name ?? 'Admin') . " | Alasan: " . ($req?->reason ?? '-');
                        })
                        ->form(function ($record) {
                            $req = $record?->pendingCorrectionRequest();
                            if (!$req) return [];

                            $isDelete = $req->request_type === 'delete';

                            return [
                                Placeholder::make('info_perbandingan')
                                    ->label(false)
                                    ->content(function () use ($record, $req, $isDelete) {
                                        $html = "<div style='background:#f9fafb; border:1px solid #e5e7eb; border-radius:8px; padding:12px; font-size:13px;'>";
                                        $html .= "<div style='font-weight:700; color:#111827; margin-bottom:8px;'>Jenis Pengajuan: " . ($isDelete ? "<span style='color:#ef4444;'>HAPUS PEMBAYARAN</span>" : "<span style='color:#8000FF;'>UBAH / KOREKSI DATA</span>") . "</div>";
                                        $html .= "<table style='width:100%; border-collapse:collapse;'>";
                                        $html .= "<tr style='border-bottom:1px solid #e5e7eb;'><td style='padding:4px 0; color:#6b7280;'>Data Saat Ini:</td><td style='padding:4px 0; font-weight:600;'>Rp " . number_format($req->old_amount, 0, ',', '.') . " (" . ucfirst($req->old_payment_method) . ") - Tgl: " . \Carbon\Carbon::parse($req->old_payment_date)->format('d/m/Y') . "</td></tr>";
                                        if (!$isDelete) {
                                            $html .= "<tr style='border-bottom:1px solid #e5e7eb;'><td style='padding:4px 0; color:#047857;'>Data Yang Diajukan:</td><td style='padding:4px 0; font-weight:700; color:#047857;'>Rp " . number_format($req->new_amount, 0, ',', '.') . " (" . ucfirst($req->new_payment_method) . ") - Tgl: " . \Carbon\Carbon::parse($req->new_payment_date)->format('d/m/Y') . "</td></tr>";
                                        }
                                        $html .= "<tr><td style='padding:4px 0; color:#b45309;'>Alasan Admin:</td><td style='padding:4px 0; font-style:italic; color:#b45309;'>" . htmlspecialchars($req->reason ?? '') . "</td></tr>";
                                        $html .= "</table>";
                                        $html .= "</div>";
                                        return new HtmlString($html);
                                    })
                                    ->columnSpanFull(),
                            ];
                        })
                        ->modalSubmitActionLabel('Setujui Pengajuan (Approve)')
                        ->action(function ($record) {
                            $req = $record?->pendingCorrectionRequest();
                            if (!$req) return;

                            $orderNumber = $record->order?->order_number ?? 'Pembayaran';
                            $shopId = $record->shop_id ?? \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;

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

                            if ($req->requester) {
                                try {
                                    Notification::make()
                                        ->title('Pengajuan Koreksi Pembayaran Disetujui')
                                        ->body("Pengajuan koreksi untuk pembayaran {$orderNumber} telah disetujui Owner.")
                                        ->success()
                                        ->sendToDatabase($req->requester);
                                } catch (\Throwable $e) {}

                                if ($req->requester->phone) {
                                    $pesanWaAdmin = "✅ *PENGAJUAN KOREKSI PEMBAYARAN DISETUJUI*\n\n"
                                        . "Halo {$req->requester->name},\n"
                                        . "Pengajuan koreksi pembayaran untuk pesanan *{$orderNumber}* telah *DISETUJUI* oleh Owner.\n\n"
                                        . "Data pembayaran telah berhasil diperbarui di sistem.";
                                    \App\Helpers\NotificationHelper::sendWaMessage($req->requester->phone, $pesanWaAdmin, $shopId);
                                }
                            }

                            Notification::make()
                                ->title('Perubahan Berhasil Disetujui')
                                ->success()
                                ->send();

                            $this->dispatch('refreshOrderSummary');
                        }),

                    // ── Aksi 2: Owner Tolak Pengajuan ──
                    Action::make('tolak_pengajuan')
                        ->label('Tolak Pengajuan')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => auth()->user()->role === 'owner' && $record?->pendingCorrectionRequest() !== null)
                        ->form([
                            Textarea::make('catatan_penolakan')
                                ->label('Alasan Penolakan (Wajib)')
                                ->placeholder('Tulis alasan mengapa pengajuan ditolak...')
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            $req = $record?->pendingCorrectionRequest();
                            if (!$req) return;

                            $orderNumber = $record->order?->order_number ?? 'Pembayaran';
                            $shopId = $record->shop_id ?? \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;
                            $alasanTolak = $data['catatan_penolakan'] ?? 'Ditolak oleh Owner';

                            $req->update([
                                'status' => 'rejected',
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                                'rejection_reason' => $alasanTolak,
                            ]);

                            if ($req->requester) {
                                try {
                                    Notification::make()
                                        ->title('Pengajuan Koreksi Pembayaran Ditolak')
                                        ->body("Pengajuan koreksi untuk pembayaran {$orderNumber} ditolak Owner. Alasan: " . $alasanTolak)
                                        ->danger()
                                        ->sendToDatabase($req->requester);
                                } catch (\Throwable $e) {}

                                if ($req->requester->phone) {
                                    $pesanWaAdmin = "❌ *PENGAJUAN KOREKSI PEMBAYARAN DITOLAK*\n\n"
                                        . "Halo {$req->requester->name},\n"
                                        . "Pengajuan koreksi pembayaran untuk pesanan *{$orderNumber}* telah *DITOLAK* oleh Owner.\n\n"
                                        . "📝 *Alasan Penolakan:* {$alasanTolak}";
                                    \App\Helpers\NotificationHelper::sendWaMessage($req->requester->phone, $pesanWaAdmin, $shopId);
                                }
                            }

                            Notification::make()
                                ->title('Pengajuan Berhasil Ditolak')
                                ->warning()
                                ->send();

                            $this->dispatch('refreshOrderSummary');
                        }),

                    // ── Aksi 3: Owner Langsung Edit ──
                    EditAction::make()
                        ->visible(fn () => auth()->user()->role === 'owner')
                        ->after(function () {
                            $this->dispatch('refreshOrderSummary');
                        }),

                    // ── Aksi 4: Admin Ajukan Koreksi ──
                    Action::make('ajukan_koreksi')
                        ->label('Ajukan Koreksi')
                        ->icon('heroicon-o-pencil-square')
                        ->color('primary')
                        ->visible(fn ($record) => auth()->user()->role !== 'owner' && $record?->pendingCorrectionRequest() === null)
                        ->fillForm(fn ($record): array => $record ? [
                            'new_amount' => $record->amount,
                            'new_payment_date' => $record->payment_date,
                            'new_payment_method' => $record->payment_method,
                            'new_note' => $record->note,
                        ] : [])
                        ->form([
                            TextInput::make('new_amount')
                                ->label('Nominal Baru yang Benar (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(),

                            DatePicker::make('new_payment_date')
                                ->label('Tanggal Pembayaran')
                                ->required()
                                ->native(false),

                            Select::make('new_payment_method')
                                ->label('Metode Pembayaran')
                                ->options([
                                    'cash' => '💵 Cash',
                                    'transfer' => '🏦 Transfer Bank',
                                    'qris' => '📱 QRIS',
                                ])
                                ->required(),

                            TextInput::make('new_note')
                                ->label('Catatan Pembayaran'),

                            Textarea::make('reason')
                                ->label('Alasan Pengajuan Koreksi (Wajib)')
                                ->placeholder('Contoh: Salah ketik kelebihan nol, pelanggan sebenarnya bayar via transfer...')
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->action(function ($record, array $data) {
                            $shopId = $record->shop_id ?? \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;
                            $orderNumber = $record->order?->order_number ?? 'Pembayaran';

                            \App\Models\PaymentCorrectionRequest::create([
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

                            $pesanWaOwner = "⚠️ *PENGAJUAN KOREKSI PEMBAYARAN*\n\n"
                                . "Halo Owner,\n"
                                . "Admin *" . auth()->user()->name . "* mengajukan *KOREKSI DATA PEMBAYARAN* pada pesanan *{$orderNumber}*.\n\n"
                                . "📋 *Rincian:*\n"
                                . "• Data Lama: Rp " . number_format($record->amount, 0, ',', '.') . " (" . $record->methodLabel() . ")\n"
                                . "• Data Baru: Rp " . number_format($data['new_amount'], 0, ',', '.') . " (" . ucfirst($data['new_payment_method']) . ")\n"
                                . "• Alasan: {$data['reason']}\n\n"
                                . "Silakan buka menu *Kas Masuk & Piutang* di sistem untuk menyetujui / menolak pengajuan.";

                            try {
                                $owners = \App\Models\User::withoutGlobalScopes()
                                    ->where(function ($q) use ($shopId) {
                                        $q->where('shop_id', $shopId)->orWhereNull('shop_id');
                                    })
                                    ->where('role', 'owner')
                                    ->get();

                                $orderUrl = "/app/{$shopId}/orders/{$record->order_id}";

                                if ($owners->isNotEmpty()) {
                                    Notification::make()
                                        ->title('⚠️ Permintaan Koreksi Pembayaran')
                                        ->body(auth()->user()->name . " mengajukan koreksi pembayaran {$orderNumber} dari Rp " . number_format($record->amount, 0, ',', '.') . " -> Rp " . number_format($data['new_amount'], 0, ',', '.') . ". Alasan: " . $data['reason'])
                                        ->warning()
                                        ->actions([
                                            \Filament\Notifications\Actions\Action::make('view')
                                                ->label('Lihat Pesanan')
                                                ->url($orderUrl),
                                        ])
                                        ->sendToDatabase($owners);
                                }

                                foreach ($owners as $owner) {
                                    if ($owner->phone) {
                                        \App\Helpers\NotificationHelper::sendWaMessage($owner->phone, $pesanWaOwner, $shopId);
                                    }
                                }

                                if ($record->shop?->phone) {
                                    \App\Helpers\NotificationHelper::sendWaMessage($record->shop->phone, $pesanWaOwner, $shopId);
                                }
                            } catch (\Throwable $e) {
                                Log::error('[Correction-Req] Gagal kirim notif: ' . $e->getMessage());
                            }

                            Notification::make()
                                ->title('Pengajuan Koreksi Terkirim')
                                ->body('Pengajuan telah dikirim ke Owner untuk ditinjau.')
                                ->success()
                                ->send();

                            $this->dispatch('refreshOrderSummary');
                        }),

                    // ── Aksi 5: Admin Ajukan Hapus ──
                    Action::make('ajukan_hapus')
                        ->label('Ajukan Hapus')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn ($record) => auth()->user()->role !== 'owner' && $record?->pendingCorrectionRequest() === null)
                        ->form([
                            Textarea::make('reason')
                                ->label('Alasan Pengajuan Hapus Pembayaran (Wajib)')
                                ->placeholder('Contoh: Transaksi pembayaran duplikat / salah input ke pesanan ini...')
                                ->required()
                                ->columnSpanFull(),
                        ])
                        ->action(function ($record, array $data) {
                            $shopId = $record->shop_id ?? \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;
                            $orderNumber = $record->order?->order_number ?? 'Pembayaran';

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

                            $pesanWaOwner = "⚠️ *PENGAJUAN HAPUS PEMBAYARAN*\n\n"
                                . "Halo Owner,\n"
                                . "Admin *" . auth()->user()->name . "* mengajukan *PENGHAPUSAN PEMBAYARAN* pada pesanan *{$orderNumber}* (Rp " . number_format($record->amount, 0, ',', '.') . ").\n\n"
                                . "📋 *Alasan:* {$data['reason']}\n\n"
                                . "Silakan buka menu *Kas Masuk & Piutang* di sistem untuk menyetujui / menolak pengajuan.";

                            try {
                                $owners = \App\Models\User::withoutGlobalScopes()
                                    ->where(function ($q) use ($shopId) {
                                        $q->where('shop_id', $shopId)->orWhereNull('shop_id');
                                    })
                                    ->where('role', 'owner')
                                    ->get();

                                $orderUrl = "/app/{$shopId}/orders/{$record->order_id}";

                                if ($owners->isNotEmpty()) {
                                    Notification::make()
                                        ->title('⚠️ Permintaan Hapus Pembayaran')
                                        ->body(auth()->user()->name . " mengajukan penghapusan pembayaran {$orderNumber} (Rp " . number_format($record->amount, 0, ',', '.') . "). Alasan: " . $data['reason'])
                                        ->danger()
                                        ->actions([
                                            \Filament\Notifications\Actions\Action::make('view')
                                                ->label('Lihat Pesanan')
                                                ->url($orderUrl),
                                        ])
                                        ->sendToDatabase($owners);
                                }

                                foreach ($owners as $owner) {
                                    if ($owner->phone) {
                                        \App\Helpers\NotificationHelper::sendWaMessage($owner->phone, $pesanWaOwner, $shopId);
                                    }
                                }

                                if ($record->shop?->phone) {
                                    \App\Helpers\NotificationHelper::sendWaMessage($record->shop->phone, $pesanWaOwner, $shopId);
                                }
                            } catch (\Throwable $e) {
                                Log::error('[Delete-Req] Gagal kirim notif: ' . $e->getMessage());
                            }

                            Notification::make()
                                ->title('Pengajuan Hapus Terkirim')
                                ->body('Pengajuan telah dikirim ke Owner untuk ditinjau.')
                                ->success()
                                ->send();

                            $this->dispatch('refreshOrderSummary');
                        }),

                    // ── Aksi 6: Owner Langsung Hapus ──
                    DeleteAction::make()
                        ->visible(fn () => auth()->user()->role === 'owner')
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
