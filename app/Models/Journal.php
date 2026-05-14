<?php

namespace App\Models;

use App\Services\AccountingService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Journal extends Model
{
    protected $table = 'journals';

    // 狀態常數
    const STATUS_DRAFT = 'draft';
    const STATUS_POSTED = 'posted';
    const STATUS_CLOSED = 'closed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'shop_id',
        'currency',
        'exchange_rate',
        'entry_date',
        'description',
        'reference_type',
        'reference_id',
        'status',
        'corrects_journal_id',
        'correction_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'exchange_rate' => 'decimal:4',
    ];

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

    public function reference(): MorphTo
    {
        return $this->morphTo();
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
        return $this->status === self::STATUS_DRAFT;
    }

    public function isDeletable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPostable(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * 是否可以產生更正分錄
     */
    public function isCorrectable(): bool
    {
        if ($this->status !== self::STATUS_POSTED) {
            return false;
        }
        
        if ($this->reference_type === 'correct') {
            return false;
        }
        
        if ($this->hasCorrection()->exists()) {
            return false;
        }
        
        return true;
    }

    public function getIsCorrectedAttribute(): bool
    {
        return $this->hasCorrection()->exists();
    }

    public function hasCorrection()
    {
        return $this->hasOne(self::class, 'corrects_journal_id');
    }

    /**
     * 檢查是否可以轉為 closed（月結用）
     */
    public function canBeClosed(): bool
    {
        if ($this->status !== self::STATUS_POSTED) {
            return false;
        }
        
        if ($this->is_corrected) {
            $correction = $this->hasCorrection;
            if (!$correction || !in_array($correction->status, [self::STATUS_POSTED, self::STATUS_CLOSED])) {
                return false;
            }
        }
        
        return true;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    /**
     * 檢查借貸是否平衡
     */
    public function isBalanced(): bool
    {
        $totalDebit = $this->items->sum('debit');
        $totalCredit = $this->items->sum('credit');
        
        return bccomp((string) $totalDebit, (string) $totalCredit, 4) === 0;
    }

    // ==================== 狀態轉換 ====================
    
    /**
     * 過帳：draft → posted
     */
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
                'status' => self::STATUS_POSTED,
                'created_by' => $postedBy,
            ]);
            
            Log::info('日記帳已過帳', [
                'journal_id' => $this->id,
                'description' => $this->description,
                'posted_by' => $postedBy,
            ]);
        });
    }

    /**
     * 結帳：posted → closed（複用 updated_by / updated_at）
     */
    public function close(string $closedBy): void
    {
        if (!$this->canBeClosed()) {
            throw new \RuntimeException('此分錄無法結帳：' . $this->getCannotCloseReason());
        }
        
        $this->update([
            'status' => self::STATUS_CLOSED,
            'updated_by' => $closedBy,   // 記錄結帳人
            'updated_at' => now(),        // 記錄結帳時間
        ]);
        
        Log::info('日記帳已結帳', [
            'journal_id' => $this->id,
            'closed_by' => $closedBy,
        ]);
    }

    protected function getCannotCloseReason(): string
    {
        if ($this->status !== self::STATUS_POSTED) {
            return "狀態為 {$this->status}，只有 posted 可以結帳";
        }
        
        if ($this->is_corrected) {
            $correction = $this->hasCorrection;
            if (!$correction) {
                return "已被更正但找不到更正分錄";
            }
            if (!in_array($correction->status, [self::STATUS_POSTED, self::STATUS_CLOSED])) {
                return "更正分錄狀態為 {$correction->status}，需要先過帳或結帳";
            }
        }
        
        return "未知原因";
    }

    // ==================== Scope ====================
    
    public function scopeInOpenPeriods($query)
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    public function scopeCorrectable($query)
    {
        return $query->where('status', self::STATUS_POSTED)
            ->where('reference_type', '!=', 'correct')
            ->whereDoesntHave('hasCorrection');
    }

    public function scopeInPeriod($query, int $year, int $month)
    {
        $start = "{$year}-{$month}-01";
        $end = date('Y-m-t', strtotime($start));
        
        return $query->whereBetween('entry_date', [$start, $end]);
    }

    // ==================== 月結批次 ====================
    
    /**
     * 批次結帳指定期間的所有 posted 分錄
     */
    public static function batchClosePeriod(int $year, int $month, string $closedBy): array
    {
        $result = [
            'closed_count' => 0,
            'skipped_count' => 0,
            'errors' => [],
        ];
        
        DB::transaction(function () use ($year, $month, $closedBy, &$result) {
            $journals = self::inPeriod($year, $month)
                ->where('status', self::STATUS_POSTED)
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

    /**
     * 檢查期間是否可以結帳
     */
    public static function canClosePeriod(int $year, int $month): array
    {
        $start = "{$year}-{$month}-01";
        $end = date('Y-m-t', strtotime($start));
        
        $drafts = self::whereBetween('entry_date', [$start, $end])
            ->where('status', self::STATUS_DRAFT)
            ->count();
        
        $pendingCorrections = self::whereBetween('entry_date', [$start, $end])
            ->where('reference_type', 'correct')
            ->where('status', self::STATUS_POSTED)
            ->whereHas('originalJournal', function($q) use ($start, $end) {
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
}