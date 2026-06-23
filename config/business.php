<?php
// config/business.php

return [

    // =========================================================================
    // 拆裝組合模組會計配置
    // =========================================================================
    
    'conversion' => [
        'wip_account' => '140509',
        'input_accounts' => [
            'disassembly' => '140509',
            'assembly'    => '140509',
            'default'     => '140509',
        ],
        'output_accounts' => [
            'pendant'      => '140501',
            'bracelet'     => '140502',
            'general'      => '140503',
            'earring'      => '140505',
            'ring'         => '140506',
            'semifinished' => '140509',
            'default'      => '140502',
        ],
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
		['value' => 'DYNAMIC:sale:settle',			'label' => '動態：應收/通路結算科目 (依通路與付款方式自動分流)'],	
        
        // ========== 退貨模組 (SalesReturn) ==========
        ['value' => 'DYNAMIC:sales_return:refund',       'label' => '動態：退貨退款科目 (Refund by Original Payment)'],
        ['value' => 'DYNAMIC:sales_return:restocking_fee', 'label' => '動態：退貨處理費 (Restocking Fee)'],
        ['value' => 'DYNAMIC:sales_return:shipping_fee',   'label' => '動態：退貨運費 (Return Shipping Fee, +/-)'],
        
        // ========== 採購模組 (Purchase) ==========
        ['value' => 'DYNAMIC:purchase:expense',      'label' => '動態：採購附加費 (Purchase Expense)'],
        
        // ========== 拆裝模組 (Conversion) ==========
        ['value' => 'DYNAMIC:conversion:output',     'label' => '動態：拆裝產出科目 (依成品類型)'],
    ],

    // 採購專用付款方式
    'purchase_methods' => [
        'wechat_pay'  => ['name' => '微信支付', 'icon' => 'o-chat-bubble-left-right', 'default_account' => '101207'],
        'cash_twd'    => ['name' => '現金-新台幣', 'icon' => 'o-banknotes', 'default_account' => '100101'],
        'bank_cathay' => ['name' => '國泰世華-新台幣', 'icon' => 'o-arrow-path', 'default_account' => '100201'],
        'china_ap'    => ['name' => '應付-大陸廠商 (賒帳/月結)', 'icon' => 'o-clock', 'default_account' => '220201'],
    ],

    // 銷售專用收款方式
    'sale_methods' => [
        'cash'        => ['name' => '現金', 'icon' => 'o-banknotes'],
        'shopee_pay'  => ['name' => '蝦皮錢包', 'icon' => 'o-shopping-bag'],
        'transfer'    => ['name' => '銀行轉帳', 'icon' => 'o-arrow-path'],
        'taiwan_pay'  => ['name' => '台灣Pay', 'icon' => 'o-wallet'],
        'credit_card' => ['name' => '信用卡', 'icon' => 'o-credit-card-outline'],
        'line_pay'    => ['name' => 'Line Pay', 'icon' => 'o-wallet'],
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
            'account_code'  => '500105',      // 買家運費收入（收入科目）
            'side'          => 'credit',
        ],
        
        'platform_coupon' => [
            'name'          => '平台優惠券',
            'target'        => 'revenue_adjustment',
            'operator'      => 'sub',
            'icon'          => 'o-ticket',
            'account_code'  => 'DYNAMIC:sale:revenue',
            'side'          => 'none',
        ],
		
		'tax_amount' => [
            'name'          => '銷項稅額',
            'target'        => 'customer',
            'operator'      => 'add',
            'icon'          => 'o-building-library',
            'account_code'  => '222103',      // 銷項稅額
            'side'          => 'credit',
        ],
		

        // ========== 同時影響買賣雙方 ==========
        'seller_discount' => [
            'name'          => '賣家折扣',
            'target'        => 'both',
            'operator'      => 'sub',
            'icon'          => 'o-tag',
            'account_code'  => '500110',      // 銷售折扣與折讓
            'side'          => 'debit',
        ],
        
        // 賣家相關（影響 final_net_amount）
        'shipping_fee_platform' => [
            'name'          => '平台代付運費',
            'target'        => 'seller',
            'operator'      => 'sub',
            'icon'          => 'o-paper-airplane',
            'account_code'  => '560106',      // 運費支出
            'side'          => 'debit',
        ],		
		
        'platform_fee' => [
            'name'          => '手續費',
            'target'        => 'seller',
            'operator'      => 'sub',
            'icon'          => 'o-calculator',
            'account_code'  => '560105',      // 支付手續費
            'side'          => 'debit',
        ],
        
        'commission' => [
            'name'          => '佣金',
            'target'        => 'seller',
            'operator'      => 'sub',
            'icon'          => 'o-user-group',
            'account_code'  => '560111',      // 佣金
            'side'          => 'debit',
        ],
        
        'order_adjustment' => [
            'name'          => '帳款調整',
            'target'        => 'seller',
            'operator'      => 'sub',
            'icon'          => 'o-adjustments-horizontal',
            'account_code'  => '560108',      // 帳款調整
            'side'          => 'debit',
        ],        
        
		'freight_amount' => [
			'name'          => '一般運費支出',
			'target'        => 'seller',
			'operator'      => 'sub',
			'icon'          => 'o-truck',
			'account_code'  => '560104',      // 一般物流費；運輸費
			'side'          => 'debit',
		],
		
    ],
    
    // 退貨費用類型
    'return_fee_types' => [
        'restocking_fee' => [
            'name' => '退貨處理費',
            'icon' => 'o-arrow-path',
        ],
        'return_shipping_fee' => [
            'name' => '退貨運費',
            'icon' => 'o-truck',
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
    // 🎯 會計科目對應（小企業會計準則）
    // =========================================================================
    'accounting_accounts' => [
        // 資產類 (1xxx)
        'assets' => [
            'cash'              => '100101',  // 新台幣現金
            'bank_twd'          => '100201',  // 國泰世華-新台幣
            'bank_cny'          => '100202',  // 銀行存款-人民幣
            'petty_cash'        => '100102',  // 零用金
        ],
        
        // 應收款項 - 依通路區分
        'receivables' => [
            'default'           => '112201',   // 應收帳款-一般客戶
            
            // 實體門市（收款方式多元）
            'retail' => [
                'cash'          => '100101',   // 門市現金
                'credit_card'   => '100201',   // 國泰世華
                'line_pay'      => '101203',   // LINE Pay → 銀行
                'taiwan_pay'    => '100201',   // 台灣Pay → 銀行
                'transfer'      => '100201',   // 銀行轉帳
                'default'       => '112201',   // 應收帳款-一般客戶
            ],
            
            // 蝦皮電商（賣家只看到蝦皮錢包入帳，不區分買家付款方式）
            'shopee' => [
                'default'       => '101202',   // 蝦皮錢包
                'shopee_pay'    => '101202',   // 蝦皮錢包
            ],
            
            // Facebook / 社群（買家直接付款給賣家，入銀行）
            'facebook' => [
                'default'       => '100201',   // 國泰世華-新台幣
                'line_pay'      => '101203',   // LINE Pay → 銀行
                'taiwan_pay'    => '100201',   // 台灣Pay → 銀行
                'transfer'      => '100201',   // 國泰世華
            ],
        ],
        
        // 存貨
        'inventory_accounts' => [
            'finished_goods_pendant' => '140501',
            'finished_goods_bracelet'=> '140502',
            'finished_goods_general' => '140503',
            'finished_goods_earring' => '140505',
            'finished_goods_ring'    => '140506',
            'semifinished_goods'     => '140509',
            'raw_materials'          => '140301',
        ],
        
        // 其他資產
        'prepayments'            => '1123',
        'fixed_assets'           => '1601',
        'accumulated_depreciation' => '1602',
            
        // 負債類 (2xxx)
        'liabilities' => [
            'tax_output'         => '222103',   // 銷項稅額
            'tax_input'          => '222104',   // 進項稅額
            'freight_income'     => '224101',   // 信用卡應付（代收款）
            'accounts_payable'   => '220202',   // 應付帳款-台灣廠商
            'accounts_payable_cn'=> '220201',   // 應付帳款-大陸廠商
        ],

        // 收入類 (5xxx) - 依通路區分
        'revenue' => [
            'retail' => [
                'code'          => '500101',
                'name'          => '門市零售收入',
            ],
            'shopee' => [
                'code'          => '500102',
                'name'          => '蝦皮電商收入',
            ],
            'facebook' => [
                'code'          => '500103',
                'name'          => '社群電商收入',
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
            'platform_fee'       => '560105',  // 支付手續費
            'platform_transaction_fee' => '560107', // 平台成交手續費
            'commission'         => '560111',  // 佣金支出
            'shipping_fee'       => '560106',  // 運費支出
            'discount'           => '500110',  // 銷售折扣與折讓
            'purchase_fee'       => '560201',  // 採購費用
            'bank_charge'        => '560101',  // 銀行手續費
            'other_expense'      => '560108',  // 其他費用
            'manufacturing_cost' => '560115',  // 加工費用
            'inventory_loss'     => '560116',  // 存貨盤損
            'coupon_expense'     => '560110',  // 銷售-雜項費用（優惠券用）
        ],
        
        // 其他收益類
        'other_income' => [
            'inventory_surplus'  => '590115',  // 盤盈收益
            'platform_subsidy'   => '630101',  // 平台補助收入
        ],
    ],

    // =========================================================================
    // 🎯 通路代碼對應
    // =========================================================================
    'channel_mapping' => [
        'retail'     => 'retail',
        'shopee'     => 'shopee',
        'facebook'   => 'facebook',
    ],

    // =========================================================================
    // 🎯 付款方式對應的科目
    // =========================================================================
    'payment_accounts' => [
        // 實體門市付款方式
        'cash'          => '100101',   // 現金
        'credit_card'   => '100201',   // 國泰世華
        'transfer'      => '100201',   // 銀行轉帳
        'taiwan_pay'    => '100201',   // 台灣Pay → 銀行
        'shopee_pay'    => '101202',   // 蝦皮錢包
        'line_pay'      => '100201',   // LINE Pay → 銀行
    ],
];