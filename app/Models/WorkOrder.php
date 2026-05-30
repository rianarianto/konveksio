<?php

namespace App\Models;

use App\Models\Scopes\ShopScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class WorkOrder extends Model
{
    protected $fillable = [
        'shop_id',
        'order_item_id',
        'wo_number',
        'status',
        'decoration_type',
        'token',
        'cutting_worker_id',
        'sewing_worker_id',
        'decoration_worker_id',
        'button_worker_id',
        'finishing_worker_id',
        'reject_reason',
        'reject_proof_image',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Urutan state machine yang valid.
     */
    public const TRANSITIONS = [
        'CREATED'       => 'QC_PREP',
        'QC_PREP'       => 'CUTTING',
        'CUTTING'       => 'QC_CUT_CHECK',
        'QC_CUT_CHECK'  => 'SEWING',
        'SEWING'        => 'QC_SEW_CHECK',
        'QC_SEW_CHECK'  => 'BUTTON',
        'BUTTON'        => 'DECORATION',
        'DECORATION'    => 'FINISHING',
        'FINISHING'     => 'QC_FINAL_CHECK',
        'QC_FINAL_CHECK'=> 'COMPLETED',
    ];

    /**
     * Peta: dari status QC → kembali ke divisi pelaksana sebelumnya (untuk reject).
     */
    public const QC_REJECT_MAP = [
        'QC_PREP'       => 'CREATED',
        'QC_CUT_CHECK'  => 'CUTTING',
        'QC_SEW_CHECK'  => 'SEWING',
        'QC_FINAL_CHECK'=> 'FINISHING',
    ];

    /**
     * Status yang tergolong QC Check (hanya QC yang boleh aksi di sini).
     */
    public const QC_STATUSES = [
        'QC_PREP', 'QC_CUT_CHECK', 'QC_SEW_CHECK', 'QC_FINAL_CHECK',
    ];

    /**
     * Status divisi pelaksana (hanya tukang yang boleh aksi di sini).
     */
    public const WORKER_STATUSES = [
        'CUTTING', 'SEWING', 'DECORATION', 'BUTTON', 'FINISHING',
    ];

    /**
     * Peta status → kolom worker yang bertugas di tahap tersebut.
     */
    public const STATUS_WORKER_MAP = [
        'CUTTING'    => 'cutting_worker_id',
        'SEWING'     => 'sewing_worker_id',
        'DECORATION' => 'decoration_worker_id',
        'BUTTON'     => 'button_worker_id',
        'FINISHING'  => 'finishing_worker_id',
    ];

    /**
     * Mapping keyword nama tahap ProductionStage → kolom worker WorkOrder.
     */
    public const STAGE_NAME_WORKER_MAP = [
        'potong'    => 'cutting_worker_id',
        'jahit'     => 'sewing_worker_id',
        'sablon'    => 'decoration_worker_id',
        'bordir'    => 'decoration_worker_id',
        'dekorasi'  => 'decoration_worker_id',
        'kancing'   => 'button_worker_id',
        'finishing' => 'finishing_worker_id',
        'packing'   => 'finishing_worker_id',
    ];

    /**
     * Cari kolom worker yang sesuai dengan nama tahap (fuzzy match).
     */
    public static function resolveWorkerColumnForStage(string $stageName): ?string
    {
        $lower = strtolower($stageName);
        foreach (self::STAGE_NAME_WORKER_MAP as $keyword => $col) {
            if (str_contains($lower, $keyword)) {
                return $col;
            }
        }
        return null;
    }

    /**
     * Label tampilan untuk tiap status.
     */
    public const STATUS_LABELS = [
        'CREATED'        => 'Dibuat',
        'QC_PREP'        => 'QC Persiapan',
        'CUTTING'        => 'Potong',
        'QC_CUT_CHECK'   => 'QC Potong',
        'SEWING'         => 'Jahit',
        'QC_SEW_CHECK'   => 'QC Jahit',
        'DECORATION'     => 'Dekorasi',
        'BUTTON'         => 'Kancing',
        'FINISHING'      => 'Finishing',
        'QC_FINAL_CHECK' => 'QC Final',
        'COMPLETED'      => 'Selesai',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new ShopScope());

        static::creating(function (WorkOrder $wo) {
            // Auto-generate token unik
            if (empty($wo->token)) {
                $wo->token = Str::random(48);
            }

            // Auto-generate wo_number
            if (empty($wo->wo_number)) {
                $wo->wo_number = static::generateWoNumber($wo->shop_id);
            }
        });
    }

    protected static function generateWoNumber(int $shopId): string
    {
        $yearMonth = now()->format('Ym');
        $prefix = "#WO-{$yearMonth}-";

        $last = static::withoutGlobalScope(ShopScope::class)
            ->where('shop_id', $shopId)
            ->where('wo_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $next = $last ? ((int) str_replace($prefix, '', $last->wo_number)) + 1 : 1;

        return $prefix . str_pad($next, 3, '0', STR_PAD_LEFT);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    public function isInQcStage(): bool
    {
        return in_array($this->status, self::QC_STATUSES);
    }

    public function isInWorkerStage(): bool
    {
        return in_array($this->status, self::WORKER_STATUSES);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function getCurrentWorker(): ?Worker
    {
        $col = self::STATUS_WORKER_MAP[$this->status] ?? null;
        return $col ? $this->$col : null;
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function cuttingWorker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'cutting_worker_id');
    }

    public function sewingWorker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'sewing_worker_id');
    }

    public function decorationWorker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'decoration_worker_id');
    }

    public function buttonWorker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'button_worker_id');
    }

    public function finishingWorker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'finishing_worker_id');
    }

    public function isQcRequiredForStage(string $status): bool
    {
        $workerCol = self::STATUS_WORKER_MAP[$status] ?? null;
        if (!$workerCol) {
            return true;
        }

        $stages = [];
        foreach (self::STAGE_NAME_WORKER_MAP as $stageKeyword => $col) {
            if ($col === $workerCol) {
                $stages[] = $stageKeyword;
            }
        }

        $groupItemIds = $this->orderItem ? $this->orderItem->getItemsInGroup()->pluck('id') : [$this->order_item_id];

        $task = \App\Models\ProductionTask::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->where(function ($q) use ($stages) {
                foreach ($stages as $stage) {
                    $q->orWhere('stage_name', 'like', "%{$stage}%");
                }
            })
            ->first();

        return $task ? (bool) $task->wajib_qc : true;
    }

    public function getNextStatus(): ?string
    {
        $category = $this->orderItem?->production_category ?? 'produksi';
        $hasDecoration = filled($this->decoration_type);

        if ($category === 'non_produksi' || $category === 'jasa') {
            return match ($this->status) {
                'CREATED'        => 'QC_PREP',
                'QC_PREP'        => $hasDecoration ? 'DECORATION' : 'FINISHING',
                'DECORATION'     => 'FINISHING',
                'FINISHING'      => $this->isQcRequiredForStage('FINISHING') ? 'QC_FINAL_CHECK' : 'COMPLETED',
                'QC_FINAL_CHECK' => 'COMPLETED',
                default          => null,
            };
        }

        // 'produksi' flow
        switch ($this->status) {
            case 'CREATED':
                return 'QC_PREP';
            case 'QC_PREP':
                return 'CUTTING';
            case 'CUTTING':
                return $this->isQcRequiredForStage('CUTTING') ? 'QC_CUT_CHECK' : 'SEWING';
            case 'QC_CUT_CHECK':
                return 'SEWING';
            case 'SEWING':
                return $this->isQcRequiredForStage('SEWING') ? 'QC_SEW_CHECK' : 'BUTTON';
            case 'QC_SEW_CHECK':
                return 'BUTTON';
            case 'BUTTON':
                return $hasDecoration ? 'DECORATION' : 'FINISHING';
            case 'DECORATION':
                return 'FINISHING';
            case 'FINISHING':
                return $this->isQcRequiredForStage('FINISHING') ? 'QC_FINAL_CHECK' : 'COMPLETED';
            case 'QC_FINAL_CHECK':
                return 'COMPLETED';
            default:
                return null;
        }
    }

    public function getPreviousStatus(): ?string
    {
        $category = $this->orderItem?->production_category ?? 'produksi';
        $hasDecoration = filled($this->decoration_type);

        if ($category === 'non_produksi' || $category === 'jasa') {
            return match ($this->status) {
                'QC_PREP'        => 'CREATED',
                'DECORATION'     => 'QC_PREP',
                'FINISHING'      => $hasDecoration ? 'DECORATION' : 'QC_PREP',
                'QC_FINAL_CHECK' => 'FINISHING',
                'COMPLETED'      => 'QC_FINAL_CHECK',
                default          => null,
            };
        }

        // 'produksi' flow
        return match ($this->status) {
            'QC_PREP'        => 'CREATED',
            'CUTTING'        => 'QC_PREP',
            'QC_CUT_CHECK'   => 'CUTTING',
            'SEWING'         => $this->isQcRequiredForStage('CUTTING') ? 'QC_CUT_CHECK' : 'CUTTING',
            'QC_SEW_CHECK'   => 'SEWING',
            'BUTTON'         => $this->isQcRequiredForStage('SEWING') ? 'QC_SEW_CHECK' : 'SEWING',
            'DECORATION'     => 'BUTTON',
            'FINISHING'      => $hasDecoration ? 'DECORATION' : 'BUTTON',
            'QC_FINAL_CHECK' => 'FINISHING',
            'COMPLETED'      => 'QC_FINAL_CHECK',
            default          => null,
        };
    }
}
