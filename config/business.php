<?php
// config/business.php
// 標註檔案路徑：config/business.php

return [

    // =========================================================================
    // 會計全動態科目策略定義 (供後台 Mary UI 下拉選單使用)
    // =========================================================================
    'accounting_dynamic_options' => [
        ['value' => 'DYNAMIC:auto:inventory',       'label' => '動態：庫存商品 (依商品類別)'],
        ['value' => 'DYNAMIC:sale:revenue',         'label' => '動態：銷貨收入 (依銷售通路)'],
        ['value' => 'DYNAMIC:sale:payment',         'label' => '動態：應收帳款/代收款 (依付款管道)'], 
        ['value' => 'DYNAMIC:sale:cost',            'label' => '動態：銷貨成本 (依商品類別自動結轉)'],
        ['value' => 'DYNAMIC:sale:channel_fee',     'label' => '動態：通路摩擦手續費 (依銷售通路如蝦皮)'],
        ['value' => 'DYNAMIC:sale:return_fee:shipping', 'label' => '動態：退貨運費支出 (依銷售通路規範)'],
        ['value' => 'DYNAMIC:purchase:expense',     'label' => '動態：進口/採購附加費 (依費用項目如關稅)'],
        ['value' => 'DYNAMIC:sale:discount',        'label' => '動態：銷貨折讓/扣抵 (依活動或通路折價券)'],
    ],

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
		// 買家相關（影響 customer_total）
		'shipping_fee_customer' => [
			'name'          => '買家支付運費',
			'target'        => 'customer',
			'operator'      => 'add',
			'icon'          => 'o-truck',
			'account_code'  => '224101',      // 其他應付款
			'side'          => 'credit',
		],
		
		'platform_coupon' => [
			'name'          => '平台優惠券',
			'target'        => 'customer',
			'operator'      => 'sub',
			'icon'          => 'o-ticket',
			'account_code'  => '112202',    // 應收帳款-平台補貼
			'side'          => 'debit',
		],

		// ========== 同時影響買賣雙方 ==========
		'seller_discount' => [
			'name'          => '賣家折扣',
			'target'        => 'both',       // 同時影響買賣雙方
			'operator'      => 'sub',
			'icon'          => 'o-tag',
			'account_code'  => '500110',    // 銷貨折讓
			'side'          => 'debit',
		],
		
		// 賣家相關（影響 final_net_amount）
		'shipping_fee_platform' => [
			'name'          => '平台代付運費',
			'target'        => 'seller',
			'operator'      => 'sub',
			'icon'          => 'o-paper-airplane',
			'account_code'  => '560106',    // 運輸費
			'side'          => 'debit',
		],		
		
		'platform_fee' => [
			'name'          => '手續費',
			'target'        => 'seller',
			'operator'      => 'sub',
			'icon'          => 'o-calculator',
			'account_code'  => '560105',
			'side'          => 'debit',
		],
		
		'commission' => [
			'name'          => '佣金',
			'target'        => 'seller',
			'operator'      => 'sub',
			'icon'          => 'o-user-group',
			'account_code'  => '560111',
			'side'          => 'debit',
		],
		
		'order_adjustment' => [
			'name'          => '帳款調整',
			'target'        => 'seller',
			'operator'      => 'sub',
			'icon'          => 'o-adjustments-horizontal',
			'account_code'  => '560108',
			'side'          => 'debit',
		],
		
		'tax' => [
			'name'          => '銷項稅額',
			'target'        => 'seller',
			'operator'      => 'sub',
			'icon'          => 'o-building-library',
			'account_code'  => '222103',
			'side'          => 'credit',
		],		
	],
	
	// 退貨費用類型
    'return_fee_types' => [
		'restocking_fee' => [
			'name' => '退貨處理費',
			'target' => 'customer',  // 買家負擔，從退款中扣除
			'operator' => 'subtract',
		],
		'return_shipping_fee' => [
			'name' => '退貨運費',
			'target' => 'seller',    // 賣家負擔，額外支出
			'operator' => 'add',
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