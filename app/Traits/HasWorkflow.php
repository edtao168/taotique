<?php
// app/Traits/HasWorkflow.php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait HasWorkflow
{
    /**
     * 取得狀態顯示名稱
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? '-';
    }

    /**
     * 取得狀態顏色
     */
    public function getStatusColorAttribute(): string
    {
        return $this->status?->color() ?? 'badge-ghost';
    }

    /**
     * 判斷是否可編輯
     */
    public function isEditable(): bool
    {
        return $this->status?->isEditable() ?? false;
    }

    /**
     * 判斷是否可刪除
     */
    public function isDeletable(): bool
    {
        return $this->status?->isDeletable() ?? false;
    }

    /**
     * 判斷是否可審核通過
     */
    public function canApprove(): bool
    {
        return $this->status?->canApprove() ?? false;
    }

    /**
     * 判斷是否為最終狀態
     */
    public function isFinalized(): bool
    {
        return $this->status?->isFinalized() ?? false;
    }

    // =========================================================================
    // SECTION: 🆕 結算相關方法
    // =========================================================================

    /**
     * 取得需要結算的付款方式
     * 可在子類別中覆寫
     */
    protected function getNonCashPaymentMethods(): array
    {
        return config('business.non_cash_payment_methods', [
            'transfer',
            'bank_transfer',
            'credit_card',
            'shopee_pay',
            'line_pay',
            'taiwan_pay',
        ]);
    }

    /**
     * 取得結算排除的狀態（這些狀態不需要結算）
     * 可在子類別中覆寫
     */
    protected function getSettlementExcludedStatuses(): array
    {
        $enumClass = static::getStatusEnumClass();
        
        return [
            $enumClass::SETTLED->value,
            $enumClass::COMPLETED->value,
            $enumClass::CANCELLED->value,
            $enumClass::REJECTED->value,
        ];
    }

    /**
     * 判斷是否需要結算
     */
    public function needsSettlement(): bool
    {
        // 1. 檢查是否有 payment_method 屬性
        if (!property_exists($this, 'payment_method')) {
            return false;
        }

        // 2. 現金不需要結算
        if ($this->payment_method === 'cash') {
            return false;
        }

        // 3. 檢查是否為非現金付款方式
        if (!in_array($this->payment_method, $this->getNonCashPaymentMethods())) {
            return false;
        }

        // 4. 最終狀態不需要結算
        if ($this->isFinalized()) {
            return false;
        }

        return true;
    }

    /**
     * 檢查是否已完成結算
     */
    public function isSettled(): bool
    {
        // 現金視為已結算
        if ($this->payment_method === 'cash') {
            return true;
        }

        $enumClass = static::getStatusEnumClass();
        return $this->status === $enumClass::SETTLED;
    }

    /**
     * Scope: 查詢需要結算的記錄
     */
    public function scopeNeedsSettlement($query)
    {
        $nonCashMethods = $this->getNonCashPaymentMethods();
        $excludedStatuses = $this->getSettlementExcludedStatuses();

        return $query->whereIn('payment_method', $nonCashMethods)
                     ->whereNotIn('status', $excludedStatuses);
    }
	
    /**
     * 狀態轉換
     */
    public function transitionTo(string $newStatus, string $event, $actor = null, array $metadata = []): void
    {
        $oldStatus = $this->status;
        
        // 檢查是否可轉換
        if (!$this->canTransition($oldStatus, $newStatus, $event)) {
            $fromLabel = $oldStatus?->label() ?? '未知';
            $toLabel = $this->getStatusLabel($newStatus);
            throw new \RuntimeException("不允許從 {$fromLabel} 轉換到 {$toLabel}");
        }
        
        // 執行轉換
        $this->status = $newStatus;
        $this->save();

        info('狀態已變更', [
            'model' => static::class,
            'id' => $this->id,
            'from' => $oldStatus?->value,
            'to' => $newStatus,
            'event' => $event,
            'actor' => $actor?->id ?? $actor,
            'metadata' => $metadata,
        ]);
    }

    /**
     * 檢查是否可轉換
     */
    protected function canTransition($from, string $to, string $event): bool
    {
        // 如果 $from 是 Enum，取其 value
        $fromValue = $from instanceof \UnitEnum ? $from->value : $from;
        info('canTransition 檢查', [
        'from' => $fromValue,
        'to' => $to,
        'event' => $event,
        'rules' => $this->getTransitionRules(),
    ]);
        $rules = $this->getTransitionRules();
        
        foreach ($rules as $rule) {
            if ($rule['from'] === $fromValue && $rule['to'] === $to) {
                if (!isset($rule['event']) || $rule['event'] === $event) {
                    return true;
                }
            }
        }
        
        return false;
    }

    /**
     * 取得可用的操作
     */
    public function getAvailableActionsAttribute(): array
    {
        $actions = [];
        $rules = $this->getTransitionRules();
        $currentValue = $this->status instanceof \UnitEnum ? $this->status->value : $this->status;
        
        foreach ($rules as $rule) {
            if ($rule['from'] === $currentValue) {
                $actions[$rule['event']] = $rule['label'] ?? $rule['event'];
            }
        }
        
        return $actions;
    }

    /**
     * 輔助方法：取得狀態標籤
     */
    protected function getStatusLabel(string $status): string
    {
        $enumClass = static::getStatusEnumClass();
        return $enumClass::tryFrom($status)?->label() ?? $status;
    }

    /**
     * 取得對應的 Enum class（子類必須實作）
     */
    abstract protected static function getStatusEnumClass(): string;

    /**
     * 定義轉換規則（子類必須實作）
     */
    abstract protected function getTransitionRules(): array;
}