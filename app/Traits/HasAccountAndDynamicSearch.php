<?php
// app/Traits/HasAccountAndDynamicSearch.php

namespace App\Traits;

use App\Models\Account;

trait HasAccountAndDynamicSearch
{
    /**
     * 即時搜尋會計科目 + 動態策略
     * @param string $value 關鍵字
     * @param array $includeIds 強制包含的實體科目 ID 陣列
     */
    public function searchAccounts(string $value = '', array $includeIds = [])
    {
        $dynamicOptions = [
			// 銷售與庫存路由
            ['id' => 'DYNAMIC:sale:payment', 'name' => '⚙️ 銷售金流路由 (DYNAMIC:sale:payment)'],
            ['id' => 'DYNAMIC:auto:inventory', 'name' => '⚙️ 庫存資產路由 (DYNAMIC:auto:inventory)'],
            ['id' => 'DYNAMIC:auto:cost', 'name' => '⚙️ 銷貨成本路由 (DYNAMIC:auto:cost)'],
			['id' => 'DYNAMIC:sale:revenue', 'name' => '⚙️ 銷貨收入路由 (DYNAMIC:sale:revenue)'],
            ['id' => 'DYNAMIC:sale:channel_fee', 'name' => '⚙️ 通路摩擦手續費 (DYNAMIC:sale:channel_fee)'],
            ['id' => 'DYNAMIC:sale:return_fee:shipping', 'name' => '⚙️ 退貨運費支出 (DYNAMIC:sale:return_fee:shipping)'],
            ['id' => 'DYNAMIC:sale:discount', 'name' => '⚙️ 銷貨折讓/扣抵 (DYNAMIC:sale:discount)'],
		
			// 採購路由
            ['id' => 'DYNAMIC:purchase:payment', 'name' => '⚙️ 採購金流/應付路由 (DYNAMIC:purchase:payment)'],
            ['id' => 'DYNAMIC:purchase:expense', 'name' => '⚙️ 進口/採購附加費路由 (DYNAMIC:purchase:expense)'],
        ];

        // 處理實體科目查詢
        $query = Account::where('is_active', true);
        
        if (!empty($value)) {
            $query->where(function($q) use ($value) {
                $q->where('id', $value)
                  ->orWhere('code', 'like', "%{$value}%")
                  ->orWhere('name', 'like', "%{$value}%");
            });
        }

        // 如果有傳入需要強制包含的歷史 ID，使用 orWhere 確保它們一定會出現在前端清單快照中
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
}