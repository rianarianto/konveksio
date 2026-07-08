<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTask extends Model
{
    protected $fillable = [
        'order_item_id',
        'shop_id',
        'stage_name',
        'assigned_to',
        'assigned_by',
        'wage_amount',
        'quantity',
        'size_quantities',
        'status',
        'is_paid',
        'is_revision',
        'worker_payroll_id',
        'description',
        'wajib_qc',
        'qc_approved',
        'qc_reviewed_at',
        'completed_at',
    ];

    protected $casts = [
        'wage_amount'     => 'integer',
        'quantity'        => 'integer',
        'size_quantities' => 'array',
        'status'          => 'string',
        'is_paid'         => 'boolean',
        'is_revision'     => 'boolean',
        'wajib_qc'        => 'boolean',
        'qc_approved'     => 'boolean',
        'completed_at'    => 'datetime',
        'qc_reviewed_at'  => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());
    }

    /**
     * Item order yang ditugaskan (baju apa yang dikerjakan).
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Karyawan (Tukang) yang mengerjakan tahap ini.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'assigned_to');
    }

    /**
     * Admin/Designer yang membuat penugasan ini (audit trail).
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Toko pemilik tugas ini (multi-tenancy).
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Payroll yang mencatat pembayaran tugas ini.
     */
    public function workerPayroll(): BelongsTo
    {
        return $this->belongsTo(WorkerPayroll::class);
    }

    /**
     * WorkOrder yang terkait dengan tugas ini (lewat order_item_id).
     */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'order_item_id', 'order_item_id');
    }

    /**
     * Format list ukuran dengan detail nama custom + gender & standard size gender (disertai fallback limit nama agar ramah UI).
     */
    public function getFormattedSizesAttribute(): array
    {
        $sqty = collect($this->size_quantities ?? [])->filter(fn($v, $k) => !str_starts_with($k, '_') && is_numeric($v));
        if ($sqty->isEmpty()) {
            return [];
        }

        $groupItemIds = $this->orderItem
            ? $this->orderItem->getItemsInGroup()->pluck('id')
            : [$this->order_item_id];
            
        $groupItems = OrderItem::whereIn('id', $groupItemIds)->get();

        $formatted = [];
        foreach ($sqty as $sz => $qty) {
            $detailsText = '';
            if ($sz === 'CUSTOM') {
                $customRecipients = $this->size_quantities['_custom_recipients'] ?? null;
                
                // Kumpulkan semua detail nama & gender yang ada di pesanan grup ini
                $availableCustoms = [];
                foreach ($groupItems as $gi) {
                    $szUpper = strtoupper($gi->size ?? '');
                    if ($gi->production_category === 'custom') {
                        $details = $gi->size_and_request_details ?? [];
                        $g = strtoupper($details['gender'] ?? 'L');
                        $gLabel = ($g === 'P' || strtoupper($g) === 'PEREMPUAN') ? 'P' : 'L';
                        foreach ($details['detail_custom'] ?? [] as $dc) {
                            if (!empty($dc['nama'])) {
                                $availableCustoms[] = [
                                    'name' => trim($dc['nama']),
                                    'gender' => $gLabel
                                ];
                            }
                        }
                    } elseif ($szUpper === 'CUSTOM') {
                        $details = $gi->size_and_request_details ?? [];
                        $g = strtoupper($details['gender'] ?? 'L');
                        $gLabel = ($g === 'P' || strtoupper($g) === 'PEREMPUAN') ? 'P' : 'L';
                        $name = trim($gi->recipient_name ?? '');
                        if ($name && $name !== '-') {
                            $availableCustoms[] = [
                                'name' => $name,
                                'gender' => $gLabel
                            ];
                        }
                    }
                }

                // Format setiap nama custom dengan gender
                $formattedNames = [];
                if (!empty($customRecipients)) {
                    foreach ($customRecipients as $name) {
                        $cleanName = trim($name);
                        if (preg_match('/[-\s]+[LP]$/i', $cleanName) || preg_match('/\([LP]\)$/i', $cleanName)) {
                            $formattedNames[] = $cleanName;
                        } else {
                            $foundKey = null;
                            foreach ($availableCustoms as $k => $av) {
                                if (strcasecmp($av['name'], $cleanName) === 0) {
                                    $foundKey = $k;
                                    break;
                                }
                            }
                            if ($foundKey !== null) {
                                $gLabel = $availableCustoms[$foundKey]['gender'];
                                $formattedNames[] = "{$cleanName} - {$gLabel}";
                                unset($availableCustoms[$foundKey]); // Hapus agar tidak dobel matching sekuensial
                            } else {
                                $formattedNames[] = "{$cleanName} - L"; // default fallback
                            }
                        }
                    }
                } else {
                    foreach ($availableCustoms as $av) {
                        $formattedNames[] = "{$av['name']} - {$av['gender']}";
                    }
                }

                if (!empty($formattedNames)) {
                    $limit = 3;
                    $shownNames = array_slice($formattedNames, 0, $limit);
                    $totalNames = count($formattedNames);
                    if ($totalNames > $limit) {
                        $remaining = $totalNames - $limit;
                        $detailsText = ' (' . implode(', ', $shownNames) . ' & +' . $remaining . ' lainnya)';
                    } else {
                        $detailsText = ' (' . implode(', ', $shownNames) . ')';
                    }
                }
            } else {
                // Untuk ukuran toko, tampilkan pembagian gender (L/P)
                $genderData = $this->size_quantities['_genders'][$sz] ?? null;
                if (!$genderData) {
                    $genderData = [];
                    foreach ($groupItems as $gi) {
                        if (strtoupper($gi->size ?? '') === strtoupper($sz)) {
                            $gVal = $gi->size_and_request_details['gender'] ?? null;
                            if ($gVal) {
                                $gKey = (strtoupper($gVal) === 'PEREMPUAN' || strtoupper($gVal) === 'P') ? 'P' : 'L';
                                $genderData[$gKey] = ($genderData[$gKey] ?? 0) + $gi->quantity;
                            }
                        }
                    }
                }
                
                if ($genderData) {
                    $gParts = [];
                    foreach ($genderData as $gLabel => $gQty) {
                        $gParts[] = ($gLabel === 'P' ? 'P' : 'L') . ': ' . $gQty;
                    }
                    $detailsText = ' (' . implode(', ', $gParts) . ')';
                }
            }

            $formatted[] = "{$sz}×{$qty}{$detailsText}";
        }

        return $formatted;
    }
}
