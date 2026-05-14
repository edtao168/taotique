<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 檔案路徑：app/Models/AccountingRuleLine.php
 */
class AccountingRuleLine extends Model
{
    protected $table = 'accounting_rule_lines';

    // 費曼註釋：嚴謹列出所有允許寫入的欄位，防止注入且支援 Seeder
    protected $fillable = [
        'accounting_rule_id',
        'account_id',
        'entry_type',
        'amount_source',
        'ratio',
        'sort_order',
        'is_active'
    ];

    /**
     * 強制轉型：確保金額精度在運算時不被轉為浮點數
     */
    protected $casts = [
        'ratio' => 'decimal:4',
        'is_active' => 'boolean',
    ];
	
	/**
     * 所屬規則
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(AccountingRule::class, 'accounting_rule_id');
    }

    /**
     * 會計科目
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}