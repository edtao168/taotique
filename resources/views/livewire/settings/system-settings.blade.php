<div>
    <x-card title="系統參數設定" shadow separator>
        <x-form wire:submit="save">
            
            {{-- 公司資訊 --}}
            <x-card title="公司資訊" subtitle="Company Info" shadow class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="公司名稱" 
                        wire:model="tenant_name" 
                        placeholder="請輸入公司名稱"
                        hint="此名稱將顯示在側邊欄、報表抬頭等位置" />
                    
                    <x-input label="公司統編" 
                        wire:model="tenant_tax_id" 
                        placeholder="請輸入統一編號" />
                    
                    <x-input label="公司電話" 
                        wire:model="tenant_phone" 
                        placeholder="請輸入公司電話" />
                    
                    <x-input label="公司地址" 
                        wire:model="tenant_address" 
                        placeholder="請輸入公司地址" />
                </div>
            </x-card>

            {{-- 核心流程 --}}
            <x-card title="核心流程" subtitle="Core Settings" shadow class="mb-4">
                <div class="grid gap-3">
                    <x-checkbox label="允許負庫存出貨" 
                        hint="開啟後允許庫存為負數時仍可出貨"
                        wire:model="payload.allow_negative_stock" />
                    
                    <x-checkbox label="強制綁定供應商" 
                        hint="採購單必須選擇供應商"
                        wire:model="payload.force_vendor_on_po" />
                    
                    <hr />
                    
                    <x-checkbox label="採購單：直接入庫 (預設)" 
                        hint="關閉則需手動執行「確認入庫」"
                        wire:model="payload.po_auto_stock_in" />
                    
                    <x-checkbox label="銷售單：直接出庫 (預設)" 
                        hint="關閉則需手動執行「確認出庫」"
                        wire:model="payload.so_auto_stock_out" />
                </div>
            </x-card>

            {{-- 單據編碼 --}}
            <x-card title="單據編碼" subtitle="Numbering Rules" shadow class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="採購單前綴" 
                        wire:model="payload.po_prefix" 
                        placeholder="例如: PO-" />
                    
                    <x-input label="採購退回單前綴" 
                        wire:model="payload.pr_prefix" 
                        placeholder="例如: PR-" />
                    
                    <x-input label="銷售單前綴" 
                        wire:model="payload.so_prefix" 
                        placeholder="例如: SO-" />
                    
                    <x-input label="銷售退回單前綴" 
                        wire:model="payload.sr_prefix" 
                        placeholder="例如: SR-" />
                    
                    <x-input label="拆裝組合單前綴" 
                        wire:model="payload.ic_prefix" 
                        placeholder="例如: IC-" />
                    
                    <x-input label="流水號位數" 
                        wire:model="payload.number_digits" 
                        type="number" 
                        min="1"
                        max="10"
                        hint="各類單據流水號碼位數" />
                </div>
            </x-card>

            {{-- 財務設定 --}}
            <x-card title="財務設定" subtitle="Finance" shadow class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-input label="營業稅率 (%)" 
                        wire:model="payload.tax_rate" 
                        type="number" 
                        step="0.1"
                        min="0"
                        max="100"
                        suffix="%" />
                    
                    @php
                        $currencies = config('business.currencies', [
                            'TWD' => ['symbol' => 'NT$', 'name' => '新台幣'],
                            'USD' => ['symbol' => '$', 'name' => '美元'],
                            'CNY' => ['symbol' => '¥', 'name' => '人民幣'],
                            'HKD' => ['symbol' => 'HK$', 'name' => '港幣'],
                        ]);
                        $currencyOptions = [];
                        foreach ($currencies as $code => $info) {
                            $currencyOptions[] = ['id' => $code, 'name' => $info['symbol'] . ' - ' . $info['name']];
                        }
                    @endphp
                    <x-choices label="預設幣別" 
                        wire:model="payload.base_currency" 
                        :options="$currencyOptions" 
                        single 
                        hint="系統預設使用的交易幣別" />
                </div>
            </x-card>

            {{-- 顯示設定 --}}
            <x-card title="顯示設定" subtitle="Display" shadow class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-checkbox label="顯示庫存成本" 
                        hint="開啟後在庫存列表顯示成本金額"
                        wire:model="payload.show_cost_fields" />

                    <x-input label="每頁顯示筆數" 
                        wire:model="payload.per_page" 
                        type="number" 
                        min="5"
                        max="200"
                        step="5"
                        hint="列表頁面每頁顯示的資料筆數" />
                </div>
            </x-card>

            {{-- 安全性設定 --}}
            <x-card title="安全性設定" subtitle="Security" shadow class="mb-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <x-checkbox label="記錄操作日誌" 
                        hint="記錄所有使用者的操作行為"
                        wire:model="payload.enable_audit_log" />

                    <x-input label="閒置登出時間" 
                        wire:model="payload.session_timeout" 
                        type="number" 
                        min="1"
                        max="480"
                        suffix="分鐘"
                        hint="使用者閒置超過此時間將自動登出" />
                </div>
            </x-card>

            {{-- 整合設定 --}}
            <x-card title="整合設定" subtitle="Integration" shadow class="mb-4">
                <x-checkbox label="啟用庫存低於安全量警報" 
                    hint="當庫存量低於安全庫存時發出警報通知"
                    wire:model="payload.stock_alert_enabled" />
            </x-card>

            {{-- 操作按鈕 --}}
            <x-slot:actions>
                <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
                <x-button label="重置" icon="o-arrow-path" wire:click="mount" />
                <x-button label="儲存設定" icon="o-check" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
            
        </x-form>
    </x-card>
</div>