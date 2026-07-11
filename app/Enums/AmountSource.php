<?php
// 檔案路徑：app/Enums/AmountSource.php
// Enum（列舉）金額來源

namespace App\Enums;

enum AmountSource: string
{
    // ==============================================
    // Sale Model 直接欄位與核心計算
    // ==============================================
    case SUBTOTAL = 'subtotal';
    case CUSTOMER_TOTAL = 'customer_total';
	case CUSTOMER_TOTAL_INC_TAX = 'customer_total_inc_tax';
    case FINAL_NET_AMOUNT = 'final_net_amount';
    case SUBTOTAL_AFTER_DISCOUNT = 'subtotal_after_discount';
	case NET_REVENUE = 'net_revenue';
    case TOTAL_FEES = 'total_fees';
    case COST_AMOUNT = 'cost_amount';

    // ==============================================
    // 費用類型
    // ==============================================
    case SHIPPING_FEE_CUSTOMER = 'shipping_fee_customer';
    case SELLER_DISCOUNT = 'seller_discount';
    case PLATFORM_COUPON = 'platform_coupon';
    case SHIPPING_FEE_PLATFORM = 'shipping_fee_platform';
    case PLATFORM_FEE = 'platform_fee';
    case ORDER_ADJUSTMENT = 'order_adjustment';
    case COMMISSION = 'commission';
    case RESTOCKING_FEE = 'restocking_fee';
    case RETURN_SHIPPING_FEE = 'return_shipping_fee';

    // ==============================================
    // 稅額與通用金額
    // ==============================================
    case TAX = 'tax_amount';
    case FREIGHT_AMOUNT = 'freight_amount';
    case AMOUNT = 'amount';

    // ==============================================
    // 採購專用金額來源（本幣換算後）
    // ==============================================
    case PURCHASE_BASE_TOTAL = 'purchase_base_total';
    case PURCHASE_BASE_ITEMS = 'purchase_base_items';
    case PURCHASE_BASE_TAX = 'purchase_base_tax';
    case PURCHASE_BASE_SHIPPING = 'purchase_base_shipping';
    case PURCHASE_BASE_OTHER_FEES = 'purchase_base_other_fees';

    // ==============================================
    // 採購外幣原始金額（未換算）
    // ==============================================
    case PURCHASE_FOREIGN_SUBTOTAL = 'purchase_foreign_subtotal';
    case PURCHASE_FOREIGN_SHIPPING = 'purchase_foreign_shipping';
    case PURCHASE_FOREIGN_TAX = 'purchase_foreign_tax';
    case PURCHASE_FOREIGN_OTHER_FEES = 'purchase_foreign_other_fees';
    case PURCHASE_FOREIGN_DISCOUNT = 'purchase_foreign_discount';
    case PURCHASE_FOREIGN_TOTAL = 'purchase_foreign_total';

    // ==============================================
    // 銷退/採退相關
    // ==============================================
    case RETURN_TOTAL = 'return_total';
    case RETURN_COST = 'return_cost';
    case RETURN_COST_BASE = 'return_cost_base';

    // ==============================================
    // 明細加總舊配置（留存相容）
    // ==============================================
    case ITEMS_COST = 'items.sum:cost*quantity';
    case ITEMS_PRODUCT_COST = 'items.sum:product.cost*quantity';
	
	// ==============================================
    // 拆裝組合模組（Conversion）
    // ==============================================
    case INPUT_TOTAL_COST = 'input_total_cost';
    case OUTPUT_TOTAL_COST = 'output_total_cost';
    case COST_VARIANCE = 'cost_variance';
    case COST_VARIANCE_ABS = 'cost_variance_abs';

