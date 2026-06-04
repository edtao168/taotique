<?php

// app/Services/AccountingService.php
// [費曼註釋：會計系統核心服務，經資深架構師去冗餘、防錯優化、完美對齊 8 大全動態會計規則策略之最終重構版]

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
        'sale_revenue' => [  
            'model' => \App\Models\Sale::class,
            'number_field' => 'invoice_number',
        ],
        'sale_cost' => [     
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
     * 核心過帳引擎：根據業務事件類型(event_type)與來源單據模型(Model)，自動生成平衡的會計傳票
     * 🛡️ 高頻交易安全：內部強制包入 DB::transaction 與 lockForUpdate
     */
    public function postFromRules(string $eventType, Model $source, ?string $context = null): ?Journal
    {
        $shopId = $source->shop_id ?? 1;

        return DB::transaction(function () use ($eventType, $source, $context, $shopId) {
            // 🎯 1. 撈取對應的過帳規則主檔與明細線，強制上排他鎖 (lockForUpdate)，確保規則在讀取時不被後台異動干擾
            $rule = AccountingRule::where('event_type', $eventType)
                ->where('shop_id', $shopId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (!$rule) {
                Log::warning("會計自動過帳跳過：找不到已啟用的過帳規則 [{$eventType}]，店鋪 ID: {$shopId}");
                return null;
            }

            // 🎯 2. 初始化或清洗獲取傳票主檔 (Journal)
            $referenceType = $this->resolveReferenceType($source);
            $journal = $this->getOrCreateJournal($source, $referenceType, $eventType);

            $entries = [];

            // 🎯 3. 逐行解析規則明細
            foreach ($rule->lines as $line) {
                if (!$line->is_active) continue;

                // 防錯與全動態核心：將 account_code (形如 DYNAMIC:sale:channel_fee) 翻譯成真實的科目 ID
                $accountId = $this->resolveAccountIdFromRule($line, $source, $context);
                if (!$accountId) {
                    throw new \RuntimeException("過帳規則解析失敗：事件 [{$eventType}] 線路 ID [{$line->id}] 無法解析出合法的會計科目。");
                }

                // 嚴謹呼叫算力基準值
                $baseAmount = $this->getAmountFromSource($source, $line->amount_source, $eventType);
                
                // 強制執行 bc 運算乘法計算比率金額
                $amount = bcmul($baseAmount, (string)$line->ratio, 4);

                // 金額為零的分錄無須入帳，直接過濾，去除冗餘傳票細項
                if (bccomp($amount, '0.0000', 4) === 0) {
                    continue;
                }

                $entries[] = [
                    'account_id' => $accountId,
                    'entry_type' => $line->entry_type, // 'debit' 或 'credit'
                    'amount'     => $amount,
                ];
            }

            if (empty($entries)) {
                Log::info("會計過帳終止：單據衍生分錄金額全數為 0，無須切分錄。單據 ID: {$source->id}");
                return null;
            }

            // 🎯 4. 同一科目、同一方向之分錄自動對沖合併（避免重複出現相同科目分錄，去除冗餘）
            $cleanedEntries = $this->netSameAccountEntries($entries);

            // 🎯 5. 數值嚴謹性校驗：驗證借貸方金額是否絕對平衡
            $this->validateBalance($cleanedEntries, $eventType, $source);

            // 🎯 6. 批量寫入傳票明細表
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

    /**
     * 防錯優化核心：精準將規則線中的科目進行動態路由或靜態返回
     */
    protected function resolveAccountIdFromRule(AccountingRuleLine $line, Model $source, ?string $context): int
    {
        // 方案一：如果該線路本身就綁定了固定的實體科目 ID，直接返回
        if (!empty($line->account_id)) {
            return $line->account_id;
        }

        $code = $line->account_code;
        $shopId = $source->shop_id ?? 1;

        // 方案二：全動態規則策略捕獲（核心重構點）
        if ($code && str_starts_with($code, 'DYNAMIC:')) {
            $dynamicSpec = substr($code, 8); // 去除 "DYNAMIC:" 前綴，得到 "sale:channel_fee" 之類的字串
            $resolvedCode = $this->resolveDynamicAccount($source, $dynamicSpec, $context);
            
            // 🛡️ 數值嚴謹性：查詢實體 Subject ID 時，強制帶入 shop_id 進行多店數據防誤隔離
            $account = Account::where('code', $resolvedCode)
                ->where('shop_id', $shopId)
                ->first();

            if ($account) {
                return $account->id;
            }

            throw new \RuntimeException("全動態科目代碼翻譯失敗：已解析出代碼 [{$resolvedCode}]，但在系統會計科目表(accounts)中找不到對應的實體科目紀錄（店鋪ID: {$shopId}）。");
        }

        throw new \RuntimeException("過帳規則明細行設定錯誤：缺少實體科目 ID，且未指定合法的全動態科目策略代碼。");
    }

    /**
     * 8 大全動態科目策略之真正翻譯機（完美重構、無痛分流）
     */
    protected function resolveDynamicAccount(Model $source, string $dynamicSpec, ?string $context): string
    {
        $parts = explode(':', $dynamicSpec);
        $domain = $parts[0] ?? ''; // auto, sale, purchase
        $type = $parts[1] ?? '';   // inventory, revenue, payment, channel_fee, cost, discount...
        
        return match($domain) {
            'auto'     => $this->resolveAutoDomainAccount($source, $type),
            'sale'     => $this->resolveSaleDomainAccount($source, $type, $parts[2] ?? null),
            'purchase' => $this->resolvePurchaseDomainAccount($source, $type),
            default    => throw new \RuntimeException("未知的全動態網域命名空間: {$domain}"),
        };
    }

    /**
     * 🚀 動態策略 A：商品庫存與成本網域 (DYNAMIC:auto:xxx)
     */
    protected function resolveAutoDomainAccount(Model $source, string $type): string
    {
        return match($type) {
            // 140501 是您的主要類別(吊墜項鍊)，實務上我們可透過單據明細關聯 product->category->accounting_code 動態返回 140501~140509
			'inventory' => $source->category_accounting_code ?? '140501', 
			'cost'      => '5401',
            default     => throw new \RuntimeException("未知的商品域動態科目類型: {$type}"),
        };
    }

    /**
     * 🚀 動態策略 B：銷售金流、折讓與通路摩擦費用網域 (DYNAMIC:sale:xxx)
     */
    protected function resolveSaleDomainAccount(Model $source, string $type, ?string $subType): string
{
		$payment = $source->payment_method ?? 'cash'; // line_pay, shopee_wallet, cash, credit_card

		return match($type) {
			// 🔹 將所有未入銀行戶頭的在途金流，統一路由到對應的應收金流科目進行高頻對沖
			'payment' => match($payment) {
				'shopee_wallet' => '113101', // 應收帳款-蝦皮代收
				'line_pay'      => '113102', // 應收帳款-LINE Pay代收
				'credit_card'   => '113103', // 應收帳款-信用卡在途
				default         => '100101', // 門市現金結帳，直接進 100101 新台幣現金
			},
			
			// 以下為預留，目前規則一、二、三中已有靜態大陸科目，此處做安全降級
			'revenue'    => '500101',
			'discount'   => '500110', 
			'return_fee' => '560106', 
			default => throw new \RuntimeException("未知的銷售域動態科目類型: {$type}"),
		};
	}

    /**
     * 🚀 動態策略 C：採購附加費與進口資本化平攤網域 (DYNAMIC:purchase:xxx)
     */
    protected function resolvePurchaseDomainAccount(Model $source, string $type): string
    {
        $expenseType = $source->expense_type ?? 'local_shipping';

        return match($type) {
            'expense' => match($expenseType) {
                'customs_duty' => '1211',   // 🌟 嚴謹：水晶進口關稅直接資本化，併入庫存商品成本
                'air_freight'  => '1211',   // 🌟 嚴謹：國際頭程空運費併入成本
                default        => '611600', // 本地快遞，當期費用化，計入運費
            },
            default => throw new \RuntimeException("未知的採購域動態科目類型: {$type}"),
        };
    }

    /**
     * 依據金額來源策略代碼，嚴謹抓取並轉換為四位小數之字串
     */
    protected function getAmountFromSource(Model $source, string $amountSource, string $eventType): string
    {
        // 🎯 核心：銷貨總成本自動累加 (規則三專用)
        if ($amountSource === 'cost_amount') {
            $source->load('items');

            $totalCost = '0.0000';
            foreach ($source->items as $item) {
                $itemCost = (string)($item->unit_cost ?? '0.0000');
                $itemQty  = (string)($item->quantity ?? '0.0000');
                $subCost  = bcmul($itemCost, $itemQty, 4);
                $totalCost = bcadd($totalCost, $subCost, 4);
            }
            return $totalCost;
        }

        // 🎯 電商全通用欄位動態轉換
        $val = match ($amountSource) {
            'customer_total'           => $source->customer_total ?? 0,
            
            // 🌟 規則一：純商品折讓後淨額
            'subtotal_after_discount'  => $source->subtotal_after_discount ?? $source->subtotal ?? 0,
            
            'tax_amount'               => $source->tax_amount ?? 0,
            'freight_amount'           => $source->freight_amount ?? 0,
            'platform_fee'             => $source->platform_fee ?? 0,
            'commission'               => $source->commission ?? 0,
            'seller_discount'          => $source->seller_discount ?? 0,
            'shipping_fee_platform'    => $source->shipping_fee_platform ?? 0,
            
            // 🌟 規則二：平台費用總計扣抵 (自動加總摩擦費用)
            'total_fees'               => $source->total_fees ?? $this->calculateTotalFees($source),
            
            default                    => $source->{$amountSource} ?? 0,
        };

        return number_format((float)$val, 4, '.', '');
    }

    /**
     * 🛡️ 數值嚴謹性：利用 BC Math 計算平台真實費用扣抵總額
     */
    private function calculateTotalFees(Model $source): string
    {
        $platformFee    = (string)($source->platform_fee ?? '0.0000');
        $commission     = (string)($source->commission ?? '0.0000');
        $sellerDiscount = (string)($source->seller_discount ?? '0.0000');
        $shippingFeePlat= (string)($source->shipping_fee_platform ?? '0.0000');

        $total = bcadd($platformFee, $commission, 4);
        $total = bcadd($total, $sellerDiscount, 4);
        $total = bcadd($total, $shippingFeePlat, 4);

        return $total;
    }

    /**
     * 同科目、同方向分錄合併沖洗，消除冗餘傳票行
     */
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

    /**
     * 數值嚴謹性核心：強制呼叫 bccomp 驗證借貸方總額是否絕對平衡
     */
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
                "會計過帳拒絕：分錄借貸不平衡！事件型態: [{$eventType}]，單據ID: [{$source->id}]。借方總額: {$debitTotal}, 貸方總額: {$creditTotal}。不平衡差額: " . bcsub($debitTotal, $creditTotal, 4)
            );
        }
    }

    /**
     * 辨識來源單據類型字串
     */
    protected function resolveReferenceType(Model $source): string
    {
        $className = get_class($source);
        foreach (self::SOURCE_MAP as $type => $config) {
            if ($config['model'] === $className) {
                return $type;
            }
        }
        return strtolower(class_basename($source));
    }

    /**
     * 防錯查重：獲取或重新開闢乾淨的傳票主檔，清除舊有項目防止高頻重複入帳
     */
    protected function getOrCreateJournal(Model $source, string $referenceType, string $eventType): Journal
    {
        $referenceId = $source->id;
        $shopId = $source->shop_id ?? 1;

        if (!$referenceId) {
            throw new \RuntimeException("會計過帳失敗：來源單據尚未持久化入庫，缺少 id 欄位。");
        }

        // 🔍 精確定位舊傳票，並串入 event_type 複合查詢，杜絕混亂
        $journal = Journal::where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('shop_id', $shopId)
            ->where('description', 'like', "%{$eventType}%") 
            ->first();

        if ($journal) {
            // 🛡️ 嚴謹排空舊明細，防微杜漸
            $journal->items()->delete();
            
            $journal->update([
                'status'     => 'posted',
                'entry_date' => now()->format('Y-m-d'),
            ]);
            
            return $journal;
        }

        // 建立全新傳票
        $docNumber = 'DOC-' . now()->format('YmdHis') . '-' . rand(1000, 9999);
        $config = self::SOURCE_MAP[$referenceType] ?? null;
        if ($config && isset($source->{$config['number_field']})) {
            $docNumber = $source->{$config['number_field']};
        }

        return Journal::create([
            'shop_id'        => $shopId,
            'journal_number' => 'JV-' . now()->format('Ymd') . '-' . sprintf('%05d', rand(1, 99999)),
            'reference_type' => $referenceType,
            'reference_id'   => $referenceId,
            'document_number'=> $docNumber,
            'entry_date'     => now()->format('Y-m-d'),
            'description'    => "自動過帳 [{$eventType}] - 單據編號: {$docNumber}",
            'status'         => 'posted',
            'created_by'     => 1,
        ]);
    }
	
	/**
     * 🌟 前端對帳核心：依據傳票關聯之多型來源，動態反查並解析原始單據編號
     * 完美對齊全通用會計規則與動態多型安全盾
     */
    public function resolveSourceNumber(?string $referenceType, ?int $referenceId): ?string
    {
        if (!$referenceType || !$referenceId) {
            return '手工分錄';
        }

        // 🛡️ 利用動態配置映射表攔截，兼容新舊複合事件類型
        $config = self::SOURCE_MAP[$referenceType] ?? null;
        if (!$config) {
            return "未知單據 (#{$referenceId})";
        }

        $modelClass = $config['model'];
        $numberField = $config['number_field'];

        // 🛡️ 使用簡潔高效率查詢，僅讀取單一主鍵與編號欄位，防止大表遍歷效能崩潰
        $source = $modelClass::where('id', $referenceId)->first([$numberField]);

        return $source ? $source->{$numberField} : "單據已刪除 (#{$referenceId})";
    }

    /**
     * 🌟 前端對帳核心：依據傳票關聯多型類型，反查人類可讀之語意化標籤
     */
    public function getSourceTypeLabel(?string $referenceType): string
    {
        if (!$referenceType || $referenceType === 'manual') {
            return '手工分錄';
        }

        return match ($referenceType) {
            'purchase'        => '採購進貨',
            'sale'            => '門市零售',
            'sale_revenue'    => '通路銷售營收',
            'sale_fee'        => '平台摩擦費用',
            'sale_cost'       => '銷貨成本結轉',
            'purchase_return' => '採購退貨',
            'sales_return'    => '銷售退貨',
            default           => strtoupper($referenceType),
        };
    }
}