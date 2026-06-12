<?php // app/Models/AccountingRuleLine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountingRuleLine extends Model
{
    protected $table = 'accounting_rule_lines';

    // 費曼註釋：嚴謹列出所有允許寫入的欄位，防止注入且支援 Seeder
    protected $fillable = [
        'accounting_rule_id',
        'account_id',
		'account_code',
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
     * 🎯 核心功能：儲存時自動補上 account_id
     * 
     * 設計理念：
     * - 規則設定時使用 account_code（可讀性高）
     * - 系統執行時使用 account_id（效能最佳）
     * - 自動同步，避免人為遺漏
     */
	protected static function booted()
	{
		static::saving(function ($line) {
			// 如果是靜態科目（非 DYNAMIC 開頭）且有 account_code 但無 account_id
			if ($line->account_code 
				&& !str_starts_with($line->account_code, 'DYNAMIC:')
				&& empty($line->account_id)
			) {
				$account = Account::where('code', $line->account_code)->first();
				if ($account) {
					$line->account_id = $account->id;
				}
			}
		});
	}

	/**
     * 獲取科目實體（統一的解析邏輯）
     * 優先級：account_id > account_code (非DYNAMIC) > DYNAMIC
     */
    public function getResolvedAccountAttribute(): ?Account
    {
        // 1. 優先使用 account_id
        if ($this->account_id && $this->account) {
            return $this->account;
        }
        
        // 2. 如果是動態科目，返回 null（運行時解析）
        if ($this->account_code && str_starts_with($this->account_code, 'DYNAMIC:')) {
            return null;
        }
        
        // 3. 嘗試透過 account_code 查找
        if ($this->account_code && !str_starts_with($this->account_code, 'DYNAMIC:')) {
            return Account::where('code', $this->account_code)->first();
        }
        
        return null;
    }
    
    /**
     * 獲取科目顯示資訊（供前端使用）
     */
    public function getDisplayAccountAttribute(): array
    {
        // 1. 動態科目
        if ($this->account_code && str_starts_with($this->account_code, 'DYNAMIC:')) {
            return [
                'type' => 'dynamic',
                'spec' => substr($this->account_code, 8),
                'exists' => true,
            ];
        }
        
        // 2. 嘗試解析靜態科目
        $account = $this->resolved_account;
        
        if ($account) {
            return [
                'type' => 'account',
                'code' => $account->code,
                'name' => $account->name,
                'exists' => true,
            ];
        }
        
        // 3. 科目不存在或無法解析
        return [
            'type' => 'missing',
            'code' => $this->account_code ?? 'N/A',
            'exists' => false,
        ];
    }
    
    /**
     * 判斷是否為動態科目
     */
    public function getIsDynamicAttribute(): bool
    {
        return $this->account_code && str_starts_with($this->account_code, 'DYNAMIC:');
    }
	
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