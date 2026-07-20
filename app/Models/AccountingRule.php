<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\belongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingRule extends Model
{
    use TenantScoped;
	
	// 顯式定義資料表名稱
    protected $table = 'accounting_rules';

    // 允許批量賦值的欄位
    protected $fillable = ['event_type', 'shop_id', 'is_active'];
	
	protected $casts = [
        'is_active' => 'boolean',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(AccountingRuleLine::class);
    }
	
	public function account(): belongsTo
	{
		return $this->belongsTo(Account::class);
	}
}