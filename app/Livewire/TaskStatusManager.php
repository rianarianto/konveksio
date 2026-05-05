<?php

namespace App\Livewire;

class TaskStatusManager extends \Livewire\Component
{
    public $orderItemId;

    public function mount($orderItemId)
    {
        $this->orderItemId = $orderItemId;
    }

    public function updateTaskStatus($taskId, $action)
    {
        $task = \App\Models\ProductionTask::find($taskId);
        if (!$task) return;

        if ($action === 'start') {
            $task->update(['status' => 'in_progress', 'started_at' => now()]);
            \Filament\Notifications\Notification::make()->title('Tugas Dimulai')->success()->send();
        } elseif ($action === 'done') {
            $task->update(['status' => 'done', 'completed_at' => now()]);
            \Filament\Notifications\Notification::make()->title('Tugas Selesai')->success()->send();
        }
    }

    public function render()
    {
        $record = \App\Models\OrderItem::with(['productionTasks.assignedTo'])->find($this->orderItemId);
        
        if (!$record) return '<div>Item tidak ditemukan</div>';

        $tasks = $record->productionTasks;
        $stageOrder = \App\Models\ProductionStage::pluck('order_sequence', 'name');

        $sortedTasks = $tasks->sortBy(function ($task) use ($stageOrder) {
            return [$stageOrder[$task->stage_name] ?? 999, $task->id];
        });

        $groupedByStage = $sortedTasks->groupBy('stage_name');
        $stagesInOrder = $groupedByStage->keys()->sortBy(fn($s) => $stageOrder[$s] ?? 999);

        $unlockedStages = [];
        foreach ($stagesInOrder as $i => $stageName) {
            if ($i === 0) {
                $unlockedStages[] = $stageName;
                continue;
            }
            $prevStage = $stagesInOrder[$i - 1];
            $prevDone = $groupedByStage[$prevStage]->every(fn($t) => $t->status === 'done');
            if ($prevDone) {
                $unlockedStages[] = $stageName;
            } else {
                break;
            }
        }

        return view('livewire.task-status-manager', [
            'record' => $record,
            'sortedTasks' => $sortedTasks,
            'unlockedStages' => $unlockedStages,
        ]);
    }
}
