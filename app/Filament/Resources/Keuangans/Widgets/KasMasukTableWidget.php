<?php

namespace App\Filament\Resources\Keuangans\Widgets;

use App\Models\Payment;
use App\Models\PaymentCorrectionRequest;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
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
                        if (!$record) return '-';

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

                        if ($order->orderItems && $order->orderItems->count() > 0) {
                            $html .= '<div class="flex flex-wrap gap-1 mt-1">';
                            $groupedItems = $order->orderItems->groupBy(fn ($item) => ($item->product_name ?: 'Item'));
                            
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
                    ->formatStateUsing(fn ($state) => 'Rp ' . number_format((int) ($state ?? 0), 0, ',', '.'))
                    ->extraAttributes([
                        'class' => 'text-purple-600',
                    ])
                    ->weight('bold')
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                TextColumn::make('payment_method')
                    ->label('Metode')
                    ->badge()
                    ->formatStateUsing(fn ($record) => $record?->methodLabel() ?? 'Cash')
                    ->color(fn ($record) => match ($record?->payment_method) {
                        'transfer' => 'primary',
                        'qris' => 'info',
                        default => 'gray',
                    })
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                TextColumn::make('recorder.name')
                    ->label('Penerima / Dicatat Oleh')
                    ->default('-')
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),

                TextColumn::make('status_approval')
                    ->label('Status')
                    ->badge()
                    ->state(function ($record) {
                        if (!$record) return 'Tercatat';
                        $pending = $record->pendingCorrectionRequest();
                        if ($pending) {
                            return '⏳ Menunggu Approval (' . ($pending->request_type === 'delete' ? 'Hapus' : 'Ubah') . ')';
                        }
                        return 'Tercatat';
                    })
                    ->color(function ($record) {
                        if (!$record) return 'success';
                        return $record->pendingCorrectionRequest() ? 'warning' : 'success';
                    })
                    ->extraCellAttributes(['style' => 'vertical-align: top;']),
            ])
            ->headerActions([
                CreateAction::make('tambah_modal')
                    ->label('Tambah Modal Kas Kecil')
                    ->icon('heroicon-o-plus-circle')
                    ->color('primary')
                    ->modalHeading('Tambah Modal Kas Kecil / Kas Masuk')
                    ->form([
                        TextInput::make('amount')
                            ->label('Nominal Modal (Rp)')
                            ->numeric()
                            ->prefix('Rp')
                            ->required()
                            ->placeholder('1000000'),

                        DatePicker::make('payment_date')
                            ->label('Tanggal Masuk')
                            ->required()
                            ->default(now())
                            ->native(false),

                        Select::make('payment_method')
                            ->label('Metode Penerimaan')
                            ->options([
                                'cash' => 'Cash (Tunai Fisik)',
                                'transfer' => 'Transfer Bank',
                            ])
                            ->default('cash')
                            ->required(),

                        TextInput::make('note')
                            ->label('Keterangan / Catatan')
                            ->placeholder('Contoh: Modal harian dari Owner')
                            ->default('Modal harian dari Owner')
                            ->required()
                            ->maxLength(255),

                        FileUpload::make('proof_image')
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
                ActionGroup::make([
                    // ── Aksi 1: Owner Review (Approve / Tolak dalam satu popup) ──
                    Action::make('review_pengajuan')
                        ->label('Review')
                        ->icon('heroicon-o-check-badge')
                        ->color('warning')
                        ->visible(fn ($record) => auth()->user()->role === 'owner' && $record?->pendingCorrectionRequest() !== null)
                        ->modalHeading('Review Pengajuan Koreksi Pembayaran')
                        ->modalDescription(function ($record) {
                            $req = $record?->pendingCorrectionRequest();
                            return "Diajukan oleh: " . ($req?->requester?->name ?? 'Admin') . " | Alasan Admin: " . ($req?->reason ?? '-');
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

                                Select::make('keputusan')
                                    ->label('Keputusan Review')
                                    ->options([
                                        'approve' => 'Setujui Pengajuan (Approve)',
                                        'reject' => 'Tolak Pengajuan (Reject / Batalkan)',
                                    ])
                                    ->default('approve')
                                    ->live()
                                    ->required(),

                                Textarea::make('alasan_penolakan')
                                    ->label('Alasan Penolakan (Wajib jika ditolak)')
                                    ->placeholder('Tuliskan alasan penolakan untuk admin...')
                                    ->visible(fn ($get) => $get('keputusan') === 'reject')
                                    ->required(fn ($get) => $get('keputusan') === 'reject')
                                    ->columnSpanFull(),
                            ];
                        })
                        ->modalSubmitActionLabel('Kirim')
                        ->modalSubmitAction(fn ($action) => $action->color('primary'))
                        ->action(function ($record, array $data) {
                            $req = $record?->pendingCorrectionRequest();
                            if (!$req) return;

                            $orderNumber = $record->order?->order_number ?? 'Pembayaran';
                            $shopId = $record->shop_id ?? \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;
                            $keputusan = $data['keputusan'] ?? 'approve';

                            if ($keputusan === 'reject') {
                                $alasanTolak = $data['alasan_penolakan'] ?? 'Ditolak oleh Owner';
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
                            } else {
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
                            }

                            $this->dispatch('refreshStats');
                        }),

                    // ── Aksi 3: Buka Pesanan (Jika terkait pesanan) ──
                    Action::make('lihat_pesanan')
                        ->label('Detail')
                        ->icon('heroicon-o-arrow-top-right-on-square')
                        ->visible(fn ($record) => (bool) $record?->order_id)
                        ->url(fn ($record) => "/app/" . ($record?->shop_id ?? Filament::getTenant()?->id) . "/orders/{$record?->order_id}?relation=1"),

                    // ── Aksi 4: Edit Modal Kas Kecil (Bukan Pesanan) ──
                    EditAction::make('edit_modal')
                        ->label('Edit')
                        ->visible(fn ($record) => $record?->type === 'modal_awal' || !$record?->order_id)
                        ->form([
                            TextInput::make('amount')
                                ->label('Nominal Modal (Rp)')
                                ->numeric()
                                ->prefix('Rp')
                                ->required(),

                            DatePicker::make('payment_date')
                                ->label('Tanggal Masuk')
                                ->required()
                                ->native(false),

                            Select::make('payment_method')
                                ->label('Metode Penerimaan')
                                ->options([
                                    'cash' => 'Cash (Tunai Fisik)',
                                    'transfer' => 'Transfer Bank',
                                ])
                                ->required(),

                            TextInput::make('note')
                                ->label('Keterangan / Catatan')
                                ->required()
                                ->maxLength(255),

                            FileUpload::make('proof_image')
                                ->label('Foto Bukti (Opsional)')
                                ->image()
                                ->disk('public')
                                ->directory('payment-proofs'),
                        ])
                        ->after(function () {
                            $this->dispatch('refreshStats');
                        }),

                    // ── Aksi 5: Hapus Modal Kas Kecil (Bukan Pesanan) ──
                    DeleteAction::make('hapus_modal')
                        ->label('Hapus')
                        ->visible(fn ($record) => ($record?->type === 'modal_awal' || !$record?->order_id) && auth()->user()->role === 'owner')
                        ->after(function () {
                            $this->dispatch('refreshStats');
                        }),
                ])
                    ->label('Opsi')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->color('gray')
                    ->size('sm')
                    ->tooltip('Menu Aksi'),
            ])
            ->emptyStateHeading('Belum Ada Kas Masuk')
            ->emptyStateDescription('Catat modal harian atau pembayaran pesanan melalui detail pesanan / tab Piutang.')
            ->filters([
                Filter::make('payment_date')
                    ->form([
                        DatePicker::make('from')
                            ->label('Dari Tanggal'),
                        DatePicker::make('until')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '>=', $date),
                            )
                            ->when(
                                $data['until'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('payment_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'Dari ' . Carbon::parse($data['from'])->format('d M Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Sampai ' . Carbon::parse($data['until'])->format('d M Y');
                        }
                        return $indicators;
                    }),

                SelectFilter::make('payment_method')
                    ->label('Metode Pembayaran')
                    ->options([
                        'cash' => 'Cash',
                        'transfer' => 'Transfer Bank',
                        'qris' => 'QRIS',
                    ]),
            ]);
    }
}
