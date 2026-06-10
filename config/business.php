<?php
// config/business.php
// 標註檔案路徑：config/business.php

return [

	// config/business.php

	'amount_sources' => [
		// ========== 通用 ==========
		'subtotal' => '商品小計 (subtotal)',
		'total_amount' => '總金額 (total_amount)',
		'tax_amount' => '稅額 (tax_amount)',
		'freight_amount' => '運費 (freight_amount)',
		
		// ========== 銷售模組 ==========
		'customer_total' => '買家實付總額 (customer_total)',
		'final_net_amount' => '賣家最終實收 (final_net_amount)',
		'subtotal_after_discount' => '折讓後商品淨額 (subtotal_after_discount)',
		'cost_amount' => '銷貨成本總計 (cost_amount)',
		
		// 費用類型（對應 fee_types）
		'shipping_fee_customer' => '買家運費 (shipping_fee_customer)',
		'seller_discount' => '賣家折扣 (seller_discount)',
		'platform_coupon' => '平台優惠券 (platform_coupon)',
		'shipping_fee_platform' => '平台運費 (shipping_fee_platform)',
		'platform_fee' => '平台手續費 (platform_fee)',
		'commission' => '佣金 (commission)',
		
		// ========== 退貨模組 ==========
		'return_total' => '退貨退款總額 (return_total)',
		'return_cost' => '退貨成本-原幣 (return_cost)',
		'return_cost_base' => '退貨成本-本幣 (return_cost_base)',
		'restocking_fee' => '退貨處理費 (restocking_fee)',
		'return_shipping_fee' => '退貨運費 (return_shipping_fee)',
		
		// ========== 採購模組 ==========
		'purchase_base_total' => '採購本幣總額 (purchase_base_total)',
		'purchase_base_items' => '採購本幣商品額 (purchase_base_items)',
		'purchase_base_tax' => '採購本幣稅額 (purchase_base_tax)',
		'purchase_base_shipping' => '採購本幣運費 (purchase_base_shipping)',
		'purchase_base_other_fees' => '採購本幣其他費用 (purchase_base_other_fees)',
	],

    // =========================================================================
    // 會計全動態科目策略定義 (供後台 Mary UI 下拉選單使用)
    // =========================================================================
'accounting_dynamic_options' => [
    // ========== 共用動態科目 ==========
    ['value' => 'DYNAMIC:auto:inventory',       'label' => '動態：庫存商品 (Inventory)'],
    ['value' => 'DYNAMIC:auto:cost',            'label' => '動態：銷貨成本 (Cost of Goods Sold)'],
    
    // ========== 銷售模組 (Sale) ==========
    ['value' => 'DYNAMIC:sale:revenue',         'label' => '動態：銷貨收入 (Sales Revenue)'],
    ['value' => 'DYNAMIC:sale:payment',         'label' => '動態：應收帳款/代收款 (Receivables by Payment)'], 
    ['value' => 'DYNAMIC:sale:channel_fee',     'label' => '動態：通路手續費 (Channel Fee)'],
    ['value' => 'DYNAMIC:sale:discount',        'label' => '動態：銷貨折讓 (Sales Discount)'],
    
    // ========== 退貨模組 (SalesReturn) ==========
    ['value' => 'DYNAMIC:sales_return:refund',       'label' => '動態：退貨退款科目 (Refund by Original Payment)'],
    ['value' => 'DYNAMIC:sales_return:restocking_fee', 'label' => '動態：退貨處理費 (Restocking Fee)'],
    ['value' => 'DYNAMIC:sales_return:shipping_fee',   'label' => '動態：退貨運費 (Return Shipping Fee, +/-)'],
    
    // ========== 採購模組 (Purchase) ==========
    ['value' => 'DYNAMIC:purchase:expense',      'label' => '動態：採購附加費 (Purchase Expense)'],
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
			'icon' => 'o-arrow-path',
			// 正數：賣家支付	// 負數：買家支付
		],
		'return_shipping_fee' => [
			'name' => '退貨運費',
			'icon' => 'o-truck',
			// 正數：賣家支付	// 負數：買家支付
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