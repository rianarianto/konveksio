<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionStage extends Model
{
    protected $fillable = [
        'shop_id',
        'name',
        'base_wage',
        'order_sequence',
        'for_produksi_custom',
        'for_non_produksi',
        'for_jasa',
    ];

    protected $casts = [
        'order_sequence' => 'integer',
        'for_produksi_custom' => 'boolean',
        'for_non_produksi' => 'boolean',
        'for_jasa' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());

        static::creating(function ($model) {
            if (is_null($model->order_sequence)) {
                $maxSequence = static::where('shop_id', $model->shop_id)->max('order_sequence') ?? 0;
                $model->order_sequence = $maxSequence + 1;
            }
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get consistent theme colors for production stages
     */
    public static function getThemeColor(?string $stageName): array
    {
        if (!$stageName) {
            return [
                'bg' => '#f9fafb',
                'border' => '#d1d5db',
                'text' => '#4b5563',
                'filament' => 'gray',
            ];
        }

        $name = strtolower($stageName);

        return match (true) {
            str_contains($name, 'potong') => [
                'bg' => '#f8fafc',     // Slate 50
                'border' => '#cbd5e1', // Slate 300
                'text' => '#64748b',
                'filament' => 'gray',
            ],
            str_contains($name, 'sablon') => [
                'bg' => '#f5f3ff',     // Violet 50
                'border' => '#ddd6fe', // Violet 200
                'text' => '#7c3aed',
                'filament' => 'violet',
            ],
            str_contains($name, 'bordir') => [
                'bg' => '#eef2ff',     // Indigo 50
                'border' => '#e0e7ff', // Indigo 200
                'text' => '#4f46e5',
                'filament' => 'indigo',
            ],
            str_contains($name, 'jahit') => [
                'bg' => '#fdf4ff',     // Fuchsia 50
                'border' => '#fae8ff', // Fuchsia 200
                'text' => '#c026d3',
                'filament' => 'fuchsia',
            ],
            str_contains($name, 'kancing') => [
                'bg' => '#fffbeb',     // Amber 50
                'border' => '#fef3c7', // Amber 200
                'text' => '#d97706',
                'filament' => 'amber',
            ],
            str_contains($name, 'qc') || str_contains($name, 'quality') => [
                'bg' => '#f0f9ff',     // Sky 50
                'border' => '#e0f2fe', // Sky 200
                'text' => '#0284c7',
                'filament' => 'sky',
            ],
            str_contains($name, 'finish') || str_contains($name, 'packing') => [
                'bg' => '#f0fdf4',     // Emerald 50
                'border' => '#d1fae5', // Emerald 200
                'text' => '#059669',
                'filament' => 'emerald',
            ],
            default => [
                'bg' => '#ffffff',     // Putih bersih untuk tugas baru
                'border' => '#e5e7eb', // Border abu-abu tipis
                'text' => '#9ca3af',
                'filament' => 'gray',
            ],
        };
    }
}
