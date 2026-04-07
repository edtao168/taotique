<?php

return [
    'payment_methods' => [
        ['id' => 'cash', 'name' => '現金', 'icon' => 'o-banknotes'],
        ['id' => 'shopee_pay', 'name' => '蝦皮錢包', 'icon' => 'o-shopping-bag'],
		['id' => 'transfer', 'name' => '銀行轉帳', 'icon' => 'o-arrow-path'],
		['id' => 'taiwan_pay', 'name' => '台灣Pay', 'icon' => 'o-wallet'],
		['id' => 'credit_card', 'name' => '信用卡', 'icon' => 'o-credit-card-outline'],
        
    ],
    
    'return_fee_types' => [
        ['id' => 'shipping_fee_customer', 'name' => '買家支付運費'],
        ['id' => 'platform_fee', 'name' => '平台成交手續費'],
        ['id' => 'payment_processing', 'name' => '金流服務費'],
        ['id' => 'other_service_fee', 'name' => '其它服務費'],
    ],
];