<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Worker extends Model implements HasTenants
{
    protected $fillable = [
        'shop_id',
        'name',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = ['active_queue_count', 'pending_count', 'in_progress_count', 'done_count'];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function getTenants(Panel $panel): Collection
    {
        return Shop::where('id', $this->shop_id)->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->shop_id === $tenant->id;
    }

    /**
     * Semua tugas produksi yang dikerjakan pekerja ini.
     */
    public function productionTasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class, 'assigned_to');
    }

    /**
     * Accessor untuk mengecek jumlah antrian aktif (status blm selesai).
     * Ini dipakai di dropdown untuk load balancing beban kerja.
     */
    public function getActiveQueueCountAttribute(): int
    {
        // Hitung total quantity dari task yang belum 'done'
        return $this->productionTasks()
            ->whereIn('status', ['pending', 'in_progress'])
            ->sum('quantity');
    }

    public function getPendingCountAttribute(): int
    {
        return $this->productionTasks()->where('status', 'pending')->sum('quantity');
    }

    public function getInProgressCountAttribute(): int
    {
        return $this->productionTasks()->where('status', 'in_progress')->sum('quantity');
    }

    public function getDoneCountAttribute(): int
    {
        return $this->productionTasks()->where('status', 'done')->sum('quantity');
    }
}
