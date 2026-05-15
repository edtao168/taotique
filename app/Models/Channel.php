<?php // app/Models/Channel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    protected $fillable = ['name', 'type', 'platform_fee_rate', 'is_active'];
	
	protected $casts = [
		'platform_fee_rate' => 'decimal:4',
		'is_active' => 'boolean',
	];

    // 一個通路會有多筆銷售紀錄
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
	
	public function scopeActive($query)
	{
		return $query->where('is_active', true);
	}
}