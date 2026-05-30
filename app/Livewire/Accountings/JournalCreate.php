<?php

namespace App\Livewire\Accountings;

use App\Models\Account;
use App\Models\AccountingRule;
use App\Models\Journal;
use App\Models\JournalItem;
use App\Traits\HasAccountSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;

class JournalCreate extends Component
{
    use Toast, HasAccountSearch;

    public ?int $journalId = null;
    public bool $isEdit = false;

    // 傳票主檔欄位
    public string $entry_date = '';
    public string $description = '';
    public string $event_type = '';
    
    // 🆕 多科目分錄陣列（核心改動）
    public array $entries = [];
    public array $availableAccounts = [];
    
    // 業務類型選項
    public array $eventTypeOptions = [];

    // ==============================================
    // 生命週期
    // ==============================================
    public function mount(Journal $journal = null): void
    {
        $this->entry_date = now()->format('Y-m-d');
        $this->loadEventTypes();
        $this->loadAvailableAccounts();
        $this->search('');
		
        // 預設兩行（借方 + 貸方）
        $this->entries = [
            ['account_code' => '', 'account_name' => '', 'entry_type' => 'debit', 'amount' => '0', 'account_id' => null],
            ['account_code' => '', 'account_name' => '', 'entry_type' => 'credit', 'amount' => '0', 'account_id' => null],
        ];

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
     * 載入業務類型選項
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
     * 載入所有可用科目（長度為6碼且啟用中）
     */
    protected function loadAvailableAccounts(): void
    {
        $this->availableAccounts = Account::where('is_active', true)
            ->whereRaw('LENGTH(code) = 6')
            ->orderBy('code')
            ->get()
            ->map(fn($a) => [
                'id' => (string)$a->code,
                'code' => $a->code,
                'name' => "【{$a->code}】{$a->name}",
                'account_id' => $a->id,
            ])
            ->toArray();
    }

    /**
     * 載入既有草稿資料（編輯模式）
     */
    protected function loadJournalData(Journal $journal): void
    {
        $journal->load('items.account');
        $this->entry_date = $journal->entry_date->format('Y-m-d');
        $this->description = $journal->description;
        $this->event_type = $journal->reference_type === 'manual' ? '' : $journal->reference_type;
        
        // 將既有分錄項目轉換為 entries 陣列
        $this->entries = [];
        foreach ($journal->items as $item) {
            $isDebit = bccomp($item->debit, '0', 4) > 0;
            $this->entries[] = [
                'account_code' => $item->account->code,
                'account_name' => $item->account->name,
                'entry_type' => $isDebit ? 'debit' : 'credit',
                'amount' => $isDebit ? $item->debit : $item->credit,
                'account_id' => $item->account_id,
            ];
        }
        
        // 如果沒有項目，維持預設兩行
        if (empty($this->entries)) {
            $this->entries = [
                ['account_code' => '', 'account_name' => '', 'entry_type' => 'debit', 'amount' => '0', 'account_id' => null],
                ['account_code' => '', 'account_name' => '', 'entry_type' => 'credit', 'amount' => '0', 'account_id' => null],
            ];
        }
    }

    // ==============================================
    // 動態增刪分錄行（參考 JournalCorrect）
    // ==============================================

    public function addEntry(): void
    {
        $this->entries[] = [
            'account_code' => '',
            'account_name' => '',
            'entry_type' => 'debit',
            'amount' => '0',
            'account_id' => null,
        ];
		$this->search('');
    }

    public function removeEntry(int $index): void
    {
        if (count($this->entries) <= 2) {
            $this->warning('至少需要保留兩個科目（借貸雙方）');
            return;
        }
        unset($this->entries[$index]);
        $this->entries = array_values($this->entries);
    }

    public function updateEntryAccount(int $index, string $accountCode): void
    {
        $account = Account::where('code', $accountCode)->first();
        if ($account) {
            $this->entries[$index]['account_code'] = $accountCode;
            $this->entries[$index]['account_name'] = $account->name;
            $this->entries[$index]['account_id'] = $account->id;
        }
    }

    public function updateEntryType(int $index, string $type): void
    {
        $this->entries[$index]['entry_type'] = $type;
    }

    // ==============================================
    // 借貸平衡驗證
    // ==============================================
    protected function getTotals(): array
    {
        $totalDebit = '0';
        $totalCredit = '0';
        
        foreach ($this->entries as $entry) {
            $amount = $entry['amount'] ?? '0';
            if (bccomp($amount, '0', 4) <= 0) continue;
            
            if ($entry['entry_type'] === 'debit') {
                $totalDebit = bcadd($totalDebit, $amount, 4);
            } else {
                $totalCredit = bcadd($totalCredit, $amount, 4);
            }
        }
        
        return [$totalDebit, $totalCredit];
    }
    
    protected function isBalanced(): bool
    {
        [$totalDebit, $totalCredit] = $this->getTotals();
        return bccomp($totalDebit, $totalCredit, 4) === 0;
    }

    // ==============================================
    // 存檔
    // ==============================================
    public function save(): void
    {
        // 1. 驗證基本欄位
        $this->validate([
            'entry_date' => 'required|date',
            'description' => 'required|max:500',
        ]);
        
        // 2. 驗證每一行分錄
        $hasValidEntry = false;
        foreach ($this->entries as $index => $entry) {
            if (empty($entry['account_code'])) {
                $this->error("第 " . ($index + 1) . " 行請選擇會計科目");
                return;
            }
            if (bccomp($entry['amount'], '0', 4) <= 0) {
                // 金額為 0 的行可以忽略（不視為錯誤，只是跳過）
                continue;
            }
            $hasValidEntry = true;
        }
        
        if (!$hasValidEntry) {
            $this->error('請至少輸入一筆金額大於 0 的分錄');
            return;
        }
        
        // 3. 驗證借貸平衡
        if (!$this->isBalanced()) {
            [$totalDebit, $totalCredit] = $this->getTotals();
            $this->error("借貸不平衡：借方 {$totalDebit} ≠ 貸方 {$totalCredit}");
            return;
        }
        
        // 4. 儲存
        try {
            DB::transaction(function () {
                if ($this->isEdit) {
                    $journal = Journal::lockForUpdate()->findOrFail($this->journalId);
                    if ($journal->status !== 'draft') {
                        throw new \RuntimeException('僅草稿可編輯');
                    }
                    
                    $journal->update([
                        'entry_date' => $this->entry_date,
                        'description' => $this->description,
                        'updated_by' => auth()->user()?->name ?? 'System',
                    ]);
                    $journal->items()->delete();
                } else {
                    $journal = Journal::create([
                        'shop_id' => 1,
                        'entry_date' => $this->entry_date,
                        'description' => $this->description,
                        'reference_type' => 'manual',
                        'status' => 'draft',
                        'created_by' => auth()->user()?->name ?? 'System',
                    ]);
                }
                
                // 寫入分錄項目（只寫金額 > 0 的）
                foreach ($this->entries as $entry) {
                    if (bccomp($entry['amount'], '0', 4) <= 0) continue;
                    
                    $journal->items()->create([
                        'account_id' => $entry['account_id'],
                        'debit' => $entry['entry_type'] === 'debit' ? $entry['amount'] : '0',
                        'credit' => $entry['entry_type'] === 'credit' ? $entry['amount'] : '0',
                        'currency' => 'TWD',
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

    /**
     * 刪除草稿
     */
    public function deleteDraft(): void
    {
        if (!$this->isEdit || !$this->journalId) return;
        
        try {
            DB::transaction(function () {
                $journal = Journal::lockForUpdate()->findOrFail($this->journalId);
                if ($journal->status !== 'draft') {
                    throw new \RuntimeException('僅草稿可刪除');
                }
                $journal->items()->delete();
                $journal->delete();
            }, 3);
            
            $this->success('已刪除');
            $this->redirect(route('accountings.journals.index'));
        } catch (\Throwable $e) {
            $this->error('刪除失敗：' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.accountings.journal-create');
    }
}