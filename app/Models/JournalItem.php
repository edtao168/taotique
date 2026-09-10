<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ⚠️ 重要：JournalItem 沒有 status 欄位。
 *
 * 已撤銷的傳票（journal.status = 'reversed'）其明細仍存在於此表。
 * 任何統計 / 報表 / 餘額查詢「必須」透過 journal 關聯過濾：
 *
 *   JournalItem::whereHas('journal', fn($q) => $q->posted())->...
 *
 * 或從 Journal 出發：
 *
 *   Journal::posted()->with('items')->...
 *
 * 直接對 journal_items 做 sum/groupBy 會計入已撤銷分錄，導致數字錯誤。
 */
class JournalItem extends Model
{
    protected $table = 'journal_items';

    protected $fillable = [
        'journal_id',
        'account_id',
        'currency',
        'debit_currency',
        'credit_currency',
        'debit',
        'credit',
        'exchange_rate',
        'shop_id',
    ];

    protected $casts = [
        'debit_currency' => 'decimal:4',
        'credit_currency' => 'decimal:4',
        'debit' => 'decimal:4',
        'credit' => 'decimal:4',
        'exchange_rate' => 'decimal:6',
    ];

    /**
     * 所屬日記帳
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * 會計科目
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}