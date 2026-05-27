<?php

namespace App\Livewire\Accountings;
/*
核心定位
底層：依據中國《小企业会计准则》+ 現代ERP規範
UI：一人店、老闆不懂會計 → 極簡、自動化
原則：能自動就不要讓老闆摻合
*/
use App\Models\Account;
use App\Models\AccountingRule;
use App\Models\Journal;
use App\Models\JournalItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;

class JournalCreate extends Component
{
    use Toast;

    public ?int $journalId = null;
    public bool $isEdit = false;

    public string $entry_date = '';
    public string $description = '';    
    public string $payment_method = '';
    public string $entry_type = '';
	
	public ?string $selected_account = ''; // 允許 null
	public ?string $amount = '';
	public ?string $currentAccountName = '';
	public ?string $event_type = null;
    public array $eventTypeOptions = [];
    public array $generated_lines = [];    
    public array $paymentOptions = [];
    public array $accountOptions = [];

    // ==============================================
    // 生命週期
    // ==============================================
    public function mount(Journal $journal = null): void
    {
        $this->entry_date = now()->format('Y-m-d');
		$this->loadEventTypes();
        $this->loadPaymentOptions();

        if ($journal && $journal->exists) {
            if ($journal->status !== 'draft') {
                $this->error('已過帳分錄不可修改，請使用更正功能');
                $this->redirect(route('accountings.journals.correct', $journal));
                return;
            }
            $this->journalId = $journal->id;
            $this->isEdit = true;
            $this->loadJournalData($journal);
        }
    }
	
	    /**
     * 載入可用的業務類型
     */
    protected function loadEventTypes(): void
    {
        $this->eventTypeOptions = AccountingRule::where('is_active', true)
            ->get()
            ->map(fn($rule) => [
                'id' => $rule->event_type,
                'name' => $this->getEventTypeLabel($rule->event_type),
            ])
            ->toArray();
    }
    
    protected function getEventTypeLabel(string $eventType): string
    {
        return match($eventType) {
            'retail_sale' => '實體店銷售',
            'online_sale' => '線上銷售',
            'expense' => '費用支出',
            'purchase' => '採購進貨',
            default => $eventType,
        };
    }
    
    /**
     * 當業務類型改變時，重新載入科目選單
     */
    public function updatedEventType()
    {
        $this->selected_account = '';
        $this->currentAccountName = '';
        $this->loadAccountOptionsByEventType();
        $this->refreshEntryPreview();
    }
    
    /**
     * 根據業務類型載入可用的科目
     */
    protected function loadAccountOptionsByEventType(): void
    {
        if (empty($this->event_type)) {
            $this->accountOptions = [];
            return;
        }
        
        $rule = AccountingRule::where('event_type', $this->event_type)
            ->where('is_active', true)
            ->first();
            
        if (!$rule) {
            $this->accountOptions = [];
            return;
        }
        
        $accountIds = AccountingRuleLine::where('accounting_rule_id', $rule->id)
            ->where('is_active', true)
            ->pluck('account_id')
            ->toArray();
            
        $this->accountOptions = Account::whereIn('id', $accountIds)
            ->where('is_active', true)
            ->whereRaw('LENGTH(code) = 6')
            ->get()
            ->map(fn($a) => [
                'id' => (string)$a->code,
                'name' => "【{$a->code}】{$a->name}"
            ])
            ->toArray();
    }

    // ==============================================
    // 自動觸發：科目/金額/資金帳戶 一變就重算
    // ==============================================
    public function updatedSelectedAccount()
    {
        $this->refreshEntryPreview();
    }

    public function updatedAmount()
    {
        $this->refreshEntryPreview();
    }

    public function updatedPaymentMethod()
    {
        $this->refreshEntryPreview();
    }

