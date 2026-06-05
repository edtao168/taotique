<?php

// app/Services/AccountingService.php

namespace App\Services;

use App\Enums\AmountSource;
use App\Models\Account;
use App\Models\Journal;
use App\Models\JournalItem;
use App\Models\AccountingRule;
use App\Models\AccountingRuleLine;
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
        'purchase_return' => [
            'model' => \App\Models\PurchaseReturn::class,
            'number_field' => 'return_number',
        ],
        'sale_return' => [
            'model' => \App\Models\SaleReturn::class,
            'number_field' => 'return_number',
        ],
        'inventory_adjustment' => [
            'model' => \App\Models\InventoryAdjustment::class,
            'number_field' => 'adjustment_number',
        ],
    ];

    /**
     * 核心過帳引擎：支持全業務事件（sale_revenue, sale_cost, purchase_post, sales_return_post 等）
     */
    public function postFromRules(string $eventType, Model $source, ?string $context = null): ?Journal
    {
        $shopId = $source->shop_id ?? 1;

        return DB::transaction(function () use ($eventType, $source, $context, $shopId) {
            // 1. 撈取並鎖定對應的過帳規則
            $rule = AccountingRule::where('event_type', $eventType)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$rule) {
                // 💡 強阻斷防禦：如果不該跳過卻沒設規則，直接拋出異常，不讓系統佛系走下去
                throw new \RuntimeException("會計自動過帳失敗：找不到已啟用的過帳規則 [{$eventType}]，店鋪 ID: {$shopId}。請先至後台配置規則！");
            }

            $referenceType = $this->resolveReferenceType($source);
            $journal = $this->getOrCreateJournal($source, $referenceType, $eventType);

            $entries = [];

            // 2. 解析規則明細
            foreach ($rule->lines as $line) {
                if (!$line->is_active) continue;

                $accountId = $this->resolveAccountIdFromRule($line, $source, $context);
                if (!$accountId) {
                    throw new \RuntimeException("過帳規則解析失敗：事件 [{$eventType}] 線路 ID [{$line->id}] 無法解析會計科目。");
                }

                $baseAmount = $this->getAmountFromSource($source, $line->amount_source, $eventType);
                $amount = bcmul($baseAmount, (string)$line->ratio, 4);

                if (bccomp($amount, '0.0000', 4) === 0) {
                    continue;
                }

                $entries[] = [
                    'account_id' => $accountId,
                    'entry_type' => $line->entry_type, // debit 或 credit
                    'amount'     => $amount,
                ];
            }

            // 🎯 【核心修復點】拒絕默默返回 null！如果解析後的過帳分錄金額總計為 0，直接拋出強烈異常觸發 Rollback
            if (empty($entries)) {
                throw new \RuntimeException("會計過帳拒絕：單據事件 [{$eventType}] 解析後的過帳分錄明細金額總計為 0。單據 ID: {$source->id}，請檢查單據相關金額或會計規則配比。");
            }

            // 3. 合併同科目分錄
            $cleanedEntries = $this->netSameAccountEntries($entries);

            // 4. 借貸平衡嚴謹校驗
            $this->validateBalance($cleanedEntries, $eventType, $source);

            // 5. 寫入傳票明細
            foreach ($cleanedEntries as $entry) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $entry['account_id'],
                    'entry_type' => $entry['entry_type'],
                    'debit'      => $entry['entry_type'] === 'debit' ? $entry['amount'] : '0.0000',
                    'credit'     => $entry['entry_type'] === 'credit' ? $entry['amount'] : '0.0000',
                ]);
            }

            return $journal;
        });
    }
	
    protected function resolveAccountIdFromRule(AccountingRuleLine $line, Model $source, ?string $context): int
    {
        if (!empty($line->account_id)) {
            return $line->account_id;
        }

        $code = $line->account_code;
        $shopId = $source->shop_id ?? 1;

        if ($code && str_starts_with($code, 'DYNAMIC:')) {
            $dynamicSpec = substr($code, 8); 
            $resolvedCode = $this->resolveDynamicAccount($source, $dynamicSpec, $context);
            
            // 🎯 【核心修復點】動態翻譯科目代碼（如將中國標準的 500101 轉為台灣的 4111 銷貨收入）
            //$resolvedCode = $this->convertToTaiwanAccount($resolvedCode);

            $account = Account::where('code', $resolvedCode)->first();

            if ($account) {
                return $account->id;
            }

            throw new \RuntimeException("全動態科目對齊失敗：依準則解析出台灣會計科目代碼 [{$resolvedCode}]，但 accounts 資料表中無此店鋪(#{$shopId})紀錄。請確認會計科目表已初始化。");
        }

        throw new \RuntimeException("過帳規則設定錯誤：缺少實體科目 ID 或全動態科目策略代碼。");
    }

    /**
     * 🚀 全動態路由翻譯機（支持小企業會計準則）
     */
    protected function resolveDynamicAccount(Model $source, string $dynamicSpec, ?string $context): string
    {
        $parts = explode(':', $dynamicSpec);
        $domain = $parts[0] ?? ''; 
        $type = $parts[1] ?? '';   
        
        return match($domain) {
            'auto'     => $this->resolveAutoDomainAccount($source, $type),
            'sale'     => $this->resolveSaleDomainAccount($source, $type, $parts[2] ?? null),
            'purchase' => $this->resolvePurchaseDomainAccount($source, $type, $context),
            default    => throw new \RuntimeException("未知的全動態網域命名空間: {$domain}"),
        };
    }

    protected function resolveAutoDomainAccount(Model $source, string $type): string
    {
        return match($type) {
            'inventory' => $source->category_accounting_code ?? '140501', // 1405 庫存商品
            default     => throw new \RuntimeException("未知的商品庫存域動態科目: {$type}"),
        };
    }

    protected function resolveSaleDomainAccount(Model $source, string $type, ?string $subType): string
    {
        $payment = $source->payment_method ?? 'cash';

        return match($type) {
            'payment' => match($payment) {
                'shopee_wallet' => '113101', // 應收帳款-蝦皮代收
                'line_pay'      => '113102', // 應收帳款-LINE Pay
                'credit_card'   => '113103', // 應收帳款-信用卡
                default         => '100101', // 門市現金
            },
            'revenue'     => '500101', // 主營業務收入
            'cost'        => '5401',   // 主營業務成本
            'channel_fee' => '560105', // 財務費用-手續費
            'discount'    => '500110', // 銷售折扣與折讓
            'return_fee'  => '560106', // 銷售費用-平台運費支出
            default => throw new \RuntimeException("未知的銷售域動態科目: {$type}"),
        };
    }

    /**
     * 🚀 補回：採購與費用網域動態科目解析 (DYNAMIC:purchase:xxx)
     */
    protected function resolvePurchaseDomainAccount(Model $source, string $type, ?string $context): string
    {
        return match($type) {
            'expense' => match($context) {
                'tariff' => '140502',      // 庫存商品-附加關稅 (依準則計入存貨成本成本)
                'freight' => '560201',     // 管理費用-運費 或 計入採購附加
                default => '560202',       // 其他附加費
            },
            default => '220201',           // 預設應付帳款-供應商
        };
    }

    /**
     * 嚴謹解算所有業務單據的金額來源（涵蓋銷售、採購、銷退）
     */
    protected function getAmountFromSource(Model $source, string $amountSource, string $eventType): string
    {
        // 🎯 銷貨成本自動結轉金額 (加權平均成本總計)
        if ($amountSource === 'cost_amount' || $amountSource === 'return_cost') {
            
            // 🛡️ 【關鍵修復點 1】強制執行 fresh 震碎並重新由資料庫撈取最新的關聯，防範 Livewire 記憶體快照污染
            if (method_exists($source, 'fresh')) {
                $source = $source->fresh(['items.product']);
            } else {
                $source->load(['items.product']);
            }
            
            $totalCost = '0.0000';
            
            foreach ($source->items as $item) {
                // 優先讀取銷售單明細表中的單位成本快照
                $itemCost = (string)($item->unit_cost ?? '0.0000');
                
                // 🛡️ 【關鍵修復點 2：降級安全盾】如果快照不幸為 0 或空，自動向外層關聯的 products.cost (70.04) 索取即時成本
                if (bccomp($itemCost, '0.0000', 4) === 0 && $item->product) {
                    $itemCost = (string)($item->product->cost ?? '0.0000');
                }
                
                $itemQty   = (string)($item->quantity ?? '0.0000');
                
                // 執行高精度嚴謹運算
                $totalCost = bcadd($totalCost, bcmul($itemCost, $itemQty, 4), 4);
            }
            
            return $totalCost;
        }

        // 2. 銷售摩擦費總計
        if ($amountSource === 'total_fees') {
            $platformFee     = (string)($source->platform_fee ?? '0.0000');
            $commission      = (string)($source->commission ?? '0.0000');
            $sellerDiscount  = (string)($source->seller_discount ?? '0.0000');
            $shippingFeePlat = (string)($source->shipping_fee_platform ?? '0.0000');

            $total = bcadd($platformFee, $commission, 4);
            $total = bcadd($total, $sellerDiscount, 4);
            return bcadd($total, $shippingFeePlat, 4);
        }

        // 3. 通用高精度欄位清洗反射
        $val = match ($amountSource) {
            'customer_total'          => $source->customer_total,
            'subtotal_after_discount' => $source->subtotal_after_discount,
            'final_net_amount'        => $source->final_net_amount,
            'tax_amount'              => $source->tax_amount,
            'freight_amount'          => $source->freight_amount,
            'subtotal'                => $source->subtotal,
            default                   => $source->getAttribute($amountSource) ?? 0,
        };

        return number_format((float)$val, 4, '.', '');
    }

    protected function netSameAccountEntries(array $entries): array
    {
        $grouped = [];
        foreach ($entries as $entry) {
            $key = $entry['account_id'] . '_' . $entry['entry_type'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = $entry;
            } else {
                $grouped[$key]['amount'] = bcadd($grouped[$key]['amount'], $entry['amount'], 4);
            }
        }
        return array_values($grouped);
    }

    protected function validateBalance(array $entries, string $eventType, Model $source): void
    {
        $debitTotal  = '0.0000';
        $creditTotal = '0.0000';

        foreach ($entries as $entry) {
            if ($entry['entry_type'] === 'debit') {
                $debitTotal = bcadd($debitTotal, $entry['amount'], 4);
            } else {
                $creditTotal = bcadd($creditTotal, $entry['amount'], 4);
            }
        }

        if (bccomp($debitTotal, $creditTotal, 4) !== 0) {
            throw new \RuntimeException(
                "會計過帳拒絕：分錄借貸不平衡！事件: [{$eventType}]。借方: {$debitTotal}, 貸方: {$creditTotal}。"
            );
        }
    }

    protected function resolveReferenceType(Model $source): string
    {
        return match (get_class($source)) {
            \App\Models\Sale::class        => 'sale',
            \App\Models\PurchaseOrder::class => 'purchase',
            \App\Models\SalesReturn::class => 'sales_return',
            default                        => 'manual',
        };
    }

    protected function getOrCreateJournal(Model $source, string $referenceType, string $eventType): Journal
    {
        $referenceId = $source->id;
        $shopId = $source->shop_id ?? 1;

        $journal = Journal::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('shop_id', $shopId)
            ->where('description', 'like', "%{$eventType}%") 
            ->first();

        if ($journal) {
            $journal->items()->delete();
            return $journal;
        }

        $docNumber = $source->invoice_number ?? $source->purchase_number ?? $source->return_number ?? ('DOC-' . now()->format('YmdHis'));

        return Journal::create([
            'shop_id'         => $shopId,
            'journal_number'  => 'JV-' . now()->format('Ymd') . '-' . sprintf('%05d', rand(1, 99999)),
            'reference_type'  => $referenceType,
            'reference_id'    => $referenceId,
            'document_number' => $docNumber,
            'entry_date'      => now()->format('Y-m-d'),
            'description'     => "自動過帳 [{$eventType}] - 單據編號: {$docNumber}",
            'status'          => 'posted',
            'created_by'      => 1,
        ]);
    }

    /**
     * 🛡️ 補回：前端多型反查原始單據編號安全盾
     */
    public function resolveSourceNumber(?string $referenceType, ?int $referenceId): ?string
    {
        if (!$referenceType || !$referenceId || $referenceType === 'manual') {
            return '手工分錄';
        }

        $config = self::SOURCE_MAP[$referenceType] ?? null;
        if (!$config) {
            return "未知單據 (#{$referenceId})";
        }

        $modelClass = $config['model'];
        $numberField = $config['number_field'];
        $source = $modelClass::where('id', $referenceId)->first([$numberField]);

        return $source ? $source->{$numberField} : "單據已刪除 (#{$referenceId})";
    }
	
	/**
	 * 🔄 未來中間層處理：將大陸科目代碼動態翻譯為台灣在地會計科目代碼
	 * 確保多店預留與跨境報表合併時的資料一致性
	 */
	protected function convertToTaiwanAccount(string $chinaCode, int $shopId): string
	{
		$mapping = [
			'500101' => '4111', // 主營業務收入-零售 -> 銷貨收入
			'5401'   => '5111', // 主營業務成本 -> 銷貨成本
			'140501' => '1210', // 庫存商品 -> 商品存貨
			'222103' => '2261', // 應交稅費-銷項稅 -> 銷項稅額
		];

		// 返回對應後的代碼，若無對應則返回原代碼
		return $mapping[$chinaCode] ?? $chinaCode;
	}
}