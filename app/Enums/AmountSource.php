<?php
// app/Enums/AmountSource.php

namespace App\Enums;

enum AmountSource: string
{
    // ==============================================
    // Sale Model 直接欄位
    // ==============================================
    
    case SUBTOTAL = 'subtotal';
    case CUSTOMER_TOTAL = 'customer_total';
    case FINAL_NET_AMOUNT = 'final_net_amount';
    
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
    
    // ==============================================
    // 稅額
    // ==============================================
    
    /** 銷項稅額 (Sale) */
    case TAX = 'tax_amount';
    
    // ==============================================
    // 採購相關
    // ==============================================
    
    case PURCHASE_TOTAL = 'total_amount';
    case PURCHASE_ITEMS_AMOUNT = 'items_amount';
    
    /** 進項稅額 (Purchase) - 使用不同值避免重複 */
    case PURCHASE_TAX = 'purchase_tax_amount';
    
    // ==============================================
    // 明細加總
    // ==============================================
    
    case ITEMS_COST = 'items.sum:cost*quantity';
    
    // ==============================================
    // 計算型金額來源
    // ==============================================
    
    /** 折讓後收入 (subtotal - seller_discount) */
    case SUBTOTAL_AFTER_DISCOUNT = 'subtotal_after_discount';
    
    /** 費用總額 */
    case TOTAL_FEES = 'total_fees';
    
    // ==============================================
    // 通用
    // ==============================================
    
    case AMOUNT = 'amount';
    
    // ==============================================
    // 方法
    // ==============================================
    
    public function label(): string
    {
        return match($this) {
            self::SUBTOTAL => 'subtotal (商品小計)',
            self::CUSTOMER_TOTAL => 'customer_total (客戶實付)',
            self::FINAL_NET_AMOUNT => 'final_net_amount (賣家淨額)',
            
            self::SHIPPING_FEE_CUSTOMER => 'shipping_fee_customer (買家運費)',
            self::SELLER_DISCOUNT => 'seller_discount (賣場折扣)',
            self::PLATFORM_COUPON => 'platform_coupon (平台優惠券)',
            self::SHIPPING_FEE_PLATFORM => 'shipping_fee_platform (平台代付運費)',
            self::PLATFORM_FEE => 'platform_fee (平台手續費)',
            self::ORDER_ADJUSTMENT => 'order_adjustment (帳款調整)',
            self::COMMISSION => 'commission (佣金)',
            
            self::TAX => 'tax_amount (銷項稅額)',
            
            self::PURCHASE_TOTAL => 'total_amount (採購總額)',
            self::PURCHASE_ITEMS_AMOUNT => 'items_amount (採購商品金額)',
            self::PURCHASE_TAX => 'purchase_tax_amount (進項稅額)',
            
            self::ITEMS_COST => 'items.sum:cost*quantity (商品成本)',
            
            self::SUBTOTAL_AFTER_DISCOUNT => 'subtotal_after_discount (折讓後收入)',
            self::TOTAL_FEES => 'total_fees (費用總額)',
            
            self::AMOUNT => 'amount (金額)',
        };
    }
    
    public function sourceType(): string
    {
        return match($this) {
            self::ITEMS_COST => 'items_sum',
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