<?php // database/seeders/AccountingRuleSeeder.php

namespace Database\Seeders;

use App\Enums\AmountSource;
use App\Models\Account;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingRuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        // 所有規則建立包裹在事務中，確保資料一致性
        DB::transaction(function () {
            $shopId = 1;

            // ==============================================
            // 1. 蝦皮銷貨（買家實付邏輯）
            // 符合小企業會計準則：應收=商品收入+運費收入
            // ==============================================
            $this->createRule('sale_shopee', $shopId, [
                ['code' => '112202',  'type' => 'debit',  'source' => AmountSource::TOTAL], // 應收帳款-電商平台
                ['code' => '500102',  'type' => 'credit', 'source' => AmountSource::ITEMS], // 蝦皮電商收入
                ['code' => '500105',  'type' => 'credit', 'source' => AmountSource::SHIPPING], // 買家運費收入
                ['code' => '222101',  'type' => 'credit', 'source' => AmountSource::TAX], // 應交增值稅（銷項稅）
            ]);

            // ==============================================
            // 2. 蝦皮 費用 + 折扣 + 調整（賣家實收邏輯）
            // ==============================================
            $this->createRule('shopee_fee_discount', $shopId, [
                ['code' => '560107',  'type' => 'debit',  'source' => AmountSource::FEE], // 平台成交手續費
                ['code' => '500110',  'type' => 'debit',  'source' => AmountSource::DISCOUNT], // 銷售折扣與折讓
                ['code' => '560108',  'type' => 'debit',  'source' => AmountSource::ADJUSTMENT], // 帳款調整費用
                ['code' => '112202',  'type' => 'credit', 'source' => AmountSource::TOTAL], // 沖減電商應收帳款
            ]);

            // ==============================================
            // 3. 蝦皮撥款（賣家實收金額）
            // ==============================================
            $this->createRule('shopee_payout', $shopId, [
                ['code' => '101202',  'type' => 'debit',  'source' => AmountSource::NET], // 蝦皮錢包（實收）
                ['code' => '112202',  'type' => 'credit', 'source' => AmountSource::NET], // 沖減電商應收帳款
            ]);

            // ==============================================
            // 4. 實體店銷貨（現金）
            // ==============================================
            $this->createRule('sale_cash', $shopId, [
                ['code' => '100101',  'type' => 'debit',  'source' => AmountSource::TOTAL], // 庫存現金-新台幣
                ['code' => '500101',  'type' => 'credit', 'source' => AmountSource::ITEMS], // 門市零售收入
                ['code' => '222101',  'type' => 'credit', 'source' => AmountSource::TAX], // 應交增值稅（銷項稅）
            ]);

            // ==============================================
            // 5. 實體店 佣金支出
            // ==============================================
            $this->createRule('cash_commission', $shopId, [
                ['code' => '560110',  'type' => 'debit',  'source' => AmountSource::COMMISSION], // 銷售費用-雜項費用（佣金）
                ['code' => '100101',  'type' => 'credit', 'source' => AmountSource::COMMISSION], // 庫存現金-新台幣
            ]);

            // ==============================================
            // 6. Facebook 銷貨
            // ==============================================
            $this->createRule('sale_facebook', $shopId, [
                ['code' => '112202',  'type' => 'debit',  'source' => AmountSource::TOTAL], // 應收帳款-電商平台
                ['code' => '500103',  'type' => 'credit', 'source' => AmountSource::ITEMS], // Facebook銷售收入
                ['code' => '500105',  'type' => 'credit', 'source' => AmountSource::SHIPPING], // 買家運費收入
                ['code' => '222101',  'type' => 'credit', 'source' => AmountSource::TAX], // 應交增值稅（銷項稅）
            ]);

            // ==============================================
            // 7. Facebook 折扣
            // ==============================================
            $this->createRule('facebook_discount', $shopId, [
                ['code' => '500110',  'type' => 'debit',  'source' => AmountSource::DISCOUNT], // 銷售折扣與折讓
                ['code' => '112202',  'type' => 'credit', 'source' => AmountSource::DISCOUNT], // 沖減電商應收帳款
            ]);

            // ==============================================
            // 8. 結轉銷貨成本（全部通路共用）
            // 符合小企業會計準則：主營業務成本增加，庫存商品減少
            // ==============================================
            $this->createRule('cost_of_goods', $shopId, [
                ['code' => '400101',  'type' => 'debit',  'source' => AmountSource::COST], // 主營業務成本-商品成本
                ['code' => '140501',  'type' => 'credit', 'source' => AmountSource::COST], // 庫存商品-吊墜項鍊（預設明細，實際動態匹配）
            ]);

            // ==============================================
            // 9. 採購入庫（核心：符合小企業會計準則的採購分錄）
            // ==============================================
            $productCategoryMaps = [
				'pendant'   => '140501', // 吊墜項鍊
				'bracelet'  => '140502', // 手鍊手鐲
				'general'   => '140503', // 百貨
				'earring'   => '140505', // 耳環
				'ring'      => '140506', // 戒指
				'part'      => '140509', // 配件半成品
				'package'   => '140901', // 禮盒包材
			];

			foreach ($productCategoryMaps as $key => $assetCode) {
				// 費曼註釋：為每一種商品分類建立獨立的入庫規則
				// 這樣在業務流程中，只需根據 Product->category 決定呼叫哪個 Rule
				$this->createRule("purchase_inbound_{$key}", $shopId, [
					[
						'code'   => $assetCode, 
						'type'   => 'debit',  
						'source' => AmountSource::ITEMS // 該分類商品的總採購成本
					],
					[
						'code'   => '222101', 
						'type'   => 'debit',  
						'source' => AmountSource::TAX   // 進項稅（若有）
					],
					[
						'code'   => '220201', 
						'type'   => 'credit', 
						'source' => AmountSource::TOTAL // 應付帳款總額（本幣 TWD）
					],
				]);
			}

            // ==============================================
            // 10. 私人收支（系統需求：支援私人收支）
            // ==============================================
            // 10a. 業主借支（會還）
			$this->createRule('private_borrow', $shopId, [
				['code' => '122101',  'type' => 'debit',  'source' => AmountSource::PRIVATE],
				['code' => '100101',  'type' => 'credit', 'source' => AmountSource::PRIVATE],
			]);

			// 10b. 業主提取（不還）
			$this->createRule('private_withdraw', $shopId, [
				['code' => '314102',  'type' => 'debit',  'source' => AmountSource::PRIVATE],
				['code' => '100101',  'type' => 'credit', 'source' => AmountSource::PRIVATE],
			]);

            // ==============================================
            // 11. 銷售退回（小企業會計準則：沖減收入+恢復庫存）
            // ==============================================
            $this->createRule('sale_return', $shopId, [
                ['code' => '500101',  'type' => 'debit',  'source' => AmountSource::ITEMS], // 沖減門市零售收入
                ['code' => '222101',  'type' => 'debit',  'source' => AmountSource::TAX], // 沖減應交增值稅（銷項稅）
                ['code' => '100101',  'type' => 'credit', 'source' => AmountSource::TOTAL], // 退還現金
                ['code' => '140501',  'type' => 'debit',  'source' => AmountSource::COST], // 恢復庫存商品
                ['code' => '400101',  'type' => 'credit', 'source' => AmountSource::COST], // 沖減主營業務成本
            ]);
			
			// ==============================================
			// 12. 老闆專業收入
			// ==============================================
			$incomeAccounts = [
				'cash'			=> '100101',
				'cathay-cude'	=> '100201',   // 國泰世華				
				'tcb-bank'		=> '100202',   // 合作金庫
				'post'			=> '100203',   // 郵局
				'megabank'		=> '100204',   // 兆豐
				'taiwanpay'		=> '101201',
				'shopee'		=> '101202',
				'linepay'		=> '101203',
			];
			// a.接案
			foreach ($incomeAccounts as $key => $debitCode) {
				$this->createRule("owner_contract_income_{$key}", $shopId, [
					['code' => $debitCode, 'type' => 'debit', 'source' => AmountSource::TOTAL],
					['code' => '500201', 'type' => 'credit', 'source' => AmountSource::TOTAL],
				]);
			}

			// b.程式開發
			foreach ($incomeAccounts as $key => $debitCode) {
				$this->createRule("owner_development_income_{$key}", $shopId, [
					['code' => $debitCode, 'type' => 'debit', 'source' => AmountSource::TOTAL],
					['code' => '500202', 'type' => 'credit', 'source' => AmountSource::TOTAL],
				]);
			}
		});
    }	

    /**
     * 規則建立輔助方法（強化資料一致性與錯誤處理）
     * 
     * @param string $eventType 事件類型
     * @param int $shopId 店鋪ID
     * @param array $lines 分錄行
     * @return void
     */
    private function createRule(string $eventType, int $shopId, array $lines): void
    {
        // 先建立/更新規則主檔
        $rule = AccountingRule::updateOrCreate(
            ['event_type' => $eventType, 'shop_id' => $shopId],
            ['is_active' => true]
        );

        // 先刪除舊規則行（避免殘留）
        $rule->lines()->delete();

        foreach ($lines as $i => $line) {
            // 嚴格驗證帳戶存在性
            $account = Account::where('code', $line['code'])
                ->where('shop_id', $shopId)
                ->lockForUpdate() // 鎖定帳戶記錄，避免併發修改
                ->first();

            // 帳戶不存在時拋出異常（而非跳過），確保規則完整
            if (!$account) {
                throw new \RuntimeException("會計規則建立失敗：帳戶代碼 {$line['code']} 不存在（shop_id: {$shopId}）");
            }

            // 建立規則行，嚴格遵循金額精度規則
            AccountingRuleLine::create([
                'accounting_rule_id' => $rule->id,
                'account_id'         => $account->id,
                'entry_type'         => $line['type'],
                'amount_source'      => $line['source']->value,
                'ratio'              => bcadd('1.0000', '0', 4), // 嚴格使用bc函數確保精度
                'sort_order'         => $i + 1,
                'created_at'         => now(),
                'updated_at'         => now(),
            ]);
        }
    }
}