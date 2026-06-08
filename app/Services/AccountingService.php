<?php
// app/Services/AccountingService.php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
use App\Models\Journal;
use App\Models\JournalItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountingService
{
    private const DECIMAL_PRECISION = 4;

    /**
     * 核心過帳引擎
     */
    public function postFromRules(string $eventType, Model $source, ?string $context = null): ?Journal
    {
        return DB::transaction(function () use ($eventType, $source, $context) {
            $rule = $this->getRule($eventType);

            // 🎯 核心修復：傳入 eventType，讓每個事件類型擁有獨立的 Journal
            $journal = $this->getOrCreateJournal($source, $eventType);

            $entries = $this->buildEntries($rule->lines, $source, $context);

            $this->validateNotEmpty($entries, $eventType, $source);
            $cleanedEntries = $this->netSameAccountEntries($entries);
            $this->validateBalance($cleanedEntries, $eventType, $source);

            $this->createJournalItems($journal, $cleanedEntries);

            return $journal;
        });
    }

    /**
     * 建立分錄陣列（內部使用，包含借貸標記）
     */
    private function buildEntries($lines, Model $source, ?string $context): array
    {
        $entries = [];

        foreach ($lines as $line) {
            if (!$line->is_active) continue;

            $amount = $source->getAmountFromSource($line->amount_source, $context);
            $adjustedAmount = bcmul($amount, (string)$line->ratio, self::DECIMAL_PRECISION);

            if (bccomp($adjustedAmount, '0.0000', self::DECIMAL_PRECISION) === 0) continue;

            $accountId = $this->resolveAccountId($line, $source, $context);

            $entries[] = [
                'account_id' => $accountId,
                'is_debit'   => $line->entry_type === 'debit',
                'amount'     => $adjustedAmount,
            ];
        }

        return $entries;
    }

    /**
     * 批次建立傳票明細（符合你的資料表結構）
     */
    private function createJournalItems(Journal $journal, array $entries): void
    {
        $items = collect($entries)->map(fn($entry) => [
            'journal_id'      => $journal->id,
            'account_id'      => $entry['account_id'],
            'shop_id'         => $journal->shop_id,
            'currency'        => 'TWD',
            'debit'           => $entry['is_debit'] ? $entry['amount'] : '0.0000',
            'credit'          => !$entry['is_debit'] ? $entry['amount'] : '0.0000',
            'debit_currency'  => $entry['is_debit'] ? $entry['amount'] : '0.0000',
            'credit_currency' => !$entry['is_debit'] ? $entry['amount'] : '0.0000',
            'exchange_rate'   => '1.000000',
            'created_at'      => now(),
            'updated_at'      => now(),
        ])->toArray();

        JournalItem::insert($items);
    }

    /**
     * 獲取或建立傳票
     * 
     * 🎯 核心修復點：
     * 將 event_type 編碼到 reference_type 中，格式為 "{model_type}:{event_type}"
     * 例如："sale:sale_revenue", "sale:sale_fee", "sale:sale_cost"
     * 這樣每個業務事件都能擁有獨立的傳票，互不覆蓋
     * 
     * 🎯 冪等性設計：
     * 如果傳票已存在（如網絡中斷導致部分寫入），刪除舊 items 並重建。
     * 這是底層服務的冪等保證，與業務層的「是否允許重新觸發」是不同層次的問題。
     * 業務層（如 Sale::processStockOut）負責決定是否呼叫本方法。
     */
    private function getOrCreateJournal(Model $source, string $eventType): Journal
    {
        $baseReferenceType = $source::getReferenceType(); // 例如 'sale'
        $referenceType = "{$baseReferenceType}:{$eventType}"; // 例如 'sale:sale_revenue'
        $referenceId = $source->id;
        $shopId = $source->shop_id ?? 1;

        $journal = Journal::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('shop_id', $shopId)
            ->first();

        if ($journal) {
            // 🎯 冪等性保證：已存在則清空重建（適用於網絡中斷等異常恢復場景）
            // 注意：這不會改變傳票狀態（如 posted -> draft），只重建明細
            $journal->items()->delete();

            // 更新描述和日期，確保資訊最新
            $journal->update([
                'description' => "自動過帳 [{$eventType}] - 單據編號: {$source->getDocumentNumber()}",
                'entry_date'  => now()->format('Y-m-d'),
                'updated_at'  => now(),
            ]);

            return $journal;
        }

        return Journal::create([
            'shop_id'         => $shopId,
            'currency'        => 'TWD',
            'exchange_rate'   => '1.0000',
            'entry_date'      => now()->format('Y-m-d'),
            'description'     => "自動過帳 [{$eventType}] - 單據編號: {$source->getDocumentNumber()}",
            'status'          => 'posted',
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'created_by'      => auth()->id() ?? 'system',
        ]);
    }

    /**
     * 合併同科目分錄
     */
    private function netSameAccountEntries(array $entries): array
    {
        return collect($entries)
            ->groupBy(fn($entry) => $entry['account_id'])
            ->map(function($group) {
                $debitTotal = $group->where('is_debit', true)->sum('amount');
                $creditTotal = $group->where('is_debit', false)->sum('amount');

                if (bccomp($debitTotal, $creditTotal, self::DECIMAL_PRECISION) > 0) {
                    return [
                        'account_id' => $group->first()['account_id'],
                        'is_debit'   => true,
                        'amount'     => bcsub($debitTotal, $creditTotal, self::DECIMAL_PRECISION),
                    ];
                } elseif (bccomp($creditTotal, $debitTotal, self::DECIMAL_PRECISION) > 0) {
                    return [
                        'account_id' => $group->first()['account_id'],
                        'is_debit'   => false,
                        'amount'     => bcsub($creditTotal, $debitTotal, self::DECIMAL_PRECISION),
                    ];
                }

                return null;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * 驗證借貸平衡
     */
    private function validateBalance(array $entries, string $eventType, Model $source): void
    {
        $debitTotal = '0.0000';
        $creditTotal = '0.0000';

        foreach ($entries as $entry) {
            if ($entry['is_debit']) {
                $debitTotal = bcadd($debitTotal, $entry['amount'], self::DECIMAL_PRECISION);
            } else {
                $creditTotal = bcadd($creditTotal, $entry['amount'], self::DECIMAL_PRECISION);
            }
        }

        if (bccomp($debitTotal, $creditTotal, self::DECIMAL_PRECISION) !== 0) {
            throw new \RuntimeException(
                sprintf("分錄借貸不平衡！事件: %s，借方: %s，貸方: %s", $eventType, $debitTotal, $creditTotal)
            );
        }
    }

    /**
     * 解析科目ID（含詳細錯誤資訊）
     */
    private function resolveAccountId(AccountingRuleLine $line, Model $source, ?string $context): int
    {
        if (!empty($line->account_id)) {
            return $line->account_id;
        }

        $code = $line->account_code;

        if ($code && str_starts_with($code, 'DYNAMIC:')) {
            $dynamicSpec = substr($code, 8);

            try {
                $resolvedCode = $source->resolveDynamicAccount($dynamicSpec, $context);
            } catch (\Exception $e) {
                throw new \RuntimeException(
                    sprintf(
                        '動態科目解析失敗 [規則ID: %d, 動態規格: %s, Model: %s#%d]: %s',
                        $line->rule_id,
                        $dynamicSpec,
                        get_class($source),
                        $source->id,
                        $e->getMessage()
                    )
                );
            }

            $account = Account::where('code', $resolvedCode)->first();

            if ($account) {
                return $account->id;
            }

            throw new \RuntimeException(
                sprintf('動態科目對應的會計科目不存在：代碼 %s (來源: %s)', $resolvedCode, $dynamicSpec)
            );
        }

        $account = Account::where('code', $code)->first();
        if ($account) {
            return $account->id;
        }

        throw new \RuntimeException("無法解析科目：{$code}");
    }

    /**
     * 獲取過帳規則（全公司共用）
     */
    private function getRule(string $eventType): AccountingRule
    {
        \Log::info('getRule called', ['eventType' => $eventType]);

        $rule = AccountingRule::where('event_type', $eventType)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        \Log::info('getRule result', [
            'eventType' => $eventType,
            'found' => $rule ? 'yes' : 'no',
            'rule_id' => $rule?->id,
            'lines_count' => $rule?->lines->count()
        ]);

        if (!$rule) {
            throw new \RuntimeException("找不到已啟用的過帳規則 [{$eventType}]");
        }

        return $rule->load(['lines' => fn($q) => $q->orderBy('sort_order')]);
    }

    /**
     * 驗證分錄不為空
     */
    private function validateNotEmpty(array $entries, string $eventType, Model $source): void
    {
        if (empty($entries)) {
            throw new \RuntimeException(
                sprintf("過帳分錄金額總計為 0，單據類型: %s#%d，事件: %s", get_class($source), $source->id, $eventType)
            );
        }
    }

    /**
     * 前端反查單據編號（從 Journal 記錄反查原始單據）
     * 
     * 🎯 核心修復點：
     * reference_type 現在格式為 "{model_type}:{event_type}"
     * 需要拆分出基礎 model 類型來反查
     */
    public function resolveSourceNumber(?string $referenceType, ?int $referenceId): ?string
    {
        if (!$referenceType || !$referenceId || $referenceType === 'manual') {
            return '手工分錄';
        }

        $baseReferenceType = $this->extractBaseReferenceType($referenceType);

        $modelClass = $this->getModelClassByReferenceType($baseReferenceType);

        if (!$modelClass || !method_exists($modelClass, 'getDocumentNumberField')) {
            return "未知單據 (#{$referenceId})";
        }

        $numberField = $modelClass::getDocumentNumberField();
        $source = $modelClass::where('id', $referenceId)->first([$numberField]);

        return $source ? $source->{$numberField} : "單據已刪除 (#{$referenceId})";
    }

    /**
     * 從編碼後的 reference_type 中提取基礎 model 類型
     * 
     * 例如："sale:sale_revenue" -> "sale"
     *       "purchase:purchase_inbound" -> "purchase"
     *       "sale" -> "sale" (向後兼容)
     */
    private function extractBaseReferenceType(string $referenceType): string
    {
        if (str_contains($referenceType, ':')) {
            return explode(':', $referenceType)[0];
        }
        return $referenceType;
    }

    /**
     * 參考類型 → Model 類別對應
     */
    private function getModelClassByReferenceType(string $referenceType): ?string
    {
        return match($referenceType) {
            'sale'           => \App\Models\Sale::class,
            'purchase'       => \App\Models\Purchase::class,
            'sales_return'   => \App\Models\SalesReturn::class,
            'purchase_return'=> \App\Models\PurchaseReturn::class,
            'conversion'     => \App\Models\Conversion::class,
            default          => null,
        };
    }

    /**
     * 取得參考類型的中文標籤
     * 
     * 🎯 核心修復點：
     * 處理帶 event_type 的 reference_type
     */
    public function getSourceTypeLabel(?string $referenceType): string
    {
        if (!$referenceType) {
            return '未知單據';
        }

        if (str_contains($referenceType, ':')) {
            [$baseType, $eventType] = explode(':', $referenceType, 2);
            $baseLabel = match($baseType) {
                'sale'           => '銷售單',
                'purchase'       => '採購單',
                'sales_return'   => '銷售退貨單',
                'purchase_return'=> '採購退貨單',
                'conversion'     => '轉換單',
                'manual'         => '手工分錄',
                default          => '未知單據',
            };
            return "{$baseLabel} [{$eventType}]";
        }

        return match($referenceType) {
            'sale'           => '銷售單',
            'purchase'       => '採購單',
            'sales_return'   => '銷售退貨單',
            'purchase_return'=> '採購退貨單',
            'conversion'     => '轉換單',
            'manual'         => '手工分錄',
            default          => '未知單據',
        };
    }

    /**
     * 🎯 新增：檢查指定業務事件的傳票是否已存在
     * 供業務層判斷是否需要觸發過帳
     */
    public function hasJournal(string $eventType, Model $source): bool
    {
        $baseReferenceType = $source::getReferenceType();
        $referenceType = "{$baseReferenceType}:{$eventType}";

        return Journal::where('reference_type', $referenceType)
            ->where('reference_id', $source->id)
            ->where('shop_id', $source->shop_id ?? 1)
            ->exists();
    }

    /**
     * 🎯 新增：撤銷（軟刪除）指定業務事件的傳票
     * 供業務層在「取消出庫/入庫」等場景呼叫
     */
    public function reverseJournal(string $eventType, Model $source): void
    {
        $baseReferenceType = $source::getReferenceType();
        $referenceType = "{$baseReferenceType}:{$eventType}";

        DB::transaction(function () use ($referenceType, $source) {
            $journal = Journal::where('reference_type', $referenceType)
                ->where('reference_id', $source->id)
                ->where('shop_id', $source->shop_id ?? 1)
                ->first();

            if ($journal) {
                // 軟刪除：將狀態改為 reversed，保留審計軌跡
                $journal->update([
                    'status' => 'reversed',
                    'description' => $journal->description . ' [已撤銷於 ' . now()->format('Y-m-d H:i:s') . ']',
                ]);

                // 可選：建立反向分錄（紅字沖銷）
                // 此處僅標記狀態，實際紅字沖銷可依業務需求擴展
            }
        });
    }
}