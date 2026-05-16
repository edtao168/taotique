<?php

// app/Services/AccountingService.php
// [費曼註釋：會計系統核心服務，處理所有自動分錄產生與來源解析]

namespace App\Services;

use App\Models\Journal;
use App\Models\JournalItem;
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
                'shop_id' => 1, // [TECH-DEBT] 未來從 auth()->user()->shop_id 取得
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