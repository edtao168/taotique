<?php // app/Models/Channel.php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use TenantScoped;
	
	protected $fillable = ['tenant_id', 'name', 'type', 'platform_fee_rate', 'is_active'];
	
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