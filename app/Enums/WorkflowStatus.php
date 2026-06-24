<?php // app/Enums/WorkflowStatus.php

namespace App\Enums;

enum WorkflowStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';
    case REJECTED = 'rejected';
    case SETTLED = 'settled';

    /**
     * 顯示名稱
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT => '草稿',
            self::PENDING => '待審核',
            self::APPROVED => '已審核',
            self::COMPLETED => '已結案',
            self::CANCELLED => '已取消',
            self::REJECTED => '已駁回',
            self::SETTLED => '已結算',
        };
    }

    /**
     * Badge 顏色
     */
    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'badge-ghost',
            self::PENDING => 'badge-warning',
            self::APPROVED => 'badge-info',
            self::COMPLETED => 'badge-success',
            self::CANCELLED => 'badge-error',
            self::REJECTED => 'badge-error',
            self::SETTLED => 'badge-success',
        };
    }

    /**
     * 是否可編輯
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING]);
    }

    /**
     * 是否可刪除
     */
    public function isDeletable(): bool
    {
        return in_array($this, [self::DRAFT, self::PENDING]);
    }

    /**
     * 是否可執行過帳/審核通過
     */
    public function canApprove(): bool
    {
        return match($this) {
			self::DRAFT, self::PENDING => true,
			default => false,
		};
    }

    /**
     * 是否可駁回
     */
    public function canReject(): bool
    {
        return $this === self::PENDING;
    }
    
    /**
     * 是否可核銷（結算）
     */
    public function canSettle(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * 是否為最終狀態（不可再異動）
     */
    public function isFinalized(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
            self::REJECTED,
            self::SETTLED,  // ✅ 已結算視為最終狀態
        ]);
    }

    /**
     * 是否為進行中狀態
     */
    public function isInProgress(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::PENDING,
            self::APPROVED,
            self::PENDING_SETTLEMENT,  // ✅ 待結算算進行中
        ]);
    }

    /**
     * 取得下一步可執行動作
     */
    public function nextActions(): array
    {
        return match($this) {
            self::DRAFT => ['submit' => '提交審核'],
            self::PENDING => ['approve' => '審核通過', 'reject' => '駁回'],
            self::APPROVED => [
                'complete' => '完成結案',
                'settle' => '提領至銀行',
            ],            
            default => [],
        };
    }

    /**
     * 取得所有選項（用於下拉選單）
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
            ->toArray();
    }
}