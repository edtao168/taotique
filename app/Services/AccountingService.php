<?php

// app/Services/AccountingService.php
// [費曼註釋：會計系統核心服務，處理所有自動分錄產生與來源解析]
/*
核心定位
底層：依據中國《小企业会计准则》+ 現代ERP規範
UI：一人店、老闆不懂會計 → 極簡、自動化
原則：能自動就不要讓老闆摻合
*/

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
        $referenceType = $this->normalizeReferenceType($referenceType);
		
		// 手動或更正分錄沒有來源單據
        if ($referenceType === 'manual' || $referenceType === 'correct' || $referenceId === null) {
            return null;
        }
        
        // 將 sale_revenue / sale_cost 統一對應到 sale
        $actualType = $referenceType;
        if (in_array($referenceType, ['sale_revenue', 'sale_cost'])) {
            $actualType = 'sale';
        }
        
        $config = self::SOURCE_MAP[$actualType] ?? null;
        
        if (!$config) {
            Log::warning('Unknown journal reference_type after normalization', [
				'original_reference_type' => $referenceType,
				'actual_type' => $actualType,
				'id' => $referenceId,
			]);
            return null;
        }

        try {
            $record = $config['model']::withTrashed()->find($referenceId);
            
            if (!$record) {
                // 特別標記，利於審計追蹤
                Log::warning('Source record not found', [
                    'type' => $referenceType,
                    'id' => $referenceId,
                ]);
                return '[已刪除]';
            }
// ✅ 檢查欄位是否存在，避免 BadMethodCallException
        $numberField = $config['number_field'];
        if (!method_exists($record, $numberField) && !property_exists($record, $numberField)) {
            Log::warning('Number field not found', [
                'model' => get_class($record),
                'field' => $numberField,
                'available' => array_keys($record->getAttributes())
            ]);
            return '#' . $record->id;
        }

            $number = $record->{$numberField} ?? (string) $record->id;
            
            // 軟刪除標記
            if (method_exists($record, 'trashed') && $record->trashed()) {
                return $number . ' [已作廢]';
            }

            return $number;

        } catch (\Throwable $e) {
            Log::error('Resolve source number failed', [
                'type' => $referenceType,
                'id' => $referenceId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // 回傳有意義的錯誤標記，而非直接崩潰
            return '[錯誤: ' . class_basename($e) . ']';
        }
    }

    /**
     * 取得人類可讀的來源類型標籤
     */
    public function getSourceTypeLabel(string $referenceType): string
    {
        $referenceType = $this->normalizeReferenceType($referenceType);
		
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
	 * 正規化參考類型
	 * 
	 * 將可能的 Model 類別名稱轉換為標準的 event_type key
	 * 例如: 'App\Models\Purchase' → 'purchase'
	 *       'App\Models\Sale' → 'sale'
	 */
	private function normalizeReferenceType(string $referenceType): string
	{
		// 定義 Model 類別名稱到標準 key 的映射
		static $modelMap = null;
		
		if ($modelMap === null) {
			$modelMap = [
				\App\Models\Purchase::class => 'purchase',
				\App\Models\Sale::class => 'sale',
				\App\Models\PurchaseReturn::class => 'purchase_return',
				\App\Models\SaleReturn::class => 'sale_return',
				\App\Models\Conversion::class => 'conversion',
			];
		}
		
		// 檢查是否為類別名稱（包含反斜線）
		if (str_contains($referenceType, '\\')) {
			$converted = $modelMap[$referenceType] ?? null;
			
			if ($converted) {
				Log::warning('normalizeReferenceType: converted class name to key', [
					'original' => $referenceType,
					'converted' => $converted,
				]);
				return $converted;
			}
			
			// 如果無法轉換，記錄錯誤但回傳原始值
			Log::error('normalizeReferenceType: unknown class name', [
				'referenceType' => $referenceType,
			]);
		}
		
		return $referenceType;
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
	public function postFromRules(Model $source, array $rules, string $eventType): Journal
	{
		return DB::transaction(function () use ($source, $rules, $eventType) {
			
			// 1. 原始解析：依據設定的規則線條產生初始分錄陣列
			$entries = $this->buildEntriesFromRules($source, $rules);

			// 🎯 這裡就是核心修正點！在驗證平衡前，先執行同科目借貸自動對沖
			$entries = $this->netSameAccountEntries($entries);

			// 2. 嚴謹驗證：此時同一個會計科目絕對不會再同時出現借與貸
			$this->validateBalance($entries);

			// 3. 尋找或建立傳票主表 (冪等性處理)
			$journal = $this->findOrCreateJournal($source, $eventType);

			// 4. 寫入傳票細項
			$this->saveJournalItems($journal, $entries);

			return $journal;
		});
	}

    /**
	 * 從規則陣列產生分錄明細
	 * 
	 * @param Model $source 來源單據 (Sale/Purchase/etc)
	 * @param array $rules 規則明細陣列
	 * @return array 分錄明細
	 */
	protected function buildEntriesFromRules(Model $source, array $rules): array
	{
		$entries = [];

		foreach ($rules as $line) {
			// ==============================================
			// 1. 解析金額來源
			// ==============================================
			$amount = $this->resolveAmountFromRule($source, $line);
			
			// 金額為 0 則跳過
			if (bccomp($amount, '0', 4) === 0) {
				continue;
			}
			
			// ==============================================
			// 2. 套用比例
			// ==============================================
			$ratio = (string) ($line['ratio'] ?? '1.0000');
			if (bccomp($ratio, '1.0000', 4) !== 0) {
				$amount = bcmul($amount, $ratio, 4);
			}
			
			// ==============================================
			// 3. 解析會計科目
			// ==============================================
			$accountId = $this->resolveAccountIdFromRule($source, $line);
			
			// ==============================================
			// 4. 建立分錄
			// ==============================================
			$isDebit = ($line['entry_type'] === 'debit');
			
			$entries[] = [
				'account_id' => $accountId,
				'debit'      => $isDebit ? $amount : '0.0000',
				'credit'     => !$isDebit ? $amount : '0.0000',
			];
		}
		
		return $entries;
	}

	/**
	 * 解析單一規則行的金額
	 */
	protected function resolveAmountFromRule(Model $source, array $line): string
	{
		$amountSource = $line['amount_source'] ?? null;
		
		// 轉換為 Enum
		if (!$amountSource instanceof AmountSource) {
			$amountSource = AmountSource::tryFrom($amountSource);
		}
		
		if (!$amountSource) {
			Log::warning('未知的金額來源', ['line' => $line]);
			return '0.0000';
		}
		
		// ==============================================
		// 特殊處理：計算型金額來源
		// ==============================================
		
		// 1. 折讓後收入 (subtotal - seller_discount)
		if ($amountSource === AmountSource::SUBTOTAL_AFTER_DISCOUNT) {
			$subtotal = (string) ($source->subtotal ?? '0');
			$discount = (string) ($source->seller_discount ?? '0');
			return bcsub($subtotal, $discount, 4);
		}
		
		// 2. 費用總額 (用於貸方沖減)
		if ($amountSource === AmountSource::TOTAL_FEES) {
			$feeTypes = ['platform_fee', 'commission', 'shipping_fee_platform', 'order_adjustment', 'seller_discount'];
			$total = '0.0000';
			foreach ($feeTypes as $feeType) {
				$value = (string) ($source->$feeType ?? '0');
				$total = bcadd($total, $value, 4);
			}
			return $total;
		}
		
		// ==============================================
		// 一般處理：根據來源類型
		// ==============================================
		
		$sourceType = $amountSource->sourceType();
		
		// 類型 A：明細加總 (items.sum:xxx)
		if ($sourceType === 'items_sum') {
			$expression = explode(':', $amountSource->value)[1] ?? '';
			return $this->sumItemsExpression($source, $expression);
		}
		
		// 類型 B：直接欄位或費用類型
		if ($amountSource->isFeeType()) {
			$feeTypeKey = $amountSource->value;
			$feeRecord = $source->fees->firstWhere('fee_type', $feeTypeKey);
			return (string) ($feeRecord->amount ?? '0.0000');
		}
		
		// 類型 C：直接 Model 欄位
		$fieldName = $amountSource->value;
		return (string) ($source->{$fieldName} ?? '0.0000');
	}

	/**
	 * 解析單一規則行的會計科目
	 */
	protected function resolveAccountIdFromRule(Model $source, array $line): int
	{
		// 如果有 account_id 且不是 0/null，直接使用
		if (!empty($line['account_id']) && $line['account_id'] > 0) {
			return $line['account_id'];
		}
		
		// 否則從 account_code 解析
		$accountCode = $line['account_code'] ?? $line['account_code_from_db'] ?? null;
		
		if (!$accountCode) {
			throw new \RuntimeException("規則缺少 account_code");
		}
		
		// 處理 DYNAMIC
		if ($accountCode === 'DYNAMIC') {
			$isDebit = ($line['entry_type'] === 'debit');
			$accountCode = $this->resolveDynamicAccountCode($source, $isDebit);
		}
		
		// 查詢科目
		$account = Account::where('code', $accountCode)
			->where('shop_id', $source->shop_id ?? 1)
			->first();
		
		if (!$account) {
			throw new \RuntimeException("找不到會計科目代碼：{$accountCode}");
		}
		
		return $account->id;
	}

	/**
	 * 動態解析會計科目代碼（根據付款方式）
	 * 
	 * @param Model $source Sale Model
	 * @param bool $isDebit true=借方, false=貸方
	 * @return string 科目代碼
	 */
	protected function resolveDynamicAccountCode(Model $source, bool $isDebit): string
	{
		$paymentMethod = $source->payment_method ?? 'cash';
		
		// 實體店零售的科目映射
		$accountMap = [
			'cash'        => '100101',  // 庫存現金-新台幣
			'credit_card' => '112201',  // 應收帳款-信用卡
			'line_pay'    => '101201',  // 電子支付帳戶
			'taiwan_pay'  => '101201',  // 電子支付帳戶
		];
		
		$defaultAccount = '100101';
		
		return $accountMap[$paymentMethod] ?? $defaultAccount;
	}

	/**
	 * 加總 items 的運算式（如 cost*quantity）
	 */
	protected function sumItemsExpression(Model $source, string $expression): string
	{
		$total = '0.0000';
		
		// 確保 items 已載入
		if (!$source->relationLoaded('items')) {
			$source->load('items');
		}
		
		foreach ($source->items as $item) {
			$itemTotal = $this->calculateItemExpression($item, $expression);
			$total = bcadd($total, $itemTotal, 4);
		}
		
		return $total;
	}

	/**
	 * 計算單一 item 的運算式值
	 */
	protected function calculateItemExpression($item, string $expression): string
	{
		// 支援乘法：field1*field2
		if (str_contains($expression, '*')) {
			[$field1, $field2] = explode('*', $expression);
			$val1 = $this->getNestedValue($item, $field1);
			$val2 = $this->getNestedValue($item, $field2);
			return bcmul($val1, $val2, 4);
		}
		
		// 單一欄位
		return (string) $this->getNestedValue($item, $expression);
	}

	/**
	 * 支援巢狀屬性存取，如 'product.cost'
	 */
	protected function getNestedValue($object, string $path)
	{
		$parts = explode('.', $path);
		$value = $object;
		
		foreach ($parts as $part) {
			if (is_null($value)) {
				return '0';
			}
			$value = $value->{$part} ?? null;
		}
		
		return $value ?? '0';
	}

    /**
     * 補齊缺失方法：將對沖清洗後的傳票細項寫入資料庫
     */
    private function saveJournalItems(Journal $journal, array $entries): void
    {
        foreach ($entries as $index => $entry) {
            JournalItem::create([
                'journal_id'    => $journal->id,
                'account_id'    => $entry['account_id'],
                'debit'         => $entry['debit'] ?? '0.0000',
                'credit'        => $entry['credit'] ?? '0.0000',
                'currency'      => $journal->currency ?? 'TWD',
                'exchange_rate' => $journal->exchange_rate ?? '1.0000',
                'sort_order'    => $index,
                'note'          => $entry['note'] ?? null,
            ]);
        }
    }

    /**
     * 解析金額來源
	 * 支援格式：
     * - 'subtotal' → 直接欄位
     * - 'customer_total' → 直接欄位
     * - 'final_net_amount' → 直接欄位
     * - 'tax_amount' → 直接欄位
     * - 'platform_fee' → 費用類型（從 fees 關聯讀取）
     * - 'commission' → 費用類型
     * - 'items.sum:unit_cost_twd*quantity' → 明細加總（支援 * 運算）
     * - 'items.sum:product.cost*quantity' → 明細加總（支援關聯）
     * - 'items.cost_total' → 明細成本加總（簡化寫法）
     */
    private function resolveAmount(Model $source, string $sourceSpec): string
    {
        // 1. 直接欄位（Sale Model 的主要欄位）
        $directFields = ['subtotal', 'customer_total', 'final_net_amount', 'tax_amount'];
        if (in_array($sourceSpec, $directFields)) {
            return (string) ($source->{$sourceSpec} ?? '0');
        }
        
        // 2. 費用類型（從 fee_types 配置讀取）
        $feeTypes = array_keys(config('business.fee_types', []));
        if (in_array($sourceSpec, $feeTypes)) {
            return (string) ($source->{$sourceSpec} ?? '0');
        }
        
        // 3. 明細加總（items.sum:xxx）
        if (str_starts_with($sourceSpec, 'items.sum:')) {
            $field = substr($sourceSpec, 10); // 移除 'items.sum:'
            return $this->sumItems($source, $field);
        }
        
        // 4. 簡化寫法：items.cost_total
        if ($sourceSpec === 'items.cost_total') {
            return $this->sumItems($source, 'product.cost*quantity');
        }
        
        // 5. 費用關聯（fees.xxx）
        if (str_starts_with($sourceSpec, 'fees.')) {
            $feeType = substr($sourceSpec, 5);
            return (string) ($source->fees()->where('fee_type', $feeType)->sum('amount'));
        }
        
        // 6. 運算式
        if (str_starts_with($sourceSpec, 'expression:')) {
            $expr = substr($sourceSpec, 11);
            return $this->evaluateExpression($source, $expr);
        }
        
        Log::warning('Unknown amount_source', ['sourceSpec' => $sourceSpec]);
        return '0';
    }

    /**
     * 加總明細欄位（支援複合運算）
	 * 
     * 範例：
     * - 'quantity' → 加總數量
     * - 'unit_cost_twd*quantity' → 加總 (單價 × 數量)
     * - 'product.cost*quantity' → 加總 (商品成本 × 數量)
     */
    private function sumItems(Model $source, string $field): string
	{
		$total = '0.0000';
		
		if (!$source->relationLoaded('items')) {
			$source->load('items');
		}

		foreach ($source->items as $index => $item) {
			
		if (str_contains($field, '*')) {
				[$a, $b] = explode('*', $field);
				$valA = $this->getNestedValue($item, $a);
				$valB = $this->getNestedValue($item, $b);
				$val = bcmul((string) $valA, (string) $valB, 4);
				Log::info('Multiplication result', [
                'a' => $a,
                'b' => $b,
                'valA' => $valA,
                'valB' => $valB,
                'val' => $val,]);
			} else {
				$val = (string) $this->getNestedValue($item, $field);
			}
		}  
		
		return $total;
	}
	
	/**
     * 支援巢狀屬性存取，如 'product.cost'
     */
    private function getNestedValue($object, string $path)
    {
        $parts = explode('.', $path);
        $value = $object;
        Log::info('getNestedValue', [
        'path' => $path,
        'parts' => $parts,
        'object_class' => get_class($object)
    ]);
        foreach ($parts as $part) {
            if (is_null($value)) {
                return '0';
            }
            $value = $value->{$part} ?? null;
			 Log::info('getNestedValue step', [
            'part' => $part,
            'value' => $value,
            'value_type' => gettype($value)
        ]);
        }
        
        return $value ?? '0';
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

        $normalizedType = $this->normalizeReferenceType($referenceType);
		$label = $this->getSourceTypeLabel($normalizedType);

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
	
	// 檔案路徑：app/Services/AccountingService.php

    /**
     * 在驗證平衡與寫入庫之前，先對同科目進行借貸相抵（對沖）
     * [費曼註釋：同一單據若對同一科目有借有貸，自動相抵，避免觸發單一科目同時借貸的防呆，保持傳票乾淨]
     */
    private function netSameAccountEntries(array $entries): array
    {
        $netted = [];

        foreach ($entries as $entry) {
            $accountId = $entry['account_id'];
            $debit = $entry['debit'] ?? '0';
            $credit = $entry['credit'] ?? '0';

            if (!isset($netted[$accountId])) {
                $netted[$accountId] = [
                    'account_id' => $accountId,
                    'debit'      => '0',
                    'credit'     => '0',
                ];
            }

            // 使用 bcadd 累加同科目的所有原始借貸數值
            $netted[$accountId]['debit']  = bcadd($netted[$accountId]['debit'], $debit, 4);
            $netted[$accountId]['credit'] = bcadd($netted[$accountId]['credit'], $credit, 4);
        }

        // 對每個科目的總借貸進行對沖
        foreach ($netted as $accountId => $amounts) {
            $debit = $amounts['debit'];
            $credit = $amounts['credit'];

            // 比較借貸大小
            $cmp = bccomp($debit, $credit, 4);
            if ($cmp >= 0) {
                // 借方大於或等於貸方：保留借方淨額，貸方歸零
                $netted[$accountId]['debit']  = bcsub($debit, $credit, 4);
                $netted[$accountId]['credit'] = '0';
            } else {
                // 貸方大於借方：保留貸方淨額，借方歸零
                $netted[$accountId]['credit'] = bcsub($credit, $debit, 4);
                $netted[$accountId]['debit']  = '0';
            }

            // 如果對沖之後借貸都變成 0，直接把這個科目移出分錄，避免產生垃圾數據
            if (bccomp($netted[$accountId]['debit'], '0', 4) === 0 && bccomp($netted[$accountId]['credit'], '0', 4) === 0) {
                unset($netted[$accountId]);
            }
        }

        return array_values($netted);
    }

	/**
	 * 尋找或建立傳票主表（完美對齊底層 nullableMorphs('reference') 多型關聯）
	 * 實作高頻交易下的過帳冪等性防護，防範重複出庫、重複扣項
	 */
	private function findOrCreateJournal(\Illuminate\Database\Eloquent\Model $source, string $eventType): Journal
	{
		// 🎯 1. 取得來源 Model 的完整命名空間字串（例如 "App\Models\Sale"）
		$referenceType = get_class($source);
		$referenceId   = $source->id;

		if (!$referenceId) {
			throw new \RuntimeException("來源單據尚未持久化，缺少 id 欄位。");
		}

		// 🎯 2. 精準對齊您的 journals 表欄位進行唯一性排他查詢
		// 加上 event_type 或 description 條件（此處以 event_type 對應或透過業務描述區分同一單據的不同事件）
		// 這裡我們比對 reference_type, reference_id，並用時效/事件行為確保唯一
		$journal = Journal::where('reference_type', $referenceType)
			->where('reference_id', $referenceId)
			// 考慮到同一個 Sale 可能有 sale_revenue(收入) 與 sale_cost(成本) 兩個日記帳
			// 專業做法是將 event_type 存入 description，或者您的 journals 有擴充 event_type？ 
			// 查閱您之前的紀錄，如果 journals 沒有 event_type 欄位，架構師會利用 description 或單號來識別
			->where('description', 'like', "%{$eventType}%") 
			->first();

		if ($journal) {
			// 🛡️ 嚴謹清空舊傳票細項，防止重新點擊出庫時分錄重複累加
			$journal->items()->delete();
			
			// 更新傳票狀態為已過帳，確保時間更新
			$journal->update([
				'status'     => 'posted',
				'entry_date' => now()->format('Y-m-d'),
			]);
			
			return $journal;
		}

		// 🎯 3. 取得業務單據編號（例如 invoice_number），用來當作人類可讀的描述或備註
		$numberField = self::SOURCE_MAP[$eventType]['number_field'] ?? 'reference_number';
		$docNumber   = $source->{$numberField} ?? ('ID_' . $referenceId);
		
		// 建立全新的日記帳主表
		return Journal::create([
			'shop_id'        => $source->shop_id ?? 1, // 多店預留
			'currency'       => $source->currency ?? 'TWD',
			'exchange_rate'  => $source->exchange_rate ?? '1.0000',
			'entry_date'     => now()->format('Y-m-d'),
			'description'    => "[自動轉入] 外單號:{$docNumber} ({$eventType})",
			'status'         => 'posted',
			'reference_type' => $referenceType, // 💡 寫入 "App\Models\Sale"
			'reference_id'   => $referenceId,   // 💡 寫入 銷售單 ID
			'created_by'     => 'System_IMS',
		]);
	}
}