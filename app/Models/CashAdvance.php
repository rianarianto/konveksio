<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CashAdvance extends Model
{
    protected $fillable = [
        'shop_id',
        'cash_advanceable_type',
        'cash_advanceable_id',
        'type',
        'status',
        'rejection_reason',
        'amount',
        'date',
        'note',
        'recorded_by',
    ];

    protected $casts = [
        'date'   => 'date',
        'amount' => 'integer',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());

        static::created(function (CashAdvance $cashAdvance) {
            if ($cashAdvance->status === 'pending') {
                try {
                    $recipientName = $cashAdvance->cashAdvanceable?->name ?? 'Karyawan';
                    $amountFormatted = 'Rp ' . number_format($cashAdvance->amount, 0, ',', '.');

                    $recipients = User::withoutGlobalScopes()
                        ->where('shop_id', $cashAdvance->shop_id)
                        ->whereIn('role', ['owner', 'admin'])
                        ->get();

                    foreach ($recipients as $recipient) {
                        $notifData = \Filament\Notifications\Notification::make()
                            ->title('Pengajuan Kasbon Baru')
                            ->body("{$recipientName} mengajukan kasbon sebesar {$amountFormatted}.")
                            ->icon('heroicon-o-banknotes')
                            ->iconColor('warning')
                            ->actions([
                                \Filament\Actions\Action::make('view')
                                    ->label('Lihat Kasbon')
                                    ->url('/app/' . $cashAdvance->shop_id . '/kasbon')
                                    ->markAsRead(),
                            ])
                            ->toDatabase();

                        $recipient->notifications()->create([
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'type' => 'Filament\Notifications\DatabaseNotification',
                            'data' => $notifData,
                            'read_at' => null,
                        ]);
                    }
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        });
    }

    /**
     * Polymorphic: bisa User atau Worker.
     */
    public function cashAdvanceable(): MorphTo
    {
        return $this->morphTo();
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
