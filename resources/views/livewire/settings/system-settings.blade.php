<div>
    <x-card title="系統參數設定" shadow separator>
		<x-tabs selected="core-tab">
			{{-- 1. 核心流程 --}}
			<x-tab name="core-tab" label="核心流程" icon="o-cpu-chip">
				<div class="grid gap-4 py-4">
					<x-checkbox label="允許負庫存出貨" wire:model="payload.allow_negative_stock" />
					<x-checkbox label="強制綁定供應商" wire:model="payload.force_vendor_on_po" />
				</div>
			</x-tab>

			{{-- 2. 編碼規則 --}}
			<x-tab name="num-tab" label="單據編碼" icon="o-hashtag">
				<div class="grid grid-cols-2 gap-4 py-4">
					<x-input label="採購單前綴" wire:model="payload.po_prefix" placeholder="例如: PO-" />
					<x-input label="流水號位數" wire:model="payload.so_number_digits" type="number" />
				</div>
			</x-tab>			
			
			<x-tab name="tab-finance" label="財務設定" icon="o-currency-dollar">
				<x-input label="營業稅率 (%)" wire:model="payload.tax_rate" type="number" suffix="%" />
				<x-choices label="預設幣別" wire:model="payload.base_currency" :options="[['id'=>'TWD','name'=>'台幣'],['id'=>'USD','name'=>'美金']]" single />
			</x-tab>			
		</x-tabs>

        <x-slot:actions>
            <x-button label="重置" icon="o-arrow-path" />
            <x-button label="儲存設定" icon="o-check" class="btn-primary" type="submit" spinner="save" />
        </x-slot:actions>
    </x-card>
</div>