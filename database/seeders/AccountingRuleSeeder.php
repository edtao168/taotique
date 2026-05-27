<?php

namespace Database\Seeders;

use App\Enums\AmountSource;
use App\Models\Account;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingRuleSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            $shopId = 1;
            
            // 清空表格
            AccountingRuleLine::query()->delete();
            AccountingRule::query()->delete();
            
            // ==============================================
            // 一、收入認列規則 (sale_revenue_*)
            // ==============================================
            
            // 1.1 實體店零售
            $this->createRule('sale_revenue_retail', $shopId, [
                ['code' => 'DYNAMIC',  'type' => 'debit',  'source' => AmountSource::CUSTOMER_TOTAL],
                ['code' => '500101',   'type' => 'credit', 'source' => AmountSource::SUBTOTAL_AFTER_DISCOUNT],
                ['code' => '222103',   'type' => 'credit', 'source' => AmountSource::TAX],
            ]);
            
            // 1.2 蝦皮
            $this->createRule('sale_revenue_shopee', $shopId, [
                ['code' => '112202',   'type' => 'debit',  'source' => AmountSource::CUSTOMER_TOTAL],
                ['code' => '500102',   'type' => 'credit', 'source' => AmountSource::SUBTOTAL_AFTER_DISCOUNT],
                ['code' => '222103',   'type' => 'credit', 'source' => AmountSource::TAX],
                ['code' => '224101',   'type' => 'credit', 'source' => AmountSource::SHIPPING_FEE_CUSTOMER],
            ]);
            
            // 1.3 Facebook
            $this->createRule('sale_revenue_facebook', $shopId, [
                ['code' => '112202',   'type' => 'debit',  'source' => AmountSource::CUSTOMER_TOTAL],
                ['code' => '500103',   'type' => 'credit', 'source' => AmountSource::SUBTOTAL_AFTER_DISCOUNT],
                ['code' => '222103',   'type' => 'credit', 'source' => AmountSource::TAX],
                ['code' => '224101',   'type' => 'credit', 'source' => AmountSource::SHIPPING_FEE_CUSTOMER],
            ]);
            
            // 1.4 直播
            $this->createRule('sale_revenue_live', $shopId, [
                ['code' => '100201',   'type' => 'debit',  'source' => AmountSource::CUSTOMER_TOTAL],
                ['code' => '500104',   'type' => 'credit', 'source' => AmountSource::SUBTOTAL_AFTER_DISCOUNT],
                ['code' => '222103',   'type' => 'credit', 'source' => AmountSource::TAX],
                ['code' => '224101',   'type' => 'credit', 'source' => AmountSource::SHIPPING_FEE_CUSTOMER],
            ]);
            
            // ==============================================
            // 二、費用認列規則 (sale_fee_*)
            // ==============================================
            
            // 2.1 實體店零售
            $this->createRule('sale_fee_retail', $shopId, [
                ['code' => '560105',   'type' => 'debit', 'source' => AmountSource::PLATFORM_FEE],
                ['code' => '560111',   'type' => 'debit', 'source' => AmountSource::COMMISSION],
                ['code' => '500110',   'type' => 'debit', 'source' => AmountSource::SELLER_DISCOUNT],
                ['code' => '560104',   'type' => 'debit', 'source' => AmountSource::SHIPPING_FEE_PLATFORM],
                ['code' => 'DYNAMIC',  'type' => 'credit', 'source' => AmountSource::TOTAL_FEES],
            ]);
            
            // 2.2 蝦皮
            $this->createRule('sale_fee_shopee', $shopId, [
                ['code' => '560105',   'type' => 'debit', 'source' => AmountSource::PLATFORM_FEE],
                ['code' => '560111',   'type' => 'debit', 'source' => AmountSource::COMMISSION],
                ['code' => '500110',   'type' => 'debit', 'source' => AmountSource::SELLER_DISCOUNT],
                ['code' => '560104',   'type' => 'debit', 'source' => AmountSource::SHIPPING_FEE_PLATFORM],
                ['code' => '560108',   'type' => 'debit', 'source' => AmountSource::ORDER_ADJUSTMENT],
                ['code' => '112202',   'type' => 'credit', 'source' => AmountSource::TOTAL_FEES],
            ]);
            
            // 2.3 Facebook
            $this->createRule('sale_fee_facebook', $shopId, [
                ['code' => '560105',   'type' => 'debit', 'source' => AmountSource::PLATFORM_FEE],
                ['code' => '560111',   'type' => 'debit', 'source' => AmountSource::COMMISSION],
                ['code' => '500110',   'type' => 'debit', 'source' => AmountSource::SELLER_DISCOUNT],
                ['code' => '560104',   'type' => 'debit', 'source' => AmountSource::SHIPPING_FEE_PLATFORM],
                ['code' => '112202',   'type' => 'credit', 'source' => AmountSource::TOTAL_FEES],
            ]);
            
            // 2.4 直播
            $this->createRule('sale_fee_live', $shopId, [
                ['code' => '560105',   'type' => 'debit', 'source' => AmountSource::PLATFORM_FEE],
                ['code' => '560111',   'type' => 'debit', 'source' => AmountSource::COMMISSION],
                ['code' => '500110',   'type' => 'debit', 'source' => AmountSource::SELLER_DISCOUNT],
                ['code' => '560104',   'type' => 'debit', 'source' => AmountSource::SHIPPING_FEE_PLATFORM],
                ['code' => '100201',   'type' => 'credit', 'source' => AmountSource::TOTAL_FEES],
            ]);
            
            // ==============================================
            // 三、成本結轉規則
            // ==============================================
            
            $this->createRule('sale_cost', $shopId, [
                ['code' => '5401',     'type' => 'debit', 'source' => AmountSource::ITEMS_COST],
                ['code' => '140501',   'type' => 'credit', 'source' => AmountSource::ITEMS_COST],
            ]);
            
            // ==============================================
            // 四、採購入庫
            // ==============================================
            
            $productCategoryMaps = [
                'pendant'   => '140501',
                'bracelet'  => '140502',
                'general'   => '140503',
                'earring'   => '140505',
                'ring'      => '140506',
                'part'      => '140509',
                'package'   => '140901',
            ];
            
            foreach ($productCategoryMaps as $category => $assetCode) {
                $this->createRule("purchase_inbound_{$category}", $shopId, [
                    ['code' => $assetCode, 'type' => 'debit',  'source' => AmountSource::PURCHASE_ITEMS_AMOUNT],
                    ['code' => '222101',   'type' => 'debit',  'source' => AmountSource::PURCHASE_TAX],
                    ['code' => '220201',   'type' => 'credit', 'source' => AmountSource::PURCHASE_TOTAL],
                ]);
            }
            
            // ==============================================
            // 五、退貨規則
            // ==============================================
            
            $this->createRule('sale_return', $shopId, [
                ['code' => '500101', 'type' => 'debit',  'source' => AmountSource::SUBTOTAL_AFTER_DISCOUNT],
                ['code' => '222103', 'type' => 'debit',  'source' => AmountSource::TAX],
                ['code' => '100101', 'type' => 'credit', 'source' => AmountSource::CUSTOMER_TOTAL],
                ['code' => '140501', 'type' => 'debit',  'source' => AmountSource::ITEMS_COST],
                ['code' => '5401',   'type' => 'credit', 'source' => AmountSource::ITEMS_COST],
            ]);
            
            // ==============================================
            // 六、私人收支
            // ==============================================
            
            $this->createRule('private_borrow', $shopId, [
                ['code' => '122101', 'type' => 'debit',  'source' => AmountSource::AMOUNT],
                ['code' => '100101', 'type' => 'credit', 'source' => AmountSource::AMOUNT],
            ]);
            
            $this->createRule('private_withdraw', $shopId, [
                ['code' => '314102', 'type' => 'debit',  'source' => AmountSource::AMOUNT],
                ['code' => '100101', 'type' => 'credit', 'source' => AmountSource::AMOUNT],
            ]);
            
            // ==============================================
            // 七、業主收入
            // ==============================================
            
            $incomeAccounts = [
                'cash'        => '100101',
                'cathay-cude' => '100201',
                'tcb-bank'    => '100202',
                'post'        => '100203',
                'megabank'    => '100204',
                'taiwanpay'   => '101201',
                'shopee'      => '101202',
                'linepay'     => '101203',
            ];
            
            foreach ($incomeAccounts as $key => $debitCode) {
                $this->createRule("owner_contract_income_{$key}", $shopId, [
                    ['code' => $debitCode, 'type' => 'debit',  'source' => AmountSource::AMOUNT],
                    ['code' => '500201',   'type' => 'credit', 'source' => AmountSource::AMOUNT],
                ]);
            }
            
            foreach ($incomeAccounts as $key => $debitCode) {
                $this->createRule("owner_development_income_{$key}", $shopId, [
                    ['code' => $debitCode, 'type' => 'debit',  'source' => AmountSource::AMOUNT],
                    ['code' => '500202',   'type' => 'credit', 'source' => AmountSource::AMOUNT],
                ]);
            }
        });
    }
    
    private function createRule(string $eventType, int $shopId, array $lines): void
    {
        AccountingRule::where('event_type', $eventType)
            ->where('shop_id', $shopId)
            ->delete();
        
        $rule = AccountingRule::create([
            'event_type' => $eventType,
            'shop_id' => $shopId,
            'is_active' => true,
        ]);
        
        foreach ($lines as $index => $line) {
            if ($line['code'] === 'DYNAMIC') {
                AccountingRuleLine::create([
                    'accounting_rule_id' => $rule->id,
                    'account_id' => null,
                    'entry_type' => $line['type'],
                    'amount_source' => $line['source']->value,
                    'ratio' => '1.0000',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]);
                continue;
            }
            
            $account = Account::where('code', $line['code'])
                ->where('shop_id', $shopId)
                ->first();
            
            if (!$account) {
                $this->command->warn("⚠️ 帳戶代碼 {$line['code']} 不存在，跳過規則 {$eventType}");
                continue;
            }
            
            AccountingRuleLine::create([
                'accounting_rule_id' => $rule->id,
                'account_id' => $account->id,
                'entry_type' => $line['type'],
                'amount_source' => $line['source']->value,
                'ratio' => '1.0000',
                'sort_order' => $index + 1,
                'is_active' => true,
            ]);
        }
    }
}