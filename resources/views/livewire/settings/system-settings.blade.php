<div>
    <x-card title="系統參數設定" shadow separator>
        {{-- 使用 wire:submit 確保 actions 區塊的按鈕能觸發 save --}}
        <x-form wire:submit="save">
            <x-tabs selected="core-tab">
                {{-- 1. 核心流程 --}}
                <x-tab name="core-tab" label="核心流程" icon="o-cpu-chip">
                    <div class="grid gap-6 py-4">
                        <div class="space-y-2">
                            <x-checkbox label="允許負庫存出貨" 
                                hint="開啟後允許庫存為負數時仍可出貨"
                                wire:model="payload.allow_negative_stock" />
                            
                            <x-checkbox label="強制綁定供應商" 
                                hint="採購單必須選擇供應商"
                                wire:model="payload.force_vendor_on_po" />
                        </div>

                        <hr />

                        <div class="space-y-2">
                            <x-checkbox label="採購單：直接入庫 (預設)" 
                                hint="關閉則需手動執行「確認入庫」"
                                wire:model="payload.po_auto_stock_in" />
                            
                            <x-checkbox label="銷售單：直接出庫 (預設)" 
                                hint="關閉則需手動執行「確認出庫」"
                                wire:model="payload.so_auto_stock_out" />
                        </div>
                    </div>
                </x-tab>

                {{-- 2. 編碼規則 --}}
                <x-tab name="num-tab" label="單據編碼" icon="o-hashtag">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 py-4">
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
                </x-tab>			
                
                {{-- 3. 財務設定 --}}
                <x-tab name="tab-finance" label="財務設定" icon="o-currency-dollar">
                    <div class="grid gap-4 py-4">
                        <x-input label="營業稅率 (%)" 
                            wire:model="payload.tax_rate" 
                            type="number" 
                            step="0.1"
                            min="0"
                            max="100"
                            suffix="%" />
                        
                        {{-- 從 business.php 的 currencies 設定讀取幣別選項 --}}
                        @php
                            $currencies = [];
                            if (config('business.currencies')) {
                                foreach (config('business.currencies') as $code => $info) {
                                    $currencies[] = [
                                        'id' => $code, 
                                        'name' => $info['symbol'] . ' - ' . $info['name']
                                    ];
                                }
                            } else {
                                // 備用預設值
                                $currencies = [
                                    ['id' => 'TWD', 'name' => 'NT$ - 新台幣'],
                                    ['id' => 'USD', 'name' => '$ - 美元'],
                                    ['id' => 'CNY', 'name' => '¥ - 人民幣'],
                                    ['id' => 'HKD', 'name' => 'HK$ - 港幣'],
                                ];
                            }
                        @endphp
                        <x-choices label="預設幣別" 
                            wire:model="payload.base_currency" 
                            :options="$currencies" 
                            single 
                            hint="系統預設使用的交易幣別" />
                    </div>
                </x-tab>

                {{-- 4. 顯示設定 --}}
                <x-tab name="tab-display" label="顯示設定" icon="o-eye">
                    <div class="grid gap-4 py-4">
                        <div class="space-y-2">
                            <x-checkbox label="顯示庫存成本" 
                                hint="開啟後在庫存列表顯示成本金額"
                                wire:model="payload.show_cost_fields" />
                        </div>

                        <x-input label="每頁顯示筆數" 
                            wire:model="payload.per_page" 
                            type="number" 
                            min="5"
                            max="200"
                            step="5"
                            hint="列表頁面每頁顯示的資料筆數" />
                    </div>
                </x-tab>

                {{-- 5. 安全性設定 --}}
                <x-tab name="tab-security" label="安全性設定" icon="o-shield-check">
                    <div class="grid gap-4 py-4">
                        <div class="space-y-2">
                            <x-checkbox label="記錄操作日誌" 
                                hint="記錄所有使用者的操作行為"
                                wire:model="payload.enable_audit_log" />
                        </div>

                        <x-input label="閒置登出時間" 
                            wire:model="payload.session_timeout" 
                            type="number" 
                            min="1"
                            max="480"
                            suffix="分鐘"
                            hint="使用者閒置超過此時間將自動登出" />
                    </div>
                </x-tab>

                {{-- 6. 整合設定 --}}
                <x-tab name="tab-integration" label="整合設定" icon="o-link">
                    <div class="grid gap-4 py-4">
                        <div class="space-y-2">
                            <x-checkbox label="啟用庫存低於安全量警報" 
                                hint="當庫存量低於安全庫存時發出警報通知"
                                wire:model="payload.stock_alert_enabled" />
                        </div>
                    </div>
                </x-tab>
            </x-tabs>

            <x-slot:actions>
                <x-button label="回首頁" icon="o-home" :link="route('dashboard')" />
                <x-button label="重置" icon="o-arrow-path" wire:click="mount" />
                <x-button label="儲存設定" icon="o-check" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
    </x-card>
</div>