<?php
// 檔案路徑：app/Enums/AmountSource.php

namespace App\Enums;

enum AmountSource: string
{
    // ==============================================
    // Sale Model 直接欄位與核心計算
    // ==============================================
    case SUBTOTAL = 'subtotal';
    case CUSTOMER_TOTAL = 'customer_total';
    case FINAL_NET_AMOUNT = 'final_net_amount';
    case SUBTOTAL_AFTER_DISCOUNT = 'subtotal_after_discount';
    case TOTAL_FEES = 'total_fees';
    case COST_AMOUNT = 'cost_amount'; // 銷貨總成本累加基準

    // ==============================================
    // 費用類型（電商摩擦費、物流費、退貨處理費）
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
    case PURCHASE_BASE_TOTAL = 'purchase_base_total';        // 本幣應付總額
    case PURCHASE_BASE_ITEMS = 'purchase_base_items';        // 本幣商品總值
    case PURCHASE_BASE_TAX = 'purchase_base_tax';            // 本幣進項稅額
    case PURCHASE_BASE_SHIPPING = 'purchase_base_shipping';  // 本幣運費
    case PURCHASE_BASE_OTHER_FEES = 'purchase_base_other_fees'; // 本幣其他費用

    // ==============================================
    // 採購外幣原始金額（未換算）
    // ==============================================
    case PURCHASE_FOREIGN_SUBTOTAL = 'subtotal';      // 外幣商品總額
    case PURCHASE_FOREIGN_SHIPPING = 'shipping_fee';  // 外幣運費
    case PURCHASE_FOREIGN_TAX = 'tax';                // 外幣進項稅
    case PURCHASE_FOREIGN_OTHER_FEES = 'other_fees';  // 外幣其他費
    case PURCHASE_FOREIGN_DISCOUNT = 'discount';      // 外幣折讓
    case PURCHASE_FOREIGN_TOTAL = 'total_amount';     // 外幣應付總額

    // ==============================================
    // 銷退/採退相關
    // ==============================================
    case RETURN_TOTAL = 'return_total';  // 退貨退款總額（原幣）
    case RETURN_COST = 'return_cost';    // 退貨成本（原幣）
    case RETURN_COST_BASE = 'return_cost_base';   // 退貨成本（本幣，已換算）

    // ==============================================
    // 明細加總舊配置（留存相容）
    // ==============================================
    case ITEMS_COST = 'items.sum:cost*quantity';
    case ITEMS_PRODUCT_COST = 'items.sum:product.cost*quantity';

    /**
     * 提供給後台 AccountingRule 設定頁面的 Mary UI 下拉選單繁體標籤
     */
    public function label(): string
    {
        return match($this) {
            self::SUBTOTAL => '商品小計 (subtotal)',
            self::CUSTOMER_TOTAL => '買家實付總額 (customer_total)',
            self::FINAL_NET_AMOUNT => '賣家最終實收 (final_net_amount)',
            self::SUBTOTAL_AFTER_DISCOUNT => '折讓後純商品淨額 (subtotal_after_discount)',
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

            self::PURCHASE_TOTAL => '採購單總金額 (total_amount)',
            self::PURCHASE_ITEMS_AMOUNT => '採購商品總價 (items_amount)',
            self::PURCHASE_TAX => '採購進項稅額 (purchase_tax_amount)',
            self::EXPENSE_AMOUNT => '採購附加費用金額 (expense_amount)',

            self::RETURN_TOTAL => '退款/退貨總金額 (return_total)',
            self::RETURN_COST => '退貨回庫成本總計 (return_cost)',
			self::RETURN_COST_BASE => '退貨成本-本幣 (return_cost_base, 已換算)',
            self::ITEMS_COST => '商品成本歷史快照累加 (舊式)',
            self::ITEMS_PRODUCT_COST => '關聯商品即時成本累加 (舊式)',
			self::PURCHASE_SUBTOTAL => '採購外幣純商品總額',
            self::PURCHASE_TOTAL_AMOUNT => '採購外幣應付總額',
            self::PURCHASE_BASE_TOTAL => '採購本幣({$currency})總金額',
            self::PURCHASE_BASE_ITEMS => '採購本幣({$currency})商品淨額(換算後)',
            self::PURCHASE_BASE_TAX => '採購本幣({$currency})進項稅額(換算後)',
            self::PURCHASE_BASE_SHIPPING => '採購本幣({$currency})運費支出(換算後)',
            self::PURCHASE_BASE_OTHER_FEES => '採購本幣({$currency})附加費(換算後)',
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

    public static function options(): array
    {
        return collect(self::cases())->map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }
}