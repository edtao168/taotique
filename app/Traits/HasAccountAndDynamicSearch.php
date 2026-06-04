<?php
// app/Traits/HasAccountAndDynamicSearch.php
// [代碼開頭標註位置：app/Traits/HasAccountAndDynamicSearch.php]
// [費曼註釋：終極修復聯邦搜尋，支援主鍵 ID、科目 Code 與名稱多重搜尋，並支援指定包容 ID 快照]

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
            ['id' => 'DYNAMIC:sale:payment', 'name' => '⚙️ 銷售金流路由 (DYNAMIC:sale:payment)'],
            ['id' => 'DYNAMIC:auto:inventory', 'name' => '⚙️ 庫存資產路由 (DYNAMIC:auto:inventory)'],
            ['id' => 'DYNAMIC:auto:cost', 'name' => '⚙️ 銷貨成本路由 (DYNAMIC:auto:cost)'],
        ];

        // 1. 處理實體科目查詢
        $query = Account::where('is_active', true);
        
        if (!empty($value)) {
            $query->where(function($q) use ($value) {
                $q->where('id', $value) // 🎯 支援直接輸入實體資料庫 ID（如 107）
                  ->orWhere('code', 'like', "%{$value}%") // 🎯 支援輸入科目代碼（如 500105）
                  ->orWhere('name', 'like', "%{$value}%"); // 🎯 支援輸入中文名稱
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
                'name' => "【{$account->code}】{$account->name}", // 🎯 將代碼、ID 與名稱全部清晰暴露，防擠壓
            ])
            ->toArray();

        // 2. 處理動態路由關鍵字過濾
        if (!empty($value)) {
            $dynamicOptions = array_values(array_filter($dynamicOptions, function($item) use ($value) {
                return str_contains(strtolower($item['name']), strtolower($value)) || 
                       str_contains(strtolower($item['id']), strtolower($value));
            }));
        }

        return array_merge($dynamicOptions, $accounts);
    }
}