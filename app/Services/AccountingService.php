<?php

// app/Services/AccountingService.php
// [費曼註釋：會計系統核心服務，處理所有自動分錄產生與來源解析]

namespace App\Services;

use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountingService
{
    /**
     * [費曼註釋：來源類型對應的 Model 類別與單據編號欄位]
     */
    private const SOURCE_MAP = [
        'purchase' => [
            'model' => \App\Models\Purchase::class,
            'number_field' => 'purchase_number',
        ],
        'sale' => [
            'model' => \App\Models\Sale::class,
            'number_field' => 'invoice_number',
        ],
		'sale_revenue' => [  // 銷售收入確認
            'model' => \App\Models\Sale::class,
            'number_field' => 'invoice_number',
        ],
        'sale_cost' => [     // 銷售成本結轉
            'model' => \App\Models\Sale::class,
            'number_field' => 'invoice_number',
        ],
        'purchase_return' => [
            'model' => \App\Models\PurchaseReturn::class,
            'number_field' => 'return_number',
        ],
        'sale_return' => [
            'model' => \App\Models\SaleReturn::class,
            'number_field' => 'return_number',
        ],
        'conversion' => [
            'model' => \App\Models\Conversion::class,
            'number_field' => 'conversion_number',
        ],
    ];

    /**
     * 解析來源單據編號
     * [費曼註釋：來源單據可能被刪除，必須優雅處理，不可影響分錄顯示]
     */
    public function resolveSourceNumber(string $referenceType, ?int $referenceId): ?string
    {
        if ($referenceType === 'manual' || $referenceType === 'correct' || $referenceId === null) {
            return null;
        }

        $config = self::SOURCE_MAP[$referenceType] ?? null;
        if (!$config) {
            Log::warning('Unknown journal reference_type', [
                'type' => $referenceType,
                'id' => $referenceId,
            ]);
            return null;
        }

        try {
            $record = $config['model']::withTrashed()->find($referenceId); // [費曼註釋：包含軟刪除，確保歷史分錄可追溯]
            
            if (!$record) {
                return '[已刪除]'; // [費曼註釋：標記孤兒分錄，審計時可識別]
            }

            $number = $record->{$config['number_field']} ?? $record->id;
            
            // [費曼註釋：軟刪除標記，提醒使用者來源單據已作廢]
            if (method_exists($record, 'trashed') && $record->trashed()) {
                return $number . ' [已作廢]';
            }

            return $number;

        } catch (\Throwable $e) {
            Log::error('Resolve source number failed', [
                'type' => $referenceType,
                'id' => $referenceId,
                'error' => $e->getMessage(),
            ]);
            return '[錯誤]';
        }
    }

    /**
     * 取得人類可讀的來源類型標籤
     */
    public function getSourceTypeLabel(string $referenceType): string
    {
        return match ($referenceType) {
            'manual' => '手動輸入',
            'auto' => '自動分錄',
            'correct' => '更正分錄',
            'purchase' => '採購進貨',
            'sale' => '銷售出貨',
			'sale_revenue' => '銷售收入確認',
            'sale_cost' => '銷售成本結轉',
            'purchase_return' => '採購退回',
            'sale_return' => '銷售退回',
            'conversion' => '拆裝組合',
            default => $referenceType ?? '未知',
        };
    }

    /**
     * 產生自動分錄（核心方法）
     * [費曼註釋：所有業務單據觸發自動分錄必須經由此方法，確保一致性]
     * 
     * T字帳範例（採購入庫）：
     * 借：庫存商品 10,000.0000
     * 貸：應付帳款 10,000.0000
     */
    public function createAutoJournal(
        string $referenceType,
        int $referenceId,
        string $description,
        array $entries, // [['account_id' => 1, 'debit' => '10000', 'credit' => '0'], ...]
        string $currency = 'TWD',
        string $exchangeRate = '1.0000',
        ?string $entryDate = null
    ): Journal {
        // [費曼註釋：驗證借貸平衡，這是會計不可違反的原則]
        $this->validateBalance($entries);

        return DB::transaction(function () use (
            $referenceType, $referenceId, $description, $entries,
            $currency, $exchangeRate, $entryDate
        ) {
            // [費曼註釋：檢查是否已存在分錄，防止重複產生]
            $existing = Journal::where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw new \RuntimeException("該單據已存在分錄 #{$existing->id}，不可重複產生");
            }

            $totalDebit = '0';
            $totalCredit = '0';

            foreach ($entries as $entry) {
                $totalDebit = bcadd($totalDebit, $entry['debit'] ?? '0', 4);
                $totalCredit = bcadd($totalCredit, $entry['credit'] ?? '0', 4);
            }

            $journal = Journal::create([
                'shop_id'  => auth()->user()->shop_id ?? 1,
                'currency' => $currency,
                'exchange_rate' => $exchangeRate,
                'entry_date' => $entryDate ?? now()->format('Y-m-d'),
                'description' => $description,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'status' => 'posted', // [費曼註釋：自動分錄直接過帳，不可修改]
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_by' => auth()->user()?->name ?? 'System',
            ]);

            foreach ($entries as $index => $entry) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'] ?? '0',
                    'credit' => $entry['credit'] ?? '0',
                    'currency' => $currency,
                    'exchange_rate' => $exchangeRate,
                    'sort_order' => $index,
					'note' => $entry['note'] ?? null,
                ]);
            }

            Log::info('Auto journal created', [
                'journal_id' => $journal->id,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
            ]);

            return $journal;
        }, 3);
    }

    /**
     * 產生更正分錄（差額法）
     * [費曼註釋：只調整差額，不動原始分錄。適合金額錯誤、科目錯誤]
     */
    public function createCorrectionJournal(
        Journal $originalJournal,
        array $correctedEntries, // 更正後的完整分錄
        string $reason
    ): Journal {
        if ($originalJournal->status !== 'posted') {
            throw new \InvalidArgumentException('僅已過帳分錄可更正');
        }

        if ($originalJournal->reference_type === 'correct') {
            throw new \InvalidArgumentException('不可對更正分錄再次更正');
        }

        // 計算差額
        $diffEntries = $this->calculateDiff($originalJournal, $correctedEntries);

        if (empty($diffEntries)) {
            throw new \InvalidArgumentException('無差異，無需更正');
        }

        return DB::transaction(function () use ($originalJournal, $diffEntries, $reason) {
            $originalJournal->lockForUpdate();

            $totalDebit = '0';
            $totalCredit = '0';

            foreach ($diffEntries as $entry) {
                if (bccomp($entry['debit'] ?? '0', '0', 4) > 0) {
                    $totalDebit = bcadd($totalDebit, $entry['debit'], 4);
                }
                if (bccomp($entry['credit'] ?? '0', '0', 4) > 0) {
                    $totalCredit = bcadd($totalCredit, $entry['credit'], 4);
                }
            }

            if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
                throw new \RuntimeException('差額分錄借貸不平衡');
            }

            $correction = Journal::create([
                'shop_id' => $originalJournal->shop_id,
                'currency' => $originalJournal->currency,
                'exchange_rate' => $originalJournal->exchange_rate, // [費曼註釋：繼承原始匯率]
                'entry_date' => now()->format('Y-m-d'),
                'description' => '[更正] ' . $originalJournal->description,
                'reference_type' => 'correct',
                'reference_id' => $originalJournal->reference_id,
                'corrects_journal_id' => $originalJournal->id,
                'correction_reason' => $reason,
                'status' => 'posted',
                'total_debit' => $totalDebit,
                'total_credit' => $totalCredit,
                'created_by' => auth()->user()?->name ?? 'System',
            ]);

            foreach ($diffEntries as $index => $entry) {
                JournalItem::create([
                    'journal_id' => $correction->id,
                    'account_id' => $entry['account_id'],
                    'debit' => $entry['debit'] ?? '0',
                    'credit' => $entry['credit'] ?? '0',
                    'currency' => $originalJournal->currency,
                    'exchange_rate' => $originalJournal->exchange_rate,
                    'sort_order' => $index,
                ]);
            }

            Log::info('Correction journal created', [
                'original_id' => $originalJournal->id,
                'correction_id' => $correction->id,
                'reason' => $reason,
            ]);

            return $correction;
        }, 3);
    }

    // ==============================================
    // 新增：規則引擎方法（取代 JournalBuilder）
    // ==============================================

    /**
     * 從規則陣列產生分錄並直接寫入
     * 
     * 【費曼註釋：統一入口，所有業務 Model 呼叫此方法】
     * 
     * @param Model $source 來源單據 Model
     * @param string $referenceType 參考類型（如 'purchase', 'sale_revenue'）
     * @param array $rules 規則陣列
     * @return Journal
     */
    public function postFromRules(Model $source, string $referenceType, array $rules): Journal
    {
        // 1. 解析規則為 entries
        $entries = $this->buildEntriesFromRules($source, $rules);

        // 2. 產生摘要
        $description = $this->buildDescription($source, $referenceType);

        // 3. 呼叫原有核心方法
        return $this->createAutoJournal(
            referenceType: $referenceType,
            referenceId: $source->id,
            description: $description,
            entries: $entries,
            currency: $source->currency ?? 'TWD',
            exchangeRate: (string) ($source->exchange_rate ?? '1.0000'),
            entryDate: $source->sold_at?->format('Y-m-d') 
                ?? $source->purchased_at?->format('Y-m-d') 
                ?? now()->format('Y-m-d')
        );
    }

    /**
     * 解析規則陣列為 entries
     * 
     * 規則格式：
     * [
     *   'account_code'  => '5001',           // 科目代碼（優先）或 account_id
     *   'account_id'    => 123,              // 直接指定科目 ID
     *   'amount_source' => 'subtotal',       // 金額來源
     *   'side'          => 'credit',         // 'debit' 或 'credit'
     *   'condition'     => 'amount > 0',     // 條件（可選）
     *   'note'          => '主營業務收入',    // 摘要（可選）
     * ]
     * 
     * amount_source 支援：
     * - 'subtotal' → $source->subtotal
     * - 'customer_total' → $source->customer_total
     * - 'items.sum:unit_cost_twd*quantity' → 明細加總
     * - 'fees.shipping' → 費用關聯
     * - 'expression:customer_total - subtotal' → 運算式
     */
    private function buildEntriesFromRules(Model $source, array $rules): array
    {
        $entries = [];

        foreach ($rules as $rule) {
            // 條件判斷
            if (isset($rule['condition']) && !$this->evaluateCondition($source, $rule['condition'])) {
                continue;
            }

            // 解析金額
            if (isset($rule['amount'])) {
				$amount = (string) $rule['amount'];
			} elseif (isset($rule['amount_source'])) {
				$amount = $this->resolveAmount($source, $rule['amount_source']);
				
				// 應用比例（如果有）
				if (isset($rule['ratio']) && bccomp($rule['ratio'], '1.0000', 4) !== 0) {
					$amount = bcmul($amount, (string) $rule['ratio'], 4);
				}
			} else {
				throw new \RuntimeException("規則缺少 amount 或 amount_source：" . json_encode($rule));
			}
            
            if (bccomp($amount, '0', 4) === 0) {
                continue;
            }

            // 解析科目
            $accountId = $this->resolveAccountId($rule);
            if (!$accountId) {
                throw new \RuntimeException("規則無法解析科目：" . json_encode($rule));
            }

            $entries[] = [
                'account_id' => $accountId,
                'debit' => $rule['side'] === 'debit' ? $amount : '0.0000',
                'credit' => $rule['side'] === 'credit' ? $amount : '0.0000',
                'note' => $rule['note'] ?? null,
            ];
        }

        // 前置平衡檢查（在 createAutoJournal 會再次檢查，這裡提早發現規則錯誤）
        $this->validateBalance($entries);

        return $entries;
    }

    /**
     * 解析金額來源
     */
    private function resolveAmount(Model $source, string $sourceSpec): string
    {
        // 明細加總模式
        if (str_starts_with($sourceSpec, 'items.sum:')) {
            $field = str_replace('items.sum:', '', $sourceSpec);
            return $this->sumItems($source, $field);
        }

        // 費用關聯模式
        if (str_starts_with($sourceSpec, 'fees.')) {
            $feeType = str_replace('fees.', '', $sourceSpec);
            return (string) ($source->fees()->where('fee_type', $feeType)->sum('amount') ?? '0');
        }

        // 運算式模式
        if (str_starts_with($sourceSpec, 'expression:')) {
            $expr = str_replace('expression:', '', $sourceSpec);
            return $this->evaluateExpression($source, $expr);
        }

        // 直接欄位
        return (string) ($source->{$sourceSpec} ?? '0');
    }

    /**
     * 加總明細欄位（支援複合運算）
     */
    private function sumItems(Model $source, string $field): string
    {
        $total = '0.0000';
        
        // 確保關聯已載入，避免 N+1
        if (!$source->relationLoaded('items')) {
            $source->load('items');
        }

        foreach ($source->items as $item) {
            if (str_contains($field, '*')) {
                [$a, $b] = explode('*', $field);
                $val = bcmul(
                    (string) ($item->{$a} ?? '0'),
                    (string) ($item->{$b} ?? '0'),
                    4
                );
            } else {
                $val = (string) ($item->{$field} ?? '0');
            }
            
            $total = bcadd($total, $val, 4);
        }

        return $total;
    }

    /**
     * 簡易運算式求值
     * [TECH-DEBT] 未來應改用正規解析器或引入數學庫
     */
    private function evaluateExpression(Model $source, string $expr): string
    {
        // 替換變數為實際值：$subtotal → 1234.56
        $expr = preg_replace_callback('/\$(\w+)/', function ($matches) use ($source) {
            return (string) ($source->{$matches[1]} ?? '0');
        }, $expr);

        // 安全檢查：僅允許數字與運算符
        if (!preg_match('/^[\d\s\+\-\*\/\(\)\.]+$/', $expr)) {
            throw new \RuntimeException("非法運算式：{$expr}");
        }

        // [TECH-DEBT] 簡易實作：使用 eval（生產環境應替換為安全解析器）
        // 此處因正規表達式已過濾危險字元，風險可控
        $result = eval("return {$expr};");
        
        return number_format((float) $result, 4, '.', '');
    }

    /**
     * 條件判斷
     */
    private function evaluateCondition(Model $source, string $condition): bool
    {
        if (preg_match('/^(\w+)\s*([><=!]+)\s*(.+)$/', $condition, $matches)) {
            $left = $this->resolveAmount($source, $matches[1]);
            $op = $matches[2];
            $right = trim($matches[3]);

            return match($op) {
                '>'  => bccomp($left, $right, 4) > 0,
                '<'  => bccomp($left, $right, 4) < 0,
                '>=' => bccomp($left, $right, 4) >= 0,
                '<=' => bccomp($left, $right, 4) <= 0,
                '==' => bccomp($left, $right, 4) === 0,
                '!=' => bccomp($left, $right, 4) !== 0,
                default => false,
            };
        }

        return true;
    }

    /**
     * 解析科目 ID（防禦式）
     * 
     * 【費曼註釋：這是 code → id 的唯一轉換點，必須嚴格防禦】
     * 
     * 規則陣列支援兩種寫法：
     * 1. 'account_code' => '1405'  ← 推薦：人類可讀、穩定
     * 2. 'account_id' => 42        ← 進階：動態計算後的 ID
     * 
     * 生產環境必須使用 account_code，因為：
     * - 科目表重新編號時，code 不變，id 會變
     * - 跨環境（dev/staging/prod）同步時，code 一致，id 不一致
     * - 審計追蹤時，code 可讀，id 無意義
     * 
     * @throws \RuntimeException 科目找不到、重複、或已停用
     */
    private function resolveAccountId(array $rule): int  // 回傳 int，不再允許 null
    {
        // 優先級 1：直接指定 ID（進階用法，如動態計算後的科目）
        if (!empty($rule['account_id'])) {
            $account = Account::find($rule['account_id']);
            
            if (!$account) {
                throw new \RuntimeException(
                    "規則指定的 account_id={$rule['account_id']} 不存在"
                );
            }
            
            if (!$account->is_active) {
                throw new \RuntimeException(
                    "規則指定的 account_id={$rule['account_id']} [{$account->code}] 已停用"
                );
            }
            
            return (int) $account->id;
        }

        // 優先級 2：透過 code 解析（標準用法）
        if (!empty($rule['account_code'])) {
            $code = $rule['account_code'];
            
            // 嚴格查詢：code 必須唯一
            $accounts = Account::where('code', $code)->get();
            
            if ($accounts->isEmpty()) {
                throw new \RuntimeException(
                    "會計科目代碼 [{$code}] 不存在，請檢查科目表或規則配置"
                );
            }
            
            if ($accounts->count() > 1) {
                // [費曼註釋：code 重複是資料品質問題，必須立即暴露]
                $ids = $accounts->pluck('id')->implode(', ');
                throw new \RuntimeException(
                    "會計科目代碼 [{$code}] 重複，找到 {$accounts->count()} 筆（id: {$ids}）。" .
                    "請立即修正科目表，code 必須唯一。"
                );
            }
            
            $account = $accounts->first();
            
            if (!$account->is_active) {
                throw new \RuntimeException(
                    "會計科目 [{$code}] {$account->name} 已停用，無法用於分錄"
                );
            }
            
            return (int) $account->id;
        }

        // 兩者都未指定
        throw new \RuntimeException(
            "規則缺少 account_code 或 account_id：" . json_encode($rule)
        );
    }

    /**
     * 產生摘要
     */
    private function buildDescription(Model $source, string $referenceType): string
    {
        $number = match(true) {
            $source instanceof \App\Models\Purchase => $source->purchase_number,
            $source instanceof \App\Models\Sale => $source->invoice_number,
            default => '#' . $source->id,
        };

        $label = $this->getSourceTypeLabel($referenceType);

        return "{$label} - {$number}";
    }
	
    /**
     * [費曼註釋：計算原始分錄與更正後分錄的差額，只回傳有變化的項目]
     */
    private function calculateDiff(Journal $original, array $corrected): array
    {
        $originalMap = [];
        foreach ($original->items as $item) {
            $key = $item->account_id;
            $originalMap[$key] = [
                'debit' => $item->debit,
                'credit' => $item->credit,
            ];
        }

        $diffs = [];
        foreach ($corrected as $new) {
            $accountId = $new['account_id'];
            $origDebit = $originalMap[$accountId]['debit'] ?? '0';
            $origCredit = $originalMap[$accountId]['credit'] ?? '0';

            $diffDebit = bcsub($new['debit'] ?? '0', $origDebit, 4);
            $diffCredit = bcsub($new['credit'] ?? '0', $origCredit, 4);

            // [費曼註釋：只保留有差異的項目，減少無謂的分錄行]
            if (bccomp($diffDebit, '0', 4) !== 0 || bccomp($diffCredit, '0', 4) !== 0) {
                $diffs[] = [
                    'account_id' => $accountId,
                    'debit' => $diffDebit,
                    'credit' => $diffCredit,
                ];
            }
        }

        return $diffs;
    }

    /**
     * 驗證借貸平衡
     * [費曼註釋：《小企業會計準則》基本原則：有借必有貸，借貸必相等]
     */
    private function validateBalance(array $entries): void
    {
        $totalDebit = '0';
        $totalCredit = '0';

        foreach ($entries as $entry) {
            try {
                $debit = $entry['debit'] ?? '0';
                $credit = $entry['credit'] ?? '0';

                // [費曼註釋：防呆——同一科目不可同時有借有貸]
                if (bccomp($debit, '0', 4) > 0 && bccomp($credit, '0', 4) > 0) {
                    throw new \InvalidArgumentException('單一科目不可同時借貸');
                }

                $totalDebit = bcadd($totalDebit, $debit, 4);
                $totalCredit = bcadd($totalCredit, $credit, 4);
            } catch (\Throwable $e) {
                throw new \RuntimeException('金額運算錯誤：' . $e->getMessage());
            }
        }

        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            throw new \RuntimeException(
                "借貸不平衡：借方 {$totalDebit} ≠ 貸方 {$totalCredit}"
            );
        }
    }
}