    // ==============================================
    // 🆕 結算/沖帳模組（Settlement）
    // ==============================================
    case SALE_SETTLE = 'sale:settle';           // 對應 DYNAMIC:sale:settle
    case SALE_PAYMENT = 'sale:payment';         // 對應 DYNAMIC:sale:payment
    case SALE_REVENUE = 'sale:revenue';         // 對應 DYNAMIC:sale:revenue
    case AUTO_INVENTORY = 'auto:inventory';     // 對應 DYNAMIC:auto:inventory
    case AUTO_COST = 'auto:cost';               // 對應 DYNAMIC:auto:cost
    case PURCHASE_EXPENSE = 'purchase:expense'; // 對應 DYNAMIC:purchase:expense
    case PURCHASE_PAYMENT = 'purchase:payment'; // 對應 DYNAMIC:purchase:payment
    case SALES_RETURN_REFUND = 'sales_return:refund';  // 對應 DYNAMIC:sales_return:refund
    case PURCHASE_RETURN_REFUND = 'purchase_return:refund'; // 對應 DYNAMIC:purchase_return:refund
    case CONVERSION_OUTPUT = 'conversion:output'; // 對應 DYNAMIC:conversion:output
    case CONVERSION_INPUT = 'conversion:input';   // 對應 DYNAMIC:conversion:input
    case CONVERSION_LOSS = 'conversion:loss';     // 對應 DYNAMIC:conversion:loss
    case CONVERSION_GAIN = 'conversion:gain';     // 對應 DYNAMIC:conversion:gain

    /**
     * 提供給後台 AccountingRule 設定頁面的 Mary UI 下拉選單繁體標籤
     */
    public function label(): string
    {
        return match($this) {
            self::SUBTOTAL => '商品小計 (subtotal)',
            self::CUSTOMER_TOTAL => '買家實付總額 (customer_total)',
			self::CUSTOMER_TOTAL_INC_TAX => '買家含稅總額 (customer_total_inc_tax)',
            self::FINAL_NET_AMOUNT => '賣家最終實收 (final_net_amount)',
            self::SUBTOTAL_AFTER_DISCOUNT => '折讓後純商品淨額 (subtotal_after_discount)',
			self::NET_REVENUE => '淨收入 (net_revenue) - 扣除所有折扣後',
            self::TOTAL_FEES => '平台摩擦費用總計 (total_fees)',
            self::COST_AMOUNT => '銷貨加權成本總計 (cost_amount)',

            self::SHIPPING_FEE_CUSTOMER => '買家自付運費 (shipping_fee_customer)',
            self::SELLER_DISCOUNT => '賣場自營折扣 (seller_discount)',
            self::PLATFORM_COUPON => '平台優惠券補貼 (platform_coupon)',
            self::SHIPPING_FEE_PLATFORM => '平台代付運費 (shipping_fee_platform)',
            self::PLATFORM_FEE => '平台手續費 (platform_fee)',
            self::ORDER_ADJUSTMENT => '平台帳款調整 (order_adjustment)',
            self::COMMISSION => '平台佣金抽成 (commission)',
            self::RESTOCKING_FEE => '買家支付退貨處理費 (restocking_fee)',
            self::RETURN_SHIPPING_FEE => '賣家承擔退貨運費 (return_shipping_fee)',
            self::TAX => '銷項稅額 (tax_amount)',
            self::FREIGHT_AMOUNT => '常規運費欄位 (freight_amount)',
            self::AMOUNT => '通用單一金額 (amount)',

            self::PURCHASE_BASE_TOTAL => '採購本幣總金額 (purchase_base_total)',
            self::PURCHASE_BASE_ITEMS => '採購本幣商品淨額 (purchase_base_items)',
            self::PURCHASE_BASE_TAX => '採購本幣進項稅額 (purchase_base_tax)',
            self::PURCHASE_BASE_SHIPPING => '採購本幣運費支出 (purchase_base_shipping)',
            self::PURCHASE_BASE_OTHER_FEES => '採購本幣附加費 (purchase_base_other_fees)',

            self::PURCHASE_FOREIGN_SUBTOTAL => '採購外幣純商品總額 (purchase_foreign_subtotal)',
            self::PURCHASE_FOREIGN_SHIPPING => '採購外幣運費 (purchase_foreign_shipping)',
            self::PURCHASE_FOREIGN_TAX => '採購外幣進項稅 (purchase_foreign_tax)',
            self::PURCHASE_FOREIGN_OTHER_FEES => '採購外幣其他費用 (purchase_foreign_other_fees)',
            self::PURCHASE_FOREIGN_DISCOUNT => '採購外幣折讓 (purchase_foreign_discount)',
            self::PURCHASE_FOREIGN_TOTAL => '採購外幣應付總額 (purchase_foreign_total)',

            self::RETURN_TOTAL => '退款/退貨總金額 (return_total)',
            self::RETURN_COST => '退貨回庫成本總計-原幣 (return_cost)',
			self::RETURN_COST_BASE => '退貨成本-本幣 (return_cost_base)',
            self::ITEMS_COST => '商品成本歷史快照累加 (舊式)',
            self::ITEMS_PRODUCT_COST => '關聯商品即時成本累加 (舊式)',
			
			self::INPUT_TOTAL_COST => '領料投入總成本 (input_total_cost)',
            self::OUTPUT_TOTAL_COST => '成品產出總成本 (output_total_cost)',
            self::COST_VARIANCE => '成本差異（投入-產出）',
            self::COST_VARIANCE_ABS => '成本差異絕對值',

            // 🆕 結算/動態標籤
            self::SALE_SETTLE => '🔄 銷售結算 (sale:settle) - 應收/暫收',
            self::SALE_PAYMENT => '💳 銷售付款方式 (sale:payment) - 依付款方式動態',
            self::SALE_REVENUE => '📈 銷售收入 (sale:revenue) - 營業收入科目',
            self::AUTO_INVENTORY => '📦 庫存自動對應 (auto:inventory) - 存貨科目',
            self::AUTO_COST => '💰 成本自動對應 (auto:cost) - 成本科目',
            self::PURCHASE_EXPENSE => '📋 採購費用 (purchase:expense) - 費用科目',
            self::PURCHASE_PAYMENT => '💳 採購付款方式 (purchase:payment) - 依付款方式動態',
            self::SALES_RETURN_REFUND => '↩️ 銷退退款 (sales_return:refund) - 退款科目',
            self::PURCHASE_RETURN_REFUND => '↩️ 採退退款 (purchase_return:refund) - 退款科目',
            self::CONVERSION_OUTPUT => '🔧 轉換產出 (conversion:output) - 成品產出',
            self::CONVERSION_INPUT => '🔧 轉換投入 (conversion:input) - 原料投入',
            self::CONVERSION_LOSS => '🔧 轉換損失 (conversion:loss) - 成本差異(損失)',
            self::CONVERSION_GAIN => '🔧 轉換收益 (conversion:gain) - 成本差異(收益)',
        };
    }

