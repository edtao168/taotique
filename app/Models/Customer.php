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
}