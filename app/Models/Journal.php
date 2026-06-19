<?php

namespace App\Models;

use App\Enums\JournalStatus;
use App\Traits\HasWorkflow;
use App\Services\AccountingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Journal extends Model
{
    use HasWorkflow;

    protected $table = 'journals';

    protected $fillable = [
        'shop_id',
        'currency',
        'exchange_rate',
        'entry_date',
        'description',
        'reference_type',
        'reference_id',
        // ❌ 沒有 source_type, source_id
        'status',
        'corrects_journal_id',
        'correction_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'exchange_rate' => 'decimal:4',
        'status' => JournalStatus::class,
    ];

    // ==================== 多型關聯（只用 reference） ====================

    /**
     * 來源單據（統一使用 reference 欄位）
     */
    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    /**
     * 別名：相容舊代碼
     */
    public function source(): MorphTo
    {
        return $this->reference();
    }

    /**
     * 別名：相容舊代碼
     */
    public function sourceDocument(): MorphTo
    {
        return $this->reference();
    }

    // ==================== 輔助方法 ====================

    public function isManualEntry(): bool
    {
        return $this->reference_type === 'manual' || $this->reference_type === null;
    }

    public function isAutoEntry(): bool
    {
        return !$this->isManualEntry();
    }

    public function getSourceNumberAttribute(): ?string
    {
        return resolve(AccountingService::class)->resolveSourceNumber(
            $this->reference_type,
            $this->reference_id
        );
    }

    public function getSourceTypeLabelAttribute(): string
    {
        return resolve(AccountingService::class)->getSourceTypeLabel(
            $this->reference_type ?? 'manual'
        );
    }

    // ==================== 狀態檢查 ====================

    public function isEditable(): bool
    {
        return $this->status === JournalStatus::DRAFT;
    }

    public function isDeletable(): bool
    {
        return $this->status === JournalStatus::DRAFT;
    }

    public function isPostable(): bool
    {
        return $this->status === JournalStatus::DRAFT;
    }

    public function isCorrectable(): bool
    {
        if ($this->status !== JournalStatus::POSTED) {
            return false;
        }

        if ($this->reference_type === 'correct') {
            return false;
        }

        if ($this->corrections()->exists()) {
            return false;
        }

        return true;
    }

    public function getIsCorrectedAttribute(): bool
    {
        return $this->corrections()->exists();
    }

    public function canBeClosed(): bool
    {
        if ($this->status !== JournalStatus::POSTED) {
            return false;
        }

        if ($this->is_corrected) {
            $correction = $this->corrections()->first();
            if (!$correction || !in_array($correction->status, [JournalStatus::POSTED, JournalStatus::CLOSED])) {
                return false;
            }
        }

        return true;
    }

    public function isClosed(): bool
    {
        return $this->status === JournalStatus::CLOSED;
    }

    public function isBalanced(): bool
    {
        $totalDebit = $this->items->sum('debit');
        $totalCredit = $this->items->sum('credit');
        return bccomp((string) $totalDebit, (string) $totalCredit, 4) === 0;
    }

    // ==================== 狀態轉換 ====================

    public function post(string $postedBy): void
    {
        if (!$this->isPostable()) {
            throw new \RuntimeException('只有草稿狀態可以過帳');
        }

        DB::transaction(function () use ($postedBy) {
            if (!$this->isBalanced()) {
                throw new \RuntimeException('借貸不平衡，無法過帳');
            }

            $this->update([
                'status' => JournalStatus::POSTED->value,
                'created_by' => $postedBy,
            ]);

            Log::info('日記帳已過帳', [
                'journal_id' => $this->id,
                'description' => $this->description,
                'posted_by' => $postedBy,
            ]);
        });
    }

    public function close(string $closedBy): void
    {
        if (!$this->canBeClosed()) {
            throw new \RuntimeException('此分錄無法結帳：' . $this->getCannotCloseReason());
        }

        $this->update([
            'status' => JournalStatus::CLOSED->value,
            'updated_by' => $closedBy,
            'updated_at' => now(),
        ]);

        Log::info('日記帳已結帳', [
            'journal_id' => $this->id,
            'closed_by' => $closedBy,
        ]);
    }

    protected function getCannotCloseReason(): string
    {
        if ($this->status !== JournalStatus::POSTED) {
            return "狀態為 {$this->status?->label()}，只有已過帳可以結帳";
        }

        if ($this->is_corrected) {
            $correction = $this->corrections()->first();
            if (!$correction) {
                return "已被更正但找不到更正分錄";
            }
            if (!in_array($correction->status, [JournalStatus::POSTED, JournalStatus::CLOSED])) {
                return "更正分錄狀態為 {$correction->status?->label()}，需要先過帳或結帳";
            }
        }

        return "未知原因";
    }

    // ==================== Scope ====================

    public function scopeInOpenPeriods($query)
    {
        return $query->where('status', '!=', JournalStatus::CLOSED->value);
    }

    public function scopeCorrectable($query)
    {
        return $query->where('status', JournalStatus::POSTED->value)
            ->where('reference_type', '!=', 'correct')
            ->whereDoesntHave('corrections');
    }

    public function scopeInPeriod($query, int $year, int $month)
    {
        $start = "{$year}-{$month}-01";
        $end = date('Y-m-t', strtotime($start));
        return $query->whereBetween('entry_date', [$start, $end]);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', JournalStatus::DRAFT->value);
    }

    public function scopePosted($query)
    {
        return $query->where('status', JournalStatus::POSTED->value);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', JournalStatus::CLOSED->value);
    }

    // ==================== 月結批次 ====================

    public static function batchClosePeriod(int $year, int $month, string $closedBy): array
    {
        $result = [
            'closed_count' => 0,
            'skipped_count' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($year, $month, $closedBy, &$result) {
            $journals = self::inPeriod($year, $month)
                ->where('status', JournalStatus::POSTED->value)
                ->get();

            foreach ($journals as $journal) {
                try {
                    if ($journal->canBeClosed()) {
                        $journal->close($closedBy);
                        $result['closed_count']++;
                    } else {
                        $result['skipped_count']++;
                        $result['errors'][] = [
                            'journal_id' => $journal->id,
                            'description' => $journal->description,
                            'reason' => $journal->getCannotCloseReason(),
                        ];
                    }
                } catch (\Exception $e) {
                    $result['errors'][] = [
                        'journal_id' => $journal->id,
                        'description' => $journal->description,
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        });

        Log::info('月結完成', [
            'period' => "{$year}-{$month}",
            'closed_by' => $closedBy,
            'result' => $result,
        ]);

        return $result;
    }

    public static function canClosePeriod(int $year, int $month): array
    {
        $start = "{$year}-{$month}-01";
        $end = date('Y-m-t', strtotime($start));

        $drafts = self::whereBetween('entry_date', [$start, $end])
            ->where('status', JournalStatus::DRAFT->value)
            ->count();

        $pendingCorrections = self::whereBetween('entry_date', [$start, $end])
            ->where('reference_type', 'correct')
            ->where('status', JournalStatus::POSTED->value)
            ->whereHas('originalJournal', function ($q) use ($start, $end) {
                $q->whereNotBetween('entry_date', [$start, $end]);
            })
            ->count();

        return [
            'can_close' => $drafts === 0,
            'draft_count' => $drafts,
            'cross_period_corrections' => $pendingCorrections,
            'warning' => $pendingCorrections > 0
                ? "發現 {$pendingCorrections} 筆跨期更正分錄，請確認"
                : null,
        ];
    }

    // ==================== 多型映射 ====================

    public static function getActualClassNameForMorph($alias)
    {
        $map = [
            // Sale 相關
            'sale' => \App\Models\Sale::class,
            'sale_revenue' => \App\Models\Sale::class,
            'sale_cost' => \App\Models\Sale::class,
            'sale_fee' => \App\Models\Sale::class,

            // Purchase 相關
            'purchase' => \App\Models\Purchase::class,
            'purchase_stock_in' => \App\Models\Purchase::class,
            'purchase_stock_in_prepaid' => \App\Models\Purchase::class,

            // Purchase Return 相關
            'purchase_return' => \App\Models\PurchaseReturn::class,
            'purchase_return_refund' => \App\Models\PurchaseReturn::class,
            'purchase_return_cost' => \App\Models\PurchaseReturn::class,

            // Sale Return 相關
            'sales_return' => \App\Models\SaleReturn::class,
            'sales_return_refund' => \App\Models\SaleReturn::class,
            'sales_return_cost' => \App\Models\SaleReturn::class,

            // Conversion 相關
            'conversion' => \App\Models\Conversion::class,
            'conversion_input' => \App\Models\Conversion::class,
            'conversion_output' => \App\Models\Conversion::class,
            'conversion_post' => \App\Models\Conversion::class,

            // 特殊類型
            'correct' => null,
            'manual' => null,
        ];

        return $map[$alias] ?? parent::getActualClassNameForMorph($alias);
    }

    // ==================== 關聯 ====================

    public function items(): HasMany
    {
        return $this->hasMany(JournalItem::class);
    }

    public function originalJournal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_journal_id');
    }

    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_journal_id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}