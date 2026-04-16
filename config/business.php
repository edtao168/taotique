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
        
	'fee_types' => [
		// --- 影響「買家支付總額 (customer_total)」的項目 ---
		'shipping_fee_customer' => [
			'name'     => '買家支付運費',
			'target'   => 'customer', // 影響買家
			'operator' => 'add',      // 加項：小計 + 運費
			'icon'     => 'o-truck',
		],
		'discount' => [
			'name'     => '賣場折扣',
			'target'   => 'customer', // 影響買家
			'operator' => 'sub',      // 減項：小計 - 折扣
			'icon'     => 'o-tag',
		],
		'platform_coupon' => [
			'name'     => '平台優惠券',
			'target'   => 'customer', 
			'operator' => 'sub',      
			'icon'     => 'o-ticket',
		],

		// --- 影響「賣家淨進帳 (final_net_amount)」的項目 ---
		'platform_fee' => [
			'name'     => '平台成交手續費',
			'target'   => 'seller',
			'operator' => 'sub',
			'icon'     => 'o-calculator',
		],
		'shipping_fee_platform' => [
			'name'     => '平台代付運費',
			'target'   => 'seller',
			'operator' => 'sub',      
			'icon'     => 'o-paper-airplane',
		],
		'order_adjustment' => [
			'name'     => '帳款調整',
			'target'   => 'seller',
			'operator' => 'add',
			'icon'     => 'o-adjustments-horizontal',
		],
		'commission' => [
			'name'     => '佣金',
			'target'   => 'seller',
			'operator' => 'sub',
			'icon'     => 'o-banknotes',
		],
	],

	'currencies' => [
		'TWD' => ['symbol' => 'NT$', 'name' => '新台幣', 'precision' => 0, 'default_rate' => '1.0000'],
		'CNY' => ['symbol' => '¥', 'name' => '人民幣', 'precision' => 2, 'default_rate' => '4.5200'],
		'HKD' => ['symbol' => 'HK$', 'name' => '港幣', 'precision' => 2, 'default_rate' => '4.1500'],
		'USD' => ['symbol' => '$', 'name' => '美元', 'precision' => 4, 'base_rate' => '32.1500'],
	],
	
	'backup' => [
        'disk' => env('BACKUP_DISK', 'local'),
        'path' => env('BACKUP_PATH', 'taotique-backup'),
    ],
];