<div class="p-6">
    <x-header title="新增商品基本資料" subtitle="定義 SKU 規格與基本售價，實際庫存與成本請於「採購入庫」處理" separator />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <x-form wire:submit="save">
            {{-- 分類選擇：確保傳回 code 以利後續查詢 --}}
            <x-select 
                label="商品分類" 
                icon="o-tag" 
                :options="$categories" 
                option-value="code"
                wire:model.live="category_id" 
                placeholder="請選擇分類" />

            <x-select 
                label="商品材質" 
                icon="o-sparkles" 
                :options="$materials" 
                option-label="name"
                option-value="id"
                wire:model.live="material_id" 
                placeholder="請選擇材質" />

			{{-- SKU 編碼規則提示 --}}
			<div class="bg-base-100 p-6 rounded-xl border border-dashed border-base-200">
				<h3 class="font-bold mb-4 flex items-center gap-2 text-sm">
					<x-icon name="o-information-circle" class="w-4 h-4 text-info" /> SKU 編碼規則
				</h3>
				
				<ul class="steps steps-horizontal w-full text-xs">
					<li class="step {{ $category_id ? 'step-primary' : '' }}">
						<span class="mt-2 block">分類碼 ({{ $category_id ?: '待選' }})</span>
					</li>
					<li class="step {{ $material_id ? 'step-primary' : '' }}">
						<span class="mt-2 block">材質碼 ({{ $material_id ? '已選' : '待選' }})</span>
					</li>
					<li class="step">
						<span class="mt-2 block">流水號 (自動配發)</span>
					</li>
				</ul>

				<div class="mt-6 text-[6px] text-gray-400 italic">
					註：此頁面僅建立商品主檔。進貨成本與匯率計算請至「採購入庫」模組處理。
				</div>
			</div>
			
            <hr class="my-4" />

            <x-input label="系統配發 SKU" wire:model="sku" readonly class="bg-base-200 font-mono" icon="o-finger-print" />
            <x-input label="商品名稱" wire:model="name" icon="o-pencil" />

            <div class="grid grid-cols-2 gap-4">
                <x-input label="零售價 (TWD)" wire:model="price" prefix="$" type="number" />
                <x-input label="預設單位" wire:model="unit" placeholder="ea, g, 條..." />
            </div>
            
            <x-input label="最低庫存警示" wire:model="min_stock" type="number" />
            <x-textarea label="備註" wire:model="remark" rows="2" />

            <x-slot:actions>
                <x-button label="取消" link="/products" />
                <x-button label="完成建檔" class="btn-primary" type="submit" spinner="save" />
            </x-slot:actions>
        </x-form>
{{-- 右側：媒體管理 (相簿與影片) --}}
        <x-media-manager :new_photos="$new_photos" />
        
		
    </div>
</div>