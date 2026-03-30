<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Customer extends Model
{
    use SoftDeletes;
	
	protected $fillable = ['name', 'phone', 'email', 'wechat', 'address', 'notes'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    /**
     * 厚 Model 邏輯：計算該客戶總消費金額 (使用 bcmath)
     */
    public function getTotalSpentAttribute(): string
    {
        $total = $this->total_spent_sum ?? '0';
		return bcadd((string)$total, '0', 2);
    }
	
	/**
	 * 統一格式化客戶下拉選單選項
	 * 只回傳必要的 ID 與名稱，確保資安與效能
	 */
	public static function getOptions(): array
	{
		return self::where('is_active', true) // 僅抓取啟用的客戶
			->orderBy('name', 'asc')
			->get(['id', 'name'])
			->map(fn($c) => [
				'id'   => $c->id,
				'name' => $c->name,
			])
			->toArray();
	}
}