    // ==============================================
    // 核心：統一刷新預覽（你要的關鍵函數）
    // ==============================================
    public function refreshEntryPreview()
    {
        $this->generated_lines = [];

        // 防呆：缺一不可
        if (
            empty($this->event_type) ||
			empty($this->selected_account) ||
            empty($this->payment_method) ||
            bccomp($this->amount, '0', 4) <= 0
        ) {
            return;
        }

        // 取得科目
        $target = Account::where('code', $this->selected_account)->where('is_active', true)->first();
        $payment = Account::where('code', $this->payment_method)->where('is_active', true)->first();

        if (!$target || !$payment) {
            $this->error('科目或資金帳戶已失效，請重新選擇');
            return;
        }

		// 驗證費用科目是否有規則
		try {
            $target->validateForEventType($this->event_type, [
                'amount' => (float)$this->amount,
            ]);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            $this->selected_account = '';
            $this->currentAccountName = '';
            return;
        }
	
        // 自動判斷收入/費用
        $this->entry_type = $target->isIncomeAccount() ? 'income' : 'expense';

        // 生成 T 字帳
        if ($this->entry_type === 'income') {
            $this->generated_lines = [
                [
                    'account_id'     => $payment->id,
                    'account_code'   => $payment->code,
                    'account_name'   => $payment->name,
                    'entry_type'     => 'debit',
                    'amount'         => $this->amount
                ],
                [
                    'account_id'     => $target->id,
                    'account_code'   => $target->code,
                    'account_name'   => $target->name,
                    'entry_type'     => 'credit',
                    'amount'         => $this->amount
                ],
            ];
        } else {
            $this->generated_lines = [
                [
                    'account_id'     => $target->id,
                    'account_code'   => $target->code,
                    'account_name'   => $target->name,
                    'entry_type'     => 'debit',
                    'amount'         => $this->amount
                ],
                [
                    'account_id'     => $payment->id,
                    'account_code'   => $payment->code,
                    'account_name'   => $payment->name,
                    'entry_type'     => 'credit',
                    'amount'         => $this->amount
                ],
            ];
        }

        // 平衡檢查
        $this->validateBalance();
    }

    // ==============================================
    // 借貸平衡驗證（會計鐵律）
    // ==============================================
    protected function validateBalance()
    {
        $dr = '0';
        $cr = '0';
        foreach ($this->generated_lines as $line) {
            if ($line['entry_type'] === 'debit') {
                $dr = bcadd($dr, $line['amount'], 4);
            } else {
                $cr = bcadd($cr, $line['amount'], 4);
            }
        }
        if (bccomp($dr, $cr, 4) !== 0) {
            $this->error("借貸不平衡：Dr {$dr} ≠ Cr {$cr}");
            $this->generated_lines = [];
        }
    }

    // ==============================================
    // 摘要改動 → 自動匹配科目
    // ==============================================
    public function updatedDescription()
    {
        $this->generated_lines = [];

        if (empty(trim($this->description))) {
            $this->reset(['selected_account', 'currentAccountName', 'amount', 'payment_method', 'entry_type']);
            return;
        }

        $this->autoMatchAccount();
        $this->refreshEntryPreview();
    }

    // ==============================================
    // 智能匹配：歷史 + 規則
    // ==============================================
    public function autoMatchAccount()
	{
		$desc = trim($this->description);
		if (mb_strlen($desc) < 2 || empty($this->event_type)) return;

		// 1. 歷史匹配：透過 JournalItem 關聯到 journals 表搜尋摘要
		$history = JournalItem::whereHas('journal', function ($q) use ($desc) { $q				
				->where('description', 'like', '%' . $desc . '%')
				->where('status', 'posted')
				->where('reference_type', $this->event_type);
			})
			->with('account')
			->latest()
			->first();

		if ($history && $history->account) {
			// 確認科目仍屬於此業務類型
			if ($this->isAccountValidForEventType($history->account)) {
				$this->selected_account = (string)$history->account->code;
				$this->currentAccountName = $history->account->name;
				$this->info('🧠 歷史匹配成功：' . $this->currentAccountName);
				return;
			}
		}

		// 2. 規則匹配 (AccountingRule)
		$rule = AccountingRule::where('event_type', $this->event_type)
			->where('is_active', true)
			->first();
			
		if ($rule) {
			// 可以根據摘要關鍵字進一步篩選
			// 或者直接取該業務類型最常用的科目
			$firstLine = AccountingRuleLine::where('accounting_rule_id', $rule->id)
				->whereHas('account', function($q) use ($desc) {
					$q->where('name', 'like', '%' . $desc . '%');
				})
				->first();
				
			if ($firstLine && $firstLine->account) {
				$this->selected_account = (string)$firstLine->account->code;
				$this->currentAccountName = $firstLine->account->name;
				$this->info('📜 規則匹配成功：' . $this->currentAccountName);
				return;
			}
		}

		// 無匹配時清空
		$this->selected_account = '';
		$this->currentAccountName = '未匹配，請手動選';
	}
	
	protected function isAccountValidForEventType(Account $account): bool
	{
		if (empty($this->event_type)) return false;
		
		$rule = AccountingRule::where('event_type', $this->event_type)
			->where('is_active', true)
			->first();
			
		if (!$rule) return false;
		
		return AccountingRuleLine::where('accounting_rule_id', $rule->id)
			->where('account_id', $account->id)
			->exists();
	}

