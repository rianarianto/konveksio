<?php

namespace App\Filament\Resources\Workers\RelationManagers;

use App\Models\ProductionTask;
use App\Models\WageCorrectionRequest;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ProductionTasksRelationManager extends RelationManager
{
    protected static string $relationship = 'productionTasks';

    protected static ?string $title = 'Riwayat Pekerjaan & Upah';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('stage_name')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['orderItem.order']))
            ->columns([
                TextColumn::make('orderItem.order.order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable()
                    ->color('primary')
                    ->weight('bold')
                    ->url(function (ProductionTask $record): ?string {
                        $orderId = $record->orderItem?->order_id;
                        $shopId = $record->shop_id ?? \Filament\Facades\Filament::getTenant()?->id;
                        return $orderId ? "/app/{$shopId}/orders/{$orderId}" : null;
                    })
                    ->openUrlInNewTab(),

                TextColumn::make('orderItem.product_name')
                    ->label('Produk')
                    ->description(fn (ProductionTask $record): string => $record->orderItem->production_category ?? '-')
                    ->searchable(),

                TextColumn::make('stage_name')
                    ->label('Tahap'),

                TextColumn::make('quantity')
                    ->label('Qty & Ukuran')
                    ->alignLeft()
                    ->html()
                    ->getStateUsing(function (ProductionTask $record): string {
                        $qtyBadge = '<span style="display:inline-flex; align-items:center; font-weight:600; font-size:12px; line-height:1; color:#2563eb; background:#eff6ff; border:1px solid #bfdbfe; border-radius:9999px; padding:3px 8px;">' . $record->quantity . ' pcs</span>';
                        
                        $sq = $record->size_quantities;
                        $sizeDetails = [];

                        if (!empty($sq) && is_array($sq)) {
                            $cleanSizes = array_filter($sq, fn($k) => !str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
                            $genders = $sq['_genders'] ?? [];
                            $customRecipients = $sq['_custom_recipients'] ?? [];

                            foreach ($cleanSizes as $sz => $cnt) {
                                if ($cnt <= 0) continue;
                                $displaySz = $sz === 'TANPA_UKURAN' ? 'Tanpa Ukuran' : $sz;
                                
                                if (!empty($genders[$sz])) {
                                    $gStrs = [];
                                    foreach ($genders[$sz] as $gLabel => $gQty) {
                                        $gStrs[] = ($gLabel === 'P' ? 'P' : 'L') . ':' . $gQty;
                                    }
                                    $sizeDetails[] = "<strong>{$displaySz}</strong> (" . implode(', ', $gStrs) . ')';
                                } else {
                                    $sizeDetails[] = "<strong>{$displaySz}</strong>: {$cnt}";
                                }
                            }

                            if (!empty($customRecipients)) {
                                $sizeDetails[] = '<span style="color:#d97706; font-weight:600;">📏 Ukur Badan: ' . implode(', ', array_slice($customRecipients, 0, 3)) . (count($customRecipients) > 3 ? '...' : '') . '</span>';
                            }
                        }

                        if (!empty($sizeDetails)) {
                            return '<div style="text-align:left;">' . $qtyBadge . '<div style="font-size:12px; color:#64748b; margin-top:4px; line-height:1.4; text-align:left;">' . implode(' • ', $sizeDetails) . '</div></div>';
                        }

                        if (!empty($record->description)) {
                            $firstLine = explode("\n", $record->description)[0];
                            return '<div style="text-align:left;">' . $qtyBadge . '<div style="font-size:12px; color:#64748b; margin-top:4px; text-align:left;">' . e($firstLine) . '</div></div>';
                        }

                        return '<div style="text-align:left;">' . $qtyBadge . '</div>';
                    }),

                TextColumn::make('wage_amount')
                    ->label('Total Upah')
                    ->money('IDR')
                    ->weight('bold')
                    ->color('success')
                    ->description(function (ProductionTask $record): ?string {
                        $pending = $record->pendingWageCorrectionRequest();
                        if ($pending) {
                            return '⏳ Koreksi diajukan: Rp ' . number_format($pending->new_wage_amount, 0, ',', '.');
                        }
                        return null;
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'pending' => 'warning',
                        'in_progress' => 'info',
                        'done' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => '⏳ Antrian',
                        'in_progress' => '🔨 Dikerjakan',
                        'done' => '✅ Selesai',
                        default => $state,
                    }),

                TextColumn::make('is_paid')
                    ->label('Upah Dibayar')
                    ->badge()
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Lunas' : 'Belum Dibayar'),

                TextColumn::make('completed_at')
                    ->label('Selesai')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('is_paid')
                    ->label('Status Upah')
                    ->options([
                        '0' => 'Belum Dibayar',
                        '1' => 'Sudah Dibayar',
                    ])
                    ->default('0')
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['value']) || $data['value'] === null || $data['value'] === '') {
                            return $query;
                        }
                        if ($data['value'] === '0') {
                            return $query->where('is_paid', false);
                        }
                        if ($data['value'] === '1') {
                            return $query->where('is_paid', true);
                        }
                        return $query;
                    }),

                SelectFilter::make('status')
                    ->options([
                        'pending' => '⏳ Antrian',
                        'in_progress' => '🔨 Dikerjakan',
                        'done' => '✅ Selesai',
                    ]),
                
                SelectFilter::make('stage_name')
                    ->label('Tahap Pekerjaan')
                    ->options(\App\Models\ProductionTask::distinct()->pluck('stage_name', 'stage_name')->toArray()),

                Filter::make('completed_at')
                    ->form([
                        DatePicker::make('completed_from')->label('Selesai Dari'),
                        DatePicker::make('completed_until')->label('Selesai Sampai'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['completed_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('completed_at', '>=', $date),
                            )
                            ->when(
                                $data['completed_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('completed_at', '<=', $date),
                            );
                    })
            ])
            ->headerActions([])
            ->actions([
                Action::make('koreksi_upah')
                    ->label('Koreksi')
                    ->icon('heroicon-m-pencil-square')
                    ->color('warning')
                    ->button()
                    ->size('xs')
                    ->tooltip(function (ProductionTask $record): ?string {
                        return $record->is_paid ? 'Tugas ini sudah dibayar/payroll closed' : 'Koreksi tarif/upah tugas ini';
                    })
                    ->disabled(fn (ProductionTask $record) => (bool) $record->is_paid)
                    ->visible(fn (ProductionTask $record) => !(auth()->user()->role === 'owner' && (bool) $record->pendingWageCorrectionRequest()))
                    ->modalHeading(fn (ProductionTask $record) => 'Koreksi Upah: ' . ($record->stage_name ?? 'Tugas') . ' (' . ($record->orderItem?->order?->order_number ?? '-') . ')')
                    ->modalDescription(function (ProductionTask $record) {
                        $isOwner = auth()->user()->role === 'owner';
                        return $isOwner
                            ? 'Sebagai Owner, perubahan upah akan langsung diperbarui ke sistem.'
                            : 'Pengajuan koreksi akan dikirimkan ke Owner untuk disetujui terlebih dahulu.';
                    })
                    ->fillForm(function (ProductionTask $record): array {
                        $pending = $record->pendingWageCorrectionRequest();
                        $sq = $record->size_quantities ?? [];
                        $customQty = (int) ($sq['CUSTOM'] ?? 0);
                        $totalQty = (int) $record->quantity;
                        $standardQty = max(0, $totalQty - $customQty);
                        $totalWage = (int) $record->wage_amount;
                        $customWage = (int) ($sq['_wage_custom'] ?? 0);

                        $standardWage = ($standardQty > 0)
                            ? (int) round(($totalWage - ($customQty * $customWage)) / $standardQty)
                            : 0;

                        if ($pending) {
                            return [
                                'total_qty' => $totalQty,
                                'standard_qty' => $standardQty,
                                'custom_qty' => $customQty,
                                'old_wage_per_pcs' => $standardWage,
                                'new_wage_per_pcs' => $pending->new_wage_per_pcs,
                                'old_wage_custom_per_pcs' => $customWage,
                                'new_wage_custom_per_pcs' => $pending->new_wage_custom_per_pcs,
                                'old_wage_amount' => $totalWage,
                                'new_wage_amount' => $pending->new_wage_amount,
                                'reason' => $pending->reason,
                            ];
                        }

                        return [
                            'total_qty' => $totalQty,
                            'standard_qty' => $standardQty,
                            'custom_qty' => $customQty,
                            'old_wage_per_pcs' => $standardWage,
                            'new_wage_per_pcs' => $standardWage,
                            'old_wage_custom_per_pcs' => $customWage,
                            'new_wage_custom_per_pcs' => $customWage,
                            'old_wage_amount' => $totalWage,
                            'new_wage_amount' => $totalWage,
                        ];
                    })
                    ->form(function (ProductionTask $record) {
                        $sq = $record->size_quantities ?? [];
                        $hasCustom = ((int) ($sq['CUSTOM'] ?? 0)) > 0;

                        return [
                            Section::make('Rincian Pengerjaan')
                                ->schema([
                                    Placeholder::make('info_pengerjaan')
                                        ->hiddenLabel()
                                        ->content(function ($get) use ($record, $hasCustom) {
                                            $orderNum = $record->orderItem?->order?->order_number ?? '-';
                                            $prod = $record->orderItem?->product_name ?? '-';
                                            $tahap = $record->stage_name ?? '-';
                                            $stdQty = $get('standard_qty') ?? 0;
                                            $cstQty = $get('custom_qty') ?? 0;

                                            $html = "
                                            <div style='background-color:#f8fafc; border:1px solid #e2e8f0; border-radius:10px; padding:14px 16px; font-family:inherit;'>
                                                <div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;'>
                                                    <span style='font-size:13px; font-weight:700; color:#1e293b;'>{$orderNum} — {$prod}</span>
                                                    <span style='font-size:11px; font-weight:600; color:#2563eb; background-color:#eff6ff; border:1px solid #bfdbfe; border-radius:9999px; padding:2px 8px;'>{$record->quantity} pcs</span>
                                                </div>
                                                <div style='display:grid; grid-template-columns:1fr 1fr; gap:8px; font-size:12px; color:#475569;'>
                                                    <div>Tahap: <strong style='color:#0f172a;'>{$tahap}</strong></div>
                                                    <div>Komposisi: <strong style='color:#0f172a;'>{$stdQty} std" . ($hasCustom ? " + {$cstQty} cst" : "") . "</strong></div>
                                                </div>
                                                <div style='margin-top:10px; padding-top:8px; border-top:1px dashed #cbd5e1; display:flex; justify-content:space-between; align-items:center;'>
                                                    <span style='font-size:12px; color:#64748b;'>Total Upah Saat Ini:</span>
                                                    <span style='font-size:14px; font-weight:700; color:#059669;'>Rp " . number_format($record->wage_amount, 0, ',', '.') . "</span>
                                                </div>
                                            </div>";

                                            return new HtmlString($html);
                                        }),
                                ]),

                            Section::make('Koreksi Tarif per Pcs')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('new_wage_per_pcs')
                                        ->label('Tarif Standar Baru (per pcs)')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->required()
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set, $get) {
                                            $stdQty = (int) ($get('standard_qty') ?? 0);
                                            $cstQty = (int) ($get('custom_qty') ?? 0);
                                            $stdWage = (int) ($state ?? 0);
                                            $cstWage = (int) ($get('new_wage_custom_per_pcs') ?? 0);
                                            $set('new_wage_amount', ($stdQty * $stdWage) + ($cstQty * $cstWage));
                                        }),

                                    TextInput::make('new_wage_custom_per_pcs')
                                        ->label('Tarif Custom/Ukur Badan (per pcs)')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->visible($hasCustom)
                                        ->required($hasCustom)
                                        ->live()
                                        ->afterStateUpdated(function ($state, $set, $get) {
                                            $stdQty = (int) ($get('standard_qty') ?? 0);
                                            $cstQty = (int) ($get('custom_qty') ?? 0);
                                            $stdWage = (int) ($get('new_wage_per_pcs') ?? 0);
                                            $cstWage = (int) ($state ?? 0);
                                            $set('new_wage_amount', ($stdQty * $stdWage) + ($cstQty * $cstWage));
                                        }),

                                    TextInput::make('new_wage_amount')
                                        ->label('Total Upah Baru (Otomatis)')
                                        ->numeric()
                                        ->prefix('Rp')
                                        ->readOnly()
                                        ->extraInputAttributes(['class' => 'font-bold text-success-600'])
                                        ->columnSpanFull(),

                                    Textarea::make('reason')
                                        ->label(fn () => auth()->user()->role === 'owner' ? 'Alasan / Catatan Koreksi (Opsional)' : 'Alasan Pengajuan Koreksi (Wajib)')
                                        ->placeholder(fn () => auth()->user()->role === 'owner' ? 'Contoh: Penyesuaian tarif borongan khusus (boleh dikosongkan)' : 'Contoh: Salah atur tarif saat atur tugas produksi...')
                                        ->required(fn ($get) => auth()->user()->role !== 'owner' && !$get('batal_pengajuan'))
                                        ->columnSpanFull(),

                                    \Filament\Forms\Components\Checkbox::make('batal_pengajuan')
                                        ->label('❌ Batalkan & Tarik pengajuan koreksi ini (Hapus antrian pending)')
                                        ->visible(fn () => (bool) $record->pendingWageCorrectionRequest())
                                        ->live()
                                        ->columnSpanFull(),
                                ]),
                        ];
                    })
                    ->modalSubmitActionLabel(fn () => auth()->user()->role === 'owner' ? 'Simpan Perubahan' : 'Kirim / Proses')
                    ->modalSubmitAction(fn ($action) => $action->color('primary'))
                    ->action(function (ProductionTask $record, array $data) {
                        $isOwner = auth()->user()->role === 'owner';
                        $shopId = $record->shop_id ?? \Filament\Facades\Filament::getTenant()?->id ?? auth()->user()->shop_id;

                        // Jika Admin memilih batalkan pengajuan di modalnya
                        if (!empty($data['batal_pengajuan'])) {
                            $pending = $record->pendingWageCorrectionRequest();
                            if ($pending) {
                                $pending->delete();
                                Notification::make()
                                    ->title('Pengajuan Dibatalkan')
                                    ->body('Pengajuan koreksi upah berhasil ditarik dan dibatalkan.')
                                    ->info()
                                    ->send();
                            }
                            return;
                        }

                        $sq = $record->size_quantities ?? [];
                        $customQty = (int) ($sq['CUSTOM'] ?? 0);
                        $totalQty = (int) $record->quantity;
                        $standardQty = max(0, $totalQty - $customQty);
                        $totalWage = (int) $record->wage_amount;
                        $customWage = (int) ($sq['_wage_custom'] ?? 0);
                        $standardWage = ($standardQty > 0)
                            ? (int) round(($totalWage - ($customQty * $customWage)) / $standardQty)
                            : 0;

                        $newStdWage = (int) ($data['new_wage_per_pcs'] ?? 0);
                        $newCstWage = isset($data['new_wage_custom_per_pcs']) ? (int) $data['new_wage_custom_per_pcs'] : $newStdWage;
                        $newTotalWage = ($standardQty * $newStdWage) + ($customQty * $newCstWage);

                        if ($isOwner) {
                            $updatedSq = $sq;
                            if ($customQty > 0) {
                                $updatedSq['_wage_custom'] = $newCstWage;
                            }
                            $record->update([
                                'wage_amount' => $newTotalWage,
                                'size_quantities' => $updatedSq,
                            ]);

                            $reason = !empty($data['reason']) ? $data['reason'] : 'Diubah langsung oleh Owner';

                            WageCorrectionRequest::create([
                                'shop_id' => $shopId,
                                'production_task_id' => $record->id,
                                'old_wage_per_pcs' => $standardWage,
                                'old_wage_custom_per_pcs' => $customWage,
                                'old_wage_amount' => $totalWage,
                                'new_wage_per_pcs' => $newStdWage,
                                'new_wage_custom_per_pcs' => $newCstWage,
                                'new_wage_amount' => $newTotalWage,
                                'reason' => $reason,
                                'requested_by' => auth()->id(),
                                'status' => 'approved',
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Upah Berhasil Dikoreksi')
                                ->body('Total upah tugas ini berhasil diperbarui menjadi Rp ' . number_format($newTotalWage, 0, ',', '.'))
                                ->success()
                                ->send();
                        } else {
                            $pending = $record->pendingWageCorrectionRequest();
                            if ($pending) {
                                $pending->update([
                                    'new_wage_per_pcs' => $newStdWage,
                                    'new_wage_custom_per_pcs' => $newCstWage,
                                    'new_wage_amount' => $newTotalWage,
                                    'reason' => $data['reason'],
                                ]);
                            } else {
                                WageCorrectionRequest::create([
                                    'shop_id' => $shopId,
                                    'production_task_id' => $record->id,
                                    'old_wage_per_pcs' => $standardWage,
                                    'old_wage_custom_per_pcs' => $customWage,
                                    'old_wage_amount' => $totalWage,
                                    'new_wage_per_pcs' => $newStdWage,
                                    'new_wage_custom_per_pcs' => $newCstWage,
                                    'new_wage_amount' => $newTotalWage,
                                    'reason' => $data['reason'],
                                    'requested_by' => auth()->id(),
                                    'status' => 'pending',
                                ]);
                            }

                            $owners = \App\Models\User::withoutGlobalScopes()
                                ->where('role', 'owner')
                                ->get();

                            $workerName = $record->assignedWorker?->name ?? 'Tukang';
                            $orderNum = $record->orderItem?->order?->order_number ?? 'Pesanan';

                            foreach ($owners as $owner) {
                                $notif = Notification::make()
                                    ->title('⚠️ Pengajuan Koreksi Upah Tukang')
                                    ->body(auth()->user()->name . " mengajukan koreksi upah {$workerName} ({$record->stage_name} - {$orderNum}) dari Rp " . number_format($totalWage, 0, ',', '.') . " -> Rp " . number_format($newTotalWage, 0, ',', '.') . ". Alasan: " . $data['reason'])
                                    ->warning()
                                    ->actions([
                                        \Filament\Actions\Action::make('review')
                                            ->label('Review Pengajuan')
                                            ->url("/app/{$shopId}/workers/{$record->assigned_to}/edit?relation=0"),
                                    ]);

                                // Kirim synchronous langsung ke database (tidak tertahan di queue)
                                $owner->notifyNow($notif->toDatabase(), ['database']);
                            }

                            Notification::make()
                                ->title('Pengajuan Koreksi Terkirim')
                                ->body('Permintaan koreksi upah telah dikirim ke Owner untuk disetujui.')
                                ->info()
                                ->send();
                        }
                    }),

                Action::make('review_koreksi')
                    ->label('Review Koreksi')
                    ->icon('heroicon-m-check-badge')
                    ->color('danger')
                    ->button()
                    ->size('xs')
                    ->visible(fn (ProductionTask $record) => auth()->user()->role === 'owner' && (bool) $record->pendingWageCorrectionRequest())
                    ->modalHeading(fn (ProductionTask $record) => 'Persetujuan Koreksi Upah: ' . ($record->stage_name ?? 'Tugas'))
                    ->modalDescription('Tinjau perubahan upah yang diajukan oleh staff/admin.')
                    ->form(function (ProductionTask $record) {
                        $req = $record->pendingWageCorrectionRequest();
                        if (!$req) return [];

                        $workerName = $record->assignedWorker?->name ?? 'Tukang';
                        $orderNum = $record->orderItem?->order?->order_number ?? '-';
                        $requesterName = $req->requester?->name ?? 'Staff';

                        return [
                            Placeholder::make('perbandingan')
                                ->hiddenLabel()
                                ->content(function () use ($req, $workerName, $orderNum, $requesterName, $record) {
                                    $hasCustom = !empty($req->new_wage_custom_per_pcs) && $req->new_wage_custom_per_pcs > 0;
                                    
                                    $html = "
                                    <div style='background-color:#fffbeb; border:1px solid #fde68a; border-radius:12px; padding:18px; margin-bottom:6px; font-family:inherit;'>
                                        <!-- Header Pengaju & Order -->
                                        <div style='display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; padding-bottom:12px; border-bottom:1px solid #fef3c7;'>
                                            <div>
                                                <div style='font-size:12px; text-transform:uppercase; letter-spacing:0.05em; color:#92400e; font-weight:700; margin-bottom:2px;'>Pengajuan Koreksi Upah</div>
                                                <div style='font-size:14px; font-weight:600; color:#1e293b;'>{$workerName} <span style='color:#64748b; font-weight:400;'>({$record->stage_name})</span></div>
                                                <div style='font-size:12px; color:#64748b; margin-top:2px;'>Pesanan: <strong style='color:#334155;'>{$orderNum}</strong> • Qty: <strong>{$record->quantity} pcs</strong></div>
                                            </div>
                                            <div style='text-align:right;'>
                                                <span style='display:inline-block; font-size:11px; font-weight:600; color:#92400e; background-color:#fef3c7; border:1px solid #fde68a; border-radius:9999px; padding:2px 10px;'>
                                                    Oleh: {$requesterName}
                                                </span>
                                                <div style='font-size:11px; color:#94a3b8; margin-top:4px;'>" . $req->created_at->format('d M Y, H:i') . "</div>
                                            </div>
                                        </div>

                                        <!-- Alasan Pengajuan -->
                                        <div style='background-color:#ffffff; border:1px solid #fef3c7; border-radius:8px; padding:10px 12px; margin-bottom:14px;'>
                                            <div style='font-size:11px; font-weight:600; color:#78350f; text-transform:uppercase; letter-spacing:0.03em;'>Alasan Koreksi:</div>
                                            <div style='font-size:13px; color:#334155; margin-top:2px; font-style:italic;'>“" . e($req->reason) . "”</div>
                                        </div>

                                        <!-- Kotak Perbandingan Tarif (Grid 2 Kolom) -->
                                        <div style='display:grid; grid-template-columns:1fr 1fr; gap:12px;'>
                                            <!-- Data Lama -->
                                            <div style='background-color:#ffffff; border:1px solid #e2e8f0; border-radius:8px; padding:12px;'>
                                                <div style='font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#64748b; margin-bottom:8px; display:flex; align-items:center; gap:4px;'>
                                                    <span style='display:inline-block; width:6px; height:6px; border-radius:9999px; background-color:#94a3b8;'></span>
                                                    Data Lama
                                                </div>
                                                <div style='font-size:12px; color:#64748b; margin-bottom:3px;'>Tarif Standar: <strong style='color:#1e293b;'>Rp " . number_format($req->old_wage_per_pcs, 0, ',', '.') . "</strong></div>" . 
                                                ($hasCustom ? "<div style='font-size:12px; color:#64748b; margin-bottom:3px;'>Tarif Custom: <strong style='color:#1e293b;'>Rp " . number_format($req->old_wage_custom_per_pcs ?? 0, 0, ',', '.') . "</strong></div>" : "") . "
                                                <div style='margin-top:8px; padding-top:6px; border-top:1px dashed #e2e8f0;'>
                                                    <div style='font-size:11px; color:#64748b;'>Total Upah:</div>
                                                    <div style='font-size:14px; font-weight:700; color:#475569;'>Rp " . number_format($req->old_wage_amount, 0, ',', '.') . "</div>
                                                </div>
                                            </div>

                                            <!-- Data Baru (Diajukan) -->
                                            <div style='background-color:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px; padding:12px;'>
                                                <div style='font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em; color:#15803d; margin-bottom:8px; display:flex; align-items:center; gap:4px;'>
                                                    <span style='display:inline-block; width:6px; height:6px; border-radius:9999px; background-color:#22c55e;'></span>
                                                    Data Baru (Diajukan)
                                                </div>
                                                <div style='font-size:12px; color:#166534; margin-bottom:3px;'>Tarif Standar: <strong style='color:#14532d;'>Rp " . number_format($req->new_wage_per_pcs, 0, ',', '.') . "</strong></div>" . 
                                                ($hasCustom ? "<div style='font-size:12px; color:#166534; margin-bottom:3px;'>Tarif Custom: <strong style='color:#14532d;'>Rp " . number_format($req->new_wage_custom_per_pcs ?? 0, 0, ',', '.') . "</strong></div>" : "") . "
                                                <div style='margin-top:8px; padding-top:6px; border-top:1px dashed #bbf7d0;'>
                                                    <div style='font-size:11px; color:#15803d;'>Total Upah Baru:</div>
                                                    <div style='font-size:15px; font-weight:800; color:#15803d;'>Rp " . number_format($req->new_wage_amount, 0, ',', '.') . "</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>";

                                    return new HtmlString($html);
                                }),

                            Radio::make('keputusan')
                                ->label('Keputusan Owner')
                                ->options([
                                    'approve' => 'Setujui & Perbarui Upah Sekarang',
                                    'reject' => 'Tolak Pengajuan',
                                ])
                                ->default('approve')
                                ->required()
                                ->live(),

                            Textarea::make('alasan_penolakan')
                                ->label('Alasan Penolakan')
                                ->visible(fn ($get) => $get('keputusan') === 'reject')
                                ->required(fn ($get) => $get('keputusan') === 'reject'),
                        ];
                    })
                    ->modalSubmitActionLabel('Kirim Keputusan')
                    ->modalSubmitAction(fn ($action) => $action->color('primary'))
                    ->action(function (ProductionTask $record, array $data) {
                        $req = $record->pendingWageCorrectionRequest();
                        if (!$req) return;

                        if ($data['keputusan'] === 'reject') {
                            $req->update([
                                'status' => 'rejected',
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                                'rejection_reason' => $data['alasan_penolakan'],
                            ]);

                            Notification::make()
                                ->title('Pengajuan Ditolak')
                                ->warning()
                                ->send();
                        } else {
                            $sq = $record->size_quantities ?? [];
                            $customQty = (int) ($sq['CUSTOM'] ?? 0);
                            if ($customQty > 0 && $req->new_wage_custom_per_pcs) {
                                $sq['_wage_custom'] = $req->new_wage_custom_per_pcs;
                            }

                            $record->update([
                                'wage_amount' => $req->new_wage_amount,
                                'size_quantities' => $sq,
                            ]);

                            $req->update([
                                'status' => 'approved',
                                'reviewed_by' => auth()->id(),
                                'reviewed_at' => now(),
                            ]);

                            Notification::make()
                                ->title('Upah Berhasil Diperbarui')
                                ->body('Perubahan upah telah disetujui dan diterapkan ke sistem.')
                                ->success()
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
