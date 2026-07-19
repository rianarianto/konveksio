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
        'items_description',
        'quantity',
        'reason',
        'status', // pending, diproses, selesai
        'action_type',
        'target_stage',
    ];

    protected $casts = [
        'return_date' => 'date',
        'quantity' => 'integer',
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
        static::created(function ($return) {
            $item = $return->orderItem;
            if (!$item) return;

            $wo = $item->workOrder;

            if ($return->action_type === 'repair') {
                $stageName = match($return->target_stage) {
                    'potong' => 'Potong',
                    'sablon' => 'Sablon/Bordir',
                    'jahit' => 'Jahit',
                    'qc' => 'QC',
                    default => 'Jahit',
                };

                // Find the latest task for this stage
                $task = \App\Models\ProductionTask::where('order_item_id', $item->id)
                    ->where('stage_name', $stageName)
                    ->latest('id')
                    ->first();

                if ($task) {
                    if ($task->is_paid) {
                        // If already paid, create a new revision task with 0 wage
                        \App\Models\ProductionTask::create([
                            'shop_id' => $return->shop_id,
                            'order_item_id' => $item->id,
                            'stage_name' => $stageName,
                            'quantity' => $return->quantity,
                            'wage_amount' => 0,
                            'status' => 'pending',
                            'is_revision' => true,
                            'assigned_to' => $task->assigned_to,
                            'note' => 'REVISI RETUR (Paid): ' . $return->reason,
                        ]);
                    } else {
                        // Reset status to pending for redo
                        $task->update([
                            'status' => 'pending',
                            'is_revision' => true,
                            'note' => ($task->note ? $task->note . ' | ' : '') . 'REVISI RETUR: ' . $return->reason,
                        ]);
                    }
                } else {
                    // Create new task with 0 wage
                    \App\Models\ProductionTask::create([
                        'shop_id' => $return->shop_id,
                        'order_item_id' => $item->id,
                        'stage_name' => $stageName,
                        'quantity' => $return->quantity,
                        'wage_amount' => 0,
                        'status' => 'pending',
                        'is_revision' => true,
                        'note' => 'REVISI RETUR (New): ' . $return->reason,
                    ]);
                }

                // Revert WorkOrder status to target stage
                if ($wo) {
                    $idx = array_search($stageName, $wo->stage_sequence);
                    $wo->update([
                        'status' => $stageName,
                        'current_stage_index' => $idx !== false ? $idx : 0,
                        'stage_entered_at' => now(),
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
                        'note' => 'REMAKE RETUR: ' . $return->reason,
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

            // Revert Order status to 'diproses' if it was 'selesai' or 'siap_diambil'
            $order = $item->order;
            if ($order && in_array($order->status, ['selesai', 'siap_diambil'])) {
                $order->update(['status' => 'diproses']);
            }
        });
    }
}
