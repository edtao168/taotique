<?php
// app/Traits/HasAccountSearch.php

namespace App\Traits;

use App\Models\Account;

trait HasAccountSearch
{
    public array $accountSearchResults = [];

    /**
     * 即時搜尋會計科目（供 x-choices 使用）
     */
    public function search(string $value = '', ?int $shopId = null)
    {
        $query = Account::query()
            ->where('is_active', true)
            ->whereRaw('LENGTH(code) = 6');  // 只顯示最底層科目
        
        if ($shopId) {
            $query->where('shop_id', $shopId);
        }
        
        if ($value) {
            $query->where(function($q) use ($value) {
                $q->where('code', 'like', "%{$value}%")
                  ->orWhere('name', 'like', "%{$value}%");
            });
        }
        
        $results = $query
            ->orderBy('code')
            ->take(20)
            ->get()
            ->map(fn($account) => [
                'id' => $account->code,      // 用 code 當 id（人類可讀）
                'value' => $account->code,   // x-choices 用的 value
                'name' => "【{$account->code}】{$account->name}",
                'account_id' => $account->id,
            ])
            ->toArray();
        
        $this->accountSearchResults = $results;
        return $results;
    }

    /**
     * 根據科目代碼取得完整科目資料
     */
    public function getAccountByCode(string $code): ?Account
    {
        return Account::where('code', $code)
            ->where('is_active', true)
            ->first();
    }

    /**
     * 驗證科目是否存在且啟用
     */
    public function validateAccount(string $code): bool
    {
        return Account::where('code', $code)
            ->where('is_active', true)
            ->exists();
    }
}