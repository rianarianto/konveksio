<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderReturn extends Model
{
    protected $fillable = [
        'shop_id',
        'order_id',
        'order_item_id',
        'return_date',
        'expected_pickup_date',
        'items_description',
        'quantity',
        'reason',
        'status', // pending, diproses, selesai
        'action_type',
        'target_stage',
        'responsibility_type', // store_guarantee, customer_paid
        'additional_fee',
        'size_breakdown',
        'photo_path',
    ];

    public $multi_size_items = [];
    public $selection_mode = 'single';

    public function setTargetStagesAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['target_stage'] = implode(',', $value);
        } else {
            $this->attributes['target_stage'] = $value;
        }
    }

    public function getTargetStagesAttribute(): array
    {
        if (!empty($this->attributes['target_stage'])) {
            return array_filter(array_map('trim', explode(',', $this->attributes['target_stage'])));
        }
        return [];
    }

    protected $casts = [
        'return_date' => 'date',
        'expected_pickup_date' => 'date',
        'quantity' => 'integer',
        'additional_fee' => 'decimal:2',
        'size_breakdown' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    protected static function booted(): void
    {
        static::saving(function ($return) {
            if (empty($return->status)) {
                $return->status = 'pending';
            }
            if (empty($return->return_date)) {
                $return->return_date = now();
            }
            if (empty($return->expected_pickup_date)) {
                $return->expected_pickup_date = now()->addDays(3);
            }
            if (empty($return->reason) && !empty($return->items_description)) {
                $return->reason = $return->items_description;
            }
            if (empty($return->items_description) && !empty($return->reason)) {
                $return->items_description = $return->reason;
            }
            if (!empty($return->multi_size_items) && is_array($return->multi_size_items)) {
                $breakdown = ['_raw' => $return->multi_size_items];
                $totQty = 0;
                $firstItemId = null;

                foreach ($return->multi_size_items as $mItem) {
                    $itemId = $mItem['order_item_id'] ?? null;
                    $rQty = (int)($mItem['return_qty'] ?? 1);
                    if ($itemId && $rQty > 0) {
                        if (!$firstItemId) $firstItemId = $itemId;
                        $itemObj = \App\Models\OrderItem::find($itemId);
                        if ($itemObj) {
                            $reqDetails = $itemObj->size_and_request_details ?? [];
                            $vLabel = !empty($reqDetails['group_label']) ? $reqDetails['group_label'] : "Varian Utama";
                            $gender = !empty($reqDetails['gender']) ? " (" . ($reqDetails['gender'] === 'L' ? 'Laki-laki' : 'Perempuan') . ")" : "";
                            $batch = $itemObj->is_addition ? " ➕ Penambahan" : "";
                            $sz = $itemObj->size ? "Ukuran {$itemObj->size}" : "Tanpa Ukuran";
                            $rec = !empty($itemObj->recipient_name) ? " — 👤 Penerima: {$itemObj->recipient_name}" : "";

                            $label = "📦 {$vLabel}{$gender}{$batch} — {$sz}{$rec}";
                            $breakdown[$label] = ($breakdown[$label] ?? 0) + $rQty;
                            $totQty += $rQty;
                        }
                    }
                }

                if ($firstItemId) {
                    $return->order_item_id = $firstItemId;
                    $return->quantity = $totQty;
                    $return->size_breakdown = $breakdown;
                }
            }

            if (empty($return->size_breakdown) && $return->order_item_id) {
                $item = \App\Models\OrderItem::find($return->order_item_id);
                if ($item) {
                    $reqDetails = $item->size_and_request_details ?? [];
                    $vLabel = !empty($reqDetails['group_label']) ? $reqDetails['group_label'] : "Varian Utama";
                    $gender = !empty($reqDetails['gender']) ? " (" . ($reqDetails['gender'] === 'L' ? 'Laki-laki' : 'Perempuan') . ")" : "";
                    $batch = $item->is_addition ? " ➕ Penambahan" : "";
                    $sz = $item->size ? "Ukuran {$item->size}" : "Tanpa Ukuran";
                    $rec = !empty($item->recipient_name) ? " — 👤 Penerima: {$item->recipient_name}" : "";

                    $szKey = "📦 {$vLabel}{$gender}{$batch} — {$sz}{$rec}";
                    $return->size_breakdown = [$szKey => $return->quantity ?: 1];
                }
            }
        });

        static::saved(function ($return) {
            $item = $return->orderItem;
            if (!$item) return;

            $origWo = $item->workOrder;
            if (!$origWo) {
                $groupItemIds = $item->getItemsInGroup()->pluck('id');
                $origWo = \App\Models\WorkOrder::withoutGlobalScopes()
                    ->whereIn('order_item_id', $groupItemIds)
                    ->where('wo_number', 'not like', '%-R%')
                    ->first();
            }
            $isCustomerPaid = $return->responsibility_type === 'customer_paid';
            $taskSizeQty = !empty($return->size_breakdown) ? $return->size_breakdown : null;

            if ($return->action_type === 'repair') {
                $rawStages = !empty($return->target_stages) && is_array($return->target_stages)
                    ? $return->target_stages
                    : explode(',', (string)$return->target_stage);

                $rawStages = array_filter(array_map('trim', $rawStages));
                if (empty($rawStages)) {
                    $rawStages = ['Jahit'];
                }

                $mappedStages = [];
                foreach ($rawStages as $stg) {
                    $stageName = match(strtolower((string)$stg)) {
                        'potong' => 'Potong',
                        'bordir/sablon', 'sablon/bordir', 'sablon', 'bordir' => 'Bordir/Sablon',
                        'finishing' => 'Finishing',
                        'kancing' => 'Kancing',
                        'qc_persiapan', 'qc_prep' => 'QC_PERSIAPAN',
                        'qc_akhir', 'qc_selesai', 'qc' => 'QC_AKHIR',
                        default => $stg ?: 'Jahit',
                    };
                    $mappedStages[] = $stageName;

                    $existingRevisionTask = \App\Models\ProductionTask::withoutGlobalScopes()
                        ->where('order_item_id', $item->id)
                        ->where('stage_name', $stageName)
                        ->where('is_revision', true)
                        ->first();

                    if (!$existingRevisionTask) {
                        $originalTask = \App\Models\ProductionTask::where('order_item_id', $item->id)
                            ->where('stage_name', $stageName)
                            ->where('is_revision', false)
                            ->latest('id')
                            ->first();

                        $originalWagePerPcs = 0;
                        if ($originalTask && $originalTask->quantity > 0) {
                            $originalWagePerPcs = $originalTask->wage_amount / $originalTask->quantity;
                        }

                        $repairWage = $isCustomerPaid ? ($originalWagePerPcs * $return->quantity) : 0;

                        \App\Models\ProductionTask::create([
                            'shop_id' => $return->shop_id,
                            'order_item_id' => $item->id,
                            'stage_name' => $stageName,
                            'quantity' => $return->quantity,
                            'wage_amount' => $repairWage,
                            'status' => 'pending',
                            'is_revision' => true,
                            'revision_source' => 'qc_akhir',
                            'assigned_by' => auth()->id() ?? 1,
                            'assigned_to' => null,
                            'size_quantities' => $taskSizeQty,
                            'note' => '⚠️ REVISI RETUR (' . ($isCustomerPaid ? 'Berbayar' : 'Garansi') . '): ' . ($return->items_description ?: $return->reason),
                        ]);
                    } else {
                        $existingRevisionTask->update([
                            'quantity' => $return->quantity,
                            'size_quantities' => $taskSizeQty,
                            'note' => '⚠️ REVISI RETUR (' . ($isCustomerPaid ? 'Berbayar' : 'Garansi') . '): ' . ($return->items_description ?: $return->reason),
                        ]);
                    }
                }

                // Delete revision tasks for stages that were unselected during edit
                \App\Models\ProductionTask::withoutGlobalScopes()
                    ->where('order_item_id', $item->id)
                    ->where('is_revision', true)
                    ->whereNotIn('stage_name', $mappedStages)
                    ->delete();

                // Create or update Retur Work Order (#WO-XXXX-R1)
                $firstStageName = $mappedStages[0] ?? 'Jahit';
                $returWo = \App\Models\WorkOrder::withoutGlobalScopes()
                    ->where('order_item_id', $item->id)
                    ->where('wo_number', 'like', '%-R%')
                    ->latest('id')
                    ->first();

                if (!$returWo) {
                    $returCount = \App\Models\WorkOrder::withoutGlobalScopes()
                        ->where('order_item_id', $item->id)
                        ->where('wo_number', 'like', '%-R%')
                        ->count() + 1;

                    $baseWoNum = $origWo ? $origWo->wo_number : ('WO-' . str_pad((string)$item->id, 5, '0', STR_PAD_LEFT));
                    $returWoNum = $baseWoNum . '-R' . $returCount;

                    \App\Models\WorkOrder::create([
                        'shop_id' => $return->shop_id,
                        'order_item_id' => $item->id,
                        'wo_number' => $returWoNum,
                        'status' => $firstStageName,
                        'stage_sequence' => $mappedStages,
                        'current_stage_index' => 0,
                        'stage_entered_at' => now(),
                        'token' => \Illuminate\Support\Str::random(64),
                        'is_express' => (bool) ($origWo?->is_express ?? false),
                        'has_qc_prep' => false,
                        'has_qc_selesai' => true,
                        'qc_worker_id' => $origWo?->qc_worker_id,
                    ]);
                } else {
                    $returWo->update([
                        'stage_sequence' => $mappedStages,
                        'qc_worker_id' => $returWo->qc_worker_id ?: $origWo?->qc_worker_id,
                    ]);
                }

            } elseif ($return->action_type === 'remake') {
                $stages = ['Potong', 'Sablon/Bordir', 'Jahit', 'QC'];
                
                foreach ($stages as $stageName) {
                    $originalTask = \App\Models\ProductionTask::where('order_item_id', $item->id)
                        ->where('stage_name', $stageName)
                        ->first();
                        
                    $wagePerPcs = 0;
                    if ($originalTask && $originalTask->quantity > 0) {
                        $wagePerPcs = $originalTask->wage_amount / $originalTask->quantity;
                    }
                    
                    \App\Models\ProductionTask::create([
                        'shop_id' => $return->shop_id,
                        'order_item_id' => $item->id,
                        'stage_name' => $stageName,
                        'quantity' => $return->quantity,
                        'wage_amount' => $wagePerPcs * $return->quantity,
                        'status' => 'pending',
                        'is_revision' => true,
                        'size_quantities' => $taskSizeQty,
                        'note' => '⚠️ REMAKE RETUR: ' . $return->reason,
                    ]);
                }

                $returCount = \App\Models\WorkOrder::withoutGlobalScopes()
                    ->where('order_item_id', $item->id)
                    ->where('wo_number', 'like', '%-R%')
                    ->count() + 1;

                $baseWoNum = $origWo ? $origWo->wo_number : ('WO-' . str_pad((string)$item->id, 5, '0', STR_PAD_LEFT));
                $returWoNum = $baseWoNum . '-R' . $returCount;

                \App\Models\WorkOrder::create([
                    'shop_id' => $return->shop_id,
                    'order_item_id' => $item->id,
                    'wo_number' => $returWoNum,
                    'status' => 'Potong',
                    'stage_sequence' => $stages,
                    'current_stage_index' => 0,
                    'stage_entered_at' => now(),
                    'token' => \Illuminate\Support\Str::random(64),
                    'is_express' => (bool) ($origWo?->is_express ?? false),
                    'has_qc_prep' => false,
                    'has_qc_selesai' => true,
                ]);
            }

            // Handles additional fee if customer paid
            $order = $item->order;
            if ($order) {
                if ($isCustomerPaid && $return->additional_fee > 0) {
                    $order->increment('total_amount', $return->additional_fee);
                }
            }
        });

        static::deleting(function ($return) {
            $item = $return->orderItem;
            if ($item) {
                // Delete revision tasks
                \App\Models\ProductionTask::where('order_item_id', $item->id)
                    ->where('is_revision', true)
                    ->delete();

                // Delete retur Work Order (WO-XXXX-R*)
                \App\Models\WorkOrder::withoutGlobalScopes()
                    ->where('order_item_id', $item->id)
                    ->where('wo_number', 'like', '%-R%')
                    ->delete();
            }
        });
    }
}
