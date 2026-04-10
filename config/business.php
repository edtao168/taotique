<?php

return [
    'payment_methods' => [
        ['id' => 'cash', 'name' => '現金', 'icon' => 'o-banknotes'],
        ['id' => 'shopee_pay', 'name' => '蝦皮錢包', 'icon' => 'o-shopping-bag'],
		['id' => 'transfer', 'name' => '銀行轉帳', 'icon' => 'o-arrow-path'],
		['id' => 'taiwan_pay', 'name' => '台灣Pay', 'icon' => 'o-wallet'],
		['id' => 'credit_card', 'name' => '信用卡', 'icon' => 'o-credit-card-outline'],
		['id' => 'line_pay', 'name' => 'Line Pay', 'icon' => 'o-wallet'],
        
    ],
    
    'return_fee_types' => [
    // 買家=(Subtotal + shipping_fee_customer - Discount - platform_coupon)
    ['id' => 'shipping_fee_customer', 'name' => '買家支付運費',	'type' => 'addition', 	 'target' => 'customer', 'is_seller_cost' => false],
    ['id' => 'shop_discount',		  'name' => '賣家折扣',		'type' => 'subtraction', 'target' => 'customer', 'is_seller_cost' => true],
    ['id' => 'platform_coupon', 	  'name' => '平台優惠券',		'type' => 'subtraction', 'target' => 'customer', 'is_seller_cost' => false],
    
    // 賣家=(Subtotal - Platform_Fee - Shipping_Platform - Discount)
    ['id' => 'shipping_fee_platform', 'name' => '平台代付運費',	'type' => 'subtraction', 'target' => 'platform', 'is_seller_cost' => true],
	['id' => 'platform_fee', 		  'name' => '平台成交手續費',	'type' => 'subtraction', 'target' => 'platform', 'is_seller_cost' => true],    
    ['id' => 'payment_processing', 	  'name' => '金流服務費',		'type' => 'subtraction', 'target' => 'platform', 'is_seller_cost' => true],
],
	
	'currencies' => [
        'TWD' => ['symbol' => 'NT$', 'name' => '新台幣', 'precision' => 0],
        'CNY' => ['symbol' => '¥', 'name' => '人民幣', 'precision' => 2],		
        'HKD' => ['symbol' => 'HK$', 'name' => '港幣', 'precision' => 2],
		'USD' => ['symbol' => '$', 'name' => '美元', 'precision' => 2],
    ],
	
	'backup' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => env('BACKUP_PATH', 'taotique-backup'),
    ],
];