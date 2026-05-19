<?php
// 路徑: app/Traits/HasAccounting.php

namespace App\Traits;

use App\Models\Journal;
use App\Models\JournalItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 費曼註釋：讓採購/銷售/退貨等 Model 共用會計結轉邏輯
 * 遵循《小企業會計準則》— 採購入庫：借庫存商品 / 貸應付帳款
 */
trait HasAccounting
{
    /**
     * 產生採購入庫的會計分錄
     * 
     * @param string $entryDate 入庫日期
     * @param int $inventoryAccountId 庫存商品科目ID (預設1405)
     * @param int $payableAccountId 應付帳款科目ID (預設2202)
     * @return Journal|null
     */
    public function createPurchaseJournal(
        string $entryDate,
        int $inventoryAccountId = 1405,
        int $payableAccountId = 2202
    ): ?Journal {
        // 冪等性：檢查是否已產生
        if ($this->journal()->exists()) {
            Log::warning('採購單已產生過日記帳', ['purchase_id' => $this->id]);
            return $this->journal;
        }

        // 取得總成本（本幣）
        $totalCost = $this->total_twd ?? $this->calculateTotalTwd();
        
        if (bccomp($totalCost, '0', 4) <= 0) {
            Log::error('採購單總成本為0，無法產生日記帳', ['purchase_id' => $this->id]);
            return null;
        }

        return DB::transaction(function () use ($entryDate, $inventoryAccountId, $payableAccountId, $totalCost) {
            // 1. 建立 Journal 主表
            $journal = $this->journal()->create([
                'shop_id'        => $this->shop_id ?? 1,
                'currency'       => $this->currency,
                'exchange_rate'  => $this->exchange_rate,
                'entry_date'     => $entryDate,
                'description'    => "採購入庫 - {$this->purchase_number}",
                'reference_type' => 'purchase',
                'reference_id'   => $this->id,
                'status'         => Journal::STATUS_POSTED, // 採購入庫直接過帳
                'created_by'     => auth()->user()?->name ?? 'System',
            ]);

            // 2. 借方：庫存商品
            $journal->items()->create([
                'account_id' => $inventoryAccountId,
                'debit'      => $totalCost,
                'credit'     => '0.0000',
                'currency'   => $this->currency,
                'exchange_rate' => $this->exchange_rate,
                'shop_id'    => $this->shop_id ?? 1,
            ]);

            // 3. 貸方：應付帳款（或現金）
            $journal->items()->create([
                'account_id' => $payableAccountId,
                'debit'      => '0.0000',
                'credit'     => $totalCost,
                'currency'   => $this->currency,
                'exchange_rate' => $this->exchange_rate,
                'shop_id'    => $this->shop_id ?? 1,
            ]);

            // 4. 平衡檢查
            if (!$journal->isBalanced()) {
                throw new \RuntimeException('採購入庫分錄不平衡，請檢查運算邏輯');
            }

            Log::info('採購單會計分錄產生成功', [
                'purchase_id' => $this->id,
                'journal_id'  => $journal->id,
                'amount'      => $totalCost
            ]);

            return $journal;
        }, 3);
    }

    /**
     * 計算總成本（本幣）- 含費用分攤
     */
    protected function calculateTotalTwd(): string
    {
        // 商品小計（本幣）
        $subtotal = '0.0000';
        foreach ($this->items as $item) {
            $itemCost = bcmul($item->cost_twd ?? '0', $item->quantity ?? '0', 4);
            $subtotal = bcadd($subtotal, $itemCost, 4);
        }

        // 加上運費/稅金/其他，減去折扣
        $total = bcadd($subtotal, $this->shipping_fee ?? '0', 4);
        $total = bcadd($total, $this->tax ?? '0', 4);
        $total = bcadd($total, $this->other_fees ?? '0', 4);
        $total = bcsub($total, $this->discount ?? '0', 4);

        return $total;
    }

    /**
     * 定義與 Journal 的關聯（多態）
     */
    public function journal()
    {
        return $this->morphOne(Journal::class, 'reference');
    }
}