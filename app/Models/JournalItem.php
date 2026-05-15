<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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