    public function sourceType(): string
    {
        return match($this) {
            self::ITEMS_COST, self::ITEMS_PRODUCT_COST => 'items_sum', 
            default => 'direct_field',
        };
    }

    public function isFeeType(): bool
    {
        return in_array($this, [
            self::SHIPPING_FEE_CUSTOMER,
            self::SELLER_DISCOUNT,
            self::PLATFORM_COUPON,
            self::SHIPPING_FEE_PLATFORM,
            self::PLATFORM_FEE,
            self::ORDER_ADJUSTMENT,
            self::COMMISSION,
            self::RESTOCKING_FEE,
            self::RETURN_SHIPPING_FEE,
        ]);
    }

    /**
     * 🆕 判斷是否為動態標籤（Dynamic Tag）
     * 這類標籤前綴為 DYNAMIC: 或格式為 xxx:xxx
     */
    public function isDynamicTag(): bool
    {
        return in_array($this, [
            self::SALE_SETTLE,
            self::SALE_PAYMENT,
            self::SALE_REVENUE,
            self::AUTO_INVENTORY,
            self::AUTO_COST,
            self::PURCHASE_EXPENSE,
            self::PURCHASE_PAYMENT,
            self::SALES_RETURN_REFUND,
            self::PURCHASE_RETURN_REFUND,
            self::CONVERSION_OUTPUT,
            self::CONVERSION_INPUT,
            self::CONVERSION_LOSS,
            self::CONVERSION_GAIN,
        ]);
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }
}