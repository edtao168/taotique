<<<<<<< HEAD
<?php // 依據中國國家標準 (CAS)

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        // 關閉外鍵檢查
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        
        DB::table('accounts')->truncate();

        $list = [
            // ==============================================
            // 1：資產類  (Assets)
            // ==============================================
            ['code' => '1001', 'name' => '庫存現金', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 1, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100101', 'name' => '新台幣現金', 'type' => 'asset', 'parent_id' => '1001', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100102', 'name' => '人民幣現金', 'type' => 'asset', 'parent_id' => '1001', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1002', 'name' => '銀行存款', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 1, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100201', 'name' => '國泰世華-新台幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100202', 'name' => '合作金庫-新台幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100203', 'name' => '郵局-新台幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100204', 'name' => '兆豐銀行-新台幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100211', 'name' => '中國銀行-人民幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100212', 'name' => '建設銀行-人民幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1012', 'name' => '其他貨幣資金', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 1, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101201', 'name' => '台灣Pay', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101202', 'name' => '蝦皮錢包', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101203', 'name' => 'LINE Pay', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101204', 'name' => '街口支付', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101206', 'name' => '數字人民幣', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101207', 'name' => '微信支付', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101208', 'name' => '支付寶', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101211', 'name' => '存出投資款-國泰世華臺股', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],			
            ['code' => '101212', 'name' => '存出投資款-國泰世華複委托-新台幣', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101213', 'name' => '存出投資款-中信證券', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101214', 'name' => '存出投資款-東吳證券', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1122', 'name' => '應收賬款', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '112201', 'name' => '一般客戶', 'type' => 'asset', 'parent_id' => '1122', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '112202', 'name' => '電商平台應收', 'type' => 'asset', 'parent_id' => '1122', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1123', 'name' => '預付賬款', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '112301', 'name' => '預付進貨', 'type' => 'asset', 'parent_id' => '1123', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '112302', 'name' => '預付房租', 'type' => 'asset', 'parent_id' => '1123', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1221', 'name' => '其他應收款', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '122101', 'name' => '業主暫借款', 'type' => 'asset', 'parent_id' => '1221', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1405', 'name' => '庫存商品', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140501', 'name' => '吊墜項鍊', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140502', 'name' => '手鍊手鐲', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140503', 'name' => '百貨', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140505', 'name' => '耳環', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140506', 'name' => '戒指', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140509', 'name' => '配件半成品', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1409', 'name' => '包裝物及低值易耗品', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140901', 'name' => '禮盒包材', 'type' => 'asset', 'parent_id' => '1409', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1601', 'name' => '固定資產', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '160101', 'name' => '展示櫃', 'type' => 'asset', 'parent_id' => '1601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '160102', 'name' => '收銀設備', 'type' => 'asset', 'parent_id' => '1601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '160103', 'name' => '燈光設備', 'type' => 'asset', 'parent_id' => '1601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1602', 'name' => '累計折舊', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1903', 'name' => '其他非流動資產', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '190301', 'name' => '私人股票投資', 'type' => 'asset', 'parent_id' => '1903', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            // ==============================================
            // 2：負債類 (Liabilities)
            // ==============================================
            ['code' => '2001', 'name' => '短期借款', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '2202', 'name' => '應付賬款', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '220201', 'name' => '大陸廠商應付', 'type' => 'liability', 'parent_id' => '2202', 'level' => 2, 'is_monetary' => 0, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '220202', 'name' => '台灣廠商應付', 'type' => 'liability', 'parent_id' => '2202', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '2211', 'name' => '應付職工薪酬', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '2221', 'name' => '應交稅費', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '222101', 'name' => '增值稅', 'type' => 'liability', 'parent_id' => '2221', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '222102', 'name' => '個人所得稅', 'type' => 'liability', 'parent_id' => '2221', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '2241', 'name' => '其他應付款', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '224101', 'name' => '信用卡應付', 'type' => 'liability', 'parent_id' => '2241', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '224102', 'name' => '業主墊款', 'type' => 'liability', 'parent_id' => '2241', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],

            // ==============================================
            // 3：權益類 (Owner's Equity，equity)
            // ==============================================
            ['code' => '3001', 'name' => '實收資本', 'type' => 'equity', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '3103', 'name' => '本年利潤', 'type' => 'equity', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '3141', 'name' => '利潤分配', 'type' => 'equity', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '314101', 'name' => '業主提取', 'type' => 'equity', 'parent_id' => '3141', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '314102', 'name' => '業主私人費用', 'type' => 'equity', 'parent_id' => '3141', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            // ==============================================
            // 4：成本類 (Cost)
            // ==============================================
            ['code' => '4001', 'name' => '主營業務成本', 'type' => 'cost', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '400101', 'name' => '商品成本', 'type' => 'cost', 'parent_id' => '4001', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '400102', 'name' => '包材成本', 'type' => 'cost', 'parent_id' => '4001', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '400103', 'name' => '進貨折扣與折讓', 'type' => 'cost', 'parent_id' => '4001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],

            // ==============================================
            // 5/6：損益類 (Profit & Loss，profit)
			// profit只是一個代號，便於統計而已
            // ==============================================
            ['code' => '5001', 'name' => '主營業務收入', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500101', 'name' => '門市零售收入', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500102', 'name' => '蝦皮電商收入', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500103', 'name' => 'Facebook銷售收入', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500104', 'name' => '直播銷售收入', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500105', 'name' => '買家運費收入', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500110', 'name' => '銷售折扣與折讓', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '5002', 'name' => '其他業務收入', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500201', 'name' => '專案接案收入', 'type' => 'profit', 'parent_id' => '5002', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500202', 'name' => '程式開發收入', 'type' => 'profit', 'parent_id' => '5002', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            
            ['code' => '5101', 'name' => '稅金及附加', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '5601', 'name' => '銷售費用', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560101', 'name' => '房租費用', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560102', 'name' => '水電瓦斯費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560103', 'name' => '一般廣告費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560104', 'name' => '一般物流費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560105', 'name' => '支付手續費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560106', 'name' => '平台運費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560107', 'name' => '平台成交手續費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560108', 'name' => '帳款調整', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560109', 'name' => '平台廣告費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560110', 'name' => '雜項費用', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '6602', 'name' => '管理費用', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660201', 'name' => '辦公用品', 'type' => 'profit', 'parent_id' => '6602', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660202', 'name' => '差旅交通', 'type' => 'profit', 'parent_id' => '6602', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660203', 'name' => '折舊費用', 'type' => 'profit', 'parent_id' => '6602', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660210', 'name' => '雜項費用', 'type' => 'profit', 'parent_id' => '6602', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '6603', 'name' => '財務費用', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660301', 'name' => '銀行手續費', 'type' => 'profit', 'parent_id' => '6603', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660302', 'name' => '匯兌損益', 'type' => 'profit', 'parent_id' => '6603', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '6111', 'name' => '投資收益', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '611101', 'name' => '股票損益', 'type' => 'profit', 'parent_id' => '6111', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '6301', 'name' => '營業外收入', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '630101', 'name' => '平台補助收入', 'type' => 'profit', 'parent_id' => '6301', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '630102', 'name' => '發票中獎收入', 'type' => 'profit', 'parent_id' => '6301', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '6401', 'name' => '營業外支出', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
        ];

        $codeToId = [];

        // 頂層科目
        foreach ($list as $item) {
            if (empty($item['parent_id'])) {
                DB::table('accounts')->insert([
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'parent_id' => null,
                    'level' => $item['level'],
                    'is_monetary' => $item['is_monetary'],
                    'currency' => $item['currency'],
                    'shop_id' => $item['shop_id'],
                    'is_active' => $item['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $codeToId[$item['code']] = DB::getPdo()->lastInsertId();
            }
        }

        // 子科目
        foreach ($list as $item) {
            if (!empty($item['parent_id'])) {
                $parentId = $codeToId[$item['parent_id']] ?? null;
                DB::table('accounts')->insert([
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'parent_id' => $parentId,
                    'level' => $item['level'],
                    'is_monetary' => $item['is_monetary'],
                    'currency' => $item['currency'],
                    'shop_id' => $item['shop_id'],
                    'is_active' => $item['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 開啟外鍵檢查
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
=======
<?php // 依據中國國家標準 (CAS)

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        // 關閉外鍵檢查
        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        
        DB::table('accounts')->truncate();

        $list = [
            // ==============================================
            // 1：資產類  (Assets)
            // ==============================================
            ['code' => '1001', 'name' => '庫存現金', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 1, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100101', 'name' => '新台幣現金', 'type' => 'asset', 'parent_id' => '1001', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100102', 'name' => '人民幣現金', 'type' => 'asset', 'parent_id' => '1001', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1002', 'name' => '銀行存款', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 1, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100201', 'name' => '國泰世華-新台幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100202', 'name' => '合作金庫-新台幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100203', 'name' => '郵局-新台幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100204', 'name' => '兆豐銀行-新台幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100211', 'name' => '中國銀行-人民幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '100212', 'name' => '建設銀行-人民幣', 'type' => 'asset', 'parent_id' => '1002', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1012', 'name' => '其他貨幣資金', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 1, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101201', 'name' => '台灣Pay', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101202', 'name' => '蝦皮錢包', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101203', 'name' => 'LINE Pay', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101204', 'name' => '街口支付', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101206', 'name' => '數字人民幣', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101207', 'name' => '微信支付', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101208', 'name' => '支付寶', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101211', 'name' => '存出投資款-國泰世華臺股', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],			
            ['code' => '101212', 'name' => '存出投資款-國泰世華複委托-新台幣', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101213', 'name' => '存出投資款-中信證券', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '101214', 'name' => '存出投資款-東吳證券', 'type' => 'asset', 'parent_id' => '1012', 'level' => 2, 'is_monetary' => 1, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1122', 'name' => '應收賬款', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '112201', 'name' => '一般客戶', 'type' => 'asset', 'parent_id' => '1122', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '112202', 'name' => '電商平台應收', 'type' => 'asset', 'parent_id' => '1122', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1123', 'name' => '預付賬款', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '112301', 'name' => '預付進貨', 'type' => 'asset', 'parent_id' => '1123', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '112302', 'name' => '預付房租', 'type' => 'asset', 'parent_id' => '1123', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1221', 'name' => '其他應收款', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '122101', 'name' => '業主暫借款', 'type' => 'asset', 'parent_id' => '1221', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1405', 'name' => '庫存商品', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140501', 'name' => '吊墜項鍊', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140502', 'name' => '手鍊手鐲', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140503', 'name' => '百貨', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140505', 'name' => '耳環', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140506', 'name' => '戒指', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140509', 'name' => '配件半成品', 'type' => 'asset', 'parent_id' => '1405', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1409', 'name' => '包裝物及低值易耗品', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '140901', 'name' => '禮盒包材', 'type' => 'asset', 'parent_id' => '1409', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1601', 'name' => '固定資產', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '160101', 'name' => '展示櫃', 'type' => 'asset', 'parent_id' => '1601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '160102', 'name' => '收銀設備', 'type' => 'asset', 'parent_id' => '1601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '160103', 'name' => '燈光設備', 'type' => 'asset', 'parent_id' => '1601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1602', 'name' => '累計折舊', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '1903', 'name' => '其他非流動資產', 'type' => 'asset', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '190301', 'name' => '私人股票投資', 'type' => 'asset', 'parent_id' => '1903', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            // ==============================================
            // 2：負債類 (Liabilities)
            // ==============================================
            ['code' => '2001', 'name' => '短期借款', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '2202', 'name' => '應付賬款', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '220201', 'name' => '大陸廠商應付', 'type' => 'liability', 'parent_id' => '2202', 'level' => 2, 'is_monetary' => 0, 'currency' => 'CNY', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '220202', 'name' => '台灣廠商應付', 'type' => 'liability', 'parent_id' => '2202', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '2211', 'name' => '應付職工薪酬', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '2221', 'name' => '應交稅費', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '222101', 'name' => '增值稅', 'type' => 'liability', 'parent_id' => '2221', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '222102', 'name' => '個人所得稅', 'type' => 'liability', 'parent_id' => '2221', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '2241', 'name' => '其他應付款', 'type' => 'liability', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '224101', 'name' => '信用卡應付', 'type' => 'liability', 'parent_id' => '2241', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '224102', 'name' => '業主墊款', 'type' => 'liability', 'parent_id' => '2241', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],

            // ==============================================
            // 3：權益類 (Owner's Equity，equity)
            // ==============================================
            ['code' => '3001', 'name' => '實收資本', 'type' => 'equity', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '3103', 'name' => '本年利潤', 'type' => 'equity', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '3141', 'name' => '利潤分配', 'type' => 'equity', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '314101', 'name' => '業主提取', 'type' => 'equity', 'parent_id' => '3141', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '314102', 'name' => '業主私人費用', 'type' => 'equity', 'parent_id' => '3141', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            // ==============================================
            // 4：成本類 (Cost)
            // ==============================================
            ['code' => '4001', 'name' => '主營業務成本', 'type' => 'cost', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '400101', 'name' => '商品成本', 'type' => 'cost', 'parent_id' => '4001', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '400102', 'name' => '包材成本', 'type' => 'cost', 'parent_id' => '4001', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '400103', 'name' => '進貨折扣與折讓', 'type' => 'cost', 'parent_id' => '4001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],

            // ==============================================
            // 5/6：損益類 (Profit & Loss，profit)
			// profit只是一個代號，便於統計而已
            // ==============================================
            ['code' => '5001', 'name' => '主營業務收入', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500101', 'name' => '門市零售收入', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500102', 'name' => '蝦皮電商收入', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500103', 'name' => 'Facebook銷售收入', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500104', 'name' => '直播銷售收入', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500105', 'name' => '買家運費收入', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500110', 'name' => '銷售折扣與折讓', 'type' => 'profit', 'parent_id' => '5001', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '5002', 'name' => '其他業務收入', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500201', 'name' => '專案接案收入', 'type' => 'profit', 'parent_id' => '5002', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '500202', 'name' => '程式開發收入', 'type' => 'profit', 'parent_id' => '5002', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            
            ['code' => '5101', 'name' => '稅金及附加', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '5601', 'name' => '銷售費用', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560101', 'name' => '房租費用', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560102', 'name' => '水電瓦斯費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560103', 'name' => '一般廣告費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560104', 'name' => '一般物流費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560105', 'name' => '支付手續費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560106', 'name' => '平台運費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560107', 'name' => '平台成交手續費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560108', 'name' => '帳款調整', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560109', 'name' => '平台廣告費', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '560110', 'name' => '雜項費用', 'type' => 'profit', 'parent_id' => '5601', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '6602', 'name' => '管理費用', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660201', 'name' => '辦公用品', 'type' => 'profit', 'parent_id' => '6602', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660202', 'name' => '差旅交通', 'type' => 'profit', 'parent_id' => '6602', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660203', 'name' => '折舊費用', 'type' => 'profit', 'parent_id' => '6602', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660210', 'name' => '雜項費用', 'type' => 'profit', 'parent_id' => '6602', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '6603', 'name' => '財務費用', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660301', 'name' => '銀行手續費', 'type' => 'profit', 'parent_id' => '6603', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '660302', 'name' => '匯兌損益', 'type' => 'profit', 'parent_id' => '6603', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '6111', 'name' => '投資收益', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '611101', 'name' => '股票損益', 'type' => 'profit', 'parent_id' => '6111', 'level' => 2, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '6301', 'name' => '營業外收入', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '630101', 'name' => '平台補助收入', 'type' => 'profit', 'parent_id' => '6301', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],
            ['code' => '630102', 'name' => '發票中獎收入', 'type' => 'profit', 'parent_id' => '6301', 'level' => 2, 'is_monetary' => 0, 'currency' => 'TWD', 'shop_id' => 1, 'is_active' => 1],

            ['code' => '6401', 'name' => '營業外支出', 'type' => 'profit', 'parent_id' => null, 'level' => 1, 'is_monetary' => 0, 'currency' => '', 'shop_id' => 1, 'is_active' => 1],
        ];

        $codeToId = [];

        // 頂層科目
        foreach ($list as $item) {
            if (empty($item['parent_id'])) {
                DB::table('accounts')->insert([
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'parent_id' => null,
                    'level' => $item['level'],
                    'is_monetary' => $item['is_monetary'],
                    'currency' => $item['currency'],
                    'shop_id' => $item['shop_id'],
                    'is_active' => $item['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $codeToId[$item['code']] = DB::getPdo()->lastInsertId();
            }
        }

        // 子科目
        foreach ($list as $item) {
            if (!empty($item['parent_id'])) {
                $parentId = $codeToId[$item['parent_id']] ?? null;
                DB::table('accounts')->insert([
                    'code' => $item['code'],
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'parent_id' => $parentId,
                    'level' => $item['level'],
                    'is_monetary' => $item['is_monetary'],
                    'currency' => $item['currency'],
                    'shop_id' => $item['shop_id'],
                    'is_active' => $item['is_active'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // 開啟外鍵檢查
        DB::statement('SET FOREIGN_KEY_CHECKS = 1');
    }
>>>>>>> b29039cfb5a4a2683aedba9883af961633089c73
}