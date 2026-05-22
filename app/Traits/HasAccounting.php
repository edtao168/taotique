<?php
// 路徑: app/Traits/HasAccounting.php

namespace App\Traits;

use App\Models\Journal;
use App\Services\AccountingService;
use Illuminate\Support\Facades\App;

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
     * 統一過帳入口
     * 
     * @param string $eventType 事件類型（如 'purchase', 'sale_revenue', 'sale_cost'）
     * @return Journal|null
     * @throws \RuntimeException
     */
    public function postJournal(string $eventType): ?Journal
    {
        // 取得規則陣列（由各 Model 實作）
        $rules = $this->getAccountingRules($eventType);

        if (empty($rules)) {
            \Illuminate\Support\Facades\Log::warning("{$eventType} 無會計規則，跳過過帳");
            return null;
        }

        // 委託 AccountingService 執行
        $service = App::make(AccountingService::class);
        
        return $service->postFromRules(
            source: $this,
            referenceType: $eventType,
            rules: $rules
        );
    }

    /**
     * 定義與 Journal 的關聯（多態）
     */
    public function journal()
    {
        return $this->morphOne(Journal::class, 'reference');
    }

    /**
     * 取得會計規則陣列（由各 Model 實作）
     * 
     * @param string $eventType 事件類型
     * @return array 規則陣列
     */
    abstract public function getAccountingRules(string $eventType): array;
}