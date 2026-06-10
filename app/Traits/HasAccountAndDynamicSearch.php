<?php
// app/Traits/HasAccountAndDynamicSearch.php

namespace App\Traits;

use App\Models\Account;

trait HasAccountAndDynamicSearch
{
    // ==================== 前端：動態科目選項（共用） ====================
    
    protected static function getDynamicAccountDefinitions(): array
    {
        $options = config('business.accounting_dynamic_options', []);
        
        $definitions = [];
        foreach ($options as $option) {
            $definitions[$option['value']] = $option['label'];
        }
        
        return $definitions;
    }
    
    public function searchAccounts(string $value = '', array $includeIds = [])
    {
        $dynamicOptions = [];
        foreach (self::getDynamicAccountDefinitions() as $id => $name) {
            $dynamicOptions[] = ['id' => $id, 'name' => '⚙️ ' . $name];
        }

        $query = Account::where('is_active', true);
        
        if (!empty($value)) {
            $query->where(function($q) use ($value) {
                $q->where('id', $value)
                  ->orWhere('code', 'like', "%{$value}%")
                  ->orWhere('name', 'like', "%{$value}%");
            });
        }

        if (!empty($includeIds)) {
            $query->orWhereIn('id', $includeIds);
        }

        $accounts = $query->orderBy('code')            
            ->get()
            ->map(fn($account) => [
                'id' => (string)$account->id,
                'name' => "【{$account->code}】{$account->name}", 
            ])
            ->toArray();

        return array_merge($dynamicOptions, $accounts);
    }
    // ==================== 會計規則讀取（共用） ====================
    
    /**
     * 獲取會計結轉規則（從資料庫動態讀取）
     * 所有模塊（Sale、SalesReturn、Purchase、PurchaseReturn）共用
     */
    public function getAccountingRules(string $eventType): array
    {
        $shopId = $this->shop_id ?? 1;
        
        $rule = AccountingRule::where('event_type', $eventType)
            ->where('shop_id', $shopId)
            ->where('is_active', true)
            ->with(['lines' => fn($q) => $q->orderBy('sort_order')])
            ->first();

        if (!$rule) {
            throw new \RuntimeException("找不到通用的動態會計規則：[{$eventType}]，店鋪 ID: {$shopId}");
        }

        return $rule->lines->toArray();
    }
    
    // ==================== 動態科目解析（抽象方法，各模塊實作） ====================
    
    /**
     * 解析動態會計科目
     * 每個模塊必須實作自己的解析邏輯
     * 
     * @param string $dynamicSpec 動態規格（如 'sale:payment'）
     * @param array|null $context 上下文
     * @return string 實際會計科目代碼
     */
    abstract public function resolveDynamicAccount(string $dynamicSpec, ?array $context = null): string;
    
    // ==================== 金額來源取得（抽象方法，各模塊實作） ====================
    
    /**
     * 根據金額來源取得對應的金額
     * 每個模塊必須實作自己的金額計算邏輯
     * 
     * @param string $source 金額來源名稱
     * @param mixed $context 額外上下文
     * @return string 金額（字串格式，支援高精度）
     */
    abstract public function getAmountFromSource(string $source, mixed $context = null): string;
}