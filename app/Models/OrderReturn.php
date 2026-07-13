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
                            'assigned_to' => $task->assigned_to,
                            'note' => 'REVISI RETUR (Paid): ' . $return->reason,
                        ]);
                    } else {
                        // Reset status to pending for redo
                        $task->update([
                            'status' => 'pending',
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
                        'note' => 'REVISI RETUR (New): ' . $return->reason,
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
            }
        });
    }
}
