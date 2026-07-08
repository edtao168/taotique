<?php
// app/Livewire/Settings/AccountingRules/Index.php

namespace App\Livewire\Settings\AccountingRules;

use App\Enums\AmountSource;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\Account;
use App\Traits\HasAccountAndDynamicSearch;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast, HasAccountAndDynamicSearch;

    public $search = '';
    public bool $myModal = false;
    public ?AccountingRule $editingItem = null;

    // 主檔欄位
    public $event_type = '';
    public $is_active = true;

    // 明細行
    public $lines = [];

    public array $comboOptions = [];
    public array $amountSources = [];

    protected $rules = [
        'event_type' => 'required|string|max:100',
        'is_active' => 'boolean',
        'lines' => 'required|array|min:2',
        'lines.*.combined_value' => 'required|string|max:50',
        'lines.*.entry_type' => 'required|in:debit,credit',
        'lines.*.amount_source' => 'required|string|max:30',
        'lines.*.ratio' => 'required|numeric|min:0',
    ];

    public function mount()
    {
        // 初始化載入不帶關鍵字的聯邦清單，供預設下拉顯示
        $this->comboOptions = $this->searchAccounts();
        
        // 🎯 預先載入金額來源選項（只載入一次）
        $this->amountSources = AmountSource::options();
    }

    public function create()
    {
        $this->reset(['event_type', 'is_active', 'lines', 'editingItem']);
        $this->is_active = true;
        $this->lines = [
            ['combined_value' => 'DYNAMIC:sale:payment', 'entry_type' => 'debit', 'amount_source' => '', 'ratio' => 1],
            ['combined_value' => 'DYNAMIC:sale:payment', 'entry_type' => 'credit', 'amount_source' => '', 'ratio' => 1],
        ];
        $this->myModal = true;
    }

    public function edit(AccountingRule $item)
    {
        $this->editingItem = $item;
        $this->event_type = $item->event_type;
        $this->is_active = $item->is_active;
        
        $this->lines = $item->lines->map(fn($line) => [
            'combined_value' => $line->account_id ? (string)$line->account_id : ($line->account_code ?? 'DYNAMIC:sale:payment'),
            'entry_type' => $line->entry_type,
            'amount_source' => $line->amount_source,
            'ratio' => $line->ratio,
        ])->toArray();
        
        $this->myModal = true;
		$this->resetErrorBag();
    }

    public function addLine()
    {
        $this->lines[] = [
            'combined_value' => 'DYNAMIC:sale:payment', 
            'entry_type'     => 'debit', 
            'amount_source'  => '', 
            'ratio'          => 1
        ];
    }

    public function removeLine($index)
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }
	
    public function delete(AccountingRule $item)
    {
        $item->delete();
        $this->success('規則已刪除');
    }

    public function toggleActive(AccountingRule $item)
    {
        $item->update(['is_active' => !$item->is_active]);
        $this->success($item->is_active ? '已啟用' : '已停用');
    }
    
    /**
     * 🎯 滿足 HasAccountAndDynamicSearch Trait 的抽象方法約束
     */
    public function resolveDynamicAccount(string $dynamicSpec, ?array $context = null): string
    {
        return '';
    }

    /**
     * 🎯 滿足 HasAccountAndDynamicSearch Trait 的抽象方法約束
     */
    public function getAmountFromSource(string $source, mixed $context = null): string
    {
        return '0.0000';
    }


    public function save()
    {
        $rules = $this->rules;
        if ($this->editingItem) {
            $rules['event_type'] = 'required|string|max:100|unique:accounting_rules,event_type,' . $this->editingItem->id;
        } else {
            $rules['event_type'] = 'required|string|max:100|unique:accounting_rules,event_type';
        }

        $this->validate($rules);

        $debitCount = collect($this->lines)->where('entry_type', 'debit')->count();
        $creditCount = collect($this->lines)->where('entry_type', 'credit')->count();
        if ($debitCount == 0 || $creditCount == 0) {
            $this->error('至少需要一筆借方和一筆貸方');
            return;
        }

        \DB::transaction(function () {
            $rule = AccountingRule::updateOrCreate(
                ['id' => $this->editingItem?->id],
                [
                    'event_type' => $this->event_type,
                    'is_active' => $this->is_active,
                    'shop_id' => 1,
                ]
            );

            $rule->lines()->delete();
            
			foreach ($this->lines as $index => $line) {
				$combinedValue = $line['combined_value'];
				$entryType = $line['entry_type'];
				$amountSource = $line['amount_source'];
				$ratio = $line['ratio'];
				
				$accountId = null;
				$accountCode = null;
				
				// 判斷類型
				if (str_starts_with($combinedValue, 'DYNAMIC:')) {
					// 動態科目：只存 account_code
					$accountCode = $combinedValue;
					$accountId = null;
				} elseif (is_numeric($combinedValue)) {
					// 可能是 ID 或 科目代碼
					
					// 先嘗試當作 ID 查詢
					$account = Account::find((int)$combinedValue);
					
					if ($account) {
						// 找到 → 是 ID
						$accountId = $account->id;
						$accountCode = $account->code;
					} else {
						// 找不到 → 可能是科目代碼（如 222104）
						$account = Account::where('code', (string)$combinedValue)->first();
						
						if ($account) {
							// 找到科目代碼
							$accountId = $account->id;
							$accountCode = $account->code;
						} else {
							// 真的找不到
							throw new \Exception("無法辨識的科目：{$combinedValue}");
						}
					}
				} else {
					// 其他情況（純字串科目代碼）
					$account = Account::where('code', $combinedValue)->first();
					
					if ($account) {
						$accountId = $account->id;
						$accountCode = $account->code;
					} else {
						$accountCode = $combinedValue;
						$accountId = null;
					}
				}
				
				AccountingRuleLine::create([
					'accounting_rule_id' => $rule->id,
					'account_id'         => $accountId,
					'account_code'       => $accountCode,
					'entry_type'         => $entryType,
					'amount_source'      => $amountSource,
					'ratio'              => $ratio,
					'sort_order'         => $index + 1,
					'is_active'          => true,
				]);
			}
        });

        $this->success($this->editingItem ? '規則已更新' : '新規則已建立');
        $this->myModal = false;
        $this->reset(['event_type', 'is_active', 'lines', 'editingItem']);
    }
    
    public function render()
    {
        $headers = [
            ['key' => 'event_type', 'label' => '事件類型', 'class' => 'font-mono'],
            ['key' => 'lines_summary', 'label' => '分錄摘要'],
            ['key' => 'status', 'label' => '狀態', 'class' => 'w-20'],
            ['key' => 'actions', 'label' => '', 'sortable' => false],
        ];

        $rows = AccountingRule::with(['lines.account'])
            ->when($this->search, fn($q) => $q->where('event_type', 'like', "%{$this->search}%"))
            ->orderBy('event_type')
            ->get();
            
        // 預先快取所有 account_code 對應的科目
        $allCodes = $rows->flatMap(fn($rule) => $rule->lines)
            ->filter(fn($line) => $line->account_code && !$line->is_dynamic && !$line->account_id)
            ->pluck('account_code')
            ->unique();
            
        $codeToAccountMap = Account::whereIn('code', $allCodes)
            ->get()
            ->keyBy('code');

        return view('livewire.settings.accounting-rules.index', [
            'rows' => $rows,
            'headers' => $headers,
            'amountSources' => $this->amountSources,  // 直接使用屬性
            'entryTypes' => [
                ['value' => 'debit', 'label' => '借方'],
                ['value' => 'credit', 'label' => '貸方'],
            ],
            'codeToAccountMap' => $codeToAccountMap,
        ]);
    }
}