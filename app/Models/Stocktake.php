<?php // app/Models/Stocktake.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stocktake extends Model
{
    protected $fillable = ['store_id', 'warehouse_id', 'user_id', 'status', 'remark'];

    public function items(): HasMany
    {
        return $this->hasMany(StocktakeItem::class);
    }
}