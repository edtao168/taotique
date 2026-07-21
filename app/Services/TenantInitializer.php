<?php
// app/Services/TenantInitializer.php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Account;
use App\Models\Warehouse;
use App\Models\Channel;
use Illuminate\Support\Facades\DB;

class TenantInitializer
{
    public function initialize(Tenant $tenant)
    {
        // 1. 建立精簡版會計科目
        $this->createAccounts($tenant);
        
        // 2. 建立預設倉庫
        $this->createWarehouse($tenant);
        
        // 3. 建立預設通路
        $this->createChannel($tenant);
    }
    
    /**
     * 建立精簡版會計科目（台灣個人記帳用）
     */
    private function createAccounts(Tenant $tenant)
    {
        $accounts = [
            // 資產類
            ['code' => '1101', 'name' => '現金', 'type' => 'asset', 'level' => 1],
            ['code' => '1102', 'name' => '銀行存款', 'type' => 'asset', 'level' => 1],
            // 負債類
            ['code' => '2101', 'name' => '應付帳款', 'type' => 'liability', 'level' => 1],
            // 收入類
            ['code' => '4101', 'name' => '銷貨收入', 'type' => 'profit', 'level' => 1],
            // 成本類
            ['code' => '5101', 'name' => '銷貨成本', 'type' => 'cost', 'level' => 1],
            // 費用類
            ['code' => '6101', 'name' => '運費', 'type' => 'cost', 'level' => 1],
            ['code' => '6102', 'name' => '平台費用', 'type' => 'cost', 'level' => 1],
            ['code' => '6103', 'name' => '其他費用', 'type' => 'cost', 'level' => 1],
        ];

        foreach ($accounts as $data) {
            Account::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['type'],
                'level' => $data['level'],
                'tenant_id' => $tenant->id,
                'is_active' => true,
                'currency' => 'TWD',
            ]);
        }
    }
    
    /**
     * 建立預設倉庫
     */
    private function createWarehouse(Tenant $tenant)
    {
		$shop = $tenant->shops()->first();
    
		if (!$shop) {
			// 如果沒有 shop，跳過倉庫建立
			return;
		}
		
		Warehouse::create([
			'shop_id' => $shop->id,
			'name' => '主倉庫',
			'is_active' => true,
		]);
	}
    
    /**
     * 建立預設通路
     */
    private function createChannel(Tenant $tenant)
	{
        DB::table('channels')->insert([
			'tenant_id' => $tenant->id,
			'name' => '門市',
			'type' => 'retail',
			'platform_fee_rate' => 0,
			'is_active' => 1,
			'created_at' => now(),
			'updated_at' => now(),
		]);
	}
}