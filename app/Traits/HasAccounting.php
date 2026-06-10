<?php
// 路徑: app/Traits/HasAccounting.php

namespace App\Traits;

use App\Models\Journal;
use App\Services\AccountingService;
use Illuminate\Support\Facades\App;
use RuntimeException;

/**
 * 費曼註釋：精簡後的會計 Trait
 * 
 * 所有業務 Model 使用此 Trait 後，只需：
 * 1. 定義 getAccountingRules(string $eventType): array
 * 2. 在適當時機呼叫 $this->postJournal($eventType)
 * 
 * 具體分錄邏輯全部委託給 AccountingService，確保：
 * - 冪等性統一控制
 * - 借貸平衡統一驗證
 * - DB Transaction 統一包裹
 * - 來源追溯統一格式
 */
trait HasAccounting
{
	/**
     * 快捷發動動態會計過帳
     * * @param string $eventType 業務事件類型 (例如: 'sale_revenue', 'sale_cost', 'sale_fee')
     * @param string|null $context 額外的動態上下文變數
     * @return \App\Models\Journal|null
     */
    public function postJournal(string $eventType, ?string $context = null)
    {
        // 透過 Laravel 容器自動解析會計核心服務
        $accountingService = app(AccountingService::class);

        /**
         * 🎯 核心修復點：
         * 第一參數：事件類型 (string)
         * 第二參數：必須是當前 Model 實例本身 ($this)，類型為 Model。
         * 第三參數：上下文標記 (string|null)
         */
        return $accountingService->postFromRules($eventType, $this, $context);
    }
	
    /**
     * 🎯 實作 HasAccounting Trait 的抽象方法
     * 從資料庫讀取會計規則（由 AccountingService 調用）
     */
    public function getAccountingRules(string $eventType): array
    {
        $rule = \App\Models\AccountingRule::where('event_type', $eventType)            
            ->where('is_active', true)
            ->with(['lines' => fn($q) => $q->orderBy('sort_order')])
            ->first();

        if (!$rule) {
            throw new \RuntimeException("找不到退貨會計規則：{$eventType}");
        }

        return $rule->lines->toArray();
    }

    /**
     * 定義與 Journal 的關聯（多態）
     */
    public function journal()
    {
        return $this->morphOne(Journal::class, 'reference');
    }
}