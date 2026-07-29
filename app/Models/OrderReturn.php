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
        static::creating(function ($return) {
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
            if (empty($return->size_breakdown) && $return->order_item_id) {
                $item = \App\Models\OrderItem::find($return->order_item_id);
                if ($item) {
                    $szKey = $item->size ? "Size {$item->size}" : "Tanpa Ukuran";
                    if (!empty($item->recipient_name)) {
                        $szKey .= " (Nama: {$item->recipient_name})";
                    }
                    $return->size_breakdown = [$szKey => $return->quantity ?: 1];
                }
            }
        });

        static::created(function ($return) {
            $item = $return->orderItem;
            if (!$item) return;

            $wo = $item->workOrder;
            $isCustomerPaid = $return->responsibility_type === 'customer_paid';

            $taskSizeQty = !empty($return->size_breakdown) ? $return->size_breakdown : null;

            if ($return->action_type === 'repair') {
                $stageName = match(strtolower((string)$return->target_stage)) {
                    'potong' => 'Potong',
                    'bordir/sablon', 'sablon/bordir', 'sablon', 'bordir' => 'Bordir/Sablon',
                    'finishing' => 'Finishing',
                    'kancing' => 'Kancing',
                    'qc_persiapan', 'qc_prep' => 'QC_PERSIAPAN',
                    'qc_akhir', 'qc_selesai', 'qc' => 'QC_AKHIR',
                    default => $return->target_stage ?: 'Jahit',
                };

                // Find the latest task for this stage
                $task = \App\Models\ProductionTask::where('order_item_id', $item->id)
                    ->where('stage_name', $stageName)
                    ->latest('id')
                    ->first();

                $originalWagePerPcs = 0;
                if ($task && $task->quantity > 0) {
                    $originalWagePerPcs = $task->wage_amount / $task->quantity;
                }

                $repairWage = $isCustomerPaid ? ($originalWagePerPcs * $return->quantity) : 0;

                if ($task) {
                    if ($task->is_paid || $isCustomerPaid) {
                        // If already paid OR paid by customer, create a new revision task
                        \App\Models\ProductionTask::create([
                            'shop_id' => $return->shop_id,
                            'order_item_id' => $item->id,
                            'stage_name' => $stageName,
                            'quantity' => $return->quantity,
                            'wage_amount' => $repairWage,
                            'status' => 'pending',
                            'is_revision' => true,
                            'revision_source' => 'qc_akhir',
                            'assigned_by' => $task->assigned_by ?? auth()->id() ?? 1,
                            'assigned_to' => $task->assigned_to,
                            'size_quantities' => $taskSizeQty,
                            'note' => '⚠️ REVISI RETUR (' . ($isCustomerPaid ? 'Berbayar' : 'Garansi') . '): ' . $return->reason,
                        ]);
                    } else {
                        // Reset status to pending for redo
                        $task->update([
                            'status' => 'pending',
                            'is_revision' => true,
                            'revision_source' => 'qc_akhir',
                            'quantity' => $return->quantity,
                            'wage_amount' => 0,
                            'size_quantities' => $taskSizeQty,
                            'note' => ($task->note ? $task->note . ' | ' : '') . '⚠️ REVISI RETUR: ' . $return->reason,
                        ]);
                    }
                } else {
                    // Create new task
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
                        'size_quantities' => $taskSizeQty,
                        'note' => '⚠️ REVISI RETUR (New): ' . $return->reason,
                    ]);
                }

                // Revert WorkOrder status to target stage & trigger WA to worker
                if ($wo) {
                    $idx = array_search($stageName, $wo->stage_sequence);
                    $wo->update([
                        'status' => $stageName,
                        'current_stage_index' => $idx !== false ? $idx : 0,
                        'stage_entered_at' => now(),
                    ]);
                    
                    try {
                        \App\Helpers\NotificationHelper::notify($wo, $stageName);
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::error("[WA Retur] Gagal kirim WA retur: " . $e->getMessage());
                    }
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
                        'size_quantities' => $taskSizeQty,
                        'note' => '⚠️ REMAKE RETUR: ' . $return->reason,
                    ]);
                }

                // Reset WorkOrder status to first stage
                if ($wo && !empty($wo->stage_sequence)) {
                    $firstStage = $wo->stage_sequence[0];
                    $wo->update([
                        'status' => $firstStage,
                        'current_stage_index' => 0,
                        'stage_entered_at' => now(),
                    ]);
                }
            }

            // Handles additional fee if customer paid
            $order = $item->order;
            if ($order) {
                if ($isCustomerPaid && $return->additional_fee > 0) {
                    $order->increment('total_amount', $return->additional_fee);
                }
                
                // Revert Order status to 'diproses' if it was 'selesai' or 'siap_diambil'
                if (in_array($order->status, ['selesai', 'siap_diambil'])) {
                    $order->update(['status' => 'diproses']);
                }
            }
        });
    }
}
