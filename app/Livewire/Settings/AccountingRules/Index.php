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
        'lines.*.account_id' => 'required|exists:accounts,id',
        'lines.*.entry_type' => 'required|in:debit,credit',
        'lines.*.amount_source' => 'required|string',
        'lines.*.ratio' => 'required|numeric|min:0',
    ];

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

        return view('livewire.settings.accounting-rules.index', [
            'rows' => $rows,
            'headers' => $headers,
            'accounts' => Account::where('is_active', true)->orderBy('code')->get(),
            'amountSources' => collect(AmountSource::cases())->map(fn($case) => [
                'value' => $case->value,
                'label' => $case->label(),
            ]),
            // 修正點：定義 entryTypes 格式供 Mary UI x-select 使用
            'entryTypes' => [
                ['id' => 'debit', 'name' => '借方'],
                ['id' => 'credit', 'name' => '貸方'],
            ]
        ]);
    }

    public function create()
    {
        $this->reset(['event_type', 'is_active', 'lines', 'editingItem']);
        $this->is_active = true;
        $this->lines = [
            ['account_id' => '', 'entry_type' => 'debit', 'amount_source' => '', 'ratio' => 1],
            ['account_id' => '', 'entry_type' => 'credit', 'amount_source' => '', 'ratio' => 1],
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
        ])->toArray();
        $this->myModal = true;
    }

    public function addLine()
    {
        $this->lines[] = ['account_id' => '', 'entry_type' => 'debit', 'amount_source' => '', 'ratio' => 1];
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
        $data = $this->validate($rules);

        // 檢查借貸平衡 (借總額 == 貸總額，此處用筆數簡單驗證)
        $debitCount = collect($this->lines)->where('entry_type', 'debit')->count();
        $creditCount = collect($this->lines)->where('entry_type', 'credit')->count();
        if ($debitCount == 0 || $creditCount == 0) {
            $this->error('至少需要一筆借方和一筆貸方');
            return;
        }

        $rule = AccountingRule::updateOrCreate(
            ['id' => $this->editingItem?->id],
            [
                'event_type' => $this->event_type,
                'is_active' => $this->is_active,
                'shop_id' => 1,
            ]
        );

        // 刪除舊明細，重新建立
        $rule->lines()->delete();
        foreach ($this->lines as $line) {
            AccountingRuleLine::create([
                'accounting_rule_id' => $rule->id,
                'account_id' => $line['account_id'],
                'entry_type' => $line['entry_type'],
                'amount_source' => $line['amount_source'],
                'ratio' => $line['ratio'],
                'sort_order' => 1,
                'is_active' => true,
            ]);
        }

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
}