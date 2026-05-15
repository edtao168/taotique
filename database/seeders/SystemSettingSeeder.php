<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    // database/seeders/SystemSettingSeeder.php
	public function run(): void
	{
		$settings = [
			// 1️⃣ 進銷存核心 (core)
			['key' => 'allow_negative_stock', 'value' => false, 'group' => 'core', 'type' => 'boolean', 'description' => '允許負庫存出貨'],
			['key' => 'force_vendor_on_po', 'value' => true, 'group' => 'core', 'type' => 'boolean', 'description' => '採購必須綁定供應商'],
			
			// 2️⃣ 單據編碼 (numbering)
			['key' => 'po_prefix', 'value' => 'PO-', 'group' => 'numbering', 'type' => 'string', 'description' => '採購單前綴'],
			['key' => 'so_number_digits', 'value' => 5, 'group' => 'numbering', 'type' => 'number', 'description' => '流水號位數'],
			
			// 3️⃣ 安全性 (security)
			['key' => 'enable_audit_log', 'value' => true, 'group' => 'security', 'type' => 'boolean', 'description' => '記錄操作日誌'],
			['key' => 'session_timeout', 'value' => 30, 'group' => 'security', 'type' => 'number', 'description' => '閒置登出時間(分)'],
			
			// 4️⃣ 顯示 (display)
			['key' => 'per_page', 'value' => 25, 'group' => 'display', 'type' => 'number', 'description' => '每頁顯示筆數'],
			['key' => 'show_cost_fields', 'value' => false, 'group' => 'display', 'type' => 'boolean', 'description' => '顯示庫存成本'],
			
			// 5️⃣ 通知 (integration)
			['key' => 'stock_alert_enabled', 'value' => true, 'group' => 'integration', 'type' => 'boolean', 'description' => '啟用庫存低於安全量警報'],
		];

		foreach ($settings as $s) {
			\App\Models\Setting::updateOrCreate(['key' => $s['key']], $s);
		}
	}
}
