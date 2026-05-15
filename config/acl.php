<?php //ACL (Access Control List，存取控制列表)
// config/acl.php
return [
    'roles' => [
        'owner' => [
            'view_cost',       // 看成本（本幣/外幣）
            'manage_inventory', // 調整庫存
            'manage_users',    // 管理員工
            'view_reports',    // 看 Chart.js 報表
            'system_settings', // 修改匯率與參數
        ],
        'manager' => [
            'view_cost',
            'manage_inventory',
            'view_reports',
        ],
        'staff' => [
            'create_order',    // 只能開單
            'view_inventory',  // 只能看庫存數量
            // 'view_cost' => 不給予
        ],
		'guest' => [            
            'view_inventory',            
        ],
    ],
];