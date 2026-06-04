<?php
// app/Livewire/Settings/AccountingRules/Index.php
// [費曼註釋：全面重構明細結構，引入聯邦搜尋，將一般科目與動態策略完美歸一]

namespace App\Livewire\Settings\AccountingRules;

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

    // 給前端 x-choices 初始化載入的聯邦清單快照
    public array $comboOptions = [];

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
            // 反向還原：如果有 account_id 優先用實體 ID，否則用 DYNAMIC 字串
            'combined_value' => $line->account_id ? (string)$line->account_id : ($line->account_code ?? 'DYNAMIC:sale:payment'),
            'entry_type' => $line->entry_type,
            'amount_source' => $line->amount_source,
            'ratio' => $line->ratio,
        ])->toArray();
        
        $this->myModal = true;
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
                $val = $line['combined_value'];
                
                $accountId = null;
                $accountCode = $val;

                // 🎯 如果傳回來的是純數字，代表使用者選的是實體會計科目 ID
                if (is_numeric($val)) {
                    $accountId = (int)$val;
                    $account = Account::find($accountId);
                    $accountCode = $account ? $account->code : 'UNKNOWN';
                }
                // 如果是 DYNAMIC: 開頭，則為動態路由，accountId 保持為 null

                AccountingRuleLine::create([
                    'accounting_rule_id' => $rule->id,
                    'account_id'         => $accountId,
                    'account_code'       => $accountCode,
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

        $amountSources = [
            ['value' => 'customer_total', 'label' => '顧客實付總額 (customer_total)'],
            ['value' => 'subtotal_after_discount', 'label' => '折讓後商品淨額 (subtotal_after_discount)'],
            ['value' => 'tax_amount', 'label' => '銷項稅額 (tax_amount)'],
            ['value' => 'freight_amount', 'label' => '買家自付運費 (freight_amount)'],
            ['value' => 'platform_fee', 'label' => '平台手續費 (platform_fee)'],
            ['value' => 'commission', 'label' => '佣金 (commission)'],
            ['value' => 'seller_discount', 'label' => '賣家活動折讓 (seller_discount)'],
            ['value' => 'shipping_fee_platform', 'label' => '平台代扣運費 (shipping_fee_platform)'],
            ['value' => 'total_fees', 'label' => '費用總計 (total_fees)'],
            ['value' => 'cost_amount', 'label' => '銷貨總成本 (cost_amount)'],
        ];

        return view('livewire.settings.accounting-rules.index', [
            'rows' => $rows,
            'headers' => $headers,
            'amountSources' => $amountSources,
            'entryTypes' => [
                ['value' => 'debit', 'label' => '借方'],
                ['value' => 'credit', 'label' => '貸方'],
            ]
        ]);
    }
}