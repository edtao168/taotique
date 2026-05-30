<?php
// app/Livewire/Accountings/JournalCorrect.php

namespace App\Livewire\Accountings;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalItem;
use App\Traits\HasAccountSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Mary\Traits\Toast;

class JournalCorrect extends Component
{
    use Toast, HasAccountSearch;

    public Journal $originalJournal;
    public ?int $originalJournalId = null;
    public ?string $event_type = null;

    // 更正表單
    public string $entry_date = '';
    public string $description = '';
    public string $correction_reason = '';
    
    // 多科目更正資料結構
    public array $originalItems = [];      // 原始分錄項目
    public array $correctedItems = [];     // 更正後分錄項目
    public array $availableAccounts = [];   // 可用科目選項
    
    // UI
    public array $diff_lines = [];
    public array $generated_lines = [];
    public array $paymentOptions = [];
    public string $correction_summary = '';
    public bool $showOriginalDrawer = false;

    public function mount(Journal $journal): void
    {        
        $this->originalJournal = $journal;
        $this->originalJournalId = $journal->id;
        
        // 防呆檢查
        if ($journal->status !== 'posted') {
            $this->error('僅已過帳分錄可更正');
            $this->redirect(route('accountings.journals.index'));
            return;
        }

        if ($journal->reference_type === 'correct') {
            $this->error('不可對更正分錄再次更正');
            $this->redirect(route('accountings.journals.index'));
            return;
        }

        $exists = Journal::where('corrects_journal_id', $this->originalJournalId)->exists();
        if ($exists) {
            $this->error('此分錄已經存在更正記錄');
            $this->redirect(route('accountings.journals.index'));
            return;
        }        

        // 🔧 修正：必須先載入原始資料，才能正確完成初始化
        $this->loadOriginalData();
        $this->loadAvailableAccounts();
        $this->loadPaymentOptions();
        $this->search('');
        
        // 🔧 修正：初始化完畢後立即計算一次差額分錄，讓畫面能立即呈現預覽
        $this->generateDiffEntries();
    }

    protected function loadOriginalData(): void
    {
        $this->originalJournal->load('items.account');
        $this->event_type = $this->originalJournal->reference_type;
        $this->entry_date = now()->format('Y-m-d');
        $this->description = '[更正] ' . $this->originalJournal->description;

        $this->originalItems = [];
        $this->correctedItems = [];
        
        foreach ($this->originalJournal->items as $item) {
            $isDebit = bccomp($item->debit, '0', 4) > 0;
            $amount = $isDebit ? $item->debit : $item->credit;
            
            $itemData = [
                'id' => $item->id,
                'account_id' => $item->account_id,
                'account_code' => $item->account->code,
                'account_name' => $item->account->name,
                'entry_type' => $isDebit ? 'debit' : 'credit',
                'amount' => $amount,
                'original_amount' => $amount,
            ];
            
            $this->originalItems[] = $itemData;
            
            // 🔧 修正：統一鍵值名稱為 account_code 與 account_id，確保與前端 blade 完美對應
            $this->correctedItems[] = [
                'id' => $item->id,
                'account_id' => $item->account_id,
                'account_code' => $item->account->code,
                'account_name' => '【' . $item->account->code . '】' . $item->account->name,
                'entry_type' => $isDebit ? 'debit' : 'credit',
                'amount' => $amount,
                'original_amount' => $amount,
            ];
        }
    }

    protected function loadAvailableAccounts(): void
    {
        $accounts = Account::where('is_active', true)
            ->whereRaw('LENGTH(code) <= 6')
            ->get();

        $this->availableAccounts = $accounts->map(fn ($account) => [
            'id' => $account->id,
            'code' => $account->code,
            'name' => '【' . $account->code . '】' . $account->name,
            'type' => $account->type,
            'normal_side' => $account->normal_side,
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
            'id' => $account->id,
            'code' => $account->code,
            'name' => '【' . $account->code . '】' . $account->name,
        ])->toArray();
    }

    /**
     * 監聽 Livewire 屬性更新（當使用者變更下拉科目或借貸別時自動觸發）
     */
    public function updatedCorrectedItems($value, $key)
    {
        // 解析格式如 "0.account_code" 或 "1.entry_type"
        if (str_contains($key, '.')) {
            list($index, $field) = explode('.', $key);
            
            if ($field === 'account_code') {
                $this->updateItemAccount((int)$index, $value);
            } else {
                $this->generateDiffEntries();
            }
        }
    }

    /**
     * 更新某個科目的金額
     */
    public function updateItemAmount($index, $amount)
    {
        $this->correctedItems[$index]['amount'] = $amount;
        $this->generateDiffEntries();
    }

