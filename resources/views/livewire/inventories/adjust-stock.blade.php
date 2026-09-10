{{-- [路徑]: resources/views/livewire/inventories/adjust-stock.blade.php --}}

<div>
    <x-header title="手動庫存調整" subtitle="處理期初庫存導入、公關贈送、商品報廢等非常規單據異動" separator>
        <x-slot:actions>
            <x-button label="檢視異動流水帳" icon="o-clock" link="{{ route('inventories.movements') }}" class="btn-outline btn-sm" />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- 左側表單 --}}
        <div class="lg:col-span-2">
            <x-card title="單據資料" shadow class="bg-base-100">
                <x-form wire:submit="save">
                    {{-- 倉庫與類型 --}}
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <x-select 
                            label="目標倉庫" 
                            icon="o-building-office"
                            wire:model="warehouse_id" 
                            :options="$warehouses" 
                            option-label="name"
                            option-value="id"
                            placeholder="請選擇倉庫" 
                            required
                        />

                        <x-select 
                            label="異動類型" 
                            icon="o-tag"
                            wire:model="type" 
                            :options="$typeOptions" 
                            option-label="name"
                            option-value="id"
                            required
                        />
                    </div>

                    {{-- 選擇商品與數量：修正 Grid 欄位與 Margin，確保 Label 與控制項水平垂直對齊 --}}
                    <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-start">
                        
                        {{-- 商品選擇區塊 --}}
                        <div class="sm:col-span-6 pt-1">  
							<x-product-picker 
								name="product_id" 
								:options="$productOptions" 
								:selected-name="$product_name"
								search-property="productSearch"
							/>
                        </div>

                        {{-- 異動數量 --}}
                        <div class="sm:col-span-6">
                            <x-input 
                                label="異動數量" 
                                icon="o-hashtag"
                                wire:model="quantity" 
                                type="number" 
                                min="0.0001" 
                                step="any"
                                class="font-mono text-sm"
                                required
                            />
                        </div>
                    </div>

                    {{-- 備註 --}}
                    <x-textarea 
                        label="異動備註 / 原因" 
                        wire:model="remark" 
                        placeholder="例：期初庫存轉入、贈送 VIP 生日禮物等" 
                        rows="3" 
                        class="mt-2"
                    />

                    {{-- 提示區域：合規性與過帳通知 --}}
                    <x-alert icon="o-exclamation-triangle" class="alert-warning shadow-sm border border-warning/30">
                        <div>
                            <span class="font-bold block">作業提示（符合《小企業會計準則》）</span>
                            提交後系統將自動建立 <span class="font-mono font-bold">InventoryAdjustment</span> 單據憑證，實時更新實體庫存 (<span class="font-mono text-xs">Inventory</span>)、寫入流水帳 (<span class="font-mono text-xs">InventoryMovement</span>)，並自動過帳生成會計日記帳傳票。
                        </div>
                    </x-alert>

                    <x-slot:actions>
                        <x-button label="取消" link="{{ route('inventories.index') }}" />
                        <x-button label="確認執行調整" icon="o-check" class="btn-primary" type="submit" spinner="save" />
                    </x-slot:actions>
                </x-form>
            </x-card>
        </div>

        {{-- 右側說明卡片 --}}
        <div class="lg:col-span-1">
            <x-card title="操作說明" class="bg-base-200/50 border border-base-300">
                <div class="space-y-4 text-sm">
                    <div class="flex items-start gap-2">
                        <x-icon name="o-information-circle" class="w-5 h-5 text-info shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold block">商品選擇邏輯</span>
                            輸入部分SKU或商品名稱，點選商品；若要重新選擇，可點擊鉛筆按鈕進行重置。
                        </div>
                    </div>

                    <div class="flex items-start gap-2">
                        <x-icon name="o-arrow-path-rounded-square" class="w-5 h-5 text-warning shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold block">正負號自動轉換</span>
                            選取「公關贈送」、「商品報廢」等類型時自動轉為負數扣庫存；選取「期初導入」自動為正數加庫存。
                        </div>
                    </div>
					
					<div class="flex items-start gap-2">
                        <x-icon name="o-document-text" class="w-5 h-5 text-success shrink-0 mt-0.5" />
                        <div>
                            <span class="font-bold block">會計與庫存連動</span>
                            系統將自動計算快照成本，異動將同步更新實體庫存並產出平衡之會計傳票，維持資產一致性。
                        </div>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</div>