    // ==============================================
    // 存檔（嚴格防呆）
    // ==============================================
    public function save()
    {
        $this->validate([
            'event_type'        => 'required|exists:accounting_rules,event_type',
			'entry_date'        => 'required|date',
            'description'       => 'required|max:500',
            'amount'            => 'required|numeric|min:0.0001',
            'selected_account'  => 'required|exists:accounts,code',
            'payment_method'    => 'required|exists:accounts,code',
        ]);
		
		// 儲存前再次驗證
		$target = Account::where('code', $this->selected_account)->first();
		if ($target && in_array($target->type, ['cost', 'profit'])) {
			try {
                $target->validateForEventType($this->event_type, [
                    'amount' => (float)$this->amount,
                ]);
			} catch (\RuntimeException $e) {
				$this->error($e->getMessage());
				return;
			}
		}

        if (empty($this->generated_lines)) {
            $this->error('請確認科目、金額、資金帳戶，生成分錄後再儲存');
            return;
        }

        try {
            DB::transaction(function () {
                if ($this->isEdit) {
                    $journal = Journal::lockForUpdate()->findOrFail($this->journalId);
                    if ($journal->status !== 'draft') throw new \RuntimeException('僅草稿可編輯');

                    $journal->update([
                        'entry_date'  => $this->entry_date,
                        'description' => $this->description,
                        'updated_by'  => auth()->user()?->name ?? 'System',
                    ]);
                    $journal->items()->delete();
                } else {
                    $journal = Journal::create([
                        'shop_id'        => 1,
                        'entry_date'     => $this->entry_date,
                        'description'    => $this->description,
                        'reference_type' => 'manual',
                        'status'         => 'draft',
                        'created_by'     => auth()->user()?->name ?? 'System',
                    ]);
                }

                // 寫入分錄
                foreach ($this->generated_lines as $line) {
                    $journal->items()->create([
                        'account_id' => $line['account_id'],
                        'debit'      => $line['entry_type'] === 'debit' ? $line['amount'] : '0',
                        'credit'     => $line['entry_type'] === 'credit' ? $line['amount'] : '0',
                        'currency'   => 'TWD',
                        'exchange_rate' => '1.0000',
                    ]);
                }
            }, 3);

            $this->success('✅ 儲存成功');
            $this->redirect(route('accountings.journals.index'));

        } catch (\Throwable $e) {
            Log::error('Journal save error', ['msg' => $e->getMessage()]);
            $this->error('儲存失敗：' . $e->getMessage());
        }
    }

    // ==============================================
    // 刪除草稿
    // ==============================================
    public function deleteDraft()
    {
        if (!$this->isEdit || !$this->journalId) return;

        try {
            DB::transaction(function () {
                $journal = Journal::lockForUpdate()->findOrFail($this->journalId);
                if ($journal->status !== 'draft') throw new \RuntimeException('僅草稿可刪除');
                $journal->items()->delete();
                $journal->delete();
            }, 3);

            $this->success('已刪除');
            $this->redirect(route('accountings.journals.index'));
        } catch (\Throwable $e) {
            $this->error('刪除失敗：' . $e->getMessage());
        }
    }

    // ==============================================
    // 選單載入
    // ==============================================
    protected function loadAccountOptions()
    {
        $this->accountOptions = Account::where('is_active', true)
            ->whereRaw('LENGTH(code) = 6')
            ->get()
            ->map(fn($a) => [
                'id'   => (string)$a->code,
                'name' => "【{$a->code}】{$a->name}"
            ])
            ->toArray();
    }

    protected function loadPaymentOptions()
    {
        $this->paymentOptions = Account::where('is_active', true)
            ->where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'like', '1001%')
                  ->orWhere('code', 'like', '1002%')
                  ->orWhere('code', 'like', '1012%');
            })
            ->whereRaw('LENGTH(code) = 6')
            ->orderBy('code')
            ->get()
            ->map(fn($a) => [
                'id'   => (string)$a->code,
                'name' => "【{$a->code}】{$a->name}"
            ])
            ->toArray();
    }

    // ==============================================
    // 載入舊草稿資料
    // ==============================================
    protected function loadJournalData(Journal $journal)
    {
        $journal->load('items.account');
        $this->entry_date = $journal->entry_date->format('Y-m-d');
        $this->description = $journal->description;

        $first = $journal->items->first();
        if ($first) {
            $this->selected_account = $first->account->code;
            $this->currentAccountName = $first->account->name;
        }

        foreach ($journal->items as $item) {
            if (str_starts_with($item->account->code, '1')) {
                $this->payment_method = $item->account->code;
                break;
            }
        }

        $totalDr = '0';
        foreach ($journal->items as $item) {
            $totalDr = bcadd($totalDr, $item->debit, 4);
        }
        $this->amount = $totalDr;

        // 載入後立刻生成預覽
        $this->refreshEntryPreview();
    }

    public function render()
    {
        return view('livewire.accountings.journal-create');
    }
}