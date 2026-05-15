<?php // app/Models/Stocktake.php

namespace App\Models;

use App\Traits\HasShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Stocktake extends Model
{
    use HasShop; // 自動包含 shop_id 的 scope 與關聯

    protected $fillable = [
        'shop_id',
        'warehouse_id',
        'user_id',
        'status',
        'remark',
    ];

    /**
     * 執行盤點過帳（示範併發控制邏輯）
     */
    public function adjustStock(): void
    {
        DB::transaction(function () {
            // 使用 lockForUpdate 防止盤點期間庫存被其他交易異動
            $record = Stocktake::where('id', $this->id)
                ->lockForUpdate()
                ->first();

            // 實作加權平均成本與庫存異動邏輯...
            // 範例：bcadd($old_qty, $diff_qty, 4);
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(StocktakeItem::class);
    }
}