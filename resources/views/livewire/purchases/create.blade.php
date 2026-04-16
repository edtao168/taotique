{{-- 檔案路徑：resources/views/livewire/purchases/create.blade.php --}}
<div x-data="{ 
        atBottom: false,
        checkScroll() {
            // 判斷是否滾動到接近底部 (留 150px 緩衝)
            this.atBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 100);
        }
     }" 
     x-init="checkScroll()"
     @scroll.window="checkScroll()">
    {{-- 頁頭：改用 Mary UI 原生標題，加入柔和層次 --}}
    <x-header separator progress-indicator>
        <x-slot:title>
            <div class="flex items-center gap-4">
                <div class="p-3 bg-primary/10 rounded-2xl text-primary">
                    <x-icon name="o-shopping-bag" class="w-8 h-8" />
                </div>
                <div>
                    <h1 class="text-2xl font-bold tracking-tight text-base-content">
                        {{ $isEdit ? '修改採購單' : '採購入庫作業' }}
                    </h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="badge badge-outline badge-sm font-mono opacity-70">{{ $isEdit ? $purchase->purchase_number : $purchase_number }}</span>
                        <span class="badge badge-ghost badge-sm uppercase tracking-tighter">Inventory Inbound</span>
                    </div>
                </div>
            </div>
        </x-slot:title>
        <x-slot:actions>
            <x-button label="返回列表" icon="o-arrow-left" link="/purchases" class="btn-ghost" />
            <x-button label="確認入庫" icon="o-check" class="btn-primary shadow-md hover:shadow-lg transition-all px-8" wire:click="save" spinner />
        </x-slot:actions>
    </x-header>

    <div class="grid grid-cols-12 gap-6">
        
        {{-- 1. 左側：單據屬性 (3格) --}}
        <div class="col-span-12 lg:col-span-3 space-y-6">
            <x-card title="單據資訊" shadow class="border-t-4 border-primary">
                <div class="space-y-4">
                    <x-choices label="供應商" wire:model="supplier_id" :options="$suppliers" single icon="o-building-storefront" />
                    
                    <div class="grid grid-cols-2 gap-4">
                        <x-datetime label="採購日期" wire:model="purchased_at" />
                        <x-select label="幣別" wire:model.live="currency" :options="[['id'=>'CNY', 'name'=>'CNY'], ['id'=>'TWD', 'name'=>'TWD']]" />
                    </div>
						<x-select label="預設入庫倉庫" wire:model.live="warehouse_id" :options="$warehouses" icon="o-home-modern" />
                    <x-textarea label="內部備註" wire:model="remark" rows="3" placeholder="備註採購細節..." />
                </div>
            </x-card>
        </div>
		
		<div x-show="!atBottom" 
			 x-transition:enter="transition ease-out duration-300"
			 x-transition:enter-start="opacity-0 transform translate-y-4"
			 x-transition:leave="transition ease-in duration-300"
			 x-transition:leave-end="opacity-0 transform translate-y-4"
			 class="fixed bottom-6 right-6 z-50 pointer-events-none">
			
			<div class="flex flex-col items-center">
				<span class="text-xs font-bold text-orange-600 bg-orange-50 px-2 py-1 rounded-full shadow-sm mb-1">下面還有</span>
				<div class="bg-orange-500 text-white p-3 rounded-full shadow-lg animate-bounce">
					<x-icon name="o-chevron-double-down" class="w-6 h-6" />
				</div>
			</div>
		</div>

        {{-- 2. 中間：商品明細 (6格) --}}
        <div class="col-span-12 lg:col-span-6 space-y-4">
            <x-card shadow>
                <x-slot:title>
                    <div class="flex justify-between items-center w-full">
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-lg">商品明細清冊</span>
                            <span class="text-xs opacity-50 font-mono tracking-widest">ITEMS</span>
                        </div>
                        <x-scanner.button mode="continuous" class="btn-sm btn-outline border-base-300" />
                    </div>
                </x-slot:title>

                {{-- PC 端標頭：柔和背景 --}}
                <div class="hidden lg:grid grid-cols-12 gap-4 mb-4 px-4 py-2 bg-base-200 rounded-lg text-xs font-bold opacity-70 uppercase">
                    <div class="col-span-5">商品描述</div>
					<div class="col-span-2">入庫倉庫</div>
                    <div class="col-span-1 text-right">數量</div>
                    <div class="col-span-2 text-right">單價 ({{ $currency }})</div>
                    <div class="col-span-2 text-right">小計</div>
                </div>

                <div class="divide-y divide-base-200">
                    @forelse($items as $index => $item)
                        <div wire:key="pur-row-{{ $index }}" class="group relative py-4 lg:px-4 hover:bg-primary/5 rounded-xl transition-all">
                            <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-center">
                                <div class="col-span-1 lg:col-span-5">
                                    <x-choices wire:model.live="items.{{ $index }}.product_id" :options="$productOptions" searchable single />
                                </div>
								<div class="col-span-1 lg:col-span-2">    
									<x-select wire:model="items.{{ $index }}.warehouse_id" :options="$warehouses" class="select rounded-none border-slate-200" />
								</div>
                                <div class="col-span-1 lg:col-span-1">
                                    <x-input type="number" step="0.0001" wire:model.live="items.{{ $index }}.quantity" class="text-right font-mono" />
                                </div>
                                <div class="col-span-1 lg:col-span-2">
                                    <x-input wire:model.live="items.{{ $index }}.foreign_price" class="text-right font-mono" />
                                </div>
                                <div class="col-span-1 lg:col-span-2 text-right font-mono font-bold text-primary">
                                    {{ number_format(bcmul($item['quantity'] ?? 0, $item['foreign_price'] ?? 0, 4), 2) }}
                                    <x-button icon="o-trash" class="btn-ghost btn-xs text-error absolute top-0 right-0 opacity-0 group-hover:opacity-100 transition-opacity" wire:click="removeRow({{ $index }})" />
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center opacity-30 italic">尚未加入商品</div>
                    @endforelse
                </div>

                <x-slot:actions>
                    <x-button label="手動新增一行商品" icon="o-plus" class="btn-ghost btn-sm w-full border-dashed border-2 hover:bg-primary/5" wire:click="addRow" />
                </x-slot:actions>
            </x-card>
        </div>

        {{-- 3. 右側：財務結算 (3格) --}}
        <div class="col-span-12 lg:col-span-3 space-y-6">
            <x-card title="結算" shadow class="bg-base-100 border-t-4 border-primary">
                <div class="space-y-6">
					<div class="flex justify-between items-center p-2 bg-base-200/50 rounded-lg">
                        <span class="font-bold opacity-70">小計</span>
                        <span class="font-mono text-right">
							{{ $currency }} {{ number_format($this->subTotal, 0) }}
						</span>
                    </div>
                    {{-- 金額微調 --}}
                    <div class="grid gap-3 pt-2">
						<x-input label="運費 (+)" wire:model.live="shipping_fee" prefix="{{ $currency }}" class="text-right font-mono" />
						{{-- 新增稅金與雜費 --}}
						<x-input label="稅金 (+)" wire:model.live="tax" prefix="{{ $currency }}" class="text-right font-mono" />
						<x-input label="其他規費 (+)" wire:model.live="other_fees" prefix="{{ $currency }}" class="text-right font-mono" />
						<x-input label="折扣 (-)" wire:model.live="discount" prefix="{{ $currency }}" class="text-right font-mono text-error font-bold" />
					</div>

                    {{-- 總額大區塊：採用銷售單那種大圓角 --}}
                    <div class="p-5 bg-slate-800 text-white rounded-xl shadow-inner relative overflow-hidden">
                        <div class="absolute -right-4 -bottom-4 opacity-10">
                            <x-icon name="o-banknotes" class="w-24 h-24" />
                        </div>
                        <div class="relative z-10">
                            <div class="text-xs opacity-60 uppercase tracking-widest mb-1">應付總額 ({{ $currency }})</div>
                            <div class="text-4xl font-black font-mono leading-none tracking-tighter">
                                {{ number_format($this->totalAmount, 2) }}
                            </div>
                        </div>
                    </div>

                    {{-- 匯率轉換 --}}
                    <div class="pt-2 px-1">
                        <x-input label="評估匯率" wire:model.live="exchange_rate" icon="o-arrows-right-left" :readonly="$currency === 'TWD'" class="text-right font-mono {{ $currency === 'TWD' ? 'bg-base-200' : '' }}" />
                    </div>

                    {{-- 最終 TWD 成本 --}}
                    <div class="p-5 bg-primary/10 rounded-xl border border-primary/20">
                        <div class="text-[13px] text-primary font-bold uppercase tracking-widest mb-1">估計庫存成本 (TWD)</div>
                        <div class="text-3xl font-black text-base-content font-mono tracking-tighter">
                            NT$ {{ number_format($this->totalTwd, 0) }}
                        </div>
                    </div>

                    <x-button label="確認入庫 / 過帳" icon="o-check-circle" class="btn-primary w-full btn-lg shadow-xl" wire:click="save" spinner />
                </div>
            </x-card>
        </div>
    </div>

    <x-scanner.modal />       
    <x-scanner.scripts />
</div>