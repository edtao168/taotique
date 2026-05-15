<?php

// app/Livewire/Accountings/JournalCorrect.php
// [費曼註釋：此元件只處理「已過帳分錄」的更正。draft 不可進入此元件]

namespace App\Livewire\Accountings;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;

class JournalCorrect extends Component
{
    use Toast;

    public Journal $originalJournal;
    public ?int $originalJournalId = null;

    // 更正表單
    public string $entry_date = '';
    public string $description = '';
    public string $amount = '';           // 正確金額
    public string $originalAmount = '';
    public string $selected_account = '';
    public string $payment_method = '';
    public string $entry_type = '';
    public string $correction_reason = '';
	public string $correction_summary = '';
	
    // UI
    public array $generated_lines = [];
    public array $diff_lines = [];       // 差額
    public array $paymentOptions = [];
    public array $accountOptions = [];
	public bool $showOriginalDrawer = false;
	

    public function mount(Journal $journal): void
    {        
		$this->originalJournal = $journal;
        $this->originalJournalId = $journal->id;
		
		// 防呆——必須是posted
        if ($journal->status !== 'posted') {
            $this->error('僅已過帳分錄可更正');
            $this->redirect(route('accountings.journals.index'));
            return;
        }

        // 防呆——防止對「更正分錄」再次更正
        if ($journal->reference_type === 'correct') {
            $this->error('不可對更正分錄再次更正');
            $this->redirect(route('accountings.journals.index'));
            return;
        }

        // 防呆——防止對有「更正分錄」的憑證再次更正
		$exists = Journal::where('corrects_journal_id', $this->originalJournalId)->exists();
		if ($exists) {
			$this->error('此分錄已經存在更正記錄...');
			return;
		}		
		
        $this->loadOriginalData();
        $this->loadPaymentOptions();
        $this->loadAccountOptions();
    }

    protected function loadOriginalData(): void
    {
        $this->originalJournal->load('items.account');

        $this->entry_date = now()->format('Y-m-d');
        $this->description = '[更正] ' . $this->originalJournal->description;

        // 計算原始金額
        $totalDebit = '0';
        foreach ($this->originalJournal->items as $item) {
            $totalDebit = bcadd($totalDebit, $item->debit, 4);
        }
        $this->originalAmount = $totalDebit;
        $this->amount = $totalDebit; // 預設帶入原金額，使用者修改後產生差額

        $firstItem = $this->originalJournal->items->first();
        if ($firstItem) {
            $this->selected_account = $firstItem->account->code;
            $this->entry_type = bccomp($firstItem->debit, '0', 4) > 0 ? 'expense' : 'income';
        }

        foreach ($this->originalJournal->items as $item) {
            if (str_starts_with($item->account->code, '1')) {
                $this->payment_method = $item->account->code;
                break;
            }
        }

        $this->generateDiffEntries();
    }

    protected function loadAccountOptions(): void
    {
        $accounts = Account::where('is_active', true)
            ->whereRaw('LENGTH(code) = 6')
            ->get();

        $this->accountOptions = $accounts->map(fn ($account) => [
            'id' => (string) $account->code,
            'name' => '【' . $account->code . '】' . $account->name,
            'type' => str_starts_with($account->code, '5') ? 'income' : 'expense',
        ])->toArray();
    }

    protected function loadPaymentOptions(): void
    {
        $paymentAccounts = Account::where('is_active', true)
            ->where('type', 'asset')
            ->where(function ($q) {
                $q->where('code', 'like', '1001%')
                  ->orWhere('code', 'like', '1002%')
                  ->orWhere('code', 'like', '1012%');
            })
            ->whereRaw('LENGTH(code) = 6')
            ->orderBy('code')
            ->get();

        $this->paymentOptions = $paymentAccounts->map(fn ($account) => [
            'id' => (string) $account->code,
            'name' => '【' . $account->code . '】' . $account->name,
        ])->toArray();
    }

    public function updatedAmount(): void
    {
        $this->generateDiffEntries();
    }

    public function updatedPaymentMethod(): void
    {
        $this->generateDiffEntries();
    }

    public function updatedSelectedAccount(): void
    {
        $account = collect($this->accountOptions)->firstWhere('id', $this->selected_account);
        $this->entry_type = $account ? $account['type'] : 'expense';
        $this->generateDiffEntries();
    }

