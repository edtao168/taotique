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
	
	    // =========================================================================
    // 🎯 會計科目對應（中小企業會計準則 - 台灣版）
    // =========================================================================
    'accounting_accounts' => [
        // 資產類 (1xxx)
        'assets' => [
            'cash'              => '100101',  // 門市現金
            'bank_twd'          => '100201',  // 銀行存款-台幣
            'bank_cny'          => '100202',  // 銀行存款-人民幣
            'petty_cash'        => '100102',  // 零用金
        ],
        
        // 應收款項 (11xx)
        'receivables' => [
            'default'           => '1131',    // 應收帳款（預設）
            
            // 實體門市
            'retail' => [
                'cash'          => '100101',  // 門市現金
                'credit_card'   => '113102',  // 應收帳款-信用卡
                'line_pay'      => '113103',  // 應收帳款-LINE Pay
                'taiwan_pay'    => '113105',  // 應收帳款-台灣Pay
                'transfer'      => '113104',  // 應收帳款-銀行轉帳
            ],
            
            // 蝦皮電商
            'shopee' => [
                'default'       => '113201',  // 蝦皮錢包（應收帳款-蝦皮）
                'shopee_pay'    => '113201',  // 蝦皮錢包
                'credit_card'   => '113202',  // 應收帳款-蝦皮信用卡
                'transfer'      => '113203',  // 應收帳款-蝦皮轉帳
            ],
            
            // Facebook / Reel
            'facebook' => [
                'default'       => '113301',  // 應收帳款-FB
                'fb_pay'        => '113301',  // FB Pay
                'credit_card'   => '113302',  // 應收帳款-FB信用卡
                'transfer'      => '113303',  // 應收帳款-FB轉帳
            ],
        ],
        
        // 收入類 (5xxx) - 依通路區分
        'revenue' => [
            'retail' => [
                'code'          => '500101',
                'name'          => '門市零售收入',
            ],
            'shopee' => [
                'code'          => '500102',
                'name'          => '電商平台收入-蝦皮',
            ],
            'facebook' => [
                'code'          => '500103',
                'name'          => '社群電商收入-FB',
            ],
            'default'           => '500101',
        ],
        
        // 成本類 (5xxx)
        'cost' => [
            'cost_of_goods_sold' => '5401',    // 主營業務成本
            'inventory'          => '1405',    // 庫存商品
            'freight_in'         => '540101',  // 進貨運費
        ],
        
        // 費用類 (56xx)
        'expenses' => [
            'platform_fee'       => '560105',  // 平台手續費-蝦皮
            'commission'         => '560111',  // 佣金支出
            'shipping_fee'       => '560106',  // 運費支出
            'discount'           => '500110',  // 銷售折扣與折讓
            'purchase_fee'       => '560201',  // 採購費用
            'bank_charge'        => '560101',  // 銀行手續費
            'other_expense'      => '560108',  // 其他費用
        ],
        
        // 負債類 (2xxx)
        'liabilities' => [
            'tax_output'         => '222103',  // 銷項稅額（營業稅）
            'freight_income'     => '224101',  // 其他應付款-代收運費
            'accounts_payable'   => '220201',  // 應付帳款
            'accounts_payable_cn'=> '220202',  // 應付帳款-大陸廠商
        ],
    ],

    // =========================================================================
    // 🎯 通路代碼對應（用於解析動態科目）
    // =========================================================================
    'channel_mapping' => [
        'retail'     => 'retail',      // 實體門市
        'shopee'     => 'shopee',      // 蝦皮
        'facebook'   => 'facebook',    // Facebook        
    ],

    // =========================================================================
    // 🎯 付款方式對應的科目（快速查詢）
    // =========================================================================
    'payment_accounts' => [
        // 實體門市付款方式
        'cash'          => '100101',   // 現金
        'credit_card'   => '100201',   // 國泰世華-新台幣
        'transfer'      => '100201',   // 銀行轉帳
		'taiwan_pay'    => '101201',   // 台灣Pay
        'shopee_pay'    => '101202',   // 蝦皮錢包
		'line_pay'      => '101203',   // LINE Pay
    ],
];