    /**
     * 更新某個科目的科目選擇
     */
    public function updateItemAccount($index, $accountCode)
    {
        $account = collect($this->availableAccounts)->firstWhere('code', $accountCode);
        if ($account) {
            $this->correctedItems[$index]['account_code'] = $accountCode;
            $this->correctedItems[$index]['account_id'] = $account['id'];
            $this->correctedItems[$index]['account_name'] = $account['name'];
            
            // 根據科目正常餘額方向自動設定借貸別
            $normalSide = $account['normal_side'] ?? 'debit';
            $this->correctedItems[$index]['entry_type'] = $normalSide;
        }
        $this->generateDiffEntries();
    }

    /**
     * 新增一行科目
     */
    public function addItem()
    {
        $this->correctedItems[] = [            
            'id' => null,
            'account_id' => null,
            'account_code' => '',
            'account_name' => '',
            'entry_type' => 'debit',
            'amount' => '0',            
            'original_amount' => '0',
        ];
        $this->search('');
        $this->generateDiffEntries();
    }

    /**
     * 刪除一行科目
     */
    public function removeItem($index)
    {
        if (count($this->correctedItems) <= 2) {
            $this->warning('至少需要保留兩個科目（借貸雙方）');
            return;
        }
        
        unset($this->correctedItems[$index]);
        $this->correctedItems = array_values($this->correctedItems);
        $this->generateDiffEntries();
    }

    /**
     * 計算更正後的借貸總額
     */
    protected function calculateTotals(): array
    {
        $totalDebit = '0';
        $totalCredit = '0';
        
        foreach ($this->correctedItems as $item) {
            if (($item['entry_type'] === 'debit')) {
                $totalDebit = bcadd($totalDebit, $item['amount'] ?? '0', 4);
            } else {
                $totalCredit = bcadd($totalCredit, $item['amount'] ?? '0', 4);
            }
        }
        
        return [$totalDebit, $totalCredit];
    }

