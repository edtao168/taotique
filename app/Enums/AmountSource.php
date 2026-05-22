<?php
// app/Enums/AmountSource.php

namespace App\Enums;

enum AmountSource: string
{
    // ==============================================
    // Sale Model 直接欄位 (來自 business.php fee_types)
    // ==============================================
    
    /** 商品小計 (不含任何費用) - Sale::$subtotal */
    case SUBTOTAL = 'subtotal';
    
    /** 客戶實付總額 (小計 + 買家運費 - 折扣) - Sale::$customer_total */
    case CUSTOMER_TOTAL = 'customer_total';
    
    /** 賣家最終淨額 (實收 - 平台費 - 賣家運費) - Sale::$final_net_amount */
    case FINAL_NET_AMOUNT = 'final_net_amount';
    
    // ==============================================
    // 費用類型 (對應 business.php fee_types)
    // ==============================================
    
    /** 買家支付運費 (加項) - Sale::$shipping_fee_customer */
    case SHIPPING_FEE_CUSTOMER = 'shipping_fee_customer';
    
    /** 賣場折扣 (減項) - Sale::$discount */
    case DISCOUNT = 'discount';
    
    /** 平台優惠券 (減項) - Sale::$platform_coupon */
    case PLATFORM_COUPON = 'platform_coupon';
    
    /** 平台代付運費 (減項) - Sale::$shipping_fee_platform */
    case SHIPPING_FEE_PLATFORM = 'shipping_fee_platform';
    
    /** 平台成交手續費 (減項) - Sale::$platform_fee */
    case PLATFORM_FEE = 'platform_fee';
    
    /** 帳款調整 (加/減項) - Sale::$order_adjustment */
    case ORDER_ADJUSTMENT = 'order_adjustment';
    
    /** 佣金 (減項) - Sale::$commission */
    case COMMISSION = 'commission';
    
    // ==============================================
    // 採購相關 (Purchase Model)
    // ==============================================
    
    /** 採購總額 - Purchase::$total_amount */
    case PURCHASE_TOTAL = 'total_amount';
    
    // ==============================================
    // 明細加總 (SaleItem / PurchaseItem)
    // ==============================================
    
    /** 商品成本加總 (數量 × 單位成本) */
    case ITEMS_COST = 'items.sum:unit_cost_twd*quantity';
    
    // ==============================================
    // 通用
    // ==============================================
    
    /** 通用金額欄位 */
    case AMOUNT = 'amount';
    
    /**
     * 顯示名稱 (用於 UI 下拉選單)
     */
    public function label(): string
    {
        return match($this) {
            self::SUBTOTAL => 'subtotal (商品小計)',
            self::CUSTOMER_TOTAL => 'customer_total (客戶實付)',
            self::FINAL_NET_AMOUNT => 'final_net_amount (賣家淨額)',
            
            self::SHIPPING_FEE_CUSTOMER => 'shipping_fee_customer (買家運費)',
            self::DISCOUNT => 'discount (賣場折扣)',
            self::PLATFORM_COUPON => 'platform_coupon (平台優惠券)',
            self::SHIPPING_FEE_PLATFORM => 'shipping_fee_platform (平台代付運費)',
            self::PLATFORM_FEE => 'platform_fee (平台手續費)',
            self::ORDER_ADJUSTMENT => 'order_adjustment (帳款調整)',
            self::COMMISSION => 'commission (佣金)',
            
            self::PURCHASE_TOTAL => 'total_amount (採購總額)',
            self::ITEMS_COST => 'items.sum:unit_cost_twd*quantity (商品成本)',
            self::AMOUNT => 'amount (金額)',
        };
    }
    
    /**
     * 取得金額來源類型 (用於 AccountingService 判斷)
     */
    public function sourceType(): string
    {
        return match($this) {
            self::ITEMS_COST => 'items_sum',
            default => 'direct_field',
        };
    }
    
    /**
     * 是否為費用類型 (來自 fee_types)
     */
    public function isFeeType(): bool
    {
        return in_array($this, [
            self::SHIPPING_FEE_CUSTOMER,
            self::DISCOUNT,
            self::PLATFORM_COUPON,
            self::SHIPPING_FEE_PLATFORM,
            self::PLATFORM_FEE,
            self::ORDER_ADJUSTMENT,
            self::COMMISSION,
        ]);
    }
    
    /**
     * 取得所有選項 (用於下拉選單)
     */
    public static function options(): array
    {
        return collect(self::cases())->map(fn($case) => [
            'value' => $case->value,
            'label' => $case->label(),
        ])->toArray();
    }
}