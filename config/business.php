<?php

return [
	// 🎯 採購專用付款方式（新增微信支付，將預設應付帳款語意調整為大陸廠商應付）
    'purchase_methods' => [
        'wechat_pay'  => ['name' => '微信支付', 'icon' => 'o-chat-bubble-left-right', 'default_account' => '101207'],
        'cash_twd'    => ['name' => '現金-新台幣', 'icon' => 'o-banknotes', 'default_account' => '100101'],
        'bank_cathay' => ['name' => '國泰世華-新台幣', 'icon' => 'o-arrow-path', 'default_account' => '100201'],
        'china_ap'    => ['name' => '應付-大陸廠商 (賒帳/月結)', 'icon' => 'o-clock', 'default_account' => '220201'],
    ],

    // 🎯 銷售專用收款方式（保持台灣通路多元零售特性）
    'sale_methods' => [
        'cash'        => ['name' => '現金', 'icon' => 'o-banknotes'],
        'shopee_pay'  => ['name' => '蝦皮錢包', 'icon' => 'o-shopping-bag'],
        'transfer'    => ['name' => '銀行轉帳', 'icon' => 'o-arrow-path'],
        'taiwan_pay'  => ['name' => '台灣Pay', 'icon' => 'o-wallet'],
        'credit_card' => ['name' => '信用卡', 'icon' => 'o-credit-card-outline'],
        'line_pay'    => ['name' => 'Line Pay', 'icon' => 'o-wallet'],
    ],

    // 🎯 會計自動結轉核心規則矩陣
    'accounting_rules' => [
        'purchase_inbound' => [
            'debit_code' => '1405', // 借方固定：1405 庫存商品
        ]
    ],
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
		'shipping_fee_platform' => [
			'name'     => '平台代付運費',
			'target'   => 'seller',
			'operator' => 'sub',      
			'icon'     => 'o-paper-airplane',
		],
		'platform_fee' => [
			'name'     => '平台成交手續費',
			'target'   => 'seller',
			'operator' => 'sub',
			'icon'     => 'o-calculator',
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
	
	// 退貨費用類型
    'return_fee_types' => [
        'shipping_fee' => [
			'name' 	   => '退貨運費（買家付為正數，賣家付為負數）',
			'operator' => 'add'
		],
		'restocking_fee' => [
			'name' 	   => '整新費',
			'operator' => 'sub',
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
	
	'media' => [
		'video_extensions' => ['mp4', 'mov', 'avi', 'webm', 'wmv', 'asf'],
		'media_mimetypes'  => 'image/jpg,image/jpeg,image/png,image/webp,image/avif,video/mp4,video/quicktime,video/x-msvideo,video/webm,video/x-ms-wmv,video/x-ms-asf',
        'media_max_kb'     => 20480,
	],
];