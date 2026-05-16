<?php

namespace App\Livewire\Accountings;

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
	
    public array $generated_lines = [];    
    public array $paymentOptions = [];
    public array $accountOptions = [];

    // ==============================================
    // 生命週期
    // ==============================================
    public function mount(Journal $journal = null): void
    {
        $this->entry_date = now()->format('Y-m-d');
        $this->loadPaymentOptions();
        $this->loadAccountOptions();

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
            empty($this->selected_account)
            || empty($this->payment_method)
            || bccomp($this->amount, '0', 4) <= 0
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
		if (mb_strlen($desc) < 2) return;

		// 1. 歷史匹配：透過 JournalItem 關聯到 journals 表搜尋摘要
		$history = JournalItem::whereHas('journal', function ($q) use ($desc) {
				// 這裡必須明確指定在 journals 表搜尋，且排除草稿
				$q->where('description', 'like', '%' . $desc . '%');
			})
			->with('account')
			->latest()
			->first();

		if ($history && $history->account) {
			$this->selected_account = (string)$history->account->code;
			$this->currentAccountName = $history->account->name;
			// 觸發 Toast 通知確認執行成功
			$this->info('🧠 歷史匹配成功：' . $this->currentAccountName);
			return;
		}

		// 2. 規則匹配 (AccountingRule)
		$rules = AccountingRule::all();
		foreach ($rules as $rule) {
			if (str_contains($desc, $rule->keyword)) {
				$account = Account::where('code', $rule->account_id)->first();
				if ($account) {
					$this->selected_account = (string)$account->code;
					$this->currentAccountName = $account->name;
					$this->info('📜 規則匹配成功：' . $this->currentAccountName);
					return;
				}
			}
		}

		// 無匹配時清空
		$this->selected_account = '';
		$this->currentAccountName = '未匹配，請手動選';
	}

    // ==============================================
    // 存檔（嚴格防呆）
    // ==============================================
    public function save()
    {
        $this->validate([
            'entry_date'        => 'required|date',
            'description'       => 'required|max:500',
            'amount'            => 'required|numeric|min:0.0001',
            'selected_account'  => 'required|exists:accounts,code',
            'payment_method'    => 'required|exists:accounts,code',
        ]);

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