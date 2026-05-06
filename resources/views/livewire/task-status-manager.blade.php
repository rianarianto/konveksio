<div>
    @php
        $primaryColor = '#7c3aed';
        $prevStageName = null;
    @endphp

    @if($record->design_image)
        @php $designUrl = asset('storage/' . $record->design_image); @endphp
        <div style="margin-bottom:16px; border:1.5px solid #c4b5fd; border-radius:12px; overflow:hidden; background:#faf5ff;">
            <div style="padding:8px 14px; background:#ede9fe; display:flex; align-items:center; gap:8px;">
                <span style="font-size:16px;">🎨</span>
                <span style="font-size:13px; font-weight:600; color:#5b21b6;">Referensi Desain</span>
                <a href="{{ $designUrl }}" target="_blank" style="margin-left:auto; font-size:11px; color:#7c3aed; text-decoration:underline;">Buka full ↗</a>
            </div>
            <div style="padding:12px; text-align:center;">
                <a href="{{ $designUrl }}" target="_blank">
                    <img src="{{ $designUrl }}" style="max-height:200px; max-width:100%; object-fit:contain; border-radius:8px;" alt="Desain">
                </a>
            </div>
        </div>
    @else
        <div style="margin-bottom:16px; border:1.5px dashed #d1d5db; border-radius:12px; padding:16px; text-align:center; background:#f9fafb;">
            <span style="font-size:13px; color:#9ca3af;">🖼️ Belum ada file desain yang diupload untuk item ini</span>
        </div>
    @endif

    <div style="border-radius:10px; overflow-x:auto; border:1px solid #e5e7eb;">
        <table style="width:100%; min-width:600px; border-collapse:collapse;">
            <thead>
                <tr style="background:#f9fafb;">
                    <th style="padding:10px 14px; text-align:left; font-size:12px; color:#6b7280; font-weight:600; border-bottom:2px solid #e5e7eb;">TAHAP</th>
                    <th style="padding:10px 14px; text-align:left; font-size:12px; color:#6b7280; font-weight:600; border-bottom:2px solid #e5e7eb;">KARYAWAN</th>
                    <th style="padding:10px 14px; text-align:left; font-size:12px; color:#6b7280; font-weight:600; border-bottom:2px solid #e5e7eb;">QTY</th>
                    <th style="padding:10px 14px; text-align:left; font-size:12px; color:#6b7280; font-weight:600; border-bottom:2px solid #e5e7eb;">STATUS</th>
                    <th style="padding:10px 14px; text-align:right; font-size:12px; color:#6b7280; font-weight:600; border-bottom:2px solid #e5e7eb;">AKSI</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sortedTasks as $task)
                    @php
                        $isUnlocked = in_array($task->stage_name, $unlockedStages);
                        $showStageLabel = $task->stage_name !== $prevStageName;
                        $prevStageName = $task->stage_name;
                        $theme = \App\Models\ProductionStage::getThemeColor($task->stage_name);
                    @endphp
                    <tr style="background: {{ $showStageLabel ? 'transparent' : $theme['bg'] }} !important">
                        <td style="padding:10px 14px; font-weight:700; color: {{ $theme['text'] }}; font-size:13px; border-bottom:1px solid #e5e7eb; background: {{ $theme['bg'] }} !important; border-left:4px solid {{ $theme['border'] }};">
                            {!! $showStageLabel ? e($task->stage_name) : '↳' !!}
                        </td>
                        <td style="padding:10px 14px; font-size:13px; color:#374151; border-bottom:1px solid #e5e7eb;">
                            {{ $task->assignedTo?->name ?? 'Tidak Diketahui' }}
                        </td>
                        <td style="padding:10px 14px; font-size:13px; color:#374151; border-bottom:1px solid #e5e7eb;">
                            {{ $task->quantity }} pcs
                        </td>
                        <td style="padding:10px 14px; border-bottom:1px solid #e5e7eb;">
                            @if($task->status === 'pending')
                                <span style="background:#fef3c7; color:#92400e; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:600">⏳ Antrian</span>
                            @elseif($task->status === 'in_progress')
                                <span style="background:#dbeafe; color:#1e40af; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:600">🔨 Dikerjakan</span>
                            @elseif($task->status === 'done')
                                <span style="background:#d1fae5; color:#065f46; padding:2px 10px; border-radius:999px; font-size:12px; font-weight:600">✅ Selesai</span>
                            @endif
                        </td>
                        <td style="padding:10px 14px; border-bottom:1px solid #e5e7eb; text-align:right;">
                            @if(!$isUnlocked)
                                <span style="color:#9ca3af; font-size:12px">🔒 Menunggu tahap sebelumnya</span>
                            @elseif($task->status === 'pending')
                                <button type="button" wire:click="updateTaskStatus({{ $task->id }}, 'start')" style="background:#2563eb; color:#fff; padding:4px 14px; border-radius:6px; font-size:12px; font-weight:600; border:none; cursor:pointer;">▶ Mulai</button>
                            @elseif($task->status === 'in_progress')
                                <button type="button" wire:click="updateTaskStatus({{ $task->id }}, 'done')" style="background:#059669; color:#fff; padding:4px 14px; border-radius:6px; font-size:12px; font-weight:600; border:none; cursor:pointer;">✓ Tandai Selesai</button>
                            @else
                                <div style="display:flex; align-items:center; justify-content:flex-end; gap:8px;">
                                    <span style="color:#6b7280; font-size:12px">Selesai</span>
                                    <button type="button" wire:click="updateTaskStatus({{ $task->id }}, 'undo')" style="background:#f3f4f6; color:#4b5563; padding:2px 8px; border-radius:6px; font-size:10px; font-weight:600; border:1px solid #d1d5db; cursor:pointer;" title="Kembalikan ke proses">↺ Undo</button>
                                </div>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
