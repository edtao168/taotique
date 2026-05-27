<?php
// 路徑: app/Traits/HasAccounting.php

namespace App\Traits;

use App\Models\Journal;
use App\Services\AccountingService;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;  // ✅ 加上這行
use RuntimeException;  // ✅ 加上這行

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
    public function postJournal(string $eventType): Journal
	{
		// ✅ 詳細日誌
    Log::info('HasAccounting::postJournal', [
        'model' => get_class($this),
        'model_id' => $this->id,
        'eventType' => $eventType,
    ]);
		// 重新載入必要的關聯，避免使用已被變更的實例
		$freshSource = static::with(['items', 'fees'])->find($this->id);
		
		if (!$freshSource) {
            throw new RuntimeException(
                "無法載入 " . class_basename($this) . " #{$this->id}，可能已被刪除"
            );
        }
		
		// 確保關聯資料已載入（避免 N+1）
        if (!$freshSource->relationLoaded('items')) {
            $freshSource->load('items');
        }
        if (!$freshSource->relationLoaded('fees')) {
            $freshSource->load('fees');
        }
        
		$rules = $this->getAccountingRules($eventType);
		 // ✅ 記錄 rules 內容
    Log::info('HasAccounting::postJournal rules', [
        'eventType' => $eventType,
        'rules' => $rules,
    ]);
    
		return app(AccountingService::class)->postFromRules(
			$freshSource,
			$rules,
			$eventType			
		);
	}
	
	/**
     * 抽象方法：子類別需實作，回傳會計規則
     */
    abstract public function getAccountingRules(string $eventType): array;

    /**
     * 定義與 Journal 的關聯（多態）
     */
    public function journal()
    {
        return $this->morphOne(Journal::class, 'reference');
    }
}