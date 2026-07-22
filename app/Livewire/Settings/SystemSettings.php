<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use Livewire\Component;
use Mary\Traits\Toast;

class SystemSettings extends Component
{
    use Toast;

    public array $payload = [];
	
	public $tenant_name;
	public $tenant_tax_id;
	public $tenant_phone;
	public $tenant_address;

    public function mount()
    {
        $tenant = auth()->user()->tenant;
		$this->tenant_name = $tenant->name ?? '';
		$this->tenant_tax_id = $tenant->tax_id ?? '';
		$this->tenant_phone = $tenant->phone ?? '';
		$this->tenant_address = $tenant->address ?? '';
	
		// 使用新的 get 方法，自動處理解碼
        $defaults = [
            // 核心流程 (core)
            'allow_negative_stock' => false,
            'force_vendor_on_po'   => true,
            'po_auto_stock_in'     => true,
            'so_auto_stock_out'    => true,
            
            // 編碼規則 (numbering)
            'po_prefix'            => 'PO-',
            'so_prefix'            => 'SO-',
            'pr_prefix'            => 'PR-',
            'sr_prefix'            => 'SR-',
            'ic_prefix'            => 'IC-',
            'number_digits'        => 5,
            
            // 財務設定
            'tax_rate'             => 5,
            'base_currency'        => 'TWD',
            
            // 顯示設定 (display)
            'per_page'             => 25,
            'show_cost_fields'     => false,
            
            // 安全性設定 (security)
            'session_timeout'      => 30,
            'enable_audit_log'     => true,
            
            // 整合設定 (integration)
            'stock_alert_enabled'  => true,
        ];
        
        // 從資料庫讀取實際值
        $settings = [];
        foreach (array_keys($defaults) as $key) {
            $settings[$key] = Setting::get($key, $defaults[$key]);
        }
        
        $this->payload = $settings;
    }

    public function save()
    {
        $tenant = auth()->user()->tenant;
		$tenant->update([
			'name' => $this->tenant_name,
			'tax_id' => $this->tenant_tax_id,
			'phone' => $this->tenant_phone,
			'address' => $this->tenant_address,
		]);
		foreach ($this->payload as $key => $value) {
            Setting::updateValue($key, $value);
        }
        
        $this->success('系統參數已儲存');
    }

    public function render()
    {
        return view('livewire.settings.system-settings');
    }
}