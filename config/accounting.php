<?php
// config/accounting.php

return [
    /*
    |--------------------------------------------------------------------------
    | 會計規則模板（同步到 accounting_rules 資料表）
    |--------------------------------------------------------------------------
    */
    'rules' => [
        // 現金銷售
        'sale_cash' => [
            'description' => '現金銷售',
            'lines' => [
                ['account_code' => '1101', 'entry_type' => 'debit', 'amount_source' => 'final_net_amount', 'ratio' => 1, 'sort_order' => 1],
                ['account_code' => '4101', 'entry_type' => 'credit', 'amount_source' => 'subtotal', 'ratio' => 1, 'sort_order' => 2],
            ],
        ],
        
        // 信用卡銷售（含手續費）
        'sale_credit_card' => [
            'description' => '信用卡銷售',
            'lines' => [
                ['account_code' => '1102', 'entry_type' => 'debit', 'amount_source' => 'final_net_amount', 'ratio' => 1, 'sort_order' => 1],
                ['account_code' => '6301', 'entry_type' => 'debit', 'amount_source' => 'platform_fee', 'ratio' => 1, 'sort_order' => 2],
                ['account_code' => '4101', 'entry_type' => 'credit', 'amount_source' => 'total_amount', 'ratio' => 1, 'sort_order' => 3],
            ],
        ],
        
        // 蝦皮銷售（蝦皮錢包）
        'sale_shopee_pay' => [
            'description' => '蝦皮銷售',
            'lines' => [
                ['account_code' => '1103', 'entry_type' => 'debit', 'amount_source' => 'final_net_amount', 'ratio' => 1, 'sort_order' => 1],
                ['account_code' => '6301', 'entry_type' => 'debit', 'amount_source' => 'platform_fee', 'ratio' => 1, 'sort_order' => 2],
                ['account_code' => '6402', 'entry_type' => 'debit', 'amount_source' => 'shipping_fee_platform', 'ratio' => 1, 'sort_order' => 3],
                ['account_code' => '4102', 'entry_type' => 'credit', 'amount_source' => 'total_amount', 'ratio' => 1, 'sort_order' => 4],
                ['account_code' => '4105', 'entry_type' => 'credit', 'amount_source' => 'shipping_fee_customer', 'ratio' => 1, 'sort_order' => 5],
            ],
        ],
        
        // 採購進貨
        'purchase_inventory' => [
            'description' => '採購進貨',
            'lines' => [
                ['account_code' => '1201', 'entry_type' => 'debit', 'amount_source' => 'total_amount', 'ratio' => 1, 'sort_order' => 1],
                ['account_code' => '2101', 'entry_type' => 'credit', 'amount_source' => 'total_amount', 'ratio' => 1, 'sort_order' => 2],
            ],
        ],
        
        // 租金費用
        'expense_rent' => [
            'description' => '租金支出',
            'lines' => [
                ['account_code' => '6101', 'entry_type' => 'debit', 'amount_source' => 'total_amount', 'ratio' => 1, 'sort_order' => 1],
                ['account_code' => '1101', 'entry_type' => 'credit', 'amount_source' => 'total_amount', 'ratio' => 1, 'sort_order' => 2],
            ],
        ],
        
        // 水電費
        'expense_utility' => [
            'description' => '水電費',
            'lines' => [
                ['account_code' => '6102', 'entry_type' => 'debit', 'amount_source' => 'total_amount', 'ratio' => 1, 'sort_order' => 1],
                ['account_code' => '1101', 'entry_type' => 'credit', 'amount_source' => 'total_amount', 'ratio' => 1, 'sort_order' => 2],
            ],
        ],
    ],
];