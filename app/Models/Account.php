<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Account extends Model
{
    use TenantScoped;
	
	protected $table = 'accounts';

    protected $fillable = [
        'code',
        'name',
        'type',
        'parent_id',
        'level',
        'is_monetary',
        'currency',
        'account_number',
        'shop_id',
        'tenant_id',
		'is_active',
    ];

    protected $casts = [
        'is_monetary' => 'boolean',
        'is_active' => 'boolean',
        'level' => 'integer',
    ];

    /**
     * 科目類型對照表（中央定義）
     */
    public static function typeLabels(): array
    {
        return [
            'asset' => '資產',
            'liability' => '負債',
            'equity' => '權益',
            'cost' => '成本',
            'profit' => '損益',
        ];
    }

    /**
     * 取得類型的中文標籤
     */
    public function getTypeLabelAttribute(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    /**
     * 取得可用的類型選項（用於下拉選單）
     */
    public static function typeOptions(): array
    {
        return self::typeLabels();
    }

    /**
     * 取得顯示名稱（依層級縮排）
     */
    public function getDisplayNameAttribute(): string
    {
        $indent = str_repeat('&nbsp;&nbsp;', ($this->level - 1) * 2);
        return $indent . e($this->name);
    }

    /**
     * 取得純文字顯示名稱（用於下拉選單）
     */
    public function getIndentedNameAttribute(): string
    {
        $indent = str_repeat('—', ($this->level - 1) * 2);
        return $indent . $this->name;
    }

    // ==============================================
    // 關聯
    // ==============================================

    /**
     * 上層科目（父科目）
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'parent_id');
    }

    /**
     * 子科目
     */
    public function children(): HasMany
    {
        return $this->hasMany(Account::class, 'parent_id');
    }

    /**
     * 此科目在過帳規則中的明細行
     */
    public function accountingRuleLines(): HasMany
    {
        return $this->hasMany(AccountingRuleLine::class, 'account_id');
    }

    /**
     * 此科目在日記帳明細中的使用
     */
    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class, 'account_id');
    }

    // ==============================================
    // 輔助方法（核心）
    // ==============================================

    /**
     * 完整的科目代碼與名稱（用於下拉選單）
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->code} - {$this->name}";
    }

    /**
     * 是否為收入科目
     * 
     * 收入科目特徵：
     * - 損益類 (type = 'profit')
     * - 科目代碼以 5001（主營業務收入）、5002（其他業務收入）、5051（其他業務收入）開頭
     * - 正常餘額在貸方
     * 
     * 根據 AccountSeeder 中的科目：
     * - 5001 主營業務收入（及其子科目）
     * - 5002 其他業務收入（及其子科目）
     */
    public function isIncomeAccount(): bool
    {
        if ($this->type !== 'profit') {
            return false;
        }
        
        // 收入科目代碼規則
        return str_starts_with($this->code, '5001') ||
               str_starts_with($this->code, '5002') ||
               str_starts_with($this->code, '5051');
    }

    /**
     * 是否為費用科目
     * 
     * 費用科目特徵：
     * - 損益類 (type = 'profit')
     * - 科目代碼以 5601（銷售費用）、6602（管理費用）、6603（財務費用）、6401（營業外支出）開頭
     * - 正常餘額在借方
     * 
     * 根據 AccountSeeder 中的科目：
     * - 5601 銷售費用（及其子科目）
     * - 6602 管理費用（及其子科目）
     * - 6603 財務費用（及其子科目）
     * - 6401 營業外支出（及其子科目）
     * - 5101 稅金及附加（可視為費用性質）
     */
    public function isExpenseAccount(): bool
    {
        if ($this->type !== 'profit') {
            return false;
        }
        
        // 費用科目代碼規則
        return str_starts_with($this->code, '5601') ||  // 銷售費用
               str_starts_with($this->code, '6602') ||  // 管理費用
               str_starts_with($this->code, '6603') ||  // 財務費用
               str_starts_with($this->code, '6401') ||  // 營業外支出
               str_starts_with($this->code, '5101');    // 稅金及附加（費用性質）
    }

    /**
     * 是否為損益類科目（收入或費用）
     * 
     * 損益類科目最終會結轉到「本年利潤」
     */
    public function isProfitLossAccount(): bool
    {
        return $this->isIncomeAccount() || $this->isExpenseAccount();
    }

    /**
     * 是否為貨幣資金帳戶
     * 
     * 貨幣資金帳戶特徵：
     * - 科目代碼以 1001（庫存現金）、1002（銀行存款）、1012（其他貨幣資金）開頭
     * - 或者 is_monetary = true
     */
    public function isMonetaryAccount(): bool
    {
        if ($this->is_monetary) {
            return true;
        }
        
        return str_starts_with($this->code, '1001') ||
               str_starts_with($this->code, '1002') ||
               str_starts_with($this->code, '1012');
    }

    /**
     * 是否為資產類科目
     */
    public function isAssetAccount(): bool
    {
        return $this->type === 'asset';
    }

    /**
     * 是否為負債類科目
     */
    public function isLiabilityAccount(): bool
    {
        return $this->type === 'liability';
    }

    /**
     * 是否為權益類科目
     */
    public function isEquityAccount(): bool
    {
        return $this->type === 'equity';
    }

    /**
     * 是否為成本類科目
     */
    public function isCostAccount(): bool
    {
        return $this->type === 'cost';
    }

    /**
     * 取得科目的正常餘額方向
     * 
     * @return string 'debit' 或 'credit'
     */
    public function getNormalBalance(): string
    {
        return match($this->type) {
            'asset', 'cost', 'expense' => 'debit',
            'liability', 'equity', 'revenue' => 'credit',
            'profit' => $this->isIncomeAccount() ? 'credit' : 'debit',
            default => 'debit',
        };
    }

    /**
     * 取得科目用途說明（用於 UI 提示）
     */
    public function getUsageHint(): string
    {
        return match(true) {
            $this->isIncomeAccount() => '收入科目，正常餘額在貸方。當您收到款項時使用。',
            $this->isExpenseAccount() => '費用科目，正常餘額在借方。當您支付費用時使用。',
            $this->isMonetaryAccount() => '資金帳戶，用來記錄現金、銀行存款、電子支付。',
            $this->isAssetAccount() => '資產科目，正常餘額在借方。',
            $this->isLiabilityAccount() => '負債科目，正常餘額在貸方。',
            $this->isEquityAccount() => '權益科目，正常餘額在貸方。',
            $this->isCostAccount() => '成本科目，正常餘額在借方。',
            default => '一般會計科目',
        };
    }

	/**
	 * 驗證此費用科目是否適用於指定的業務類型
	 * 
	 * @param string $eventType 業務類型 (如 'retail_sale', 'online_sale', 'expense')
	 * @param array $options 選項，例如 ['amount' => 1000]
	 * @throws \RuntimeException
	 */
	public function validateForEventType(string $eventType, array $options = []): void
	{
		// 只檢查費用/成本/損益科目
		if (!in_array($this->type, ['cost', 'profit'])) {
			return;
		}
		
		// 取得該業務類型的規則
		$rule = AccountingRule::where('event_type', $eventType)
			->where('is_active', true)
			->first();
		
		if (!$rule) {
			$eventLabel = $this->getEventTypeLabel($eventType);
			throw new \RuntimeException("業務類型「{$eventLabel}」尚未設定過帳規則");
		}
		
		// 檢查此科目是否在該規則中
		$hasRule = AccountingRuleLine::where('accounting_rule_id', $rule->id)
			->where('account_id', $this->id)
			->exists();
			
		if (!$hasRule) {
			$eventLabel = $this->getEventTypeLabel($eventType);
			throw new \RuntimeException("科目「{$this->name}」不適用於「{$eventLabel}」業務");
		}
		
		// 可選：檢查金額限制（如果 rule_lines 有定義）
		if (isset($options['amount'])) {
			// 可以從 rule_line 取得金額限制
			$ruleLine = AccountingRuleLine::where('accounting_rule_id', $rule->id)
				->where('account_id', $this->id)
				->first();
				
			// 假設有 min_amount, max_amount 欄位（目前沒有，先註解）
			// if ($ruleLine && $ruleLine->max_amount && $options['amount'] > $ruleLine->max_amount) {
			//     throw new \RuntimeException("金額超過限制");
			// }
		}
	}

	/**
	 * 取得業務類型的中文標籤
	 */
	protected function getEventTypeLabel(string $eventType): string
	{
		return match($eventType) {
			'retail_sale' => '實體店銷售',
			'online_sale' => '線上銷售',
			'expense' => '費用支出',
			'purchase' => '採購進貨',
			default => $eventType,
		};
	}

	/**
	 * @deprecated 使用 validateForEventType() 代替
	 */
	public function validateExpenseRule(array $options = []): void
	{
		// 這個方法已棄用，因為需要 eventType 參數
		throw new \RuntimeException('請使用 validateForEventType($eventType, $options) 方法');
	}
}