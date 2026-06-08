<?php

// database/seeders/AccountingRuleSeeder.php

namespace Database\Seeders;

use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('開始建立會計規則...');
        
        // ==============================================
        // 1. 採購入庫規則（統一的規則）
        // ==============================================
        
        // 注意：event_type 必須唯一，不能重複
        // 如果已經有 purchase_inbound_xxx 的規則，先停用它們
        
        // 停用舊的採購規則（依商品類別分開的）
        $oldPurchaseRules = AccountingRule::where('event_type', 'like', 'purchase_inbound_%')
            ->where('event_type', '!=', 'purchase_stock_in')
            ->get();
        
        foreach ($oldPurchaseRules as $oldRule) {
            $oldRule->is_active = false;
            $oldRule->save();
            $this->command->info("⏸️ 停用舊規則: {$oldRule->event_type}");
        }
        
        // 建立或更新統一的採購入庫規則
        $purchaseRule = AccountingRule::updateOrCreate(
            [
                'event_type' => 'purchase_stock_in',
                'shop_id' => 1,
            ],
            [
                'is_active' => true,
            ]
        );
        
        // 刪除舊明細
        $purchaseRule->lines()->delete();
        
        // 借方：庫存商品（動態依商品類別）
        AccountingRuleLine::create([
            'accounting_rule_id' => $purchaseRule->id,
            'account_code' => 'DYNAMIC:auto:inventory',
            'entry_type' => 'debit',
            'amount_source' => 'purchase_base_items',
            'ratio' => '1.0000',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        
        // 借方：進項稅額
        AccountingRuleLine::create([
            'accounting_rule_id' => $purchaseRule->id,
            'account_code' => '222101',
            'entry_type' => 'debit',
            'amount_source' => 'purchase_base_tax',
            'ratio' => '1.0000',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        
        // 借方：運費附加費
        AccountingRuleLine::create([
            'accounting_rule_id' => $purchaseRule->id,
            'account_code' => 'DYNAMIC:purchase:expense',
            'entry_type' => 'debit',
            'amount_source' => 'purchase_base_shipping',
            'ratio' => '1.0000',
            'sort_order' => 3,
            'is_active' => true,
        ]);
        
        // 借方：其他費用
        AccountingRuleLine::create([
            'accounting_rule_id' => $purchaseRule->id,
            'account_code' => 'DYNAMIC:purchase:expense',
            'entry_type' => 'debit',
            'amount_source' => 'purchase_base_other_fees',
            'ratio' => '1.0000',
            'sort_order' => 4,
            'is_active' => true,
        ]);
        
        // 貸方：應付帳款/付款管道
        AccountingRuleLine::create([
            'accounting_rule_id' => $purchaseRule->id,
            'account_code' => 'DYNAMIC:purchase:payment',
            'entry_type' => 'credit',
            'amount_source' => 'purchase_base_total',
            'ratio' => '1.0000',
            'sort_order' => 5,
            'is_active' => true,
        ]);
        
        $this->command->info('✅ 採購入庫規則建立完成 (ID: ' . $purchaseRule->id . ')');
        
        // ==============================================
        // 2. 確認銷售規則已啟用
        // ==============================================
        
        // 確保 sale_revenue 規則啟用
        $saleRevenueRule = AccountingRule::where('event_type', 'sale_revenue')->first();
        if ($saleRevenueRule) {
            $saleRevenueRule->is_active = true;
            $saleRevenueRule->save();
            $this->command->info('✅ 銷售收入規則已啟用 (ID: ' . $saleRevenueRule->id . ')');
        } else {
            $this->command->error('❌ sale_revenue 規則不存在！請先建立。');
        }
        
        // 確保 sale_fee 規則啟用
        $saleFeeRule = AccountingRule::where('event_type', 'sale_fee')->first();
        if ($saleFeeRule) {
            $saleFeeRule->is_active = true;
            $saleFeeRule->save();
            $this->command->info('✅ 銷售費用規則已啟用 (ID: ' . $saleFeeRule->id . ')');
        } else {
            $this->command->error('❌ sale_fee 規則不存在！請先建立。');
        }
        
        // 確保 sale_cost 規則啟用
        $saleCostRule = AccountingRule::where('event_type', 'sale_cost')->first();
        if ($saleCostRule) {
            $saleCostRule->is_active = true;
            $saleCostRule->save();
            $this->command->info('✅ 銷售成本規則已啟用 (ID: ' . $saleCostRule->id . ')');
        } else {
            $this->command->error('❌ sale_cost 規則不存在！請先建立。');
        }
        
        $this->command->info('🎉 所有會計規則初始化完成！');
    }
}