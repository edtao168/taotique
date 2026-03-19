{{-- 檔案路徑：resources/views/livewire/products/create.blade.php --}}
<div class="p-6">
    <x-header title="新增商品基本資料" subtitle="定義 SKU 規格與基本售價，實際庫存與成本請於「採購入庫」處理" separator />

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <x-form wire:submit="save" class="space-y-3"> {{-- 比照 edit 調整間距 --}}
            {{-- 分類與材質並列 --}}
            <div class="grid grid-cols-2 gap-4">
                <x-select 
                    label="商品分類" 
                    icon="o-tag" 
                    :options="$categories" 
                    option-value="code"
                    wire:model.live="category_id" 
                    placeholder="請選擇" />

                <x-select 
                    label="商品材質" 
                    icon="o-sparkles" 
                    :options="$materials" 
                    option-label="name"
                    option-value="id"
                    wire:model.live="material_id" 
                    placeholder="請選擇" />
            </div>

			{{-- SKU 編碼規則提示 --}}
			<div class="bg-base-200/50 p-6 rounded-xl border border-dashed border-base-300">
				<div class="flex items-center gap-2 mb-2">
					<x-icon name="o-information-circle" class="w-4 h-4 text-info" />
					<span class="text-xs font-bold opacity-70">SKU 編碼規則：分類碼 + 材質碼 + 流水號</span>
				</div>
				
				<ul class="steps steps-horizontal w-full text-[10px]">
					<li class="step {{ $category_id ? 'step-primary' : '' }}">
						<span class="mt-0 block">分類 ({{ $category_id ?: '?' }})</span>
					</li>
					<li class="step {{ $material_id ? 'step-primary' : '' }}">
						<span class="mt-0 block">材質 ({{ $material_id ? 'OK' : '?' }})</span>
					</li>
					<li class="step">
						<span class="mt-0 block">流水號</span>
					</li>
				</ul>

				<div class="mt-3 p-2 bg-warning/10 border-l-4 border-warning rounded-r text-warning-content">
					<p class="text-xs font-bold">
						註：此頁面僅建立商品主檔。進貨成本與匯率計算請至「採購入庫」模組處理。
					</p>
				</div>
			</div>
			
            <x-input label="系統配發 SKU" wire:model="sku" readonly class="bg-base-200 font-mono" icon="o-finger-print" />
            <x-input label="商品名稱" wire:model="name" icon="o-pencil" />

            <div class="grid grid-cols-2 gap-4">
                <x-input label="零售價 (TWD)" wire:model="price" prefix="$" type="number" />
                <x-input label="預設單位" wire:model="unit" placeholder="ea, g, 條..." />
            </div>
            
            <x-input label="最低庫存警示" wire:model="min_stock" type="number" />
            <x-textarea label="備註" wire:model="remark" rows="2" />

            <x-slot:actions>
                <x-button label="取消" :link="route('products.index')" />
                <x-button label="完成建檔" class="btn-primary" type="submit" spinner="save" icon="o-check" />
            </x-slot:actions>
        </x-form>

		{{-- 右側：媒體管理 --}}
        <div class="h-full">
            <x-media-manager :new_photos="$new_photos" :editable="true" /> 
        </div>
    </div>
</div>