    /**
     * [費曼註釋：計算差額分錄，只產生「需要調整」的部分。這是會計更正的核心邏輯]
     * 
     * T字帳範例：
     * 原始：借 業務招待費 1000 / 貸 現金 1000（錯誤，應為 1200）
     * 更正：借 業務招待費 200 / 貸 現金 200（只補差額 200）
     */
	protected function generateDiffEntries(): void
	{
		$this->diff_lines = [];
		$this->generated_lines = [];

		if (empty($this->selected_account) || empty($this->payment_method)) {
			return;
		}

		$paymentAccount = Account::where('code', $this->payment_method)->first();
		$targetAccount = Account::where('code', $this->selected_account)->first();
		if (!$paymentAccount || !$targetAccount) return;

		// 取得原始資料
		$firstItem = $this->originalJournal->items->first();
		$originalTargetCode = $firstItem ? $firstItem->account->code : '';
		
		$originalPaymentCode = '';
		foreach ($this->originalJournal->items as $item) {
			if (str_starts_with($item->account->code, '1')) {
				$originalPaymentCode = $item->account->code;
				break;
			}
		}

		$isAccountChanged = $originalTargetCode !== $this->selected_account;
		$isPaymentChanged = $originalPaymentCode !== $this->payment_method;
		$isAmountChanged  = bccomp($this->amount, $this->originalAmount, 4) !== 0;

		if (!$isAccountChanged && !$isPaymentChanged && !$isAmountChanged) {
			return;
		}

		// ========== 產生口語化摘要 ==========
		$this->correction_summary = $this->buildSummary(
			$isAmountChanged, $isAccountChanged, $isPaymentChanged,
			$originalTargetCode, $this->selected_account,
			$originalPaymentCode, $this->payment_method,
			$this->originalAmount, $this->amount
		);

		// A. 沖銷原始分錄（標記為紅色/取消）
		foreach ($this->originalJournal->items as $item) {
			$isDebit = bccomp($item->debit, '0', 4) > 0;
			$this->diff_lines[] = [
				'account_id'    => $item->account_id,
				'account_code'  => $item->account->code,
				'account_name'  => $item->account->name,
				'entry_type'    => $isDebit ? 'credit' : 'debit',
				'amount'        => $isDebit ? $item->debit : $item->credit,
				'action'        => 'cancel',           // ← 動作標記
				'action_label'  => '取消這筆',          // ← 口語標籤
				'color'         => 'error',           // ← 紅色
				'icon'          => 'o-x-circle',      // ← 叉叉圖示
			];
		}

		// B. 建立新分錄（標記為綠色/確認）
		if ($this->entry_type === 'expense') {
			$this->diff_lines[] = [
				'account_id'    => $targetAccount->id,
				'account_code'  => $targetAccount->code,
				'account_name'  => $targetAccount->name,
				'entry_type'    => 'debit',
				'amount'        => $this->amount,
				'action'        => 'confirm',         // ← 動作標記
				'action_label'  => '改為這筆',         // ← 口語標籤
				'color'         => 'success',         // ← 綠色
				'icon'          => 'o-check-circle', // ← 打勾圖示
			];
			$this->diff_lines[] = [
				'account_id'    => $paymentAccount->id,
				'account_code'  => $paymentAccount->code,
				'account_name'  => $paymentAccount->name,
				'entry_type'    => 'credit',
				'amount'        => $this->amount,
				'action'        => 'confirm',
				'action_label'  => '改為這筆',
				'color'         => 'success',
				'icon'          => 'o-check-circle',
			];
		} else {
			// income...
			$this->diff_lines[] = [
				'account_id'    => $paymentAccount->id,
				'account_code'  => $paymentAccount->code,
				'account_name'  => $paymentAccount->name,
				'entry_type'    => 'debit',
				'amount'        => $this->amount,
				'action'        => 'confirm',
				'action_label'  => '改為這筆',
				'color'         => 'success',
				'icon'          => 'o-check-circle',
			];
			$this->diff_lines[] = [
				'account_id'    => $targetAccount->id,
				'account_code'  => $targetAccount->code,
				'account_name'  => $targetAccount->name,
				'entry_type'    => 'credit',
				'amount'        => $this->amount,
				'action'        => 'confirm',
				'action_label'  => '改為這筆',
				'color'         => 'success',
				'icon'          => 'o-check-circle',
			];
		}

		$this->generateFullPreview($paymentAccount, $targetAccount);
	}

	/**
	 * 產生口語化更正摘要
	 */
	protected function buildSummary(
		bool $isAmountChanged, bool $isAccountChanged, bool $isPaymentChanged,
		string $oldAccount, string $newAccount,
		string $oldPayment, string $newPayment,
		string $oldAmount, string $newAmount
	): string {
		$parts = [];

		if ($isAmountChanged) {
			$diff = bcsub($newAmount, $oldAmount, 4);
			$direction = bccomp($diff, '0', 4) > 0 ? '增加' : '減少';
			$absDiff = bccomp($diff, '0', 4) > 0 ? $diff : bcsub('0', $diff, 4);
			$parts[] = "金額從 {$oldAmount} {$direction} 為 {$newAmount}（差額 {$absDiff}）";
		}

		if ($isAccountChanged) {
			$oldName = Account::where('code', $oldAccount)->value('name') ?? $oldAccount;
			$newName = Account::where('code', $newAccount)->value('name') ?? $newAccount;
			$parts[] = "科目從「{$oldName}」改為「{$newName}」";
		}

		if ($isPaymentChanged) {
			$oldName = Account::where('code', $oldPayment)->value('name') ?? $oldPayment;
			$newName = Account::where('code', $newPayment)->value('name') ?? $newPayment;
			$parts[] = "付款帳戶從「{$oldName}」改為「{$newName}」";
		}

		return empty($parts) ? '' : implode('，', $parts) . '。';
	}

