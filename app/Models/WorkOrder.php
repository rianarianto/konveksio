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
        'stage_sequence',
        'current_stage_index',
        'current_review_stage',
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
        'stage_entered_at',
        'is_express',
        'has_qc_prep',
        'has_qc_selesai',
        'qc_worker_id',
    ];

    protected $casts = [
        'started_at'          => 'datetime',
        'completed_at'        => 'datetime',
        'stage_entered_at'    => 'datetime',
        'stage_sequence'      => 'array',
        'is_express'          => 'boolean',
        'has_qc_prep'         => 'boolean',
        'has_qc_selesai'      => 'boolean',
    ];

    /**
     * Status khusus (bukan nama tahap).
     */
    public const STATUS_CREATED = 'CREATED';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_QC_REVIEW = 'QC_REVIEW';
    public const STATUS_QC_PREP = 'QC_PREP';

    /**
     * Mapping keyword nama tahap → kolom worker WorkOrder.
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
     * Cari worker_id yang ditugaskan di tahap saat ini.
     */
    public function getCurrentWorkerId(): ?int
    {
        $col = self::resolveWorkerColumnForStage($this->status);
        return $col ? $this->$col : null;
    }

    public function getCurrentWorker(): ?Worker
    {
        $workerId = $this->getCurrentWorkerId();
        return $workerId ? Worker::find($workerId) : null;
    }



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
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isInQcStage(): bool
    {
        return $this->status === self::STATUS_QC_REVIEW || $this->status === self::STATUS_QC_PREP;
    }

    public function isInWorkerStage(): bool
    {
        $stages = $this->stage_sequence ?? [];
        return in_array($this->status, $stages);
    }

    public function getStatusLabelAttribute(): string
    {
        if ($this->status === self::STATUS_CREATED) return 'Dibuat';
        if ($this->status === self::STATUS_COMPLETED) return 'Selesai';
        if ($this->status === self::STATUS_QC_PREP) return 'QC Persiapan';
        if ($this->status === self::STATUS_QC_REVIEW) return 'QC ' . ($this->current_review_stage ?? '');
        return $this->status; // nama tahap langsung (Potong, Jahit, dll)
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

    /**
     * Cek apakah tahap tertentu wajib QC.
     * Baca dari production_task yang match nama tahap.
     */
    public function isQcRequiredForStage(string $stageName): bool
    {
        $groupItemIds = $this->orderItem
            ? $this->orderItem->getItemsInGroup()->pluck('id')
            : [$this->order_item_id];

        $task = \App\Models\ProductionTask::withoutGlobalScopes()
            ->whereIn('order_item_id', $groupItemIds)
            ->where('stage_name', $stageName)
            ->first();

        return $task ? (bool) $task->wajib_qc : false;
    }

    /**
     * Ambil nama tahap saat ini (jika di worker stage).
     */
    public function getCurrentStageName(): ?string
    {
        $stages = $this->stage_sequence ?? [];
        if (in_array($this->status, $stages)) {
            return $this->status;
        }
        if ($this->status === self::STATUS_QC_REVIEW) {
            return $this->current_review_stage;
        }
        return null;
    }

    /**
     * Status berikutnya berdasarkan stage_sequence yang dinamis.
     */
    public function getNextStatus(): ?string
    {
        $stages = $this->stage_sequence ?? [];
        if (empty($stages)) return null;

        // CREATED → QC_PREP (jika on) atau langsung tahap pertama
        if ($this->status === self::STATUS_CREATED) {
            if ($this->has_qc_prep) {
                return self::STATUS_QC_PREP;
            }
            return $stages[0] ?? self::STATUS_COMPLETED;
        }

        // QC_PREP → tahap pertama di stage_sequence
        if ($this->status === self::STATUS_QC_PREP) {
            return $stages[0] ?? self::STATUS_COMPLETED;
        }

        // QC_REVIEW → tahap berikutnya setelah yang di-review
        if ($this->status === self::STATUS_QC_REVIEW) {
            $reviewIdx = array_search($this->current_review_stage, $stages);
            $nextIdx = ($reviewIdx !== false) ? $reviewIdx + 1 : 0;
            return $stages[$nextIdx] ?? self::STATUS_COMPLETED;
        }

        // COMPLETED → sudah selesai
        if ($this->status === self::STATUS_COMPLETED) {
            return null;
        }

        // Worker stage → cek wajib_qc, lalu lanjut
        $currentIdx = array_search($this->status, $stages);
        if ($currentIdx === false) return null;

        // Kalau wajib_qc = true untuk tahap ini → masuk QC_REVIEW
        if ($this->isQcRequiredForStage($this->status)) {
            return self::STATUS_QC_REVIEW;
        }

        // Kalau tidak, lanjut ke tahap berikutnya atau COMPLETED
        $nextIdx = $currentIdx + 1;
        return $stages[$nextIdx] ?? self::STATUS_COMPLETED;
    }

    /**
     * Status sebelumnya berdasarkan stage_sequence yang dinamis.
     */
    public function getPreviousStatus(): ?string
    {
        $stages = $this->stage_sequence ?? [];
        if (empty($stages)) return null;

        // CREATED → ga ada sebelumnya
        if ($this->status === self::STATUS_CREATED) {
            return null;
        }

        // QC_PREP → balik ke CREATED
        if ($this->status === self::STATUS_QC_PREP) {
            return self::STATUS_CREATED;
        }

        // QC_REVIEW → balik ke tahap yang di-review
        if ($this->status === self::STATUS_QC_REVIEW) {
            return $this->current_review_stage;
        }

        // COMPLETED → cek tahap terakhir
        if ($this->status === self::STATUS_COMPLETED) {
            $lastStage = end($stages);
            // Kalau tahap terakhir wajib_qc, previous-nya QC_REVIEW
            if ($this->isQcRequiredForStage($lastStage)) {
                return self::STATUS_QC_REVIEW;
            }
            return $lastStage;
        }

        // Worker stage → cek tahap sebelumnya
        $currentIdx = array_search($this->status, $stages);
        if ($currentIdx === false) return self::STATUS_CREATED;

        // Ini tahap pertama → QC_PREP (jika on) atau CREATED
        if ($currentIdx === 0) {
            return $this->has_qc_prep ? self::STATUS_QC_PREP : self::STATUS_CREATED;
        }

        $prevStage = $stages[$currentIdx - 1];
        // Kalau tahap sebelumnya wajib_qc, previous-nya QC_REVIEW
        if ($this->isQcRequiredForStage($prevStage)) {
            return self::STATUS_QC_REVIEW;
        }
        return $prevStage;
    }

    /**
     * Set status ke stage tertentu, update current_stage_index & stage_entered_at.
     */
    public function advanceToStage(string $newStatus): void
    {
        $stages = $this->stage_sequence ?? [];
        $idx = array_search($newStatus, $stages);

        $this->update([
            'status' => $newStatus,
            'current_stage_index' => $idx !== false ? $idx : $this->current_stage_index,
            'stage_entered_at' => now(),
            'current_review_stage' => ($newStatus === self::STATUS_QC_REVIEW)
                ? $this->status  // tahap yang sedang di-review = tahap sekarang
                : null,
        ]);
    }
}
