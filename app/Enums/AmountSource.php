<?php

namespace App\Enums;

enum AmountSource: string
{
    /** 原始交易總額 (例如：售價 $1000，含所有費用/稅金/運費) */
    case TOTAL = 'total_amount';

    /** 商品淨價 (不含稅、不含運費、不含手續費) */
    case ITEMS = 'items_amount';

    /** 平台手續費/服務費 */
    case FEE = 'fee_amount';

    /** 運費 (買家付或賣家付) */
    case SHIPPING = 'shipping_amount';

    /** 實收淨額 (總額 - 手續費 - 折扣 - 調整等) */
    case NET = 'net_amount';
	
	/** 商業折扣 (銷售/採購折扣) */
    case DISCOUNT = 'discount_amount';

    /** 稅金 (增值稅/營業稅等) */
    case TAX = 'tax_amount';

    /** 佣金 (銷售/採購佣金) */
    case COMMISSION = 'commission_amount';

    /** 帳款調整 (正負調整) */
    case ADJUSTMENT = 'adjustment_amount';

    /** 商品成本 (加權平均成本) */
    case COST = 'cost_amount';

    /** 私人收支金額 */
    case PRIVATE = 'private_amount';

    /**
     * 取得會計準則對應的顯示名稱
     */
    public function label(): string
    {
        return match ($this) {
            self::TOTAL => '原始交易總額',
            self::ITEMS => '商品淨價',
            self::FEE => '平台手續費',
            self::SHIPPING => '運費',
            self::NET => '實收淨額',
            self::DISCOUNT => '商業折扣',
            self::TAX => '稅金',
            self::COMMISSION => '佣金',
            self::ADJUSTMENT => '帳款調整',
            self::COST => '商品成本',
            self::PRIVATE => '私人收支金額',
        };
    }
}