    /**
     * [費曼註釋：產生「原始 + 更正」的完整預覽，讓使用者確認最終淨額正確]
     */
    protected function generateFullPreview(Account $paymentAccount, Account $targetAccount): void
    {
        $this->generated_lines = [];
		// 原始分錄（標記為原始）
        foreach ($this->originalJournal->items as $item) {
            $this->generated_lines[] = [
                'account_id' => $item->account_id,
                'account_code' => $item->account->code,
                'account_name' => $item->account->name,
                'entry_type' => bccomp($item->debit, '0', 4) > 0 ? 'debit' : 'credit',
                'amount' => bccomp($item->debit, '0', 4) > 0 ? $item->debit : $item->credit,
                'is_original' => true,
            ];
        }

        // 差額分錄（標記為更正）
        foreach ($this->diff_lines as $line) {
            $this->generated_lines[] = array_merge($line, ['is_original' => false]);
        }
    }

    /**
     * [費曼註釋：儲存更正分錄。核心原則：不碰原始分錄，只產生新的差額分錄]
     */
    public function save(): void
    {
        // 在執行 transaction 前再次檢查，如果已經更正過，則不允許再次儲存
		$exists = Journal::where('corrects_journal_id', $this->originalJournalId)->exists();
		if ($exists) {
			$this->error('此分錄已經存在更正記錄，請先刪除舊的更正分錄。');
			return;
		}
		
		$this->validate([
            'entry_date' => 'required|date',
            'correction_reason' => 'required|string|min:5|max:500',
            'amount' => 'required|numeric|min:0',
            'selected_account' => 'required|exists:accounts,code',
            'payment_method' => 'required|exists:accounts,code',
        ]);

        if (empty($this->diff_lines)) {
            $this->error('金額無變更，無需更正');
            return;
        }

        try {
            DB::transaction(function () {
                // [費曼註釋：鎖定原始分錄，防止併發重複更正]
                $original = Journal::lockForUpdate()->findOrFail($this->originalJournalId);

                if ($original->status !== 'posted') {
                    throw new \RuntimeException('原始分錄狀態已變更');
                }

                // [費曼註釋：計算總差額借貸，確保平衡]
                $totalDebit = '0';
                $totalCredit = '0';
                foreach ($this->diff_lines as $line) {
                    if ($line['entry_type'] === 'debit') {
                        $totalDebit = bcadd($totalDebit, $line['amount'], 4);
                    } else {
                        $totalCredit = bcadd($totalCredit, $line['amount'], 4);
                    }
                }

                if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
                    throw new \RuntimeException('差額分錄借貸不平衡');
                }

                // 建立更正分錄
                $correction = Journal::create([
                    'shop_id' => $original->shop_id,
                    'currency' => $original->currency,
                    'exchange_rate' => $original->exchange_rate, // [費曼註釋：繼承原始匯率，不可使用即期匯率]
                    'entry_date' => $this->entry_date,
                    'description' => $this->description,
                    'reference_type' => 'correct',
                    'corrects_journal_id' => $original->id,
                    'correction_reason' => $this->correction_reason,
                    'status' => 'posted', // [費曼註釋：更正分錄直接過帳，不可再修改]
                    'created_by' => auth()->user()?->name ?? 'System',
                ]);

                foreach ($this->diff_lines as $line) {
                    JournalItem::create([
                        'journal_id' => $correction->id,
                        'account_id' => $line['account_id'],
                        'debit' => $line['entry_type'] === 'debit' ? $line['amount'] : '0',
                        'credit' => $line['entry_type'] === 'credit' ? $line['amount'] : '0',
                        'currency' => $original->currency,
                        'exchange_rate' => $original->exchange_rate,
                    ]);
                }

                Log::info('Journal corrected', [
                    'original_id' => $original->id,
                    'correction_id' => $correction->id,
                    'reason' => $this->correction_reason,
                ]);
            }, 3);

            $this->success('✅ 更正分錄已建立');
            $this->redirect(route('accountings.journals.index'));

        } catch (\Throwable $e) {
            Log::error('JournalCorrect save failed', [
                'error' => $e->getMessage(),
                'original_id' => $this->originalJournalId,
            ]);
            $this->error('更正失敗：' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.accountings.journal-correct');
    }
}