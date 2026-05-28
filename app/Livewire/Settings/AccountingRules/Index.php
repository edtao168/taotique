<?php
// app/Livewire/Settings/AccountingRules/Index.php

namespace App\Livewire\Settings\AccountingRules;

use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\Account;
use App\Enums\AmountSource;
use Livewire\Component;
use Mary\Traits\Toast;

class Index extends Component
{
    use Toast;

    public $search = '';
    public bool $myModal = false;
    public ?AccountingRule $editingItem = null;

    // 主檔欄位
    public $event_type = '';
    public $is_active = true;

    // 明細行 (動態陣列)
    public $lines = [];

    protected $rules = [
        'event_type' => 'required|string|max:100|unique:accounting_rules,event_type',
        'is_active' => 'boolean',
        'lines' => 'required|array|min:2',
        'lines.*.account_id' => 'nullable|exists:accounts,id',
		'lines.*.account_code' => 'nullable|string|max:20',
        'lines.*.entry_type' => 'required|in:debit,credit',
        'lines.*.amount_source' => 'required|string',
        'lines.*.ratio' => 'required|numeric|min:0',
    ];

    

    public function create()
    {
		$this->reset(['event_type', 'is_active', 'lines', 'editingItem']);
		$this->is_active = true;
		$this->lines = [
			['account_id' => null, 'account_code' => 'DYNAMIC', 'entry_type' => 'debit', 'amount_source' => '', 'ratio' => 1],
			['account_id' => null, 'account_code' => 'DYNAMIC', 'entry_type' => 'credit', 'amount_source' => '', 'ratio' => 1],
		];
		$this->myModal = true;
	}

    public function edit(AccountingRule $item)
    {
        $this->editingItem = $item;
        $this->event_type = $item->event_type;
        $this->is_active = $item->is_active;
        $this->lines = $item->lines->map(fn($line) => [
            'account_id' => $line->account_id,
            'entry_type' => $line->entry_type,
            'amount_source' => $line->amount_source,
            'ratio' => $line->ratio,
			'account_code' => $line->account_id ? $line->account?->code : 'DYNAMIC',
        ])->toArray();
        $this->myModal = true;
    }

    public function addLine()
    {
        $this->lines[] = [
        'account_id'   => null, 
        'account_code' => 'DYNAMIC',
        'entry_type'   => 'debit', 
        'amount_source'=> '', 
        'ratio'        => 1
    ];
    }

    public function removeLine($index)
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save()
	{
		$rules = $this->rules;
		if ($this->editingItem) {
			$rules['event_type'] = 'required|string|max:100|unique:accounting_rules,event_type,' . $this->editingItem->id;
		}
		
		// 【驗證前清洗】確保前端傳回的字串 "null"、空字串、0 或 'DYNAMIC'，能正確統一處理
		foreach ($this->lines as $index => $line) {
			$currentId = $line['account_id'] ?? null;
			
			// 統一將各種「空值」變體轉為真正的 null
			if (empty($currentId) || $currentId === 'null' || $currentId === 'DYNAMIC' || $currentId === 0 || $currentId === '0') {
				$this->lines[$index]['account_id'] = null;
				$this->lines[$index]['account_code'] = 'DYNAMIC';
			} else {
				// 若有實質 ID，預先查出 code 存入，減少 transaction 內查詢
				$account = Account::find($currentId);
				$this->lines[$index]['account_code'] = $account ? $account->code : 'DYNAMIC';
			}
		}

		// 執行驗證
		$this->validate($rules);

		// 檢查借貸平衡
		$debitCount = collect($this->lines)->where('entry_type', 'debit')->count();
		$creditCount = collect($this->lines)->where('entry_type', 'credit')->count();
		if ($debitCount == 0 || $creditCount == 0) {
			$this->error('至少需要一筆借方和一筆貸方');
			return;
		}

		// 強制開啟資料庫事務
		\DB::transaction(function () {
			$rule = AccountingRule::updateOrCreate(
				['id' => $this->editingItem?->id],
				[
					'event_type' => $this->event_type,
					'is_active' => $this->is_active,
					'shop_id' => 1,
				]
			);

			// 刪除舊明細
			$rule->lines()->delete();
			
			// 寫入新明細（直接信任清洗後的 $this->lines，不再二次判斷）
			foreach ($this->lines as $index => $line) {
				AccountingRuleLine::create([
					'accounting_rule_id' => $rule->id,
					'account_id'         => $line['account_id'],
					'account_code'       => $line['account_code'],
					'entry_type'         => $line['entry_type'],
					'amount_source'      => $line['amount_source'],
					'ratio'              => $line['ratio'],
					'sort_order'         => $index + 1,
					'is_active'          => true,
				]);
			}
		});

		$this->success($this->editingItem ? '規則已更新' : '新規則已建立');
		$this->myModal = false;
		$this->reset(['event_type', 'is_active', 'lines', 'editingItem']);
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
	
	public function render()
    {
        $headers = [
            ['key' => 'event_type', 'label' => '事件類型', 'class' => 'font-mono'],
            ['key' => 'lines_summary', 'label' => '分錄摘要'],
            ['key' => 'status', 'label' => '狀態', 'class' => 'w-20'],
            ['key' => 'actions', 'label' => '', 'sortable' => false],
        ];

        $rows = AccountingRule::with('lines.account')
            ->when($this->search, fn($q) => $q->where('event_type', 'like', "%{$this->search}%"))
            ->orderBy('event_type')
            ->get();

        $accounts = Account::where('is_active', true)
        ->orderBy('code')
        ->get()
        ->map(fn($account) => [
            'id' => $account->id,
            'name' => "{$account->code} - {$account->name}",  // 關鍵：code + name
            'code' => $account->code,
        ]);

		return view('livewire.settings.accounting-rules.index', [
			'rows' => $rows,
			'headers' => $headers,
			'accounts' => $accounts,
			'amountSources' => collect(AmountSource::cases())->map(fn($case) => [
				'value' => $case->value,
				'label' => $case->label(),
			]),
			'entryTypes' => [
				['id' => 'debit', 'name' => '借方'],
				['id' => 'credit', 'name' => '貸方'],
			]
        ]);
    }
}