    /**
     * 核心邏輯：產生差額分錄（支援多科目）
     */
    protected function generateDiffEntries(): void
    {
        $this->diff_lines = [];
        $this->generated_lines = [];
        
        // 建立原始科目的索引映射
        $originalMap = [];
        foreach ($this->originalItems as $item) {
            $key = $item['account_code'] . '_' . $item['entry_type'];
            $originalMap[$key] = $item;
        }
        
        $hasAnyChange = false;
        $correctionParts = [];
        
        // 比對每個更正項目與原始項目的差異
        foreach ($this->correctedItems as $item) {
            if (empty($item['account_code'])) {
                continue; // 忽略尚未選取科目的空行
            }

            // 尋找相同科目在原分錄中是否存在
            $originalItem = null;
            foreach ($this->originalItems as $orig) {
                if ($orig['account_code'] === $item['account_code']) {
                    $originalItem = $orig;
                    break;
                }
            }
            
            $originalAmount = $originalItem ? $originalItem['amount'] : '0';
            $newAmount = $item['amount'] ?? '0';
            
            // 檢查科目與方向是否改變
            $directionChanged = $originalItem ? ($originalItem['entry_type'] !== $item['entry_type']) : false;
            $amountDiff = bcsub($newAmount, $originalAmount, 4);
            $amountChanged = bccomp($amountDiff, '0', 4) !== 0;
            
            if (!$originalItem || $directionChanged || $amountChanged) {
                $hasAnyChange = true;
                
                if ($amountChanged && $originalItem && !$directionChanged) {
                    $direction = bccomp($amountDiff, '0', 4) > 0 ? '增加' : '減少';
                    $absDiff = abs($amountDiff);
                    $correctionParts[] = "{$item['account_name']} {$direction} {$absDiff}";
                }
                
                // 如果原科目存在，但金額改變或方向變了，先全額沖銷原科目
                if ($originalItem) {
                    $this->diff_lines[] = [
                        'account_id' => $originalItem['account_id'],
                        'account_code' => $originalItem['account_code'],
                        'account_name' => $originalItem['account_name'],
                        'entry_type' => $originalItem['entry_type'] === 'debit' ? 'credit' : 'debit',
                        'amount' => $originalAmount,
                        'action' => 'cancel',
                        'action_label' => '沖銷原分錄',
                        'color' => 'error',
                        'icon' => 'o-x-circle',
                    ];
                }
                
                // 建立新的更正後項目
                if (bccomp($newAmount, '0', 4) !== 0) {
                    $this->diff_lines[] = [
                        'account_id' => $item['account_id'],
                        'account_code' => $item['account_code'],
                        'account_name' => str_replace(['【', '】'], '', explode('】', $item['account_name'])[1] ?? $item['account_name']),
                        'entry_type' => $item['entry_type'],
                        'amount' => $newAmount,
                        'action' => 'confirm',
                        'action_label' => $originalItem ? '修正為此金額' : '新增科目分錄',
                        'color' => 'success',
                        'icon' => 'o-check-circle',
                    ];
                }
            }
        }
        
        // 檢查是否有被刪除的原始項目
        foreach ($this->originalItems as $originalItem) {
            $found = false;
            foreach ($this->correctedItems as $item) {
                if ($item['account_code'] === $originalItem['account_code']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $hasAnyChange = true;
                $correctionParts[] = "刪除 {$originalItem['account_name']}";
                $this->diff_lines[] = [
                    'account_id' => $originalItem['account_id'],
                    'account_code' => $originalItem['account_code'],
                    'account_name' => $originalItem['account_name'],
                    'entry_type' => $originalItem['entry_type'] === 'debit' ? 'credit' : 'debit',
                    'amount' => $originalItem['amount'],
                    'action' => 'cancel',
                    'action_label' => '刪除原分錄',
                    'color' => 'error',
                    'icon' => 'o-x-circle',
                ];
            }
        }
        
        // 驗證更正差額借貸平衡
        $diffDebit = '0';
        $diffCredit = '0';
        foreach ($this->diff_lines as $line) {
            if ($line['entry_type'] === 'debit') {
                $diffDebit = bcadd($diffDebit, $line['amount'], 4);
            } else {
                $diffCredit = bcadd($diffCredit, $line['amount'], 4);
            }
        }
        
        if (bccomp($diffDebit, $diffCredit, 4) !== 0) {
            $this->diff_lines = [];
            $this->correction_summary = '⚠️ 更正後差額借貸不平衡，請確認各科目金額配置';
            return;
        }
        
        $this->correction_summary = $hasAnyChange 
            ? '更正內容摘要：' . implode('、', $correctionParts) . '。'
            : '分錄內容無任何變動';
        
        $this->generateFullPreview();
    }

    protected function generateFullPreview(): void
    {
        $this->generated_lines = [];
        
        foreach ($this->originalItems as $item) {
            $this->generated_lines[] = [
                'account_code' => $item['account_code'],
                'account_name' => $item['account_name'],
                'entry_type' => $item['entry_type'],
                'amount' => $item['amount'],
                'is_original' => true,
            ];
        }
        
        foreach ($this->diff_lines as $line) {
            $this->generated_lines[] = array_merge($line, ['is_original' => false]);
        }
    }

    /**
     * 儲存更正分錄
     */
    public function save(): void
    {
        $exists = Journal::where('corrects_journal_id', $this->originalJournalId)->exists();
        if ($exists) {
            $this->error('此分錄已經存在更正記錄');
            return;
        }
        
        $this->validate([
            'entry_date' => 'required|date',
            'correction_reason' => 'required|string|min:5|max:500',
        ]);
        
        foreach ($this->correctedItems as $index => $item) {
            if (empty($item['account_code'])) {
                $this->error("第 " . ($index + 1) . " 行未選擇有效會計科目");
                return;
            }
			if (bccomp($item['amount'], '0', 4) == 0) {
				$this->error("第 " . ($index + 1) . " 行的金額不能為 0");
				return;
			}
        }
        
        list($totalDebit, $totalCredit) = $this->calculateTotals();
        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            $this->error('更正後總體借貸不平衡：借方 = ' . $totalDebit . '，貸方 = ' . $totalCredit);
            return;
        }
        
        if (empty($this->diff_lines)) {
            $this->error('無任何變更，無需產生更正憑證');
            return;
        }
        
        try {
            DB::transaction(function () {
                $original = Journal::lockForUpdate()->findOrFail($this->originalJournalId);
                
                if ($original->status !== 'posted') {
                    throw new \RuntimeException('原始分錄狀態已變更，非已過帳狀態');
                }
                
                $correction = Journal::create([
                    'shop_id' => $original->shop_id,
                    'currency' => $original->currency,
                    'exchange_rate' => $original->exchange_rate,
                    'entry_date' => $this->entry_date,
                    'description' => $this->description,
                    'reference_type' => 'correct',
                    'corrects_journal_id' => $original->id,
                    'correction_reason' => $this->correction_reason,
                    'status' => 'posted',
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
                        'shop_id' => $original->shop_id,
                    ]);
                }
                
                Log::info('Journal corrected successfully', [
                    'original_id' => $original->id,
                    'correction_id' => $correction->id,
                ]);
            });
            
            $this->success('✅ 更正分錄已成功建立並過帳');
            $this->redirect(route('accountings.journals.index'));
            
        } catch (\Throwable $e) {
            Log::error('JournalCorrect save failed', [
                'error' => $e->getMessage(),
                'original_id' => $this->originalJournalId,
            ]);
            $this->error('更正程序失敗：' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.accountings.journal-correct');
    }
}